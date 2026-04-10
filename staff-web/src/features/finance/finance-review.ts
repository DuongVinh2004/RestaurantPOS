import type {
  FinancialReconciliationQuery,
  FinancialReconciliationRow,
} from '../../core/api/staff-api';

export type FinanceFilterState = {
  reservationCode: string;
  status: string;
  depositStatus: string;
  paymentCurrency: string;
  cashierUserId: string;
  hasDiscrepancy: 'all' | 'yes' | 'no';
  activityFrom: string;
  activityTo: string;
};

export type FinanceReviewUrlState = FinanceFilterState & {
  page: number;
  selectedReservationId: number | null;
};

export type FinanceSummary = {
  discrepancyCount: number;
  outstandingAmount: number;
  overRefundAmount: number;
  fullySettledCount: number;
};

export function buildFinanceQuery(
  filters: FinanceFilterState,
  page: number,
  perPage: number,
  reservationId?: number | null,
): FinancialReconciliationQuery {
  return {
    reservation_id: reservationId ?? undefined,
    reservation_code: normalizeText(filters.reservationCode),
    status: normalizeText(filters.status),
    deposit_status: normalizeText(filters.depositStatus),
    payment_currency: normalizeCurrency(filters.paymentCurrency),
    cashier_user_id: normalizeInteger(filters.cashierUserId),
    has_discrepancy: normalizeDiscrepancy(filters.hasDiscrepancy),
    activity_from: normalizeDate(filters.activityFrom),
    activity_to: normalizeDate(filters.activityTo),
    page,
    per_page: perPage,
    sort: '-last_payment_activity_at',
  };
}

export function readFinanceReviewUrlState(search: string | URLSearchParams): FinanceReviewUrlState {
  const params = toSearchParams(search);

  return {
    reservationCode: params.get('reservation_code')?.trim() ?? '',
    status: params.get('status')?.trim() ?? '',
    depositStatus: params.get('deposit_status')?.trim() ?? '',
    paymentCurrency: params.get('payment_currency')?.trim() ?? '',
    cashierUserId: params.get('cashier_user_id')?.trim() ?? '',
    hasDiscrepancy: readDiscrepancy(params.get('has_discrepancy')),
    activityFrom: params.get('activity_from')?.trim() ?? '',
    activityTo: params.get('activity_to')?.trim() ?? '',
    page: readPositivePage(params.get('page')),
    selectedReservationId: readPositiveInteger(params.get('focus')),
  };
}

export function buildFinanceReviewSearch(
  currentSearch: string | URLSearchParams,
  patch: Partial<FinanceReviewUrlState>,
): string {
  const params = toSearchParams(currentSearch);
  const merged = {
    ...readFinanceReviewUrlState(params),
    ...patch,
  } satisfies FinanceReviewUrlState;

  setOrDelete(params, 'reservation_code', normalizeText(merged.reservationCode) ?? null);
  setOrDelete(params, 'status', normalizeText(merged.status) ?? null);
  setOrDelete(params, 'deposit_status', normalizeText(merged.depositStatus) ?? null);
  setOrDelete(params, 'payment_currency', normalizeCurrency(merged.paymentCurrency) ?? null);
  setOrDelete(params, 'cashier_user_id', merged.cashierUserId.trim() !== '' ? merged.cashierUserId.trim() : null);
  setOrDelete(params, 'has_discrepancy', merged.hasDiscrepancy !== 'all' ? merged.hasDiscrepancy : null);
  setOrDelete(params, 'activity_from', normalizeDate(merged.activityFrom) ?? null);
  setOrDelete(params, 'activity_to', normalizeDate(merged.activityTo) ?? null);
  setOrDelete(params, 'page', merged.page > 1 ? String(merged.page) : null);
  setOrDelete(params, 'focus', merged.selectedReservationId ? String(merged.selectedReservationId) : null);

  return params.toString();
}

export function summarizeFinance(rows: Array<FinancialReconciliationRow>): FinanceSummary {
  return rows.reduce<FinanceSummary>((summary, row) => ({
    discrepancyCount: summary.discrepancyCount + (row.flags.has_discrepancy ? 1 : 0),
    outstandingAmount: roundMoney(summary.outstandingAmount + Number(row.reconciliation.bill_outstanding_amount ?? 0)),
    overRefundAmount: roundMoney(summary.overRefundAmount + Number(row.payment_summary.over_refunded_amount ?? 0)),
    fullySettledCount: summary.fullySettledCount + (row.flags.is_fully_settled ? 1 : 0),
  }), {
    discrepancyCount: 0,
    outstandingAmount: 0,
    overRefundAmount: 0,
    fullySettledCount: 0,
  });
}

export function financeFlagLabels(row: FinancialReconciliationRow): Array<string> {
  const labels = new Set<string>();

  if (row.flags.has_discrepancy) {
    labels.add('Có chênh lệch');
  }
  if (row.flags.has_bill_outstanding) {
    labels.add('Còn thiếu');
  }
  if (row.flags.has_bill_overpaid) {
    labels.add('Thu dư');
  }
  if (row.flags.has_over_refund) {
    labels.add('Hoàn quá tiền');
  }
  if (row.flags.has_mixed_payment_currencies) {
    labels.add('Lệch loại tiền');
  }
  if (row.flags.is_fully_settled) {
    labels.add('Đã quyết toán');
  }

  return Array.from(labels);
}

export function canIssueInvoiceForRow(row: FinancialReconciliationRow | null | undefined): boolean {
  if (!row) {
    return false;
  }

  return Number(row.reconciliation.final_bill_amount ?? 0) > 0;
}

function normalizeText(value: string): string | undefined {
  const normalized = value.trim();
  return normalized === '' ? undefined : normalized;
}

function normalizeCurrency(value: string): string | undefined {
  const normalized = value.trim().toUpperCase();
  return normalized === '' ? undefined : normalized;
}

function normalizeInteger(value: string): number | undefined {
  const normalized = value.trim();
  if (normalized === '') {
    return undefined;
  }

  const parsed = Number(normalized);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : undefined;
}

function normalizeDate(value: string): string | undefined {
  const normalized = value.trim();
  return normalized === '' ? undefined : normalized;
}

function normalizeDiscrepancy(value: FinanceFilterState['hasDiscrepancy']): boolean | undefined {
  if (value === 'yes') {
    return true;
  }
  if (value === 'no') {
    return false;
  }
  return undefined;
}

function roundMoney(value: number): number {
  return Math.round(value * 100) / 100;
}

function readDiscrepancy(value: string | null): FinanceFilterState['hasDiscrepancy'] {
  return value === 'yes' || value === 'no' ? value : 'all';
}

function readPositiveInteger(value: string | null): number | null {
  if (!value) {
    return null;
  }

  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function readPositivePage(value: string | null): number {
  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : 1;
}

function toSearchParams(search: string | URLSearchParams): URLSearchParams {
  if (search instanceof URLSearchParams) {
    return new URLSearchParams(search);
  }

  return new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);
}

function setOrDelete(params: URLSearchParams, key: string, value: string | null): void {
  if (!value) {
    params.delete(key);
    return;
  }

  params.set(key, value);
}
