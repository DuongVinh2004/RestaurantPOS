import { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  buildBoardWindow,
  getCurrentCashierShift,
  getTableBoard,
  listBranches,
  listConversations,
  listDailyInventoryReporting,
  listDailyOperationsReporting,
  listDailySalesReporting,
  listFinancialReconciliation,
  listKitchenStations,
  listReservations,
  listWaitingList,
} from '../../../../shared/api/staff-api';
import { isApiStatus } from '../../../../shared/api/errors';
import {
  hasStaffStartupBranch,
  isStaffCashierShiftActionRequired,
  isStaffSessionOperatorReady,
} from '../../../../app/auth/startup';
import { can } from '../../../../shared/auth/capabilities';
import { formatFreshnessLabel } from '../../../../shared/utils/format';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { translateUiCode } from '../../../../shared/utils/translation';
import { StaleDataNotice } from '../../../../shared/ui/states/StateBlocks';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import {
  buildDashboardFocus,
  buildDashboardKpis,
  buildCashierSnapshot,
  buildCheckoutSnapshot,
  buildConversationSnapshot,
  buildKitchenSnapshot,
  buildReportingSnapshot,
  buildReservationWaitingSnapshot,
  buildShiftHealthModel,
  buildTableBoardSnapshot,
  buildUrgentAlerts,
} from './dashboard-view-model';
import { dashboardErrorMessage } from './dashboard-errors';
import { DashboardTopBar } from './components/DashboardTopBar';
import { KpiCard } from './components/KpiCard';
import { ShiftHealthCard } from './components/ShiftHealthCard';
import { UrgentAlertCard } from './components/UrgentAlertCard';
import { MiniTableBoardCard } from './components/MiniTableBoardCard';
import { QueueSnapshotCard } from './components/QueueSnapshotCard';
import { CashierSnapshotCard } from './components/CashierSnapshotCard';
import { ConversationSnapshotCard } from './components/ConversationSnapshotCard';
import { AnalyticsOverviewSection } from './components/AnalyticsOverviewSection';
import { buildInventoryQuery, buildOperationsQuery, buildSalesQuery } from '../../../../domains/reporting/reporting-hub';

export function DashboardPage() {
  const navigate = useNavigate();
  const session = useAuthStore((state) => state.session);
  const refreshSession = useAuthStore((state) => state.refresh);
  const branchId = useFlowStore((state) => state.branchId);
  const boardWindow = useMemo(() => buildBoardWindow(), []);
  const canListStaffBranches = !!session && can(session, 'reservation.manage');
  const branchesQuery = useQuery({
    queryKey: ['staff-branches', session?.staff_api_key_id ?? null],
    queryFn: listBranches,
    enabled: canListStaffBranches,
    staleTime: 5 * 60_000,
  });
  const activeBranch = useMemo(() => {
    const branches = canListStaffBranches ? branchesQuery.data?.data ?? [] : [];
    const activeBranchId = branchId ?? session?.startup.default_branch?.branch_id ?? null;

    if (activeBranchId === null) {
      return null;
    }

    return branches.find((branch) => branch.branch_id === activeBranchId) ?? session?.startup.default_branch ?? null;
  }, [branchId, branchesQuery.data?.data, canListStaffBranches, session?.startup.default_branch]);
  const branchTimeZone = activeBranch?.timezone ?? session?.startup.default_branch?.timezone ?? 'Asia/Ho_Chi_Minh';
  const reportingFilters = useMemo(() => ({
    dateFrom: isoDateInTimeZone(branchTimeZone),
    dateTo: isoDateInTimeZone(branchTimeZone),
    currency: '',
    ingredientId: '',
  }), [branchTimeZone]);

  const canViewTables = !!session && can(session, 'table.board.view');
  const canManageReservations = !!session && can(session, 'reservation.manage');
  const canManageWaitingList = !!session && can(session, 'waiting_list.manage');
  const canManageKitchen = !!session && can(session, 'kitchen.manage');
  const canManageSettlement = !!session && can(session, 'settlement.manage');
  const canManageCashier = !!session && can(session, 'cashier.shift.manage');
  const canManageConversations = !!session && can(session, 'conversation.manage');
  const canViewReporting = !!session && can(session, 'reporting.view');

  const tableBoardQuery = useQuery({
    queryKey: ['dashboard-table-board', branchId, boardWindow.from, boardWindow.to],
    queryFn: () => getTableBoard({
      ...boardWindow,
      branch_id: branchId ?? undefined,
      include_holds: true,
      group_by: 'zone',
    }),
    enabled: canViewTables,
    refetchInterval: 30_000,
  });

  const reservationsQuery = useQuery({
    queryKey: ['dashboard-reservations', branchId],
    queryFn: () => listReservations({
      bucket: 'today',
      branch_id: branchId ?? undefined,
      per_page: 6,
      sort: 'start_time',
    }),
    enabled: canManageReservations,
  });

  const waitingListQuery = useQuery({
    queryKey: ['dashboard-waiting-list', branchId],
    queryFn: () => listWaitingList({
      branch_id: branchId ?? undefined,
      active_only: true,
      per_page: 6,
      sort: '-priority',
    }),
    enabled: canManageWaitingList,
  });

  const kitchenStationsQuery = useQuery({
    queryKey: ['dashboard-kitchen-stations', branchId],
    queryFn: () => listKitchenStations(branchId ?? undefined),
    enabled: canManageKitchen,
    refetchInterval: 20_000,
  });

  const financeQuery = useQuery({
    queryKey: ['dashboard-finance', branchId],
    queryFn: () => listFinancialReconciliation({
      branch_id: branchId ?? undefined,
      per_page: 6,
      sort: '-last_payment_activity_at',
    }),
    enabled: canManageSettlement,
  });

  const currentShiftQuery = useQuery({
    queryKey: ['dashboard-cashier-shift-current', branchId],
    queryFn: () => getCurrentCashierShift(branchId ?? undefined),
    enabled: canManageCashier,
    retry: (failureCount, error) => !isApiStatus(error, 404) && failureCount < 1,
  });

  const conversationsQuery = useQuery({
    queryKey: ['dashboard-conversations', branchId],
    queryFn: () => listConversations({
      branch_id: branchId ?? undefined,
      status: 'Open',
      page: 1,
      per_page: 4,
      sort_by: 'latest_activity',
      sort_dir: 'desc',
    }),
    enabled: canManageConversations,
  });

  const salesQuery = useQuery({
    queryKey: ['dashboard-reporting-sales', branchId, reportingFilters],
    queryFn: () => listDailySalesReporting(buildSalesQuery(reportingFilters, branchId, 1, 1)),
    enabled: canViewReporting,
  });

  const operationsQuery = useQuery({
    queryKey: ['dashboard-reporting-operations', branchId, reportingFilters],
    queryFn: () => listDailyOperationsReporting(buildOperationsQuery(reportingFilters, branchId, 1, 1)),
    enabled: canViewReporting,
  });

  const inventoryQuery = useQuery({
    queryKey: ['dashboard-reporting-inventory', branchId, reportingFilters],
    queryFn: () => listDailyInventoryReporting(buildInventoryQuery(reportingFilters, branchId, 1, 1)),
    enabled: canViewReporting,
  });

  if (!session) {
    return null;
  }

  const currentShift = isApiStatus(currentShiftQuery.error, 404) ? null : currentShiftQuery.data?.data ?? null;
  const lastUpdatedAt = [
    tableBoardQuery.dataUpdatedAt,
    reservationsQuery.dataUpdatedAt,
    waitingListQuery.dataUpdatedAt,
    kitchenStationsQuery.dataUpdatedAt,
    financeQuery.dataUpdatedAt,
    currentShiftQuery.dataUpdatedAt,
    conversationsQuery.dataUpdatedAt,
    salesQuery.dataUpdatedAt,
    operationsQuery.dataUpdatedAt,
    inventoryQuery.dataUpdatedAt,
  ].reduce((latest, value) => Math.max(latest, value ?? 0), 0);

  const lastUpdatedLabel = lastUpdatedAt > 0
    ? formatTimeLabel(lastUpdatedAt, branchTimeZone)
    : 'chưa có snapshot';
  const freshnessLabel = lastUpdatedAt > 0
    ? formatFreshnessLabel(lastUpdatedAt)
    : 'Chưa có snapshot';
  const freshnessTone = lastUpdatedAt === 0
    ? 'warning'
    : Date.now() - lastUpdatedAt > 5 * 60_000
      ? 'warning'
      : 'success';
  const shiftLabel = currentShift?.shift_code ?? session.startup.active_cashier_shift?.shift_code ?? 'Chưa có ca thu ngân';
  const readinessLabel = isStaffSessionOperatorReady(session)
    ? 'Sẵn sàng vận hành'
    : isStaffCashierShiftActionRequired(session)
      ? 'Cần mở ca thu ngân'
      : !hasStaffStartupBranch(session)
        ? 'Cần xác nhận chi nhánh'
        : `Cần ${translateUiCode(session.startup.readiness.access)}`;

  const shiftHealth = buildShiftHealthModel(session, currentShift);
  const alerts = buildUrgentAlerts({
    session,
    tableBoard: tableBoardQuery.data ?? null,
    waitingList: waitingListQuery.data ?? null,
    kitchenStations: kitchenStationsQuery.data ?? null,
    financeRows: financeQuery.data?.data ?? [],
    conversations: conversationsQuery.data ?? null,
    salesMeta: salesQuery.data?.meta,
    operationsMeta: operationsQuery.data?.meta,
    inventoryMeta: inventoryQuery.data?.meta,
  });
  const kpis = buildDashboardKpis({
    tableBoard: tableBoardQuery.data ?? null,
    waitingList: waitingListQuery.data ?? null,
    kitchenStations: kitchenStationsQuery.data ?? null,
    financeRows: financeQuery.data?.data ?? [],
    currentShift,
  });
  const tableBoardSnapshot = buildTableBoardSnapshot(tableBoardQuery.data ?? null);
  const reservationWaitingSnapshot = buildReservationWaitingSnapshot(
    reservationsQuery.data ?? null,
    waitingListQuery.data ?? null,
    branchTimeZone,
  );
  const kitchenSnapshot = buildKitchenSnapshot(kitchenStationsQuery.data ?? null);
  const checkoutSnapshot = buildCheckoutSnapshot(financeQuery.data?.data ?? []);
  const cashierSnapshot = buildCashierSnapshot(session, currentShift);
  const conversationSnapshot = buildConversationSnapshot(conversationsQuery.data ?? null, branchTimeZone);
  const reportingSnapshot = buildReportingSnapshot({
    salesRows: salesQuery.data?.data ?? [],
    operationsRows: operationsQuery.data?.data ?? [],
    inventoryRows: inventoryQuery.data?.data ?? [],
    salesMeta: salesQuery.data?.meta,
    operationsMeta: operationsQuery.data?.meta,
    inventoryMeta: inventoryQuery.data?.meta,
  });
  const focus = buildDashboardFocus(session);
  const primaryAlert = alerts[0] ?? null;
  const activityItems = alerts.slice(0, 3).map((alert) => (
    alert.ageLabel ? `${alert.title} • ${alert.ageLabel}` : alert.title
  ));

  const workspaceCards = [
    canViewTables ? {
      priorityKey: staffRoutePaths.ops.tables,
      element: (
        <MiniTableBoardCard
          key="table-board"
          snapshot={tableBoardSnapshot}
          onOpen={navigate}
          loading={tableBoardQuery.isLoading}
          error={tableBoardQuery.error ? dashboardErrorMessage(tableBoardQuery.error, 'tables') : null}
        />
      ),
    } : null,
    (canManageReservations || canManageWaitingList) ? {
      priorityKey: staffRoutePaths.ops.reservations,
      element: (
        <QueueSnapshotCard
          key="guest-flow"
          snapshot={reservationWaitingSnapshot}
          onOpen={navigate}
          loading={reservationsQuery.isLoading || waitingListQuery.isLoading}
          error={reservationsQuery.error || waitingListQuery.error
            ? dashboardErrorMessage(reservationsQuery.error ?? waitingListQuery.error, 'guest-flow')
            : null}
        />
      ),
    } : null,
    canManageKitchen ? {
      priorityKey: staffRoutePaths.kitchen.landing,
      element: (
        <QueueSnapshotCard
          key="kitchen"
          snapshot={kitchenSnapshot}
          onOpen={navigate}
          loading={kitchenStationsQuery.isLoading}
          error={kitchenStationsQuery.error ? dashboardErrorMessage(kitchenStationsQuery.error, 'kitchen') : null}
        />
      ),
    } : null,
    canManageSettlement ? {
      priorityKey: staffRoutePaths.ops.financeReview,
      element: (
        <QueueSnapshotCard
          key="finance"
          snapshot={checkoutSnapshot}
          onOpen={navigate}
          loading={financeQuery.isLoading}
          error={financeQuery.error ? dashboardErrorMessage(financeQuery.error, 'finance') : null}
        />
      ),
    } : null,
    canManageConversations ? {
      priorityKey: staffRoutePaths.ops.conversations,
      element: (
        <ConversationSnapshotCard
          key="conversations"
          snapshot={conversationSnapshot}
          onOpen={navigate}
          loading={conversationsQuery.isLoading}
          error={conversationsQuery.error ? dashboardErrorMessage(conversationsQuery.error, 'conversations') : null}
        />
      ),
    } : null,
    canManageCashier ? {
      priorityKey: staffRoutePaths.ops.cashierShift,
      element: (
        <CashierSnapshotCard
          key="cashier"
          snapshot={cashierSnapshot}
          onOpen={navigate}
          loading={currentShiftQuery.isLoading}
          error={currentShiftQuery.error && !isApiStatus(currentShiftQuery.error, 404)
            ? dashboardErrorMessage(currentShiftQuery.error, 'cashier')
            : null}
        />
      ),
    } : null,
  ].filter(Boolean) as Array<{ priorityKey: string; element: JSX.Element }>;

  const sortedWorkspaceCards = workspaceCards
    .slice()
    .sort((left, right) => rankPriority(left.priorityKey, focus.priorityPaths) - rankPriority(right.priorityKey, focus.priorityPaths));
  const leftColumnCards = sortedWorkspaceCards
    .filter((_, index) => index % 2 === 0)
    .map((card) => card.element);
  const rightColumnCards = sortedWorkspaceCards
    .filter((_, index) => index % 2 === 1)
    .map((card) => card.element);

  async function handleRefreshAll() {
    await Promise.all([
      refreshSession(),
      canListStaffBranches ? branchesQuery.refetch() : Promise.resolve(),
      canViewTables ? tableBoardQuery.refetch() : Promise.resolve(),
      canManageReservations ? reservationsQuery.refetch() : Promise.resolve(),
      canManageWaitingList ? waitingListQuery.refetch() : Promise.resolve(),
      canManageKitchen ? kitchenStationsQuery.refetch() : Promise.resolve(),
      canManageSettlement ? financeQuery.refetch() : Promise.resolve(),
      canManageCashier ? currentShiftQuery.refetch() : Promise.resolve(),
      canManageConversations ? conversationsQuery.refetch() : Promise.resolve(),
      canViewReporting ? salesQuery.refetch() : Promise.resolve(),
      canViewReporting ? operationsQuery.refetch() : Promise.resolve(),
      canViewReporting ? inventoryQuery.refetch() : Promise.resolve(),
    ]);
  }

  const isRefreshing = [
    branchesQuery.isFetching,
    tableBoardQuery.isFetching,
    reservationsQuery.isFetching,
    waitingListQuery.isFetching,
    kitchenStationsQuery.isFetching,
    financeQuery.isFetching,
    currentShiftQuery.isFetching,
    conversationsQuery.isFetching,
    salesQuery.isFetching,
    operationsQuery.isFetching,
    inventoryQuery.isFetching,
  ].some(Boolean);

  return (
    <div className="staff-dashboard-page">
      <DashboardTopBar
        focus={focus}
        shiftLabel={shiftLabel}
        readinessLabel={readinessLabel}
        readinessTone={shiftHealth.statusTone}
        updatedLabel={freshnessLabel}
        freshnessTone={freshnessTone}
        primaryAlert={primaryAlert}
        activityItems={activityItems}
        onOpen={navigate}
        onRefresh={handleRefreshAll}
        refreshing={isRefreshing}
      />

      {freshnessTone === 'warning' ? (
        <div className="staff-dashboard-freshness-banner">
          <StaleDataNotice
            title="Snapshot dashboard có thể đã cũ"
            description="Làm mới snapshot trước khi chốt quyết định ở hàng chờ nóng, luồng bếp hoặc luồng tiền."
            lastUpdatedLabel={lastUpdatedLabel}
            onRefresh={() => void handleRefreshAll()}
          />
        </div>
      ) : null}

      <section className="staff-dashboard-alert-section">
        <div className="staff-dashboard-alert-strip">
          {alerts.map((alert) => (
            <UrgentAlertCard key={alert.key} alert={alert} onOpen={navigate} />
          ))}
        </div>
      </section>

      <section className="staff-dashboard-kpi-section">
        <div className="staff-dashboard-kpi-grid">
          {kpis.map((kpi) => (
            <KpiCard key={kpi.key} kpi={kpi} onOpen={navigate} />
          ))}
        </div>
      </section>

      <section className="staff-dashboard-main-zone">
        <div className="staff-dashboard-main-zone-head">
          <div>
            <h2>{focus.title}</h2>
            <p>{focus.description}</p>
          </div>
        </div>

        <div className="staff-dashboard-main-grid">
          <div className="staff-dashboard-main-column">
            {leftColumnCards}
          </div>
          <div className="staff-dashboard-main-column">
            {rightColumnCards}
          </div>
        </div>
      </section>

      <section className="staff-dashboard-secondary-grid">
        <ShiftHealthCard
          health={shiftHealth}
          lastUpdatedLabel={lastUpdatedLabel}
          onOpen={navigate}
        />

        {canViewReporting ? (
          <>
            <QueueSnapshotCard
              snapshot={reportingSnapshot}
              onOpen={navigate}
              loading={salesQuery.isLoading || operationsQuery.isLoading || inventoryQuery.isLoading}
              error={salesQuery.error || operationsQuery.error || inventoryQuery.error
                ? dashboardErrorMessage(salesQuery.error ?? operationsQuery.error ?? inventoryQuery.error, 'reporting')
                : null}
            />
            <AnalyticsOverviewSection />
          </>
        ) : null}
      </section>
    </div>
  );
}

function isoDateInTimeZone(timeZone: string): string {
  const formatter = new Intl.DateTimeFormat('en-CA', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  });

  return formatter.format(new Date());
}

function formatTimeLabel(timestamp: number, timeZone: string): string {
  return new Intl.DateTimeFormat('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
    hour12: false,
    timeZone,
  }).format(new Date(timestamp));
}

function rankPriority(path: string, priorityPaths: Array<string>): number {
  const index = priorityPaths.findIndex((candidate) => path.startsWith(candidate));
  return index === -1 ? priorityPaths.length + 1 : index;
}
