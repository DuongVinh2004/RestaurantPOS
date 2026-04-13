import { mkdtempSync, readFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import {
  buildSmokeEvidence,
  canExecuteRefund,
  createSmokeConfig,
  resolveConversationId,
  resolveRefundReservationId,
  resolveReservationSelection,
  describeHealthFailurePayload,
  describeConversationAiAssist,
  describeMutationGate,
  describeStartupBlockers,
  resolveCheckInPlan,
  resolveMutationGate,
  resolveMenuItemSelection,
  resolveSmokeMode,
  shouldPrepareCashierBeforeFinance,
  shouldPrepareCashierBeforeOperationalOrderFlow,
  shouldRecordBootstrapFailure,
  writeSmokeEvidence,
} from './live-smoke.mjs';

describe('live smoke config', () => {
  it('hydrates credentials and canonical ids from the UAT manifest when env is absent', () => {
    const config = createSmokeConfig({}, createManifestResult());

    expect(config.apiBaseUrl).toBe('http://127.0.0.1:8000');
    expect(config.identifier).toBe('uat.staff');
    expect(config.password).toBe('UatDemo!123');
    expect(config.reservationQuery).toBe('RES-77');
    expect(config.reservationId).toBe(77);
    expect(config.reservationIdSource).toBe('manifest');
    expect(config.orderTableId).toBe(11);
    expect(config.menuItemIds).toEqual([201, 202]);
    expect(config.menuItemIdsSource).toBe('manifest');
    expect(config.refundReservationId).toBe(91);
    expect(config.refundReservationIdSource).toBe('manifest');
    expect(config.refundReservationCode).toBe('RSV-RF-91');
    expect(config.conversationId).toBe('conv-uat-1');
    expect(config.conversationIdSource).toBe('manifest');
    expect(config.cashierBranchId).toBe(5);
    expect(config.cashierCurrency).toBe('VND');
    expect(config.credentialSource).toBe('manifest');
    expect(config.mode).toBe('read-only');
    expect(config.target).toBe('local');
  });

  it('reports actionable startup blockers when credentials are still missing', () => {
    const config = createSmokeConfig({}, {
      path: 'C:\\repo\\storage\\app\\uat\\scenario-pack.json',
      exists: false,
      data: null,
      error: null,
    });

    expect(describeStartupBlockers(config)).toEqual([
      expect.stringContaining('STAFF_WEB_SMOKE_IDENTIFIER'),
    ]);
    expect(describeStartupBlockers(config)[0]).toContain('booking:uat-pack:bootstrap');
    expect(describeStartupBlockers(config)[0]).toContain('C:\\repo\\storage\\app\\uat\\scenario-pack.json');
  });

  it('treats partial mutation gates as mutation-gated mode', () => {
    expect(resolveSmokeMode({
      STAFF_WEB_SMOKE_ALLOW_SETTLEMENT_FINALIZE: '1',
    })).toBe('mutation-gated');
    expect(resolveSmokeMode({})).toBe('read-only');
  });

  it('hydrates mutation gates from the manifest when env is absent', () => {
    const manifest = createManifestResult();
    manifest.data.staff_web_smoke = {
      mutations: {
        kitchen_dispatch: true,
        settlement_finalize: true,
        refund_execute: true,
      },
    };

    const gate = resolveMutationGate({}, manifest.data);

    expect(gate.kitchenDispatch).toEqual({ enabled: true, source: 'manifest' });
    expect(gate.settlementFinalize).toEqual({ enabled: true, source: 'manifest' });
    expect(gate.refundExecute).toEqual({ enabled: true, source: 'manifest' });
    expect(gate.cashierOpen).toEqual({ enabled: true, source: 'prerequisite' });
    expect(gate.cashierClose).toEqual({ enabled: false, source: 'default-off' });
    expect(describeMutationGate(gate)).toContain('kitchenDispatch=on(manifest)');
    expect(describeMutationGate(gate)).toContain('settlementFinalize=on(manifest)');
    expect(describeMutationGate(gate)).toContain('cashierOpen=on(prerequisite)');
  });

  it('lets env override manifest mutation gates per action', () => {
    const manifest = createManifestResult();
    manifest.data.staff_web_smoke = {
      mutations: {
        allow_mutations: true,
        cashier_close: true,
      },
    };

    const config = createSmokeConfig({
      STAFF_WEB_SMOKE_ALLOW_CASHIER_CLOSE: '0',
    }, manifest);

    expect(config.allowOrderCreate).toBe(true);
    expect(config.allowSettlementFinalize).toBe(true);
    expect(config.allowCashierClose).toBe(false);
    expect(config.mode).toBe('mutation-gated');
  });

  it('primes cashier before finance mutations but stays lazy for read-only smoke', () => {
    expect(shouldPrepareCashierBeforeFinance(createSmokeConfig({}, createManifestResult()))).toBe(false);

    expect(shouldPrepareCashierBeforeFinance(createSmokeConfig({
      STAFF_WEB_SMOKE_ALLOW_SETTLEMENT_FINALIZE: '1',
    }, createManifestResult()))).toBe(true);

    expect(shouldPrepareCashierBeforeFinance(createSmokeConfig({
      STAFF_WEB_SMOKE_ALLOW_REFUND_MUTATION: '1',
    }, createManifestResult()))).toBe(true);
  });

  it('primes cashier before operational order mutations so branch-scoped reads can pass', () => {
    expect(shouldPrepareCashierBeforeOperationalOrderFlow(createSmokeConfig({}, createManifestResult()))).toBe(false);

    expect(shouldPrepareCashierBeforeOperationalOrderFlow(createSmokeConfig({
      STAFF_WEB_SMOKE_ALLOW_ORDER_CREATE: '1',
    }, createManifestResult()))).toBe(true);

    expect(shouldPrepareCashierBeforeOperationalOrderFlow(createSmokeConfig({
      STAFF_WEB_SMOKE_ALLOW_ORDER_ADD_ITEM: '1',
    }, createManifestResult()))).toBe(true);

    expect(shouldPrepareCashierBeforeOperationalOrderFlow(createSmokeConfig({
      STAFF_WEB_SMOKE_ALLOW_KITCHEN_DISPATCH: '1',
    }, createManifestResult()))).toBe(true);
  });

  it('locks menu item selection to canonical scenario ids before order add-item', () => {
    expect(resolveMenuItemSelection({
      configuredMenuItemIds: [202, 201],
      configuredMenuItemIdsSource: 'manifest',
      menuItems: [
        { item_id: 200, is_available: true, name: 'Fallback' },
        { item_id: 201, is_available: true, name: 'Canonical Item' },
      ],
    })).toEqual({
      item: { item_id: 201, is_available: true, name: 'Canonical Item' },
      source: 'manifest',
      matchedConfiguredId: 201,
    });
  });

  it('does not silently fall back to a different menu item when canonical ids are stale', () => {
    expect(resolveMenuItemSelection({
      configuredMenuItemIds: [202, 203],
      configuredMenuItemIdsSource: 'manifest',
      menuItems: [
        { item_id: 200, is_available: true, name: 'Fallback' },
      ],
    })).toEqual({
      item: null,
      source: 'manifest',
      matchedConfiguredId: null,
    });
  });

  it('prefers board check-in payload before order create when reservation is still confirmed', () => {
    expect(resolveCheckInPlan({
      reservation: {
        status: 'Confirmed',
        row_version: 7,
        table_ids: [11],
      },
      boardRow: {
        table_id: 11,
        actions: {
          check_in: {
            available: true,
            preferred_payload: {
              row_version: 8,
              table_ids: [11],
            },
          },
        },
      },
    })).toEqual({
      required: true,
      payload: {
        row_version: 8,
        table_ids: [11],
      },
      tableId: 11,
    });
  });

  it('falls back to manifest-compatible check-in payload when board action metadata is missing', () => {
    expect(resolveCheckInPlan({
      reservation: {
        status: 'Confirmed',
        row_version: 7,
      },
      boardRow: null,
      fallbackTableId: 11,
    })).toEqual({
      required: true,
      payload: {
        row_version: 7,
        table_ids: [11],
      },
      tableId: 11,
    });
  });

  it('does not trust a stale manifest reservation id when runtime lookup disagrees', () => {
    expect(resolveReservationSelection({
      configuredReservationId: 77,
      configuredReservationIdSource: 'manifest',
      reservationLookup: [
        {
          reservation_id: 91,
          table_ids: [11],
        },
      ],
      boardReservation: null,
    })).toEqual({
      reservation: {
        reservation_id: 91,
        table_ids: [11],
      },
      reservationId: 91,
    });
  });

  it('falls back to a runtime operational reservation when the manifest reservation is already completed', () => {
    expect(resolveReservationSelection({
      configuredReservationId: 77,
      configuredReservationIdSource: 'manifest',
      reservationLookup: [
        {
          reservation_id: 77,
          status: 'Completed',
          table_ids: [11],
          checked_out_at: '2026-04-11T07:00:00Z',
        },
        {
          reservation_id: 91,
          status: 'Reserved',
          table_ids: [12],
          checked_out_at: null,
        },
      ],
      boardReservation: null,
    })).toEqual({
      reservation: {
        reservation_id: 91,
        status: 'Reserved',
        table_ids: [12],
        checked_out_at: null,
      },
      reservationId: 91,
    });
  });

  it('keeps an explicit env reservation id even when lookup is empty', () => {
    expect(resolveReservationSelection({
      configuredReservationId: 77,
      configuredReservationIdSource: 'env',
      reservationLookup: [],
      boardReservation: null,
    })).toEqual({
      reservation: null,
      reservationId: 77,
    });
  });

  it('reconciles refund reservation id from runtime lookup before preview calls', () => {
    expect(resolveRefundReservationId({
      configuredReservationId: 91,
      configuredReservationIdSource: 'manifest',
      refundReservationLookup: [
        { reservation_id: 13, reservation_code: 'RSV-RF-91' },
      ],
      selectedReservation: { reservation_id: 77 },
      fallbackReservationId: 77,
    })).toBe(13);
  });

  it('does not silently fall back to a different reservation when the manifest refund scenario is stale', () => {
    expect(resolveRefundReservationId({
      configuredReservationId: 91,
      configuredReservationIdSource: 'manifest',
      refundReservationLookup: [],
      selectedReservation: { reservation_id: 77 },
      fallbackReservationId: 77,
    })).toBeNull();
  });

  it('falls back to the first runtime conversation when a manifest conversation id is stale', () => {
    expect(resolveConversationId({
      configuredConversationId: 'conv-stale',
      configuredConversationIdSource: 'manifest',
      conversations: [
        { conversation_id: 'conv-live-1' },
        { conversation_id: 'conv-live-2' },
      ],
    })).toBe('conv-live-1');
  });

  it('treats refund preview amount as the execute gate', () => {
    expect(canExecuteRefund({
      refund: {
        refund_amount: '100000.00',
        refund_payment_ids: [],
      },
    })).toBe(true);

    expect(canExecuteRefund({
      refund: {
        refund_amount: '0.00',
        refund_payment_ids: [1],
      },
    })).toBe(false);
  });

  it('summarizes conversation ai assist without turning the smoke lane into a hard blocker', () => {
    expect(describeConversationAiAssist({
      status: 'ready',
      provider: 'local_heuristic',
      summary: 'Reservation RES-77 needs follow-up.',
    })).toEqual({
      status: 'PASS',
      detail: 'status=ready, provider=local_heuristic, note=Reservation RES-77 needs follow-up.',
    });

    expect(describeConversationAiAssist({
      status: 'disabled',
      provider: 'local_heuristic',
      fallback_reason: 'Conversation AI assist is disabled for this rollout.',
    })).toEqual({
      status: 'PASS',
      detail: 'status=disabled, provider=local_heuristic, note=Conversation AI assist is disabled for this rollout.',
    });

    expect(describeConversationAiAssist(null)).toEqual({
      status: 'SKIP',
      detail: 'ai assist payload not present in conversation detail',
    });

    expect(describeConversationAiAssist({
      status: 'broken',
      provider: 'local_heuristic',
    })).toEqual({
      status: 'FAIL',
      detail: 'unexpected ai_assist.status=broken; expected ready|disabled|unavailable',
    });
  });

  it('records bootstrap failures only when no earlier step already failed', () => {
    expect(shouldRecordBootstrapFailure([])).toBe(true);
    expect(shouldRecordBootstrapFailure([
      { status: 'PASS', step: 'login', detail: 'ok' },
    ])).toBe(true);
    expect(shouldRecordBootstrapFailure([
      { status: 'FAIL', step: 'backend health', detail: 'network failure' },
    ])).toBe(false);
  });

  it('summarizes public health failures from the additive checks payload', () => {
    expect(describeHealthFailurePayload({
      status: 'fail',
      checks: {
        db: { ok: true, reason: null },
        redis: {
          ok: false,
          reason: 'redis_unavailable',
          error: 'MISCONF Redis is configured to save RDB snapshots',
        },
        scheduler: {
          ok: false,
          reason: 'scheduler_heartbeat_missing',
          age_seconds: null,
          stale_threshold_seconds: 180,
        },
        disk: { ok: true, reason: null },
      },
    })).toBe('health=fail redis=redis_unavailable (MISCONF Redis is configured to save RDB snapshots); scheduler=scheduler_heartbeat_missing ttl=180s');
  });

  it('builds preview-aware smoke evidence with decision summary', () => {
    const config = createSmokeConfig({
      STAFF_WEB_SMOKE_TARGET: 'staging',
      STAFF_WEB_SMOKE_PREVIEW_URL: 'https://preview.example.test',
      STAFF_WEB_SMOKE_PREVIEW_LABEL: 'vercel-preview',
    }, createManifestResult());

    const evidence = buildSmokeEvidence(config, [
      { status: 'PASS', step: 'login', detail: 'ok' },
      { status: 'SKIP', step: 'settlement finalize', detail: 'mutation gate disabled' },
    ]);

    expect(evidence.decision).toBe('pass');
    expect(evidence.target).toBe('staging');
    expect(evidence.preview.url).toBe('https://preview.example.test');
    expect(evidence.preview.status).toBe('url-recorded');
    expect(evidence.summary.skip_count).toBe(1);
  });

  it('writes json and markdown evidence files when an evidence dir is configured', () => {
    const evidenceDir = mkdtempSync(path.join(os.tmpdir(), 'staff-web-live-smoke-'));
    const config = createSmokeConfig({
      STAFF_WEB_SMOKE_TARGET: 'preview',
      STAFF_WEB_SMOKE_EVIDENCE_DIR: evidenceDir,
    }, createManifestResult());

    const written = writeSmokeEvidence(config, [
      { status: 'FAIL', step: 'backend health', detail: 'network failure' },
    ]);

    expect(written).not.toBeNull();
    expect(readFileSync(written.jsonPath, 'utf8')).toContain('"decision": "block"');
    expect(readFileSync(written.latestJsonPath, 'utf8')).toContain('"target": "preview"');
    expect(readFileSync(written.latestJsonPath, 'utf8')).toContain('"status": "not-configured"');
    expect(readFileSync(written.markdownPath, 'utf8')).toContain('# Staff-Web Live Smoke');
    expect(readFileSync(written.markdownPath, 'utf8')).toContain('Preview status');
  });
});

function createManifestResult() {
  return {
    path: 'C:\\repo\\storage\\app\\uat\\scenario-pack.json',
    exists: true,
    error: null,
    data: {
      pack: {
        base_url: 'http://127.0.0.1:8000',
      },
      branch: {
        branch_id: 5,
        currency: 'VND',
      },
      auth: {
        staff: {
          username: 'uat.staff',
          password: 'UatDemo!123',
        },
      },
      reservations: {
        dine_in_checkin: {
          reservation_code: 'RES-77',
          reservation_id: 77,
        },
        refund_partial_ready: {
          reservation_id: 91,
          reservation_code: 'RSV-RF-91',
        },
      },
      scenarios: {
        dine_in_checkout: {
          reservation_id: 77,
          table_id: 11,
          menu_item_ids: [201, 202],
        },
        refund_partial: {
          reservation_id: 91,
        },
        conversation_inbox: {
          conversation_id: 'conv-uat-1',
        },
      },
    },
  };
}
