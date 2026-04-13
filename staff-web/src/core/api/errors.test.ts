import { describe, expect, it } from 'vitest';
import { StaffApiError } from './http';
import { formatApiError } from './errors';

describe('core api errors', () => {
  it('formats traceable errors without English operator copy', () => {
    const error = new StaffApiError(403, {
      error_code: 'forbidden',
      message: 'Forbidden.',
      required_capability: 'settlement.manage',
      request_id: 'req-staff-403',
    }, 'Forbidden');

    expect(formatApiError(error, 'Không thể hoàn tất thao tác.')).toContain('Thiếu quyền: settlement.manage.');
    expect(formatApiError(error, 'Không thể hoàn tất thao tác.')).toContain('Mã truy vết: req-staff-403.');
  });
});
