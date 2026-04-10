import { describe, expect, it } from 'vitest';
import { formatApiError, normalizeApiError } from './api-errors';
import { buildApiError } from '../test/fixtures';
import { StaffApiError } from '../core/api/http';

describe('api-errors', () => {
  it('normalizes validation payloads with field errors', () => {
    const error = buildApiError(422, {
      error_code: 'validation_error',
      message: 'Validation failed.',
      errors: {
        row_version: ['The row_version field is required.'],
      },
    });

    const normalized = normalizeApiError(error, 'Fallback');

    expect(normalized.kind).toBe('validation');
    expect(normalized.status).toBe(422);
    expect(normalized.message).toBe('Validation failed.');
    expect(normalized.code).toBe('validation_error');
    expect(normalized.validation.row_version).toEqual(['The row_version field is required.']);
  });

  it('falls back to the first useful API message', () => {
    const error = buildApiError(409, {
      error: {
        message: 'Stale row_version detected.',
      },
    });

    expect(formatApiError(error, 'Fallback')).toBe('Stale row_version detected.');
  });

  it('merges top-level and details validation errors and prefers actionable detail messages', () => {
    const error = buildApiError(422, {
      error_code: 'validation_error',
      message: 'Validation error.',
      request_id: 'req-422',
      errors: {
        conversation_inbox: ['Conversation inbox is disabled for this rollout.'],
      },
      details: {
        errors: {
          row_version: ['Du lieu da thay doi (row_version mismatch). Hay reload roi thu lai.'],
        },
      },
    });

    const normalized = normalizeApiError(error, 'Fallback');

    expect(normalized.message).toBe('Conversation inbox is disabled for this rollout.');
    expect(normalized.requestId).toBe('req-422');
    expect(normalized.validation.conversation_inbox).toEqual(['Conversation inbox is disabled for this rollout.']);
    expect(normalized.validation.row_version).toEqual(['Du lieu da thay doi (row_version mismatch). Hay reload roi thu lai.']);
  });

  it('surfaces required capability and request id in formatted operator messages', () => {
    const error = buildApiError(403, {
      error_code: 'forbidden',
      message: 'Forbidden.',
      required_capability: 'conversation.manage',
      request_id: 'req-403',
    });

    const formatted = formatApiError(error, 'Fallback');

    expect(formatted).toContain('conversation.manage');
    expect(formatted).toContain('req-403');
  });

  it('normalizes StaffApiError payloads from the new staff-api seam', () => {
    const error = new StaffApiError(409, {
      error_code: 'conflict',
      message: 'State conflict detected.',
      request_id: 'req-staff-api',
    }, 'Conflict');

    const normalized = normalizeApiError(error, 'Fallback');

    expect(normalized.kind).toBe('conflict');
    expect(normalized.status).toBe(409);
    expect(normalized.code).toBe('conflict');
    expect(normalized.requestId).toBe('req-staff-api');
  });
});
