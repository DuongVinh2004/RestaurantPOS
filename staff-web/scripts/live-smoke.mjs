import { randomUUID } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const DEFAULT_MANIFEST_PATH = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'storage', 'app', 'uat', 'scenario-pack.json');
const manifest = readSmokeManifest(process.env.STAFF_WEB_SMOKE_MANIFEST_PATH ?? DEFAULT_MANIFEST_PATH);
const config = createSmokeConfig(process.env, manifest);
const results = [];
const ALLOWED_AI_ASSIST_STATUSES = new Set(['ready', 'disabled', 'unavailable']);
let staffToken = '';

if (isExecutedDirectly()) {
  const startupBlockers = describeStartupBlockers(config);
  printStartupContext(config);

  if (startupBlockers.length > 0) {
    pushResult('FAIL', 'startup validation', startupBlockers.join(' | '));
    finalizeSmokeRun(config);
  }

  main().catch((error) => {
    if (shouldRecordBootstrapFailure()) {
      pushResult('FAIL', 'smoke bootstrap', formatErrorDetails(error));
    }
    finalizeSmokeRun(config);
  });
}

async function main() {
  await runStep('backend health', async () => {
    const response = await requestJson('GET', '/api/v1/health', {
      expectedStatuses: [200],
    });

    return describePayload(response.data);
  }, { critical: true });

  await runStep('login', async () => {
    const response = await requestJson('POST', '/api/v1/auth/staff/login', {
      body: {
        identifier: config.identifier,
        password: config.password,
        device_name: config.deviceName,
      },
    });

    const session = requireStartupContract(response.data?.data, 'login');
    staffToken = session.access_token ?? '';

    return `staff_api_key_id=${session.staff_api_key_id ?? 'n/a'} | ${summarizeStartup(session)}`;
  }, { critical: true });

  await runStep('session me', async () => {
    const response = await requestJson('GET', '/api/v1/auth/staff/me', {
      token: staffToken,
    });

    const session = requireStartupContract(response.data?.data, 'session me');
    return `user=${session.user?.username ?? session.user?.full_name ?? 'n/a'} | ${summarizeStartup(session)}`;
  }, { critical: true });

  await runStep('session refresh', async () => {
    const response = await requestJson('POST', '/api/v1/auth/staff/refresh', {
      token: staffToken,
    });

    const session = requireStartupContract(response.data?.data, 'session refresh');
    staffToken = session.access_token ?? staffToken;

    return `expires_at=${session.expires_at_utc ?? 'n/a'} | ${summarizeStartup(session)}`;
  }, { critical: true });

  const boardWindow = buildBoardWindow();
  const boardResponse = await runStep('board refresh', async () => {
    const response = await requestJson('GET', '/api/v1/staff/tables/board', {
      token: staffToken,
      query: {
        from: boardWindow.from,
        to: boardWindow.to,
        include_holds: true,
        group_by: 'zone',
      },
    });

    return response;
  });

  const board = boardResponse?.data;
  const boardVersion = readNumber(board?.meta?.realtime, 'current_version');

  await runStep('board changes', async () => {
    const response = await requestJson('GET', '/api/v1/staff/tables/board/changes', {
      token: staffToken,
      query: {
        after_version: boardVersion ?? undefined,
        limit: 20,
      },
    });

    return summarizeRealtime(response.data?.data);
  });

  const waitingResponse = await runStep('waiting list', async () => {
    const response = await requestJson('GET', '/api/v1/staff/waiting-list', {
      token: staffToken,
      query: {
        active_only: true,
        per_page: 12,
        sort: '-priority',
      },
    });

    return response;
  });

  const waiting = waitingResponse?.data;
  const waitingVersion = readNumber(waiting?.meta?.realtime, 'current_version');

  await runStep('waiting changes', async () => {
    const response = await requestJson('GET', '/api/v1/staff/waiting-list/changes', {
      token: staffToken,
      query: {
        after_version: waitingVersion ?? undefined,
        limit: 20,
      },
    });

    return summarizeRealtime(response.data?.data);
  });

  const reservationLookupResponse = await runStep('reservation lookup', async () => {
    const response = await requestJson('GET', '/api/v1/staff/reservations', {
      token: staffToken,
      query: {
        bucket: 'all',
        q: config.reservationQuery || undefined,
        per_page: 8,
        sort: '-start_time',
      },
    });

    return response;
  });

  const reservationLookup = asArray(reservationLookupResponse?.data?.data);
  const boardReservation = asArray(board?.data)
    .map((row) => row?.reservation)
    .find((reservation) => reservation && readNumber(reservation, 'reservation_id'));
  let selectedReservation = pickReservation({
    reservationId: config.reservationId,
    reservationLookup,
    boardReservation,
  });
  const reservationId = selectedReservation
    ? readNumber(selectedReservation, 'reservation_id')
    : config.reservationId ?? readNumber(boardReservation, 'reservation_id');
  let orderTableId = config.orderTableId ?? readFirstTableId(selectedReservation) ?? null;
  const selectedBoardRow = findBoardRow(board?.data, reservationId, orderTableId);
  const checkInPlan = resolveCheckInPlan({
    reservation: selectedReservation,
    boardRow: selectedBoardRow,
    fallbackTableId: orderTableId,
  });
  orderTableId = checkInPlan.tableId ?? orderTableId;

  if (!selectedReservation && !reservationId) {
    pushResult('SKIP', 'reservation orders', 'no reservation from lookup, board, or explicit reservation_id');
  }

  const reservationOrdersResponse = reservationId
    ? await runStep('reservation orders', async () => {
      const response = await requestJson('GET', `/api/v1/staff/reservations/${reservationId}/orders`, {
        token: staffToken,
      });

      return response;
    })
    : null;

  const reservationOrders = asArray(reservationOrdersResponse?.data?.data);
  const menuItemsResponse = await runStep('menu items', async () => {
    const response = await requestJson('GET', '/api/v1/menu/items', {
      token: staffToken,
      query: {
        per_page: 4,
        service_time: new Date().toISOString(),
      },
    });

    return response;
  });
  const menuItem = asArray(menuItemsResponse?.data?.data).find((item) => item?.is_available) ?? null;
  const boardOrder = asArray(board?.data)
    .map((row) => row?.active_order)
    .find((order) => order && readNumber(order, 'order_id'));

  let orderId = config.orderId ?? pickOrderId(reservationOrders, boardOrder);
  let orderDetailResponse = orderId
    ? await runStep('order load', async () => requestJson('GET', `/api/v1/staff/orders/${orderId}`, {
      token: staffToken,
    }))
    : null;

  if (!orderDetailResponse && config.allowOrderCreate && selectedReservation) {
    let readyForOrderCreate = true;

    if (checkInPlan.required) {
      if (reservationId && checkInPlan.payload) {
        const checkInResponse = await runStep('reservation check-in', async () => requestJson('POST', `/api/v1/staff/reservations/${reservationId}/check-in`, {
          token: staffToken,
          expectedStatuses: [200, 201],
          idempotencyKey: idempotencyKey('reservation-check-in'),
          body: checkInPlan.payload,
        }));

        selectedReservation = checkInResponse?.data?.data ?? selectedReservation;
        orderTableId = readFirstTableId(selectedReservation) ?? checkInPlan.tableId ?? orderTableId;
        readyForOrderCreate = Boolean(checkInResponse);
      } else {
        pushResult('SKIP', 'reservation check-in', 'missing canonical check-in payload or table_id');
        readyForOrderCreate = false;
      }
    }

    const tableId = orderTableId ?? readFirstTableId(selectedReservation);
    const rowVersion = readNumber(selectedReservation, 'row_version');

    if (readyForOrderCreate && tableId && reservationId && rowVersion) {
      const createOrderResponse = await runStep('order create', async () => requestJson('POST', `/api/v1/staff/tables/${tableId}/orders`, {
        token: staffToken,
        expectedStatuses: [200, 201],
        idempotencyKey: idempotencyKey('order-create'),
        body: {
          reservation_id: reservationId,
          row_version: rowVersion,
          notes: 'staff-web live smoke',
        },
      }));

      orderId = readNumber(createOrderResponse?.data?.data, 'order_id');
      orderDetailResponse = orderId
        ? await runStep('order load (created)', async () => requestJson('GET', `/api/v1/staff/orders/${orderId}`, {
          token: staffToken,
        }))
        : null;
    } else if (!readyForOrderCreate) {
      pushResult('SKIP', 'order create', 'reservation is not in service and check-in did not complete');
    } else {
      pushResult('SKIP', 'order create', 'missing table_id or row_version from canonical reservation source');
    }
  } else if (!orderDetailResponse && !config.allowOrderCreate) {
    pushResult('SKIP', 'order create', 'no existing order and order-create gate disabled');
  } else if (!orderDetailResponse) {
    pushResult('SKIP', 'order create', 'no canonical reservation available for order create');
  }

  let orderDetail = orderDetailResponse?.data?.data ?? null;

  if (orderId && orderDetail && config.allowOrderAddItem && menuItem) {
    await runStep('order add-item', async () => {
      await requestJson('POST', `/api/v1/staff/orders/${orderId}/items`, {
        token: staffToken,
        idempotencyKey: idempotencyKey('order-item'),
        body: {
          row_version: readNumber(orderDetail?.order, 'row_version'),
          items: [
            {
              menu_item_id: readNumber(menuItem, 'item_id'),
              qty: 1,
              note: 'staff-web live smoke',
            },
          ],
        },
      });

      return `menu_item_id=${readNumber(menuItem, 'item_id')}`;
    });

    const reloadedOrder = await runStep('order reload', async () => requestJson('GET', `/api/v1/staff/orders/${orderId}`, {
      token: staffToken,
    }));
    orderDetail = reloadedOrder?.data?.data ?? orderDetail;
  } else if (!orderId) {
    pushResult('SKIP', 'order add-item', 'no order available');
  } else if (!config.allowOrderAddItem) {
    pushResult('SKIP', 'order add-item', 'order add-item gate disabled');
  } else {
    pushResult('SKIP', 'order add-item', 'no available menu item');
  }

  const settlementPreviewResponse = orderId
    ? await runStep('settlement preview', async () => requestJson('GET', `/api/v1/staff/orders/${orderId}/settlement-preview`, {
      token: staffToken,
      query: {
        currency: readString(orderDetail?.order?.totals, 'currency') ?? 'VND',
      },
    }))
    : null;

  const settlementPreview = settlementPreviewResponse?.data?.data ?? null;

  if (orderId && orderDetail && settlementPreview && config.allowSettlementFinalize) {
    const outstandingAmount = Number(settlementPreview.outstanding_amount ?? readNumber(orderDetail?.order?.totals, 'outstanding') ?? 0);
    const orderStatus = readString(orderDetail?.order, 'status');

    if (outstandingAmount > 0 && orderStatus?.toLowerCase() !== 'closed') {
      await runStep('settlement finalize', async () => {
        const response = await requestJson('POST', `/api/v1/staff/orders/${orderId}/settlement/finalize`, {
          token: staffToken,
          idempotencyKey: idempotencyKey('settlement-finalize'),
          body: {
            payment_method: config.paymentMethod,
            payment_provider: config.paymentProvider,
            paid_amount: outstandingAmount,
            currency: settlementPreview.currency ?? readString(orderDetail?.order?.totals, 'currency') ?? 'VND',
            notes: 'staff-web live smoke',
            row_version: readNumber(orderDetail?.order, 'row_version'),
          },
        });

        return summarizeSettlement(response.data?.data);
      });
    } else {
      pushResult('SKIP', 'settlement finalize', 'order already settled or has no outstanding amount');
    }
  } else if (orderId && orderDetail && settlementPreview) {
    pushResult('SKIP', 'settlement finalize', 'mutation gate disabled');
  } else {
    pushResult('SKIP', 'settlement finalize', 'no order available for finalize');
  }

  const refundReservationId = config.refundReservationId ?? reservationId;

  const refundPreviewResponse = refundReservationId
    ? await runStep('refund preview', async () => requestJson('GET', `/api/v1/staff/reservations/${refundReservationId}/refund-preview`, {
      token: staffToken,
      query: {
        refund_scope: 'all',
      },
    }))
    : null;

  const refundPreview = refundPreviewResponse?.data?.data ?? null;

  if (refundPreview && config.allowRefundMutation) {
    const refundAmount = Number(refundPreview.refund?.refund_amount ?? 0);

    if (canExecuteRefund(refundPreview)) {
      await runStep('refund execute', async () => {
        const response = await requestJson('POST', `/api/v1/staff/reservations/${refundReservationId}/refund`, {
          token: staffToken,
          idempotencyKey: idempotencyKey('refund'),
          body: {
            payment_method: config.paymentMethod,
            payment_provider: config.paymentProvider,
            refund_scope: refundPreview.refund?.refund_scope ?? 'all',
            refund_amount: refundAmount,
            currency: refundPreview.refund?.currency ?? 'VND',
            notes: 'staff-web live smoke',
            reason: 'staff_web_live_smoke',
            row_version: readNumber(refundPreview.reservation, 'row_version'),
          },
        });

        return `reservation_id=${readNumber(response.data?.data, 'reservation_id') ?? refundReservationId}`;
      });
    } else {
      pushResult('SKIP', 'refund execute', 'preview shows no refundable amount');
    }
  } else if (refundPreview) {
    pushResult('SKIP', 'refund execute', 'mutation gate disabled');
  } else {
    pushResult('SKIP', 'refund execute', 'no reservation available for refund');
  }

  let cashierCurrentResponse = await runStep('cashier current', async () => {
    const response = await requestJson('GET', '/api/v1/staff/cashier/shifts/current', {
      token: staffToken,
      expectedStatuses: [200, 404],
    });

    return response;
  });

  if (cashierCurrentResponse?.status === 404 && config.allowCashierOpen) {
    cashierCurrentResponse = await runStep('cashier open', async () => requestJson('POST', '/api/v1/staff/cashier/shifts/open', {
      token: staffToken,
      expectedStatuses: [200, 201],
      idempotencyKey: idempotencyKey('cashier-open'),
      body: {
        opening_float_amount: config.cashierOpeningFloat,
        branch_id: config.cashierBranchId ?? undefined,
        currency: config.cashierCurrency,
        terminal_code: config.cashierTerminalCode,
        notes: 'staff-web live smoke',
      },
    }));
  } else if (cashierCurrentResponse?.status === 404) {
    pushResult('SKIP', 'cashier open', 'no open shift and cashier-open gate disabled');
  } else {
    pushResult('SKIP', 'cashier open', 'current shift already exists');
  }

  const cashierShiftId = readNumber(cashierCurrentResponse?.data?.data, 'cashier_shift_id');
  const cashierShiftResponse = cashierShiftId
    ? await runStep('cashier show', async () => requestJson('GET', `/api/v1/staff/cashier/shifts/${cashierShiftId}`, {
      token: staffToken,
    }))
    : null;

  const cashierShift = cashierShiftResponse?.data?.data ?? cashierCurrentResponse?.data?.data ?? null;

  if (cashierShiftId && cashierShift && config.allowCashierClose) {
    await runStep('cashier close', async () => requestJson('POST', `/api/v1/staff/cashier/shifts/${cashierShiftId}/close`, {
      token: staffToken,
      idempotencyKey: idempotencyKey('cashier-close'),
      body: {
        actual_cash_amount: Number(cashierShift.expected_cash_amount ?? cashierShift.opening_float_amount ?? 0),
        notes: 'staff-web live smoke',
        row_version: readNumber(cashierShift, 'row_version'),
      },
    }));
  } else if (cashierShiftId) {
    pushResult('SKIP', 'cashier close', 'close gate disabled');
  } else {
    pushResult('SKIP', 'cashier close', 'no cashier shift available');
  }

  const conversationsResponse = await runStep('conversations list', async () => requestJson('GET', '/api/v1/staff/conversations', {
    token: staffToken,
    query: {
      per_page: 16,
      q: config.conversationQuery || undefined,
    },
  }));

  const conversationId = config.conversationId || readString(asArray(conversationsResponse?.data?.data)[0], 'conversation_id');

  if (conversationId) {
    const conversationDetailResponse = await runStep('conversation detail', async () => requestJson('GET', `/api/v1/staff/conversations/${conversationId}`, {
      token: staffToken,
      query: {
        message_limit: 20,
        event_limit: 12,
        include_closed_assignments: false,
      },
    }));

    pushConversationAiAssistResult(conversationDetailResponse?.data?.data?.ai_assist);
  } else {
    pushResult('SKIP', 'conversation detail', 'conversation list is empty and no explicit conversation_id was provided');
    pushResult('SKIP', 'conversation ai assist', 'conversation detail was skipped');
  }

  finalizeSmokeRun(config);
}

async function requestJson(method, routePath, { token, query, body, expectedStatuses = [200], idempotencyKey } = {}) {
  const url = new URL(routePath, `${config.apiBaseUrl}/`);

  for (const [key, value] of Object.entries(query ?? {})) {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value));
    }
  }

  const headers = {
    Accept: 'application/json',
  };

  if (token) {
    headers['X-Staff-Key'] = token;
  }

  if (idempotencyKey) {
    headers['Idempotency-Key'] = idempotencyKey;
  }

  let payload;
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  let response;
  try {
    response = await fetch(url, {
      method,
      headers,
      body: payload,
    });
  } catch (cause) {
    const error = new Error(`${method} ${routePath} network failure`);
    error.details = {
      kind: 'network',
      url: url.toString(),
      message: cause instanceof Error ? cause.message : String(cause),
    };
    throw error;
  }

  const contentType = response.headers.get('content-type') ?? '';
  const data = contentType.includes('application/json')
    ? await response.json().catch(() => null)
    : await response.text();

  if (!expectedStatuses.includes(response.status)) {
    const error = new Error(`${method} ${routePath} failed with ${response.status}`);
    error.details = { status: response.status, data, url: url.toString() };
    throw error;
  }

  return {
    status: response.status,
    data,
  };
}

async function runStep(name, fn, options = {}) {
  const critical = options.critical ?? false;

  try {
    const value = await fn();
    if (value && typeof value === 'object' && 'status' in value && 'data' in value) {
      pushResult('PASS', name, describePayload(value.data));
      return value;
    }

    pushResult('PASS', name, value ?? 'ok');
    return value ?? null;
  } catch (error) {
    pushResult('FAIL', name, formatErrorDetails(error));
    if (critical) {
      throw error;
    }

    return null;
  }
}

function pushResult(status, step, detail) {
  results.push({ status, step, detail });
}

function pushConversationAiAssistResult(aiAssist) {
  const summary = describeConversationAiAssist(aiAssist);
  pushResult(summary.status, 'conversation ai assist', summary.detail);
}

function printStartupContext(activeConfig) {
  console.log('staff-web live smoke');
  console.log('='.repeat(30));
  console.log(`api: ${activeConfig.apiBaseUrl}/api/v1`);
  console.log(`target: ${activeConfig.target}`);
  console.log(`mode: ${activeConfig.mode}`);
  console.log(`credentials: ${activeConfig.credentialSource}`);
  console.log(`manifest: ${describeManifest(activeConfig.manifest)}`);
  console.log(`preview: ${describePreview(activeConfig)}`);
  console.log(`mutation gates: ${describeMutationGate(activeConfig.mutationGate)}`);
  console.log('');
}

function printSummary(activeConfig) {
  const counts = summarizeResults();

  console.log('');
  console.log('staff-web live smoke summary');
  console.log('='.repeat(30));
  console.log(`mode=${activeConfig.mode} pass=${counts.PASS} skip=${counts.SKIP} fail=${counts.FAIL}`);
  console.log(`decision=${counts.FAIL > 0 ? 'block' : 'pass'} mutation_gates=${describeMutationGate(activeConfig.mutationGate)}`);

  for (const result of results) {
    console.log(`[${result.status}] ${result.step}: ${result.detail}`);
  }
}

function finalizeSmokeRun(activeConfig) {
  printSummary(activeConfig);

  const evidence = writeSmokeEvidence(activeConfig);
  if (evidence) {
    console.log(`evidence_json=${evidence.jsonPath}`);
    console.log(`evidence_markdown=${evidence.markdownPath}`);
  }

  process.exit(results.some((result) => result.status === 'FAIL') ? 1 : 0);
}

function summarizeResults(smokeResults = results) {
  return {
    PASS: smokeResults.filter((result) => result.status === 'PASS').length,
    SKIP: smokeResults.filter((result) => result.status === 'SKIP').length,
    FAIL: smokeResults.filter((result) => result.status === 'FAIL').length,
  };
}

function describePayload(data) {
  if (Array.isArray(data)) {
    return `items=${data.length}`;
  }

  if (data && typeof data === 'object') {
    const records = asArray(data.data);
    if (records.length > 0) {
      return `items=${records.length}`;
    }

    const primaryId = readNumber(data.data, 'order_id')
      ?? readNumber(data.data, 'reservation_id')
      ?? readNumber(data.data, 'cashier_shift_id');
    if (primaryId) {
      return `id=${primaryId}`;
    }
  }

  return 'ok';
}

function summarizeRealtime(data) {
  return `current_version=${readNumber(data, 'current_version') ?? 'n/a'}, has_changes=${Boolean(data?.has_changes)}, stale_cursor=${Boolean(data?.stale_cursor)}`;
}

function summarizeSettlement(data) {
  return `payment_status=${readString(data, 'payment_status') ?? 'n/a'}, outstanding=${data?.outstanding_amount ?? 'n/a'}`;
}

function summarizeStartup(session) {
  const readiness = session?.startup?.readiness ?? {};
  return `access=${readString(readiness, 'access') ?? 'n/a'}, branch=${readString(readiness, 'branch') ?? 'n/a'}, shift=${readString(readiness, 'cashier_shift') ?? 'n/a'}`;
}

function requireStartupContract(session, source) {
  if (!session || typeof session !== 'object') {
    throw new Error(`${source} did not return a staff session payload.`);
  }

  const startup = session.startup;
  if (!startup || typeof startup !== 'object') {
    throw new Error(`${source} is missing data.startup from the staff auth session envelope.`);
  }

  const readiness = startup.readiness;
  const access = readString(readiness, 'access');
  const branch = readString(readiness, 'branch');
  const cashierShift = readString(readiness, 'cashier_shift');

  if (!access || !branch || !cashierShift) {
    throw new Error(`${source} returned an incomplete startup readiness contract.`);
  }

  if (access !== 'ready') {
    throw new Error(`${source} startup readiness denies staff-web access.`);
  }

  if (branch !== 'ready' || !startup.default_branch) {
    throw new Error(`${source} startup readiness did not resolve a default branch.`);
  }

  return session;
}

function findBoardRow(rows, reservationId, tableId) {
  return asArray(rows).find((row) => {
    const rowReservationId = readNumber(row?.reservation, 'reservation_id');
    const rowTableId = readNumber(row, 'table_id');

    return (reservationId !== null && reservationId !== undefined && rowReservationId === reservationId)
      || (tableId !== null && tableId !== undefined && rowTableId === tableId);
  }) ?? null;
}

export function resolveCheckInPlan({ reservation, boardRow, fallbackTableId = null }) {
  const status = readString(reservation, 'status')?.toLowerCase() ?? null;
  const checkedInAt = readString(reservation, 'checked_in_at');
  const actionPayload = boardRow?.actions?.check_in?.available && boardRow?.actions?.check_in?.preferred_payload
    ? boardRow.actions.check_in.preferred_payload
    : null;
  const tableId = fallbackTableId
    ?? readFirstTableId(actionPayload)
    ?? readFirstTableId(reservation)
    ?? readNumber(boardRow, 'table_id');

  if (!reservation || status !== 'confirmed' || checkedInAt) {
    return {
      required: false,
      payload: actionPayload,
      tableId,
    };
  }

  if (actionPayload) {
    return {
      required: true,
      payload: actionPayload,
      tableId,
    };
  }

  const rowVersion = readNumber(reservation, 'row_version');
  if (rowVersion && tableId) {
    return {
      required: true,
      payload: {
        row_version: rowVersion,
        table_ids: [tableId],
      },
      tableId,
    };
  }

  return {
    required: true,
    payload: null,
    tableId,
  };
}

export function canExecuteRefund(refundPreview) {
  return Number(refundPreview?.refund?.refund_amount ?? 0) > 0;
}

export function describeConversationAiAssist(aiAssist) {
  if (!aiAssist || typeof aiAssist !== 'object') {
    return {
      status: 'SKIP',
      detail: 'ai assist payload not present in conversation detail',
    };
  }

  const assistStatus = readString(aiAssist, 'status');
  const provider = readString(aiAssist, 'provider') ?? 'n/a';
  const summary = readString(aiAssist, 'summary');
  const fallbackReason = readString(aiAssist, 'fallback_reason');

  if (!assistStatus) {
    return {
      status: 'SKIP',
      detail: 'conversation detail did not expose ai_assist.status',
    };
  }

  if (!ALLOWED_AI_ASSIST_STATUSES.has(assistStatus)) {
    return {
      status: 'FAIL',
      detail: `unexpected ai_assist.status=${assistStatus}; expected ready|disabled|unavailable`,
    };
  }

  return {
    status: 'PASS',
    detail: `status=${assistStatus}, provider=${provider}, note=${summary ?? fallbackReason ?? 'n/a'}`,
  };
}

export function shouldRecordBootstrapFailure(smokeResults = results) {
  return !smokeResults.some((result) => result.status === 'FAIL');
}

function pickReservation({ reservationId, reservationLookup, boardReservation }) {
  if (reservationId) {
    return reservationLookup.find((reservation) => readNumber(reservation, 'reservation_id') === reservationId) ?? boardReservation ?? null;
  }

  return reservationLookup.find((reservation) => readFirstTableId(reservation)) ?? boardReservation ?? null;
}

function pickOrderId(reservationOrders, boardOrder) {
  return readNumber(reservationOrders[0], 'order_id') ?? readNumber(boardOrder, 'order_id') ?? null;
}

function readFirstTableId(reservation) {
  const tableIds = Array.isArray(reservation?.table_ids) ? reservation.table_ids : [];
  return typeof tableIds[0] === 'number' ? tableIds[0] : null;
}

function asArray(value) {
  return Array.isArray(value) ? value : [];
}

function readNumber(source, key) {
  const value = source?.[key];

  if (typeof value === 'number') {
    return value;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isNaN(parsed) ? null : parsed;
  }

  return null;
}

function readString(source, key) {
  const value = source?.[key];
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function readBooleanEnv(value) {
  return value === '1' || value === 'true' || value === 'TRUE';
}

function readExplicitBooleanEnv(value) {
  if (value === undefined || value === null || value === '') {
    return null;
  }

  return readBooleanEnv(value);
}

function readOptionalNumber(value) {
  if (!value) {
    return null;
  }

  const parsed = Number(value);
  return Number.isNaN(parsed) ? null : parsed;
}

function normalizeApiBaseUrl(value) {
  return value.trim().replace(/\/+$/, '').replace(/\/api\/v1$/i, '');
}

function buildBoardWindow(reference = new Date()) {
  return {
    from: new Date(reference.getTime() - 60 * 60 * 1000).toISOString(),
    to: new Date(reference.getTime() + 4 * 60 * 60 * 1000).toISOString(),
  };
}

function idempotencyKey(prefix) {
  return `${prefix}-${randomUUID()}`;
}

function formatErrorDetails(error) {
  const details = error?.details;
  if (details?.kind === 'network') {
    return `network failure at ${details.url}: ${details.message}. Ensure the backend HTTP server is reachable and verify local runtime prerequisites with "php artisan booking:doctor --json".`;
  }

  if (details?.status) {
    const requestId = readString(details.data, 'request_id');
    const message = readString(details.data, 'message') ?? JSON.stringify(details.data);
    return `${details.status}${requestId ? ` req=${requestId}` : ''} ${message}`;
  }

  return error instanceof Error ? error.message : String(error);
}

export function buildSmokeEvidence(activeConfig, smokeResults = results) {
  const counts = summarizeResults(smokeResults);

  return {
    ok: counts.FAIL === 0,
    decision: counts.FAIL === 0 ? 'pass' : 'block',
    target: activeConfig.target,
    mode: activeConfig.mode,
    api_url: `${activeConfig.apiBaseUrl}/api/v1`,
    credential_source: activeConfig.credentialSource,
    manifest: {
      path: activeConfig.manifest.path,
      exists: activeConfig.manifest.exists,
      error: activeConfig.manifest.error,
    },
    preview: {
      label: activeConfig.previewLabel,
      url: activeConfig.previewUrl,
      status: activeConfig.previewUrl ? 'url-recorded' : 'not-configured',
    },
    mutation_gates: activeConfig.mutationGate,
    summary: {
      pass_count: counts.PASS,
      skip_count: counts.SKIP,
      fail_count: counts.FAIL,
    },
    steps: smokeResults,
    generated_at_utc: new Date().toISOString(),
  };
}

export function renderSmokeEvidenceMarkdown(evidence) {
  const lines = [
    '# Staff-Web Live Smoke',
    '',
    `- Decision: \`${evidence.decision}\``,
    `- Target: \`${evidence.target}\``,
    `- Mode: \`${evidence.mode}\``,
    `- API: \`${evidence.api_url}\``,
    `- Preview: \`${evidence.preview.url || evidence.preview.label || 'not-configured'}\``,
    `- Preview status: \`${evidence.preview.status || 'unknown'}\``,
    '',
    '## Steps',
    '',
    '| Step | Status | Detail |',
    '| --- | --- | --- |',
  ];

  for (const step of evidence.steps) {
    lines.push(`| ${step.step} | ${step.status} | ${String(step.detail ?? '').replaceAll('|', '\\|')} |`);
  }

  return lines.join('\n');
}

export function writeSmokeEvidence(activeConfig, smokeResults = results) {
  if (!activeConfig.evidenceDir) {
    return null;
  }

  const evidence = buildSmokeEvidence(activeConfig, smokeResults);
  const resolvedDir = path.isAbsolute(activeConfig.evidenceDir)
    ? activeConfig.evidenceDir
    : path.resolve(process.cwd(), activeConfig.evidenceDir);
  const targetSlug = slugifyEvidenceTarget(activeConfig.target);
  const timestamp = new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d{3}Z$/, 'Z');
  const baseName = `staff-web-live-smoke-${targetSlug}-${timestamp.toLowerCase()}`;
  const jsonPath = path.join(resolvedDir, `${baseName}.json`);
  const markdownPath = path.join(resolvedDir, `${baseName}.md`);
  const latestJsonPath = path.join(resolvedDir, `latest-${targetSlug}.json`);
  const latestMarkdownPath = path.join(resolvedDir, `latest-${targetSlug}.md`);
  const jsonContents = `${JSON.stringify(evidence, null, 2)}\n`;
  const markdownContents = `${renderSmokeEvidenceMarkdown(evidence)}\n`;

  mkdirSync(resolvedDir, { recursive: true });
  writeFileSync(jsonPath, jsonContents, 'utf8');
  writeFileSync(markdownPath, markdownContents, 'utf8');
  writeFileSync(latestJsonPath, jsonContents, 'utf8');
  writeFileSync(latestMarkdownPath, markdownContents, 'utf8');

  return {
    jsonPath,
    markdownPath,
    latestJsonPath,
    latestMarkdownPath,
  };
}

function slugifyEvidenceTarget(target) {
  return String(target || 'local')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'local';
}

function describePreview(activeConfig) {
  return activeConfig.previewUrl || activeConfig.previewLabel || 'not-configured';
}

export function createSmokeConfig(env = process.env, manifestResult = readSmokeManifest(DEFAULT_MANIFEST_PATH)) {
  const manifestData = manifestResult.data;
  const mutationGate = resolveMutationGate(env, manifestData);
  const previewUrl = env.STAFF_WEB_SMOKE_PREVIEW_URL ?? '';
  const previewLabel = env.STAFF_WEB_SMOKE_PREVIEW_LABEL ?? 'preview';
  const evidenceDir = env.STAFF_WEB_SMOKE_EVIDENCE_DIR ?? '';

  return {
    apiBaseUrl: normalizeApiBaseUrl(
      env.STAFF_WEB_SMOKE_API_URL
      ?? env.VITE_API_URL
      ?? readManifestString(manifestData, 'pack.base_url')
      ?? 'http://localhost:8000/api/v1',
    ),
    identifier: env.STAFF_WEB_SMOKE_IDENTIFIER ?? readManifestString(manifestData, 'auth.staff.username') ?? '',
    password: env.STAFF_WEB_SMOKE_PASSWORD ?? readManifestString(manifestData, 'auth.staff.password') ?? '',
    deviceName: env.STAFF_WEB_SMOKE_DEVICE_NAME ?? 'staff-web-live-smoke',
    reservationQuery: env.STAFF_WEB_SMOKE_RESERVATION_QUERY ?? readManifestString(manifestData, 'reservations.dine_in_checkin.reservation_code') ?? '',
    reservationId: readOptionalNumber(env.STAFF_WEB_SMOKE_RESERVATION_ID) ?? readManifestNumber(manifestData, 'scenarios.dine_in_checkout.reservation_id') ?? readManifestNumber(manifestData, 'reservations.dine_in_checkin.reservation_id'),
    orderTableId: readOptionalNumber(env.STAFF_WEB_SMOKE_ORDER_TABLE_ID) ?? readManifestNumber(manifestData, 'scenarios.dine_in_checkout.table_id'),
    refundReservationId: readOptionalNumber(env.STAFF_WEB_SMOKE_REFUND_RESERVATION_ID)
      ?? readManifestNumber(manifestData, 'scenarios.refund_partial.reservation_id')
      ?? readManifestNumber(manifestData, 'reservations.refund_partial_ready.reservation_id')
      ?? readManifestNumber(manifestData, 'scenarios.refund_cancel.reservation_id')
      ?? readManifestNumber(manifestData, 'reservations.refund_cancel_ready.reservation_id'),
    orderId: readOptionalNumber(env.STAFF_WEB_SMOKE_ORDER_ID),
    conversationId: env.STAFF_WEB_SMOKE_CONVERSATION_ID ?? readManifestString(manifestData, 'scenarios.conversation_inbox.conversation_id') ?? readManifestString(manifestData, 'conversation.conversation_id') ?? '',
    conversationQuery: env.STAFF_WEB_SMOKE_CONVERSATION_QUERY ?? '',
    allowMutations: Object.values(mutationGate).some((gate) => gate.enabled),
    allowOrderCreate: mutationGate.orderCreate.enabled,
    allowOrderAddItem: mutationGate.orderAddItem.enabled,
    allowSettlementFinalize: mutationGate.settlementFinalize.enabled,
    allowRefundMutation: mutationGate.refundExecute.enabled,
    allowCashierOpen: mutationGate.cashierOpen.enabled,
    allowCashierClose: mutationGate.cashierClose.enabled,
    mutationGate,
    paymentMethod: env.STAFF_WEB_SMOKE_PAYMENT_METHOD ?? 'Cash',
    paymentProvider: env.STAFF_WEB_SMOKE_PAYMENT_PROVIDER ?? 'Cash',
    cashierOpeningFloat: readOptionalNumber(env.STAFF_WEB_SMOKE_CASHIER_OPENING_FLOAT) ?? 100000,
    cashierCurrency: env.STAFF_WEB_SMOKE_CASHIER_CURRENCY ?? readManifestString(manifestData, 'branch.currency') ?? 'VND',
    cashierBranchId: readOptionalNumber(env.STAFF_WEB_SMOKE_CASHIER_BRANCH_ID) ?? readManifestNumber(manifestData, 'branch.branch_id'),
    cashierTerminalCode: env.STAFF_WEB_SMOKE_CASHIER_TERMINAL_CODE ?? 'staff-web-live-smoke',
    credentialSource: resolveCredentialSource(env, manifestData),
    mode: resolveSmokeMode(env, manifestData),
    target: env.STAFF_WEB_SMOKE_TARGET ?? 'local',
    previewUrl: typeof previewUrl === 'string' ? previewUrl.trim() : '',
    previewLabel: typeof previewLabel === 'string' && previewLabel.trim() !== '' ? previewLabel.trim() : 'preview',
    evidenceDir: typeof evidenceDir === 'string' && evidenceDir.trim() !== '' ? evidenceDir.trim() : '',
    manifest: manifestResult,
  };
}

export function readSmokeManifest(manifestPath) {
  const resolvedPath = path.isAbsolute(manifestPath) ? manifestPath : path.resolve(process.cwd(), manifestPath);

  if (!existsSync(resolvedPath)) {
    return {
      path: resolvedPath,
      exists: false,
      data: null,
      error: null,
    };
  }

  try {
    return {
      path: resolvedPath,
      exists: true,
      data: JSON.parse(readFileSync(resolvedPath, 'utf8')),
      error: null,
    };
  } catch (error) {
    return {
      path: resolvedPath,
      exists: true,
      data: null,
      error: error instanceof Error ? error.message : String(error),
    };
  }
}

export function describeStartupBlockers(activeConfig) {
  const blockers = [];

  if (activeConfig.manifest.error) {
    blockers.push(`unable to parse STAFF_WEB_SMOKE_MANIFEST_PATH at ${activeConfig.manifest.path}: ${activeConfig.manifest.error}`);
  }

  if (!activeConfig.identifier || !activeConfig.password) {
    blockers.push(
      `missing staff login credentials. Set STAFF_WEB_SMOKE_IDENTIFIER and STAFF_WEB_SMOKE_PASSWORD, or bootstrap the canonical UAT pack with "php artisan booking:uat-pack:bootstrap --base-url=http://127.0.0.1:8000 --json" to create ${activeConfig.manifest.path} with auth.staff.username/password.`,
    );
  }

  return blockers;
}

export function resolveSmokeMode(env = process.env, manifestData = null) {
  return Object.values(resolveMutationGate(env, manifestData)).some((gate) => gate.enabled)
    ? 'mutation-gated'
    : 'read-only';
}

export function resolveMutationGate(env = process.env, manifestData = null) {
  const sharedMutationGate = readExplicitBooleanEnv(env.STAFF_WEB_SMOKE_ALLOW_MUTATIONS)
    ?? readManifestMutationGate(manifestData, 'allow_mutations');

  return {
    orderCreate: resolveMutationGateAction({
      envValue: readExplicitBooleanEnv(env.STAFF_WEB_SMOKE_ALLOW_ORDER_CREATE),
      manifestValue: readManifestMutationGate(manifestData, 'order_create'),
      inheritedValue: sharedMutationGate,
    }),
    orderAddItem: resolveMutationGateAction({
      envValue: readExplicitBooleanEnv(env.STAFF_WEB_SMOKE_ALLOW_ORDER_ADD_ITEM),
      manifestValue: readManifestMutationGate(manifestData, 'order_add_item'),
      inheritedValue: sharedMutationGate,
    }),
    settlementFinalize: resolveMutationGateAction({
      envValue: readExplicitBooleanEnv(env.STAFF_WEB_SMOKE_ALLOW_SETTLEMENT_FINALIZE),
      manifestValue: readManifestMutationGate(manifestData, 'settlement_finalize'),
      inheritedValue: sharedMutationGate,
    }),
    refundExecute: resolveMutationGateAction({
      envValue: readExplicitBooleanEnv(env.STAFF_WEB_SMOKE_ALLOW_REFUND_MUTATION),
      manifestValue: readManifestMutationGate(manifestData, 'refund_execute'),
      inheritedValue: sharedMutationGate,
    }),
    cashierOpen: resolveMutationGateAction({
      envValue: readExplicitBooleanEnv(env.STAFF_WEB_SMOKE_ALLOW_CASHIER_OPEN),
      manifestValue: readManifestMutationGate(manifestData, 'cashier_open'),
      inheritedValue: sharedMutationGate,
    }),
    cashierClose: resolveMutationGateAction({
      envValue: readExplicitBooleanEnv(env.STAFF_WEB_SMOKE_ALLOW_CASHIER_CLOSE),
      manifestValue: readManifestMutationGate(manifestData, 'cashier_close'),
      inheritedValue: null,
    }),
  };
}

export function describeMutationGate(mutationGate) {
  return Object.entries(mutationGate)
    .map(([key, gate]) => `${key}=${gate.enabled ? 'on' : 'off'}(${gate.source})`)
    .join(', ');
}

function resolveCredentialSource(env, manifestData) {
  if (env.STAFF_WEB_SMOKE_IDENTIFIER && env.STAFF_WEB_SMOKE_PASSWORD) {
    return 'env';
  }

  if (readManifestString(manifestData, 'auth.staff.username') && readManifestString(manifestData, 'auth.staff.password')) {
    return 'manifest';
  }

  return 'missing';
}

function describeManifest(manifestResult) {
  if (manifestResult.error) {
    return `invalid (${manifestResult.path})`;
  }

  if (manifestResult.exists) {
    return `loaded (${manifestResult.path})`;
  }

  return `missing (${manifestResult.path})`;
}

function readManifestString(source, dottedPath) {
  const value = readManifestValue(source, dottedPath);
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function readManifestNumber(source, dottedPath) {
  const value = readManifestValue(source, dottedPath);
  if (typeof value === 'number') {
    return value;
  }
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isNaN(parsed) ? null : parsed;
  }
  return null;
}

function readManifestBoolean(source, dottedPath) {
  const value = readManifestValue(source, dottedPath);

  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    return readBooleanEnv(value);
  }

  return null;
}

function readManifestMutationGate(source, key) {
  return readManifestBoolean(source, `staff_web_smoke.mutations.${key}`)
    ?? readManifestBoolean(source, `scenarios.staff_web_smoke.mutations.${key}`);
}

function resolveMutationGateAction({ envValue, manifestValue, inheritedValue }) {
  if (envValue !== null) {
    return { enabled: envValue, source: 'env' };
  }

  if (manifestValue !== null) {
    return { enabled: manifestValue, source: 'manifest' };
  }

  if (inheritedValue !== null) {
    return { enabled: inheritedValue, source: 'shared' };
  }

  return { enabled: false, source: 'default-off' };
}

function readManifestValue(source, dottedPath) {
  return dottedPath.split('.').reduce((current, segment) => {
    if (!current || typeof current !== 'object') {
      return null;
    }

    return current[segment] ?? null;
  }, source);
}

function isExecutedDirectly() {
  return process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);
}
