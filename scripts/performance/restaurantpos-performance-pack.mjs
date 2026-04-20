import crypto from 'node:crypto';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, isAbsolute, resolve } from 'node:path';
import { performance } from 'node:perf_hooks';
import { fileURLToPath } from 'node:url';

if (typeof fetch !== 'function') {
  throw new Error('Node 18+ with global fetch is required for the performance harness.');
}

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');

const args = parseArgs(process.argv.slice(2));
const profile = requiredArg(args, 'profile');
const baseUrl = requiredArg(args, 'base-url').replace(/\/+$/, '');
const manifestPath = resolveRepoPath(requiredArg(args, 'manifest-path'));
const catalogPath = resolveRepoPath(requiredArg(args, 'catalog-path'));
const outputDir = resolveRepoPath(requiredArg(args, 'output-dir'));

const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
const catalog = JSON.parse(readFileSync(catalogPath, 'utf8'));
const scenarioMap = new Map((catalog.scenarios ?? []).map((scenario) => [scenario.key, scenario]));
const selectedKeys = args.scenario.length > 0
  ? args.scenario
  : (catalog.scenarios ?? [])
      .filter((scenario) => scenario.automation === 'automated' && scenario.profile_settings?.[profile])
      .map((scenario) => scenario.key);

const handlers = {
  availability_read_load: runAvailabilityReadLoad,
  reservation_show_load: runReservationShowLoad,
  waiting_list_queue_load: runWaitingListQueueLoad,
  staff_board_timeline_load: runStaffBoardTimelineLoad,
  checkout_preview_load: runCheckoutPreviewLoad,
  reservation_create_race: runReservationCreateRace,
  payment_webhook_burst: runPaymentWebhookBurst,
  mixed_service_day_soak: runMixedServiceDaySoak,
  webhook_duplicate_storm: runWebhookDuplicateStorm,
};

if (selectedKeys.length === 0) {
  throw new Error(`No automated scenarios selected for profile [${profile}].`);
}

mkdirSync(outputDir, { recursive: true });

const context = await buildContext({ baseUrl, manifest, profile });
const results = [];

for (const scenarioKey of selectedKeys) {
  const scenario = scenarioMap.get(scenarioKey);
  if (!scenario) {
    throw new Error(`Scenario [${scenarioKey}] is not defined in the catalog.`);
  }

  const handler = handlers[scenario.runner_key];
  if (!handler) {
    throw new Error(`Scenario [${scenarioKey}] has no runner handler [${scenario.runner_key}].`);
  }

  const startedAt = new Date().toISOString();

  try {
    const raw = await handler(scenario, profile, context);
    const completed = {
      ...raw,
      scenario_key: scenario.key,
      scenario_label: scenario.label,
      type: scenario.type,
      profile,
      status: 'completed',
      started_at_utc: startedAt,
      ended_at_utc: new Date().toISOString(),
      endpoints_touched: scenario.endpoints ?? [],
    };

    const artifactPath = resolve(outputDir, `${scenario.key}.json`);
    completed.artifact_path = relativeToRepo(artifactPath);
    writeJson(artifactPath, completed);
    results.push({
      scenario_key: scenario.key,
      artifact_path: completed.artifact_path,
      status: 'completed',
    });
  } catch (error) {
    const failed = {
      scenario_key: scenario.key,
      scenario_label: scenario.label,
      type: scenario.type,
      profile,
      status: 'failed',
      started_at_utc: startedAt,
      ended_at_utc: new Date().toISOString(),
      endpoints_touched: scenario.endpoints ?? [],
      requests: {
        count: 0,
        status_counts: {},
        tag_counts: {},
      },
      operations: {
        count: 0,
        success_count: 0,
        controlled_conflict_count: 0,
        unexpected_error_count: 1,
        cleanup_error_count: 0,
        latency_ms: zeroLatencySummary(),
        throughput_rps: 0,
      },
      business_counters: {
        accepted_response_count: 0,
        duplicate_count: 0,
        applied_count: 0,
        ignored_count: 0,
        failed_delivery_count: 0,
      },
      rates: {
        unexpected_error_rate: 1,
        accepted_response_rate: 0,
        controlled_conflict_rate: 0,
        duplicate_rate: 0,
        failed_delivery_rate: 0,
      },
      notes: [String(error instanceof Error ? error.message : error)],
      samples: [],
    };

    const artifactPath = resolve(outputDir, `${scenario.key}.json`);
    failed.artifact_path = relativeToRepo(artifactPath);
    writeJson(artifactPath, failed);
    results.push({
      scenario_key: scenario.key,
      artifact_path: failed.artifact_path,
      status: 'failed',
    });
  }
}

writeJson(resolve(outputDir, 'raw-index.json'), {
  profile,
  base_url: baseUrl,
  generated_at_utc: new Date().toISOString(),
  scenario_count: results.length,
  scenarios: results,
});

async function buildContext({ baseUrl: runtimeBaseUrl, manifest: runtimeManifest, profile: runtimeProfile }) {
  const customerPrimary = await loginCustomer(runtimeBaseUrl, runtimeManifest.auth.customer_primary, `perf-${runtimeProfile}-primary`);
  const customerSecondary = await loginCustomer(runtimeBaseUrl, runtimeManifest.auth.customer_secondary, `perf-${runtimeProfile}-secondary`);

  return {
    baseUrl: runtimeBaseUrl,
    profile: runtimeProfile,
    manifest: runtimeManifest,
    auth: {
      anonymous: {
        Accept: 'application/json',
      },
      staff: {
        Accept: 'application/json',
        'X-Staff-Key': runtimeManifest.auth.staff.api_key,
      },
      admin: {
        Accept: 'application/json',
        'X-Staff-Key': runtimeManifest.auth.admin.api_key,
      },
      customerPrimary,
      customerSecondary,
    },
    runtime: {
      checkoutContext: null,
      webhookContext: null,
    },
  };
}

async function loginCustomer(base, auth, sessionLabel) {
  const response = await requestJson(base, {
    method: 'POST',
    path: '/api/v1/auth/customer/login',
    headers: {
      Accept: 'application/json',
    },
    body: {
      identifier: auth.username,
      password: auth.password,
      session_label: sessionLabel,
    },
    tag: 'customer_login',
  });

  if (response.status !== 200 || !response.json?.data?.access_token) {
    throw new Error(`Customer login failed for [${auth.username}] with status [${response.status}].`);
  }

  return {
    Accept: 'application/json',
    'X-Customer-Token': response.json.data.access_token,
  };
}

function parseArgs(argv) {
  const parsed = { scenario: [] };

  for (const token of argv) {
    if (!token.startsWith('--')) {
      continue;
    }

    const withoutPrefix = token.slice(2);
    const separatorIndex = withoutPrefix.indexOf('=');
    if (separatorIndex === -1) {
      parsed[withoutPrefix] = true;
      continue;
    }

    const key = withoutPrefix.slice(0, separatorIndex);
    const value = withoutPrefix.slice(separatorIndex + 1);

    if (key === 'scenario') {
      parsed.scenario.push(value);
      continue;
    }

    parsed[key] = value;
  }

  return parsed;
}

function requiredArg(parsed, key) {
  const value = parsed[key];
  if (typeof value !== 'string' || value.trim() === '') {
    throw new Error(`Missing required argument [--${key}=...].`);
  }

  return value.trim();
}

function resolveRepoPath(targetPath) {
  return isAbsolute(targetPath) ? targetPath : resolve(repoRoot, targetPath);
}

function relativeToRepo(targetPath) {
  const normalizedTarget = targetPath.replace(/\\/g, '/');
  const normalizedRoot = repoRoot.replace(/\\/g, '/');

  if (normalizedTarget.startsWith(`${normalizedRoot}/`)) {
    return normalizedTarget.slice(normalizedRoot.length + 1);
  }

  return normalizedTarget;
}

function writeJson(path, payload) {
  writeFileSync(path, JSON.stringify(payload, null, 2));
}

function zeroLatencySummary() {
  return {
    p50: 0,
    p95: 0,
    p99: 0,
    mean: 0,
    max: 0,
  };
}

function createCollector() {
  return {
    latencies: [],
    requestCount: 0,
    operationCount: 0,
    successCount: 0,
    controlledConflictCount: 0,
    unexpectedErrorCount: 0,
    cleanupErrorCount: 0,
    acceptedResponseCount: 0,
    duplicateCount: 0,
    appliedCount: 0,
    ignoredCount: 0,
    failedDeliveryCount: 0,
    statusCounts: {},
    tagCounts: {},
    notes: [],
    samples: [],
    record(operation) {
      this.operationCount += 1;
      this.latencies.push(operation.latencyMs);

      if (operation.status === 'success') {
        this.successCount += 1;
      } else if (operation.status === 'controlled_conflict') {
        this.controlledConflictCount += 1;
      } else {
        this.unexpectedErrorCount += 1;
      }

      if (Number.isFinite(operation.cleanupErrorCount) && operation.cleanupErrorCount > 0) {
        this.cleanupErrorCount += operation.cleanupErrorCount;
      }

      if (operation.business?.acceptedResponse) {
        this.acceptedResponseCount += 1;
      }

      if (operation.business?.duplicate) {
        this.duplicateCount += 1;
      }

      if (operation.business?.deliveryStatus === 'Applied') {
        this.appliedCount += 1;
      } else if (operation.business?.deliveryStatus === 'Ignored') {
        this.ignoredCount += 1;
      } else if (operation.business?.deliveryStatus === 'Failed') {
        this.failedDeliveryCount += 1;
      }

      for (const requestResult of operation.requestResults ?? []) {
        this.requestCount += 1;
        const statusKey = requestResult.status > 0 ? String(requestResult.status) : 'network_error';
        this.statusCounts[statusKey] = (this.statusCounts[statusKey] ?? 0) + 1;
        if (requestResult.tag) {
          this.tagCounts[requestResult.tag] = (this.tagCounts[requestResult.tag] ?? 0) + 1;
        }
      }

      for (const note of operation.notes ?? []) {
        if (note && !this.notes.includes(note)) {
          this.notes.push(note);
        }
      }

      if (this.samples.length < 5) {
        this.samples.push({
          status: operation.status,
          latency_ms: round(operation.latencyMs),
          request_statuses: (operation.requestResults ?? []).map((requestResult) => requestResult.status),
          delivery_status: operation.business?.deliveryStatus ?? null,
          duplicate: operation.business?.duplicate ?? false,
        });
      }
    },
    finalize(elapsedSeconds) {
      return {
        requests: {
          count: this.requestCount,
          status_counts: this.statusCounts,
          tag_counts: this.tagCounts,
        },
        operations: {
          count: this.operationCount,
          success_count: this.successCount,
          controlled_conflict_count: this.controlledConflictCount,
          unexpected_error_count: this.unexpectedErrorCount,
          cleanup_error_count: this.cleanupErrorCount,
          latency_ms: summarizeLatencies(this.latencies),
          throughput_rps: round(this.operationCount / Math.max(elapsedSeconds, 0.001), 4),
        },
        business_counters: {
          accepted_response_count: this.acceptedResponseCount,
          duplicate_count: this.duplicateCount,
          applied_count: this.appliedCount,
          ignored_count: this.ignoredCount,
          failed_delivery_count: this.failedDeliveryCount,
        },
        rates: {
          unexpected_error_rate: round(this.unexpectedErrorCount / Math.max(this.operationCount, 1), 4),
          accepted_response_rate: round(this.acceptedResponseCount / Math.max(this.operationCount, 1), 4),
          controlled_conflict_rate: round(this.controlledConflictCount / Math.max(this.operationCount, 1), 4),
          duplicate_rate: round(this.duplicateCount / Math.max(this.operationCount, 1), 4),
          failed_delivery_rate: round(this.failedDeliveryCount / Math.max(this.operationCount, 1), 4),
        },
        notes: this.notes,
        samples: this.samples,
      };
    },
  };
}

function summarizeLatencies(latencies) {
  if (latencies.length === 0) {
    return zeroLatencySummary();
  }

  const sorted = [...latencies].sort((left, right) => left - right);
  const sum = sorted.reduce((carry, value) => carry + value, 0);

  return {
    p50: round(percentile(sorted, 0.5)),
    p95: round(percentile(sorted, 0.95)),
    p99: round(percentile(sorted, 0.99)),
    mean: round(sum / sorted.length),
    max: round(sorted[sorted.length - 1]),
  };
}

function percentile(sorted, ratio) {
  if (sorted.length === 0) {
    return 0;
  }

  const index = Math.min(sorted.length - 1, Math.max(0, Math.ceil(sorted.length * ratio) - 1));
  return sorted[index];
}

function round(value, precision = 2) {
  const factor = 10 ** precision;
  return Math.round((value + Number.EPSILON) * factor) / factor;
}

function newIdempotencyKey(prefix) {
  return `${prefix}-${Date.now()}-${crypto.randomBytes(3).toString('hex')}`;
}

function simulatedWebhookSecret() {
  const secret = process.env.PAYMENT_PROVIDER_SIMULATED_WEBHOOK_SECRET ?? '';
  if (!secret) {
    throw new Error('PAYMENT_PROVIDER_SIMULATED_WEBHOOK_SECRET must be set for webhook scenarios.');
  }

  return secret;
}

function signWebhookPayload(payload) {
  const body = JSON.stringify(payload);
  return {
    body,
    signature: crypto.createHmac('sha256', simulatedWebhookSecret()).update(body).digest('hex'),
  };
}

async function requestJson(base, { method = 'GET', path, headers = {}, body = undefined, timeoutMs = 30000, tag = '' }) {
  const url = path.startsWith('http') ? path : `${base}${path}`;
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);
  const startedAt = performance.now();

  try {
    const response = await fetch(url, {
      method,
      headers: body === undefined
        ? headers
        : {
            'Content-Type': 'application/json',
            ...headers,
          },
      body: body === undefined ? undefined : JSON.stringify(body),
      signal: controller.signal,
    });
    const text = await response.text();
    let json = null;
    try {
      json = text ? JSON.parse(text) : null;
    } catch {
      json = null;
    }

    return {
      ok: response.ok,
      status: response.status,
      latencyMs: performance.now() - startedAt,
      json,
      text,
      tag,
      path,
    };
  } catch (error) {
    return {
      ok: false,
      status: 0,
      latencyMs: performance.now() - startedAt,
      json: null,
      text: String(error instanceof Error ? error.message : error),
      tag,
      path,
    };
  } finally {
    clearTimeout(timeout);
  }
}

function buildOperation({ responses, status, latencyMs, acceptedResponse = false, duplicate = false, deliveryStatus = null, cleanupErrorCount = 0, notes = [] }) {
  return {
    status,
    latencyMs,
    requestResults: responses.map((response) => ({
      tag: response.tag,
      status: response.status,
      path: response.path,
      latency_ms: round(response.latencyMs),
    })),
    business: {
      acceptedResponse,
      duplicate,
      deliveryStatus,
    },
    cleanupErrorCount,
    notes,
  };
}

async function runTimedWorkers({ durationSeconds, concurrency, action, collector }) {
  const startedAt = performance.now();
  const deadline = Date.now() + durationSeconds * 1000;

  async function worker(workerIndex) {
    while (Date.now() < deadline) {
      collector.record(await action(workerIndex));
    }
  }

  await Promise.all(Array.from({ length: concurrency }, (_, index) => worker(index)));

  return collector.finalize((performance.now() - startedAt) / 1000);
}

async function runOneShotBurst({ concurrency, action, collector }) {
  const startedAt = performance.now();

  await Promise.all(Array.from({ length: concurrency }, async (_, index) => {
    collector.record(await action(index));
  }));

  return collector.finalize((performance.now() - startedAt) / 1000);
}

async function ensureCheckoutContext(context) {
  if (context.runtime.checkoutContext) {
    return context.runtime.checkoutContext;
  }

  const reservationId = context.manifest.scenarios.dine_in_checkout.reservation_id;
  const tableId = context.manifest.scenarios.dine_in_checkout.table_id;
  const [firstItemId, secondItemId] = context.manifest.scenarios.dine_in_checkout.menu_item_ids;

  const activeOrder = await requestJson(context.baseUrl, {
    method: 'GET',
    path: `/api/v1/reservations/${reservationId}/active-order`,
    headers: context.auth.customerPrimary,
    tag: 'checkout_active_order_lookup',
  });

  if (activeOrder.status === 200 && activeOrder.json?.data?.active_order?.order_id) {
    context.runtime.checkoutContext = {
      reservationId,
      orderId: activeOrder.json.data.active_order.order_id,
    };

    return context.runtime.checkoutContext;
  }

  const checkIn = await requestJson(context.baseUrl, {
    method: 'POST',
    path: `/api/v1/staff/reservations/${reservationId}/check-in`,
    headers: {
      ...context.auth.staff,
      'Idempotency-Key': newIdempotencyKey('perf-check-in'),
    },
    body: {
      table_ids: [tableId],
      checked_in_at: new Date().toISOString(),
      row_version: context.manifest.reservations.dine_in_checkin.row_version,
    },
    tag: 'checkout_check_in',
  });

  if (checkIn.status !== 200) {
    throw new Error(`Check-in setup failed with status [${checkIn.status}] for reservation [${reservationId}].`);
  }

  const createOrder = await requestJson(context.baseUrl, {
    method: 'POST',
    path: `/api/v1/staff/tables/${tableId}/orders`,
    headers: {
      ...context.auth.staff,
      'Idempotency-Key': newIdempotencyKey('perf-order-create'),
    },
    body: {
      reservation_id: reservationId,
      row_version: checkIn.json?.data?.row_version,
      items: [
        {
          menu_item_id: firstItemId,
          qty: 1,
        },
      ],
    },
    tag: 'checkout_order_create',
  });

  if (createOrder.status !== 201 && createOrder.status !== 200) {
    throw new Error(`Order creation setup failed with status [${createOrder.status}] for reservation [${reservationId}].`);
  }

  const orderId = createOrder.json?.data?.order_id;
  if (!orderId) {
    throw new Error('Checkout setup did not return order_id.');
  }

  const addItem = await requestJson(context.baseUrl, {
    method: 'POST',
    path: `/api/v1/staff/orders/${orderId}/items`,
    headers: {
      ...context.auth.staff,
      'Idempotency-Key': newIdempotencyKey('perf-order-items'),
    },
    body: {
      row_version: createOrder.json?.data?.row_version,
      items: [
        {
          menu_item_id: secondItemId,
          qty: 1,
        },
      ],
    },
    tag: 'checkout_order_add_item',
  });

  if (addItem.status !== 200) {
    throw new Error(`Checkout item setup failed with status [${addItem.status}] for order [${orderId}].`);
  }

  context.runtime.checkoutContext = {
    reservationId,
    orderId,
  };

  return context.runtime.checkoutContext;
}

async function ensureWebhookContext(context) {
  if (context.runtime.webhookContext) {
    return context.runtime.webhookContext;
  }

  const reservationId = context.manifest.scenarios.deposit_self_pay.reservation_id;
  const preview = await requestJson(context.baseUrl, {
    method: 'GET',
    path: `/api/v1/reservations/${reservationId}/deposit-preview`,
    headers: context.auth.customerPrimary,
    tag: 'deposit_preview',
  });

  if (preview.status !== 200) {
    throw new Error(`Deposit preview setup failed with status [${preview.status}] for reservation [${reservationId}].`);
  }

  const rowVersion = preview.json?.data?.reservation?.row_version
    ?? preview.json?.data?.deposit?.reservation?.row_version
    ?? context.manifest.reservations.deposit_pending.row_version;

  const createSession = await requestJson(context.baseUrl, {
    method: 'POST',
    path: `/api/v1/reservations/${reservationId}/deposit/payment-sessions`,
    headers: {
      ...context.auth.customerPrimary,
      'Idempotency-Key': newIdempotencyKey('perf-deposit-session'),
    },
    body: {
      row_version: rowVersion,
      amount: Number(context.manifest.scenarios.deposit_self_pay.payment_amount),
      payment_method: 'Online',
      provider_code: context.manifest.scenarios.deposit_self_pay.provider_code,
      currency: context.manifest.branch.currency,
    },
    tag: 'deposit_session_create',
  });

  if ((createSession.status !== 201 && createSession.status !== 200) || !createSession.json?.data?.payment_session?.provider_session_code) {
    throw new Error(`Deposit payment-session setup failed with status [${createSession.status}] for reservation [${reservationId}].`);
  }

  context.runtime.webhookContext = {
    reservationId,
    providerCode: context.manifest.scenarios.deposit_self_pay.provider_code,
    providerSessionCode: createSession.json.data.payment_session.provider_session_code,
  };

  return context.runtime.webhookContext;
}

function classifyReadResponse(response) {
  const status = response.status === 200 ? 'success' : 'unexpected_error';
  return buildOperation({
    responses: [response],
    status,
    latencyMs: response.latencyMs,
    notes: status === 'unexpected_error' ? [`Unexpected status [${response.status}] for [${response.tag}]`] : [],
  });
}

function classifyWebhookResponse(response) {
  const deliveryStatus = response.json?.data?.delivery_status ?? null;
  const success = response.status === 202;

  return buildOperation({
    responses: [response],
    status: success ? 'success' : 'unexpected_error',
    latencyMs: response.latencyMs,
    acceptedResponse: success,
    duplicate: Boolean(response.json?.data?.duplicate),
    deliveryStatus,
    notes: success ? [] : [`Unexpected webhook status [${response.status}]`],
  });
}

async function runAvailabilityReadLoad(scenario, profileKey, context) {
  const settings = scenario.profile_settings[profileKey];
  const collector = createCollector();
  const availability = context.manifest.scenarios.availability_hold_reservation;
  const query = `/api/v1/tables/available?branch_id=${availability.branch_id}&from=${encodeURIComponent(availability.from_utc)}&to=${encodeURIComponent(availability.to_utc)}&guest_count=${availability.guest_count}&session_id=${encodeURIComponent(availability.session_id)}&suggest=1`;

  return runTimedWorkers({
    durationSeconds: settings.duration_seconds,
    concurrency: settings.concurrency,
    collector,
    action: async () => classifyReadResponse(await requestJson(context.baseUrl, {
      method: 'GET',
      path: query,
      headers: context.auth.anonymous,
      tag: 'availability_read',
    })),
  });
}

async function runReservationShowLoad(scenario, profileKey, context) {
  const settings = scenario.profile_settings[profileKey];
  const collector = createCollector();
  const reservationId = context.manifest.reservations.deposit_pending.reservation_id;

  return runTimedWorkers({
    durationSeconds: settings.duration_seconds,
    concurrency: settings.concurrency,
    collector,
    action: async () => classifyReadResponse(await requestJson(context.baseUrl, {
      method: 'GET',
      path: `/api/v1/reservations/${reservationId}`,
      headers: context.auth.customerPrimary,
      tag: 'reservation_show',
    })),
  });
}

async function runWaitingListQueueLoad(scenario, profileKey, context) {
  const settings = scenario.profile_settings[profileKey];
  const collector = createCollector();

  return runTimedWorkers({
    durationSeconds: settings.duration_seconds,
    concurrency: settings.concurrency,
    collector,
    action: async () => {
      const useChanges = Math.random() < 0.3;
      const response = await requestJson(context.baseUrl, {
        method: 'GET',
        path: useChanges
          ? '/api/v1/staff/waiting-list/changes'
          : '/api/v1/staff/waiting-list?active_only=1&per_page=25&sort=-priority',
        headers: context.auth.staff,
        tag: useChanges ? 'waiting_list_changes' : 'waiting_list_index',
      });

      return classifyReadResponse(response);
    },
  });
}

async function runStaffBoardTimelineLoad(scenario, profileKey, context) {
  const settings = scenario.profile_settings[profileKey];
  const collector = createCollector();
  const availability = context.manifest.scenarios.availability_hold_reservation;
  const timelineDate = String(availability.from_utc).slice(0, 10);
  const boardQuery = `/api/v1/staff/tables/board?from=${encodeURIComponent(availability.from_utc)}&to=${encodeURIComponent(availability.to_utc)}`;
  const timelineQuery = `/api/v1/staff/reservations/timeline?date=${timelineDate}&lane_by=table&include_candidate_tables=1`;

  return runTimedWorkers({
    durationSeconds: settings.duration_seconds,
    concurrency: settings.concurrency,
    collector,
    action: async () => {
      const useBoard = Math.random() < 0.5;
      const response = await requestJson(context.baseUrl, {
        method: 'GET',
        path: useBoard ? boardQuery : timelineQuery,
        headers: context.auth.staff,
        tag: useBoard ? 'staff_board' : 'staff_timeline',
      });

      return classifyReadResponse(response);
    },
  });
}

async function runCheckoutPreviewLoad(scenario, profileKey, context) {
  const settings = scenario.profile_settings[profileKey];
  const collector = createCollector();
  const checkoutContext = await ensureCheckoutContext(context);
  const refundReservationId = context.manifest.scenarios.refund_partial.reservation_id;

  return runTimedWorkers({
    durationSeconds: settings.duration_seconds,
    concurrency: settings.concurrency,
    collector,
    action: async () => {
      const choice = Math.random();
      let response;

      if (choice < 0.25) {
        response = await requestJson(context.baseUrl, {
          method: 'GET',
          path: `/api/v1/reservations/${checkoutContext.reservationId}/active-order`,
          headers: context.auth.customerPrimary,
          tag: 'checkout_active_order',
        });
      } else if (choice < 0.5) {
        response = await requestJson(context.baseUrl, {
          method: 'GET',
          path: `/api/v1/reservations/${checkoutContext.reservationId}/bill-preview`,
          headers: context.auth.customerPrimary,
          tag: 'checkout_bill_preview',
        });
      } else if (choice < 0.75) {
        response = await requestJson(context.baseUrl, {
          method: 'GET',
          path: `/api/v1/staff/orders/${checkoutContext.orderId}/settlement-preview`,
          headers: context.auth.staff,
          tag: 'checkout_settlement_preview',
        });
      } else {
        response = await requestJson(context.baseUrl, {
          method: 'GET',
          path: `/api/v1/staff/reservations/${refundReservationId}/refund-preview?refund_scope=deposit&refund_amount=20000&cancel_after_payment=0`,
          headers: context.auth.staff,
          tag: 'checkout_refund_preview',
        });
      }

      return classifyReadResponse(response);
    },
  });
}

async function runReservationCreateRace(scenario, profileKey, context) {
  const settings = scenario.profile_settings[profileKey];
  const collector = createCollector();
  const availability = context.manifest.scenarios.availability_hold_reservation;
  const preferredTableId = availability.preferred_table_ids[0];
  const cleanupReservations = [];
  const cleanupHolds = [];

  const result = await runOneShotBurst({
    concurrency: settings.concurrency,
    collector,
    action: async (workerIndex) => {
      const startedAt = performance.now();
      const sessionId = `${availability.session_id}-perf-race-${workerIndex}-${Date.now()}-${crypto.randomBytes(2).toString('hex')}`;
      const holdResponse = await requestJson(context.baseUrl, {
        method: 'POST',
        path: '/api/v1/table-holds',
        headers: {
          ...context.auth.anonymous,
          'Idempotency-Key': newIdempotencyKey('perf-hold'),
        },
        body: {
          branch_id: availability.branch_id,
          session_id: sessionId,
          start_time: availability.from_utc,
          end_time: availability.to_utc,
          table_ids: [preferredTableId],
          hold_minutes: 5,
        },
        tag: 'reservation_race_hold',
      });

      if (holdResponse.status !== 200 && holdResponse.status !== 201) {
        return buildOperation({
          responses: [holdResponse],
          status: isConflictStatus(holdResponse.status) ? 'controlled_conflict' : 'unexpected_error',
          latencyMs: performance.now() - startedAt,
          notes: isConflictStatus(holdResponse.status)
            ? []
            : [`Unexpected hold status [${holdResponse.status}]`],
        });
      }

      const holdId = holdResponse.json?.data?.hold_id;
      const reservationResponse = await requestJson(context.baseUrl, {
        method: 'POST',
        path: '/api/v1/reservations',
        headers: {
          ...context.auth.customerPrimary,
          'Idempotency-Key': newIdempotencyKey('perf-reservation'),
        },
        body: {
          hold_id: holdId,
          session_id: sessionId,
          start_time: availability.from_utc,
          end_time: availability.to_utc,
          guest_count: availability.guest_count,
          notes: 'Performance verification reservation create race',
        },
        tag: 'reservation_race_create',
      });

      if (reservationResponse.status === 200 || reservationResponse.status === 201) {
        cleanupReservations.push({
          reservationId: reservationResponse.json?.data?.reservation_id,
          rowVersion: reservationResponse.json?.data?.row_version,
        });

        return buildOperation({
          responses: [holdResponse, reservationResponse],
          status: 'success',
          latencyMs: performance.now() - startedAt,
        });
      }

      if (holdId) {
        cleanupHolds.push({ holdId, sessionId });
      }

      return buildOperation({
        responses: [holdResponse, reservationResponse],
        status: isConflictStatus(reservationResponse.status) ? 'controlled_conflict' : 'unexpected_error',
        latencyMs: performance.now() - startedAt,
        notes: isConflictStatus(reservationResponse.status)
          ? []
          : [`Unexpected reservation status [${reservationResponse.status}]`],
      });
    },
  });

  for (const reservation of cleanupReservations) {
    if (!reservation.reservationId || !reservation.rowVersion) {
      continue;
    }

    const cleanup = await requestJson(context.baseUrl, {
      method: 'PATCH',
      path: `/api/v1/reservations/${reservation.reservationId}/status`,
      headers: {
        ...context.auth.staff,
        'Idempotency-Key': newIdempotencyKey('perf-reservation-cancel'),
      },
      body: {
        status: 'Cancelled',
        row_version: reservation.rowVersion,
        cancel_reason: 'performance_verification_cleanup',
      },
      tag: 'reservation_race_cleanup',
    });

    if (cleanup.status !== 200) {
      result.operations.cleanup_error_count += 1;
      result.notes.push(`Cleanup cancel failed for reservation [${reservation.reservationId}] with status [${cleanup.status}]`);
    }
  }

  const uniqueCleanupHolds = new Map();
  for (const hold of cleanupHolds) {
    if (hold?.holdId) {
      uniqueCleanupHolds.set(hold.holdId, hold);
    }
  }

  for (const hold of uniqueCleanupHolds.values()) {
    const cleanup = await requestJson(context.baseUrl, {
      method: 'DELETE',
      path: `/api/v1/table-holds/${hold.holdId}`,
      headers: {
        ...context.auth.staff,
        'Idempotency-Key': newIdempotencyKey('perf-hold-cancel'),
      },
      tag: 'reservation_race_hold_cleanup',
    });

    if (cleanup.status !== 200) {
      result.operations.cleanup_error_count += 1;
      result.notes.push(`Cleanup hold cancel failed for hold [${hold.holdId}] with status [${cleanup.status}]`);
    }
  }

  return result;
}

async function runPaymentWebhookBurst(scenario, profileKey, context) {
  const settings = scenario.profile_settings[profileKey];
  const collector = createCollector();
  const webhookContext = await ensureWebhookContext(context);

  return runOneShotBurst({
    concurrency: settings.concurrency,
    collector,
    action: async (workerIndex) => {
      const payload = {
        provider_event_code: `perf-burst-${workerIndex}-${Date.now()}-${crypto.randomBytes(2).toString('hex')}`,
        provider_session_code: webhookContext.providerSessionCode,
        payment_scope: 'deposit',
        simulation_outcome: 'succeeded',
      };
      const signed = signWebhookPayload(payload);

      const response = await requestJson(context.baseUrl, {
        method: 'POST',
        path: `/api/v1/payments/providers/${webhookContext.providerCode}/webhooks`,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Payment-Signature': signed.signature,
          'X-Payment-Timestamp': new Date().toISOString(),
        },
        body: payload,
        tag: 'webhook_burst',
      });

      return classifyWebhookResponse(response);
    },
  });
}

async function runMixedServiceDaySoak(scenario, profileKey, context) {
  const settings = scenario.profile_settings[profileKey];
  const collector = createCollector();
  const availability = context.manifest.scenarios.availability_hold_reservation;
  const timelineDate = String(availability.from_utc).slice(0, 10);
  const checkoutContext = await ensureCheckoutContext(context);
  const webhookContext = await ensureWebhookContext(context);
  const reservationId = context.manifest.reservations.deposit_pending.reservation_id;
  const refundReservationId = context.manifest.scenarios.refund_partial.reservation_id;

  return runTimedWorkers({
    durationSeconds: settings.duration_seconds,
    concurrency: settings.concurrency,
    collector,
    action: async (workerIndex) => {
      const choice = Math.random();

      if (choice < 0.2) {
        return classifyReadResponse(await requestJson(context.baseUrl, {
          method: 'GET',
          path: `/api/v1/tables/available?branch_id=${availability.branch_id}&from=${encodeURIComponent(availability.from_utc)}&to=${encodeURIComponent(availability.to_utc)}&guest_count=${availability.guest_count}&session_id=${encodeURIComponent(availability.session_id)}&suggest=1`,
          headers: context.auth.anonymous,
          tag: 'soak_availability',
        }));
      }

      if (choice < 0.35) {
        return classifyReadResponse(await requestJson(context.baseUrl, {
          method: 'GET',
          path: `/api/v1/reservations/${reservationId}`,
          headers: context.auth.customerPrimary,
          tag: 'soak_reservation_show',
        }));
      }

      if (choice < 0.5) {
        return classifyReadResponse(await requestJson(context.baseUrl, {
          method: 'GET',
          path: '/api/v1/staff/waiting-list?active_only=1&per_page=25&sort=-priority',
          headers: context.auth.staff,
          tag: 'soak_waiting_list',
        }));
      }

      if (choice < 0.65) {
        return classifyReadResponse(await requestJson(context.baseUrl, {
          method: 'GET',
          path: `/api/v1/staff/tables/board?from=${encodeURIComponent(availability.from_utc)}&to=${encodeURIComponent(availability.to_utc)}`,
          headers: context.auth.staff,
          tag: 'soak_staff_board',
        }));
      }

      if (choice < 0.8) {
        return classifyReadResponse(await requestJson(context.baseUrl, {
          method: 'GET',
          path: `/api/v1/staff/reservations/timeline?date=${timelineDate}&lane_by=table&include_candidate_tables=1`,
          headers: context.auth.staff,
          tag: 'soak_staff_timeline',
        }));
      }

      if (choice < 0.9) {
        return classifyReadResponse(await requestJson(context.baseUrl, {
          method: 'GET',
          path: `/api/v1/staff/orders/${checkoutContext.orderId}/settlement-preview`,
          headers: context.auth.staff,
          tag: 'soak_settlement_preview',
        }));
      }

      if (choice < 0.97) {
        return classifyReadResponse(await requestJson(context.baseUrl, {
          method: 'GET',
          path: `/api/v1/staff/reservations/${refundReservationId}/refund-preview?refund_scope=deposit&refund_amount=20000&cancel_after_payment=0`,
          headers: context.auth.staff,
          tag: 'soak_refund_preview',
        }));
      }

      const payload = {
        provider_event_code: `perf-soak-${workerIndex}-${Date.now()}-${crypto.randomBytes(2).toString('hex')}`,
        provider_session_code: webhookContext.providerSessionCode,
        payment_scope: 'deposit',
        simulation_outcome: 'succeeded',
      };
      const signed = signWebhookPayload(payload);
      return classifyWebhookResponse(await requestJson(context.baseUrl, {
        method: 'POST',
        path: `/api/v1/payments/providers/${webhookContext.providerCode}/webhooks`,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Payment-Signature': signed.signature,
          'X-Payment-Timestamp': new Date().toISOString(),
        },
        body: payload,
        tag: 'soak_webhook',
      }));
    },
  });
}

async function runWebhookDuplicateStorm(scenario, profileKey, context) {
  const settings = scenario.profile_settings[profileKey];
  const collector = createCollector();
  const webhookContext = await ensureWebhookContext(context);
  const payload = {
    provider_event_code: `perf-duplicate-${Date.now()}-${crypto.randomBytes(2).toString('hex')}`,
    provider_session_code: webhookContext.providerSessionCode,
    payment_scope: 'deposit',
    simulation_outcome: 'succeeded',
  };

  return runOneShotBurst({
    concurrency: settings.concurrency,
    collector,
    action: async () => {
      const signed = signWebhookPayload(payload);
      const response = await requestJson(context.baseUrl, {
        method: 'POST',
        path: `/api/v1/payments/providers/${webhookContext.providerCode}/webhooks`,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Payment-Signature': signed.signature,
          'X-Payment-Timestamp': new Date().toISOString(),
        },
        body: payload,
        tag: 'webhook_duplicate_storm',
      });

      return classifyWebhookResponse(response);
    },
  });
}

function isConflictStatus(status) {
  return [409, 422, 423].includes(status);
}
