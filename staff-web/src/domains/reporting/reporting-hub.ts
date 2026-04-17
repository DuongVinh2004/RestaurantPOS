import type {
  GetV1StaffReportingDailyInventoryQueryParams,
  GetV1StaffReportingDailyOperationsQueryParams,
  GetV1StaffReportingDailySalesQueryParams,
  ReportingDailyInventoryMovementSnapshot,
  ReportingDailyOperationSnapshot,
  ReportingDailySalesSnapshot,
  StaffReportingCollectionMeta,
} from '../../shared/api/sdk';
import { translateUiCode } from '../../shared/utils/translation';

export type ReportingTabKey = 'sales' | 'operations' | 'inventory';

export type ReportingFilterState = {
  dateFrom: string;
  dateTo: string;
  currency: string;
  ingredientId: string;
};

type SnapshotHealth = StaffReportingCollectionMeta['snapshot_health'];
type ExtendedSnapshotHealth = SnapshotHealth & {
  scope_count?: number;
  healthy_scope_count?: number;
  stale_scope_count?: number;
  stale_scope_examples?: Array<Record<string, unknown>>;
  health_reference_refreshed_at_utc?: string | null;
  health_reference_refresh_age_seconds?: number | null;
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
    return 'Kh\u00f4ng r\u00f5';
  }

  const health = toExtendedSnapshotHealth(meta.snapshot_health);
  if (health.is_empty) {
    return 'Ph\u1ea1m vi tr\u1ed1ng';
  }

  if (health.status === 'degraded' && isPartialScopeStaleness(health)) {
    return 'Stale t\u1eebng ph\u1ea7n';
  }

  if (health.status === 'degraded') {
    return 'C\u1ea7n ki\u1ec3m tra';
  }

  return '\u1ed4n \u0111\u1ecbnh';
}

export function snapshotHealthDescription(meta?: StaffReportingCollectionMeta | null): string {
  if (!meta?.snapshot_health) {
    return 'Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u m\u00f4 t\u1ea3 \u1ea3nh ch\u1ee5p b\u00e1o c\u00e1o.';
  }

  const health = toExtendedSnapshotHealth(meta.snapshot_health);
  const reasons = health.reasons.length > 0
    ? health.reasons.map((reason) => translateUiCode(reason)).join(', ')
    : 'kh\u00f4ng c\u00f3';
  const referenceAge = snapshotHealthReferenceAgeSeconds(meta);
  const scopeSummary = snapshotHealthScopeSummary(meta);
  const examples = snapshotHealthScopeExamples(meta);

  return [
    `S\u1ed1 d\u00f2ng ${health.row_count}`,
    `Ng\u00e0y kinh doanh m\u1edbi nh\u1ea5t ${health.latest_business_date ?? 'Kh\u00f4ng c\u00f3'}`,
    `Tu\u1ed5i d\u1eef li\u1ec7u tham chi\u1ebfu ${referenceAge ?? 'Kh\u00f4ng c\u00f3'} gi\u00e2y`,
    scopeSummary,
    examples ? `V\u00ed d\u1ee5 stale ${examples}` : null,
    `Nguy\u00ean nh\u00e2n ${reasons}`,
  ].filter((part): part is string => !!part).join(' | ');
}

export function snapshotHealthReferenceAgeSeconds(meta?: StaffReportingCollectionMeta | null): number | null {
  if (!meta?.snapshot_health) {
    return null;
  }

  const health = toExtendedSnapshotHealth(meta.snapshot_health);

  if (typeof health.health_reference_refresh_age_seconds === 'number') {
    return health.health_reference_refresh_age_seconds;
  }

  return typeof health.latest_refresh_age_seconds === 'number' ? health.latest_refresh_age_seconds : null;
}

export function snapshotHealthScopeSummary(meta?: StaffReportingCollectionMeta | null): string | null {
  if (!meta?.snapshot_health) {
    return null;
  }

  const health = toExtendedSnapshotHealth(meta.snapshot_health);
  if (typeof health.scope_count !== 'number') {
    return null;
  }

  const staleCount = typeof health.stale_scope_count === 'number' ? health.stale_scope_count : 0;
  const healthyCount = typeof health.healthy_scope_count === 'number'
    ? health.healthy_scope_count
    : Math.max(0, health.scope_count - staleCount);

  return `Ph\u1ea1m vi ${health.scope_count} | stale ${staleCount} | \u1ed5n ${healthyCount}`;
}

export function snapshotHealthScopeExamples(meta?: StaffReportingCollectionMeta | null): string | null {
  if (!meta?.snapshot_health) {
    return null;
  }

  const health = toExtendedSnapshotHealth(meta.snapshot_health);
  const examples = Array.isArray(health.stale_scope_examples) ? health.stale_scope_examples : [];
  if (examples.length === 0) {
    return null;
  }

  return examples
    .slice(0, 2)
    .map((example) => Object.entries(example)
      .filter(([key, value]) => value !== null && value !== undefined && key !== 'latest_refreshed_at_utc')
      .map(([key, value]) => `${key}=${formatScopeValue(key, value)}`)
      .join(', '))
    .filter((value) => value !== '')
    .join(' ; ');
}

function isPartialScopeStaleness(health: ExtendedSnapshotHealth): boolean {
  return typeof health.scope_count === 'number'
    && typeof health.stale_scope_count === 'number'
    && health.stale_scope_count > 0
    && health.stale_scope_count < health.scope_count;
}

function toExtendedSnapshotHealth(health: SnapshotHealth): ExtendedSnapshotHealth {
  return health as ExtendedSnapshotHealth;
}

function formatScopeValue(key: string, value: unknown): string {
  if (key === 'latest_refresh_age_seconds' && typeof value === 'number') {
    return `${value}s`;
  }

  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return String(value);
  }

  return JSON.stringify(value);
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
