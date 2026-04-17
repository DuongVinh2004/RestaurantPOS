import { describe, expect, it } from 'vitest';
import { isRowVersionConflict, rowVersionConflictMessage } from './conflicts';
import { buildApiError } from '../../test/fixtures';

describe('conflicts', () => {
  it('recognizes row_version conflicts from 409 payloads', () => {
    const error = buildApiError(409, {
      error_code: 'conflict',
      message: 'Conflict.',
      errors: {
        row_version: ['The supplied row_version is stale.'],
      },
    });

    expect(isRowVersionConflict(error)).toBe(true);
  });

  it('recognizes stale row_version from 422 validation payloads', () => {
    const error = buildApiError(422, {
      error_code: 'validation_error',
      message: 'Validation error.',
      details: {
        errors: {
          row_version: ['Du lieu da thay doi (row_version mismatch). Hay reload roi thu lai.'],
        },
      },
    });

    expect(isRowVersionConflict(error)).toBe(true);
  });

  it('does not treat missing row_version validation as a concurrency conflict', () => {
    const error = buildApiError(422, {
      error_code: 'validation_error',
      message: 'Validation error.',
      errors: {
        row_version: ['The row_version field is required.'],
      },
    });

    expect(isRowVersionConflict(error)).toBe(false);
  });

  it('returns a frontend-safe refresh message', () => {
    expect(rowVersionConflictMessage('Order #99')).toContain('Order #99');
    expect(rowVersionConflictMessage('Order #99')).toContain('tải lại dữ liệu');
  });
});
