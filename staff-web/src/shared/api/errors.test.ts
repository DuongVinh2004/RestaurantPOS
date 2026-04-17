import { describe, expect, it } from 'vitest';
import { formatApiError, normalizeApiError } from './errors';
import { StaffApiError } from './http';

describe('core api errors', () => {
  it('formats traceable errors without dropping capability and request id', () => {
    const error = new StaffApiError(403, {
      error_code: 'forbidden',
      message: 'Forbidden.',
      required_capability: 'settlement.manage',
      request_id: 'req-staff-403',
    }, 'Forbidden');

    const formatted = formatApiError(error, 'Khong the hoan tat thao tac.');

    expect(formatted).toContain('settlement.manage');
    expect(formatted).toContain('req-staff-403');
  });

  it('normalizes machine-readable domain metadata for mutation mapping', () => {
    const error = new StaffApiError(409, {
      error_code: 'stale_row_version',
      category_code: 'stale_write',
      state_reason: 'table_busy',
      conflict_type: 'stale_write',
      replay_state: 'payload_mismatch',
      next_actions: ['refresh', 'retry'],
      message: 'The resource was modified by another writer.',
      request_id: 'req-staff-409',
    }, 'Conflict');

    expect(normalizeApiError(error, 'Fallback')).toMatchObject({
      code: 'stale_row_version',
      categoryCode: 'stale_write',
      stateReason: 'table_busy',
      conflictType: 'stale_write',
      replayState: 'payload_mismatch',
      nextActions: ['refresh', 'retry'],
      requestId: 'req-staff-409',
    });
  });
});
