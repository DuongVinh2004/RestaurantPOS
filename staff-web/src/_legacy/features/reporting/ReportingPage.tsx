import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { BarChart3, Boxes, CalendarRange, RefreshCcw } from 'lucide-react';
import {
  listDailyInventoryReporting as loadDailyInventoryReporting,
  listDailyOperationsReporting as loadDailyOperationsReporting,
  listDailySalesReporting as loadDailySalesReporting,
} from '../../core/api/staff-api';
import { formatApiError, isApiStatus } from '../../core/api/errors';
import { useStaffSession } from '../../app/session-context';
import { formatDateTime, formatMoney } from '../../lib/format';
import type {
  StaffReportingDailyInventoryCollectionEnvelope,
  StaffReportingDailyOperationsCollectionEnvelope,
  StaffReportingDailySalesCollectionEnvelope,
} from '../../api/sdk';
import { ActionButton, Banner, EmptyState, MetricCard, PageHeader, Panel, StatusPill } from '../../components/ui';

type ReportingFilters = {
  branchId?: number;
  ingredientId?: number;
  startDate?: string;
  endDate?: string;
};

type PillTone = 'neutral' | 'success' | 'warning' | 'danger' | 'info';
type SnapshotHealth = NonNullable<StaffReportingDailySalesCollectionEnvelope['meta']>['snapshot_health'];

const DEFAULT_REPORT_DAYS = 7;

export function ReportingPage() {
  const { expire, session } = useStaffSession();
  const [branchIdInput, setBranchIdInput] = useState(() => {
    const branchId = session?.startup.default_branch?.branch_id;

    return branchId ? String(branchId) : '';
  });
  const [ingredientIdInput, setIngredientIdInput] = useState('');
  const [startDateInput, setStartDateInput] = useState(() => isoDateDaysAgo(DEFAULT_REPORT_DAYS - 1));
  const [endDateInput, setEndDateInput] = useState(() => isoDateDaysAgo(0));
  const [sales, setSales] = useState<StaffReportingDailySalesCollectionEnvelope | null>(null);
  const [operations, setOperations] = useState<StaffReportingDailyOperationsCollectionEnvelope | null>(null);
  const [inventory, setInventory] = useState<StaffReportingDailyInventoryCollectionEnvelope | null>(null);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const bootstrapFiltersRef = useRef(
    buildReportingFilters(branchIdInput, ingredientIdInput, startDateInput, endDateInput),
  );

  const branchSummary = session?.startup.default_branch
    ? `${session.startup.default_branch.branch_code} | ${session.startup.default_branch.branch_name}`
    : 'All branches';
  const salesSummary = useMemo(() => summarizeSales(sales?.data ?? []), [sales]);
  const operationsSummary = useMemo(() => summarizeOperations(operations?.data ?? []), [operations]);
  const inventorySummary = useMemo(() => summarizeInventory(inventory?.data ?? []), [inventory]);

  const refreshReports = useCallback(async (filters: ReportingFilters, nextNotice: string | null) => {
    setBusyKey('refresh');
    setError(null);

    try {
      const [nextSales, nextOperations, nextInventory] = await Promise.all([
        loadDailySalesReporting({
          branch_id: filters.branchId,
          start_date: filters.startDate,
          end_date: filters.endDate,
          per_page: 7,
          sort: '-business_date',
        }),
        loadDailyOperationsReporting({
          branch_id: filters.branchId,
          start_date: filters.startDate,
          end_date: filters.endDate,
          per_page: 7,
          sort: '-business_date',
        }),
        loadDailyInventoryReporting({
          branch_id: filters.branchId,
          ingredient_id: filters.ingredientId,
          start_date: filters.startDate,
          end_date: filters.endDate,
          per_page: 7,
          sort: '-business_date',
        }),
      ]);

      setSales(nextSales);
      setOperations(nextOperations);
      setInventory(nextInventory);
      setNotice(nextNotice);
    } catch (cause) {
      if (isApiStatus(cause, 401)) {
        expire('Phien staff da het han. Dang nhap lai de tiep tuc.');
        return;
      }

      setError(formatApiError(cause, 'Khong tai duoc reporting read models.'));
    } finally {
      setBusyKey(null);
    }
  }, [expire]);

  useEffect(() => {
    bootstrapFiltersRef.current = buildReportingFilters(branchIdInput, ingredientIdInput, startDateInput, endDateInput);
  }, [branchIdInput, ingredientIdInput, startDateInput, endDateInput]);

  useEffect(() => {
    void refreshReports(bootstrapFiltersRef.current, null);
  }, [refreshReports, session?.staff_api_key_id]);

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Reporting"
        title="Daily read models cho branch lead"
        description="Surface nay gom sales, operations, va inventory snapshots theo business date de staff lead kiem tra tinh hinh ngay ma khong can nhay qua API hoac admin fallback."
        actions={(
          <ActionButton
            onClick={() => refreshReports(
              buildReportingFilters(branchIdInput, ingredientIdInput, startDateInput, endDateInput),
              'Da reload reporting snapshots.',
            )}
            busy={busyKey === 'refresh'}
            icon={<RefreshCcw className="h-4 w-4" />}
          >
            Reload reporting
          </ActionButton>
        )}
      />

      {notice ? <Banner tone="success">{notice}</Banner> : null}
      {error ? <Banner tone="error">{error}</Banner> : null}

      <Panel>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="eyebrow">Scope</p>
            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Daily reporting filters</h2>
            <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
              Mac dinh page dung startup branch va cua so {DEFAULT_REPORT_DAYS} ngay gan nhat. Khi can doi scope, update filter roi reload de doi soat cung mot branch-date window cho ca ba read model.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <StatusPill value={`Startup ${branchSummary}`} tone="info" />
            <StatusPill value={`Sales ${snapshotStatusLabel(sales?.meta?.snapshot_health.status)}`} tone={snapshotTone(sales?.meta?.snapshot_health.status)} />
            <StatusPill value={`Ops ${snapshotStatusLabel(operations?.meta?.snapshot_health.status)}`} tone={snapshotTone(operations?.meta?.snapshot_health.status)} />
            <StatusPill value={`Inventory ${snapshotStatusLabel(inventory?.meta?.snapshot_health.status)}`} tone={snapshotTone(inventory?.meta?.snapshot_health.status)} />
          </div>
        </div>

        <div className="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Branch ID</span>
            <input
              value={branchIdInput}
              onChange={(event) => setBranchIdInput(event.target.value)}
              className="mt-3 w-full bg-transparent text-sm outline-none"
              inputMode="numeric"
              placeholder="All"
            />
          </label>
          <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Start date</span>
            <input
              value={startDateInput}
              onChange={(event) => setStartDateInput(event.target.value)}
              className="mt-3 w-full bg-transparent text-sm outline-none"
              type="date"
            />
          </label>
          <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">End date</span>
            <input
              value={endDateInput}
              onChange={(event) => setEndDateInput(event.target.value)}
              className="mt-3 w-full bg-transparent text-sm outline-none"
              type="date"
            />
          </label>
          <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Ingredient ID</span>
            <input
              value={ingredientIdInput}
              onChange={(event) => setIngredientIdInput(event.target.value)}
              className="mt-3 w-full bg-transparent text-sm outline-none"
              inputMode="numeric"
              placeholder="All"
            />
          </label>
        </div>
        <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap gap-2">
            <StatusPill value={`Date ${startDateInput || 'N/A'} -> ${endDateInput || 'N/A'}`} tone="info" />
            <StatusPill value={ingredientIdInput.trim() !== '' ? `Ingredient ${ingredientIdInput}` : 'All ingredients'} />
          </div>
          <div className="flex items-end">
            <ActionButton
              onClick={() => refreshReports(
                buildReportingFilters(branchIdInput, ingredientIdInput, startDateInput, endDateInput),
                'Da ap dung reporting filters moi.',
              )}
              busy={busyKey === 'refresh'}
              icon={<CalendarRange className="h-4 w-4" />}
            >
              Apply filters
            </ActionButton>
          </div>
        </div>
      </Panel>

      <div className="grid gap-4 xl:grid-cols-3">
        <Panel>
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Sales</p>
              <h3 className="text-xl font-semibold text-slate-950">Net paid va bill totals</h3>
            </div>
            <BarChart3 className="h-5 w-5 text-slate-400" />
          </div>
          <div className="mt-4 grid gap-3 md:grid-cols-3 xl:grid-cols-1">
            <MetricCard label="Net paid" value={formatMoney(salesSummary.netPaidAmount)} />
            <MetricCard label="Gross bill" value={formatMoney(salesSummary.grossBillAmount)} />
            <MetricCard label="Tax" value={formatMoney(salesSummary.taxAmount)} />
          </div>
          {sales?.meta?.snapshot_health ? (
            <div className="mt-4 rounded-[18px] border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-600">
              {renderSnapshotHealth(sales.meta.snapshot_health)}
            </div>
          ) : null}
          <div className="mt-4 space-y-3">
            {(sales?.data ?? []).length === 0 ? (
              <EmptyState
                title="Chua co sales snapshot"
                description="Neu scope nay chua rebuild hoac dang rong, snapshot health se hien degraded de operator biet day la empty scope thay vi loi am tham."
              />
            ) : (
              (sales?.data ?? []).slice(0, 5).map((row) => (
                <div key={`${row.branch_id}-${row.business_date}-${row.currency}`} className="rounded-[22px] border border-slate-200 bg-slate-50 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-slate-900">{branchCode(row.branch) ?? 'BRANCH'} | {row.business_date ?? 'N/A'}</p>
                      <p className="mt-1 text-xs text-slate-500">{branchName(row.branch)} | {row.currency}</p>
                    </div>
                    <StatusPill value={`Bill ${row.billed.reservation_count}`} tone="info" />
                  </div>
                  <div className="mt-4 grid gap-3 md:grid-cols-3">
                    <MetricCard label="Gross" value={formatMoney(row.billed.gross_bill_amount, row.currency)} />
                    <MetricCard label="Net" value={formatMoney(row.payments.net_paid_amount, row.currency)} />
                    <MetricCard label="Cash gap" value={formatMoney(row.cashier.cash_discrepancy_amount, row.currency)} />
                  </div>
                </div>
              ))
            )}
          </div>
        </Panel>

        <Panel>
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Operations</p>
              <h3 className="text-xl font-semibold text-slate-950">Reservation va waiting throughput</h3>
            </div>
            <RefreshCcw className="h-5 w-5 text-slate-400" />
          </div>
          <div className="mt-4 grid gap-3 md:grid-cols-3 xl:grid-cols-1">
            <MetricCard label="Completed" value={String(operationsSummary.completedCount)} />
            <MetricCard label="Waiting seated" value={String(operationsSummary.waitingSeatedCount)} />
            <MetricCard label="Avg turn" value={formatMinutes(operationsSummary.avgTurnMinutes)} />
          </div>
          {operations?.meta?.snapshot_health ? (
            <div className="mt-4 rounded-[18px] border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-600">
              {renderSnapshotHealth(operations.meta.snapshot_health)}
            </div>
          ) : null}
          <div className="mt-4 space-y-3">
            {(operations?.data ?? []).length === 0 ? (
              <EmptyState
                title="Chua co operations snapshot"
                description="Page nay doc tu reporting snapshots, nen scope rong hoac chua rebuild se hien trang thai degraded thay vi du lieu gia."
              />
            ) : (
              (operations?.data ?? []).slice(0, 5).map((row) => (
                <div key={`${row.branch_id}-${row.business_date}`} className="rounded-[22px] border border-slate-200 bg-slate-50 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-slate-900">{branchCode(row.branch) ?? 'BRANCH'} | {row.business_date ?? 'N/A'}</p>
                      <p className="mt-1 text-xs text-slate-500">{branchName(row.branch)}</p>
                    </div>
                    <StatusPill value={`Turn ${formatMinutes(row.turn_time.avg_turn_minutes)}`} tone="success" />
                  </div>
                  <div className="mt-4 grid gap-3 md:grid-cols-3">
                    <MetricCard label="Scheduled" value={String(row.reservations.scheduled_count)} />
                    <MetricCard label="Completed" value={String(row.reservations.completed_count)} />
                    <MetricCard label="Waiting seated" value={String(row.waiting_list.seated_count)} />
                  </div>
                </div>
              ))
            )}
          </div>
        </Panel>

        <Panel>
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Inventory</p>
              <h3 className="text-xl font-semibold text-slate-950">Movement delta va ingredient drift</h3>
            </div>
            <Boxes className="h-5 w-5 text-slate-400" />
          </div>
          <div className="mt-4 grid gap-3 md:grid-cols-3 xl:grid-cols-1">
            <MetricCard label="Movements" value={String(inventorySummary.movementCount)} />
            <MetricCard label="Net delta" value={formatQuantity(inventorySummary.netQuantityDelta)} />
            <MetricCard label="Wastage" value={formatQuantity(inventorySummary.wastageQuantity)} />
          </div>
          {inventory?.meta?.snapshot_health ? (
            <div className="mt-4 rounded-[18px] border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-600">
              {renderSnapshotHealth(inventory.meta.snapshot_health)}
            </div>
          ) : null}
          <div className="mt-4 space-y-3">
            {(inventory?.data ?? []).length === 0 ? (
              <EmptyState
                title="Chua co inventory snapshot"
                description="Khi inventory uplift da rebuild cho scope nay, page se hien movement_count, net_quantity_delta, va ingredient summary theo tung business date."
              />
            ) : (
              (inventory?.data ?? []).slice(0, 5).map((row) => (
                <div key={`${row.branch_id}-${row.business_date}-${row.ingredient_id}`} className="rounded-[22px] border border-slate-200 bg-slate-50 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-slate-900">{ingredientCode(row)} | {row.business_date ?? 'N/A'}</p>
                      <p className="mt-1 text-xs text-slate-500">{ingredientName(row)} | {branchCode(row.branch) ?? 'BRANCH'}</p>
                    </div>
                    <StatusPill value={`Move ${row.movement_summary.movement_count}`} tone="info" />
                  </div>
                  <div className="mt-4 grid gap-3 md:grid-cols-3">
                    <MetricCard label="Net delta" value={`${formatQuantity(row.movement_summary.net_quantity_delta)} ${row.unit_code}`} />
                    <MetricCard label="Stock in" value={`${formatQuantity(row.movement_summary.stock_in_quantity)} ${row.unit_code}`} />
                    <MetricCard label="Last move" value={formatDateTime(row.movement_summary.last_movement_at)} />
                  </div>
                </div>
              ))
            )}
          </div>
        </Panel>
      </div>
    </div>
  );
}

function buildReportingFilters(
  branchIdInput: string,
  ingredientIdInput: string,
  startDateInput: string,
  endDateInput: string,
): ReportingFilters {
  return {
    branchId: parsePositiveInteger(branchIdInput),
    ingredientId: parsePositiveInteger(ingredientIdInput),
    startDate: startDateInput || undefined,
    endDate: endDateInput || undefined,
  };
}

function parsePositiveInteger(value: string): number | undefined {
  const parsed = Number(value);

  if (!Number.isInteger(parsed) || parsed <= 0) {
    return undefined;
  }

  return parsed;
}

function isoDateDaysAgo(daysAgo: number): string {
  const value = new Date();
  value.setHours(0, 0, 0, 0);
  value.setDate(value.getDate() - daysAgo);

  return value.toISOString().slice(0, 10);
}

function snapshotTone(status: string | null | undefined): PillTone {
  return status === 'degraded' ? 'warning' : 'success';
}

function snapshotStatusLabel(status: string | null | undefined): string {
  return status ?? 'unknown';
}

function renderSnapshotHealth(snapshotHealth: SnapshotHealth): string {
  const reasons = snapshotHealth.reasons.length > 0 ? snapshotHealth.reasons.join(', ') : 'none';

  return [
    `Rows ${snapshotHealth.row_count}`,
    `latest business date ${snapshotHealth.latest_business_date ?? 'N/A'}`,
    `refreshed ${formatDateTime(snapshotHealth.latest_refreshed_at_utc)}`,
    `reasons ${reasons}`,
  ].join(' | ');
}

function summarizeSales(rows: StaffReportingDailySalesCollectionEnvelope['data']) {
  return rows.reduce((carry, row) => ({
    grossBillAmount: carry.grossBillAmount + row.billed.gross_bill_amount,
    netPaidAmount: carry.netPaidAmount + row.payments.net_paid_amount,
    taxAmount: carry.taxAmount + row.invoices.tax_amount,
  }), {
    grossBillAmount: 0,
    netPaidAmount: 0,
    taxAmount: 0,
  });
}

function summarizeOperations(rows: StaffReportingDailyOperationsCollectionEnvelope['data']) {
  const turnCount = rows.reduce((sum, row) => sum + row.turn_time.turn_count, 0);
  const turnMinutes = rows.reduce((sum, row) => sum + row.turn_time.turn_minutes_total, 0);

  return {
    completedCount: rows.reduce((sum, row) => sum + row.reservations.completed_count, 0),
    waitingSeatedCount: rows.reduce((sum, row) => sum + row.waiting_list.seated_count, 0),
    avgTurnMinutes: turnCount > 0 ? turnMinutes / turnCount : null,
  };
}

function summarizeInventory(rows: StaffReportingDailyInventoryCollectionEnvelope['data']) {
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

function formatQuantity(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return 'N/A';
  }

  return new Intl.NumberFormat('vi-VN', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(value);
}

function formatMinutes(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return 'N/A';
  }

  return `${formatQuantity(value)} phut`;
}

function branchCode(branch: { branch_code: string } | null | undefined): string | null {
  return branch?.branch_code ?? null;
}

function branchName(branch: { branch_name: string } | null | undefined): string {
  return branch?.branch_name ?? 'Unknown branch';
}

function ingredientCode(
  row: StaffReportingDailyInventoryCollectionEnvelope['data'][number],
): string {
  return row.ingredient?.code ?? `Ingredient #${row.ingredient_id}`;
}

function ingredientName(
  row: StaffReportingDailyInventoryCollectionEnvelope['data'][number],
): string {
  return row.ingredient?.name ?? 'Unknown ingredient';
}
