import type {
  GetV1StaffReportingDailyInventoryQueryParams,
  GetV1StaffReportingDailyOperationsQueryParams,
  GetV1StaffReportingDailySalesQueryParams,
  ReportingDailyInventoryMovementSnapshot,
  ReportingDailyOperationSnapshot,
  ReportingDailySalesSnapshot,
  StaffReportingCollectionMeta,
} from '../../core/api/sdk';
import { translateUiCode } from '../../core/utils/translation';

export type ReportingTabKey = 'sales' | 'operations' | 'inventory';

export type ReportingFilterState = {
  dateFrom: string;
  dateTo: string;
  currency: string;
  ingredientId: string;
};

export function buildSalesQuery(
  filters: ReportingFilterState,
  branchId: number | null,
  page: number,
  perPage: number,
): GetV1StaffReportingDailySalesQueryParams {
  return {
    branch_id: branchId ?? undefined,
    currency: nullableString(filters.currency),
    start_date: nullableString(filters.dateFrom),
    end_date: nullableString(filters.dateTo),
    per_page: perPage,
    page,
    sort: '-business_date',
  };
}

export function buildOperationsQuery(
  filters: ReportingFilterState,
  branchId: number | null,
  page: number,
  perPage: number,
): GetV1StaffReportingDailyOperationsQueryParams {
  return {
    branch_id: branchId ?? undefined,
    start_date: nullableString(filters.dateFrom),
    end_date: nullableString(filters.dateTo),
    per_page: perPage,
    page,
    sort: '-business_date',
  };
}

export function buildInventoryQuery(
  filters: ReportingFilterState,
  branchId: number | null,
  page: number,
  perPage: number,
): GetV1StaffReportingDailyInventoryQueryParams {
  return {
    branch_id: branchId ?? undefined,
    ingredient_id: parsePositiveInteger(filters.ingredientId),
    start_date: nullableString(filters.dateFrom),
    end_date: nullableString(filters.dateTo),
    per_page: perPage,
    page,
    sort: '-business_date',
  };
}

export function summarizeSales(rows: Array<ReportingDailySalesSnapshot>) {
  return rows.reduce((carry, row) => ({
    netPaidAmount: carry.netPaidAmount + row.payments.net_paid_amount,
    grossBillAmount: carry.grossBillAmount + row.billed.gross_bill_amount,
    invoiceCount: carry.invoiceCount + row.invoices.issued_count,
  }), {
    netPaidAmount: 0,
    grossBillAmount: 0,
    invoiceCount: 0,
  });
}

export function summarizeOperations(rows: Array<ReportingDailyOperationSnapshot>) {
  const turnCount = rows.reduce((sum, row) => sum + row.turn_time.turn_count, 0);
  const turnMinutes = rows.reduce((sum, row) => sum + row.turn_time.turn_minutes_total, 0);

  return {
    completedCount: rows.reduce((sum, row) => sum + row.reservations.completed_count, 0),
    waitingSeatedCount: rows.reduce((sum, row) => sum + row.waiting_list.seated_count, 0),
    avgTurnMinutes: turnCount > 0 ? turnMinutes / turnCount : null,
  };
}

export function summarizeInventory(rows: Array<ReportingDailyInventoryMovementSnapshot>) {
  return rows.reduce((carry, row) => ({
    movementCount: carry.movementCount + row.movement_summary.movement_count,
    netQuantityDelta: carry.netQuantityDelta + row.movement_summary.net_quantity_delta,
    wastageQuantity: carry.wastageQuantity + row.movement_summary.wastage_quantity,
  }), {
    movementCount: 0,
    netQuantityDelta: 0,
    wastageQuantity: 0,
  });
}

export function snapshotHealthTone(status: StaffReportingCollectionMeta['snapshot_health']['status'] | undefined): 'success' | 'warning' {
  return status === 'degraded' ? 'warning' : 'success';
}

export function snapshotHealthLabel(meta?: StaffReportingCollectionMeta | null): string {
  if (!meta?.snapshot_health) {
    return 'Không rõ';
  }

  const health = meta.snapshot_health;
  if (health.is_empty) {
    return 'Phạm vi trống';
  }

  if (health.status === 'degraded') {
    return 'Cần kiểm tra';
  }

  return 'Ổn định';
}

export function snapshotHealthDescription(meta?: StaffReportingCollectionMeta | null): string {
  if (!meta?.snapshot_health) {
    return 'Chưa có dữ liệu mô tả ảnh chụp báo cáo.';
  }

  const health = meta.snapshot_health;
  const reasons = health.reasons.length > 0 ? health.reasons.map((reason) => translateUiCode(reason)).join(', ') : 'không có';
  return [
    `Số dòng ${health.row_count}`,
    `Ngày kinh doanh mới nhất ${health.latest_business_date ?? 'Không có'}`,
    `Tuổi dữ liệu ${health.latest_refresh_age_seconds ?? 'Không có'} giây`,
    `Nguyên nhân ${reasons}`,
  ].join(' | ');
}

function parsePositiveInteger(value: string): number | undefined {
  const parsed = Number(value);
  if (!Number.isInteger(parsed) || parsed <= 0) {
    return undefined;
  }

  return parsed;
}

function nullableString(value: string | null | undefined): string | undefined {
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : undefined;
}
