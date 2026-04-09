import { mkdtempSync, readFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import {
  buildSmokeEvidence,
  canExecuteRefund,
  createSmokeConfig,
  describeConversationAiAssist,
  describeMutationGate,
  describeStartupBlockers,
  resolveCheckInPlan,
  resolveMutationGate,
  resolveSmokeMode,
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
    expect(config.orderTableId).toBe(11);
    expect(config.refundReservationId).toBe(91);
    expect(config.conversationId).toBe('conv-uat-1');
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
        settlement_finalize: true,
        refund_execute: true,
      },
    };

    const gate = resolveMutationGate({}, manifest.data);

    expect(gate.settlementFinalize).toEqual({ enabled: true, source: 'manifest' });
    expect(gate.refundExecute).toEqual({ enabled: true, source: 'manifest' });
    expect(gate.cashierClose).toEqual({ enabled: false, source: 'default-off' });
    expect(describeMutationGate(gate)).toContain('settlementFinalize=on(manifest)');
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
        },
      },
      scenarios: {
        dine_in_checkout: {
          reservation_id: 77,
          table_id: 11,
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
