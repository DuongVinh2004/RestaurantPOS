import { describe, expect, it } from 'vitest';
import { StaffApiError } from '../api/http';
import { mapMutationErrorToFeedback } from './mutation-ux';

describe('mutation-ux mapping', () => {
  const context = {
    actionLabel: 'Cap nhat ban',
    fallbackMessage: 'Khong the cap nhat ban.',
  };

  it('maps stale row version errors to conflict feedback', () => {
    const error = new StaffApiError(409, {
      error_code: 'stale_row_version',
      category_code: 'stale_write',
      conflict_type: 'stale_write',
      message: 'The resource was modified by another writer.',
    }, 'Conflict');

    expect(mapMutationErrorToFeedback(error, context)).toMatchObject({
      phase: 'conflict',
      retryable: true,
      errorCode: 'stale_row_version',
      categoryCode: 'stale_write',
    });
  });

  it('maps capability denials to denied feedback', () => {
    const error = new StaffApiError(403, {
      error_code: 'forbidden',
      category_code: 'forbidden_capability',
      required_capability: 'table.release',
      message: 'Forbidden.',
    }, 'Forbidden');

    expect(mapMutationErrorToFeedback(error, context)).toMatchObject({
      phase: 'denied',
      retryable: false,
      errorCode: 'forbidden',
      categoryCode: 'forbidden_capability',
    });
  });

  it('maps validation payloads to validation_failed feedback', () => {
    const error = new StaffApiError(422, {
      error_code: 'validation_error',
      category_code: 'domain_invariant_violation',
      errors: {
        row_version: ['Row version is required.'],
      },
      message: 'Validation error.',
    }, 'Validation');

    expect(mapMutationErrorToFeedback(error, context)).toMatchObject({
      phase: 'validation_failed',
      retryable: false,
      errorCode: 'validation_error',
      categoryCode: 'domain_invariant_violation',
    });
  });

  it('maps rate limit responses to retriable failure feedback', () => {
    const error = new StaffApiError(429, {
      category_code: 'rate_limited',
      message: 'Too many requests.',
    }, 'Too many requests');

    expect(mapMutationErrorToFeedback(error, context)).toMatchObject({
      phase: 'retriable_failure',
      retryable: true,
      categoryCode: 'rate_limited',
    });
  });

  it('maps idempotency in-progress errors to conflict feedback', () => {
    const error = new StaffApiError(409, {
      error_code: 'idempotency_in_progress',
      category_code: 'idempotency_conflict',
      replay_state: 'same_payload_in_progress',
      message: 'Conflict.',
    }, 'Conflict');

    expect(mapMutationErrorToFeedback(error, context)).toMatchObject({
      phase: 'conflict',
      retryable: true,
      errorCode: 'idempotency_in_progress',
      categoryCode: 'idempotency_conflict',
    });
  });
});
