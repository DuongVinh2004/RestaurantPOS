import { describe, expect, it } from 'vitest';
import { buildApiError } from '../test/fixtures';
import { formatFinanceOperatorError } from './financeErrors';

describe('financeErrors', () => {
  it('turns finance capability failures into a targeted operator message', () => {
    const error = buildApiError(403, {
      error_code: 'forbidden',
      message: 'Forbidden.',
      required_capability: 'settlement.manage',
      request_id: 'req-fin-403',
    });

    expect(formatFinanceOperatorError(error, 'Fallback')).toContain('settlement.manage');
    expect(formatFinanceOperatorError(error, 'Fallback')).toContain('req-fin-403');
  });

  it('classifies refund invariants separately from generic validation', () => {
    const error = buildApiError(422, {
      error_code: 'validation_error',
      message: 'Validation error.',
      errors: {
        refund_amount: ['Requested refund exceeds refundable balance.'],
      },
    });

    expect(formatFinanceOperatorError(error, 'Fallback')).toContain('Finance invariant');
    expect(formatFinanceOperatorError(error, 'Fallback')).toContain('Requested refund exceeds refundable balance.');
  });

  it('keeps unknown failures on the generic formatter path', () => {
    expect(formatFinanceOperatorError(new Error('network down'), 'Fallback')).toBe('network down');
  });
});
