import { describe, expect, it } from 'vitest';
import {
  buildFinanceReviewSearch,
  buildFinanceQuery,
  canIssueInvoiceForRow,
  financeFlagLabels,
  readFinanceReviewUrlState,
  summarizeFinance,
  type FinanceFilterState,
} from './finance-review';
import type { FinancialReconciliationRow } from '../../core/api/staff-api';

const baseFilters: FinanceFilterState = {
  reservationCode: '',
  status: '',
  depositStatus: '',
  paymentCurrency: '',
  cashierUserId: '',
  hasDiscrepancy: 'all',
  activityFrom: '',
  activityTo: '',
};

describe('finance review helpers', () => {
  it('builds a query from filters and preserves reservation context', () => {
    expect(buildFinanceQuery({
      ...baseFilters,
      reservationCode: 'RSV-001',
      status: 'Completed',
      depositStatus: 'Paid',
      paymentCurrency: 'vnd',
      cashierUserId: '15',
      hasDiscrepancy: 'yes',
      activityFrom: '2026-04-01',
      activityTo: '2026-04-10',
    }, 3, 15, 77)).toEqual({
      reservation_id: 77,
      reservation_code: 'RSV-001',
      status: 'Completed',
      deposit_status: 'Paid',
      payment_currency: 'VND',
      cashier_user_id: 15,
      has_discrepancy: true,
      activity_from: '2026-04-01',
      activity_to: '2026-04-10',
      page: 3,
      per_page: 15,
      sort: '-last_payment_activity_at',
    });
  });

  it('summarizes discrepancies, outstanding totals and settled rows', () => {
    const rows = [
      makeRow({
        reservationId: 1,
        hasDiscrepancy: true,
        outstandingAmount: 15000,
        overRefundAmount: 5000,
        isFullySettled: false,
      }),
      makeRow({
        reservationId: 2,
        hasDiscrepancy: false,
        outstandingAmount: 0,
        overRefundAmount: 0,
        isFullySettled: true,
      }),
    ];

    expect(summarizeFinance(rows)).toEqual({
      discrepancyCount: 1,
      outstandingAmount: 15000,
      overRefundAmount: 5000,
      fullySettledCount: 1,
    });
  });

  it('maps flags and invoice eligibility from reconciliation data', () => {
    const row = makeRow({
      reservationId: 5,
      hasDiscrepancy: true,
      hasBillOutstanding: true,
      hasMixedCurrencies: true,
      isFullySettled: false,
      finalBillAmount: 240000,
    });

    expect(financeFlagLabels(row)).toEqual(['Có chênh lệch', 'Còn thiếu', 'Lệch loại tiền']);
    expect(canIssueInvoiceForRow(row)).toBe(true);
    expect(canIssueInvoiceForRow(makeRow({
      reservationId: 6,
      finalBillAmount: null,
    }))).toBe(false);
  });

  it('reads and writes deep-linkable finance review state while preserving journey params', () => {
    expect(readFinanceReviewUrlState('?reservation_code=RSV-001&status=Completed&has_discrepancy=yes&page=3&focus=77')).toEqual({
      reservationCode: 'RSV-001',
      status: 'Completed',
      depositStatus: '',
      paymentCurrency: '',
      cashierUserId: '',
      hasDiscrepancy: 'yes',
      activityFrom: '',
      activityTo: '',
      page: 3,
      selectedReservationId: 77,
    });

    expect(buildFinanceReviewSearch(
      '?source=checkout&order_id=12',
      {
        reservationCode: 'RSV-001',
        status: 'Completed',
        depositStatus: 'Paid',
        paymentCurrency: 'vnd',
        cashierUserId: '15',
        hasDiscrepancy: 'yes',
        activityFrom: '2026-04-01',
        activityTo: '2026-04-10',
        page: 2,
        selectedReservationId: 77,
      },
    )).toBe('source=checkout&order_id=12&reservation_code=RSV-001&status=Completed&deposit_status=Paid&payment_currency=VND&cashier_user_id=15&has_discrepancy=yes&activity_from=2026-04-01&activity_to=2026-04-10&page=2&focus=77');
  });
});

function makeRow({
  reservationId,
  hasDiscrepancy = false,
  hasBillOutstanding = false,
  hasMixedCurrencies = false,
  isFullySettled = false,
  outstandingAmount = 0,
  overRefundAmount = 0,
  finalBillAmount = 180000,
}: {
  reservationId: number;
  hasDiscrepancy?: boolean;
  hasBillOutstanding?: boolean;
  hasMixedCurrencies?: boolean;
  isFullySettled?: boolean;
  outstandingAmount?: number;
  overRefundAmount?: number;
  finalBillAmount?: number | null;
}): FinancialReconciliationRow {
  return {
    reservation: {
      reservation_id: reservationId,
      reservation_code: `RSV-${reservationId}`,
      status: 'Completed',
      deposit_status: 'Paid',
      start_time: '2026-04-10T00:00:00Z',
      end_time: '2026-04-10T01:30:00Z',
      billed_at: '2026-04-10T01:45:00Z',
      updated_at: '2026-04-10T01:50:00Z',
      bill_currency: 'VND',
      customer: {
        user_id: 10,
        full_name: 'Customer',
        email: 'customer@example.com',
        phone: '0900000000',
      },
    },
    payment_summary: {
      payment_count: 2,
      refund_count: overRefundAmount > 0 ? 1 : 0,
      captured_amount: 180000,
      refunded_amount: overRefundAmount,
      net_paid_amount: 180000 - overRefundAmount,
      deposit_captured_amount: 50000,
      deposit_refunded_amount: 0,
      deposit_net_amount: 50000,
      final_captured_amount: 130000,
      final_refunded_amount: overRefundAmount,
      final_net_amount: 130000 - overRefundAmount,
      over_refunded_amount: overRefundAmount,
      last_payment_activity_at: '2026-04-10T02:00:00Z',
      last_refund_at: overRefundAmount > 0 ? '2026-04-10T02:05:00Z' : null,
      currency: {
        currency: hasMixedCurrencies ? null : 'VND',
        has_mixed_currencies: hasMixedCurrencies,
      },
    },
    reconciliation: {
      deposit_required_amount: 50000,
      deposit_recorded_paid_amount: 50000,
      deposit_computed_net_amount: 50000,
      deposit_sync_gap_amount: 0,
      final_bill_amount: finalBillAmount,
      bill_outstanding_amount: outstandingAmount,
      bill_overpaid_amount: 0,
    },
    flags: {
      has_refunds: overRefundAmount > 0,
      has_payments: true,
      has_discrepancy: hasDiscrepancy,
      has_deposit_sync_gap: false,
      has_over_refund: overRefundAmount > 0,
      has_mixed_payment_currencies: hasMixedCurrencies,
      has_bill_outstanding: hasBillOutstanding,
      has_bill_overpaid: false,
      discrepancy_reasons: hasDiscrepancy ? ['deposit_sync_gap'] : [],
      is_fully_settled: isFullySettled,
    },
  };
}
