import type {
  CashierShiftEnvelope,
  StaffConversationCollectionEnvelope,
  StaffKitchenStationCollectionEnvelope,
  StaffReportingDailyInventoryCollectionEnvelope,
  StaffReportingDailyOperationsCollectionEnvelope,
  StaffReportingDailySalesCollectionEnvelope,
  StaffReservationLookupCollectionEnvelope,
  StaffTableBoardEnvelope,
  StaffWaitingListCollectionEnvelope,
} from '../../../../shared/api/sdk';
import type { FinancialReconciliationRow } from '../../../../shared/api/staff-api';
import {
  canManageStaffCashierShift,
  hasStaffStartupBranch,
  isStaffCashierShiftActionRequired,
  isStaffSessionOperatorReady,
} from '../../../../app/auth/startup';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import type { StaffSession } from '../../../../shared/auth/storage';
import type { StatusTone } from '../../../../shared/status/status';
import { formatDateTime, formatMoney, formatRelativeAge } from '../../../../shared/utils/format';
import {
  cashierShiftTone,
  conversationTone,
  kitchenTone,
  paymentTone,
  reservationTone,
  tableTone,
  waitingTone,
} from '../../../../shared/status/status';
import { translateUiCode } from '../../../../shared/utils/translation';
import { conversationSummaryStats, conversationTitle } from '../../../../domains/conversations/conversation-inbox';
import { financeFlagLabels, readFinanceFlag, readFinanceMetric, summarizeFinance } from '../../../../domains/finance/finance-review';
import {
  snapshotHealthDescription,
  snapshotHealthLabel,
  snapshotHealthTone,
  summarizeInventory,
  summarizeOperations,
  summarizeSales,
} from '../../../../domains/reporting/reporting-hub';

export type DashboardAlertTone = 'warning' | 'error' | 'success' | 'info' | 'neutral';
export type DashboardAlertIconKey = 'reservation' | 'waiting' | 'kitchen' | 'finance' | 'cashier' | 'conversation' | 'branch' | 'reporting' | 'stable';
export type DashboardKpiIconKey = 'service' | 'available' | 'waiting' | 'kitchen' | 'finance' | 'revenue';
export type DashboardSnapshotVariant = 'guest-flow' | 'queue' | 'finance' | 'support' | 'reporting';

export type DashboardAlertModel = {
  key: string;
  title: string;
  value: string;
  description: string;
  path: string;
  actionLabel: string;
  tone: DashboardAlertTone;
  iconKey: DashboardAlertIconKey;
  ageLabel?: string;
  groupLabel?: string;
};

export type DashboardKpiModel = {
  key: string;
  label: string;
  value: string;
  subtext: string;
  path: string;
  actionLabel: string;
  tone: StatusTone;
  iconKey: DashboardKpiIconKey;
  trendLabel?: string;
};

export type DashboardMetricModel = {
  label: string;
  value: string;
  tone?: StatusTone;
  hint?: string;
};

export type DashboardListItemModel = {
  key: string;
  title: string;
  subtitle: string;
  meta?: string;
  statusLabel?: string;
  statusTone?: StatusTone;
  path: string;
  actionLabel?: string;
};

export type DashboardBoardCellModel = {
  key: string;
  label: string;
  meta: string;
  stateLabel: string;
  stateTone: StatusTone;
  path: string;
};

export type DashboardSnapshotModel = {
  variant: DashboardSnapshotVariant;
  title: string;
  description: string;
  path: string;
  actionLabel: string;
  urgencyLabel?: string;
  urgencyTone?: StatusTone;
  priorityHint?: string;
  metrics: Array<DashboardMetricModel>;
  items: Array<DashboardListItemModel>;
  emptyTitle: string;
  emptyDescription: string;
};

export type DashboardTableBoardModel = {
  title: string;
  description: string;
  path: string;
  actionLabel: string;
  urgencyLabel?: string;
  urgencyTone?: StatusTone;
  priorityHint?: string;
  metrics: Array<DashboardMetricModel>;
  boardCells: Array<DashboardBoardCellModel>;
  attentionItems: Array<DashboardListItemModel>;
  emptyTitle: string;
  emptyDescription: string;
};

export type DashboardShiftHealthAction = {
  label: string;
  path: string;
  tone: 'primary' | 'secondary';
};

export type DashboardShiftHealthModel = {
  title: string;
  statusLabel: string;
  statusTone: StatusTone;
  summary: string;
  metrics: Array<DashboardMetricModel>;
  actions: Array<DashboardShiftHealthAction>;
};

export type DashboardCashierSnapshotModel = {
  title: string;
  description: string;
  path: string;
  actionLabel: string;
  urgencyLabel?: string;
  urgencyTone?: StatusTone;
  reviewLabel?: string;
  metrics: Array<DashboardMetricModel>;
  notes: Array<string>;
};

export type DashboardFocusModel = {
  roleLabel: string;
  title: string;
  description: string;
  priorityPaths: Array<string>;
};

type TableRow = StaffTableBoardEnvelope['data'][number];
type SalesMeta = StaffReportingDailySalesCollectionEnvelope['meta'];
type OperationsMeta = StaffReportingDailyOperationsCollectionEnvelope['meta'];
type InventoryMeta = StaffReportingDailyInventoryCollectionEnvelope['meta'];

export function buildDashboardFocus(session: StaffSession): DashboardFocusModel {
  const capabilities = new Set(session.capabilities);
  const roleName = (session.user?.role_name ?? '').toLowerCase();

  if (roleName.includes('cashier') || (capabilities.has('cashier.shift.manage') && capabilities.has('settlement.manage') && !capabilities.has('kitchen.manage'))) {
    return {
      roleLabel: 'Cashier',
      title: 'Buồng điều phối thanh toán',
      description: 'Ưu tiên bill chờ chốt, độ an toàn ca thu ngân và các rủi ro chênh lệch trước khi xử lý phần còn lại.',
      priorityPaths: [staffRoutePaths.ops.cashierShift, staffRoutePaths.ops.financeReview, staffRoutePaths.ops.checkout, staffRoutePaths.ops.conversations],
    };
  }

  if (roleName.includes('kitchen') || (capabilities.has('kitchen.manage') && !capabilities.has('settlement.manage'))) {
    return {
      roleLabel: 'Kitchen',
      title: 'Bảng điều phối bếp',
      description: 'Giữ nhịp ra món thông suốt, nhìn sớm trạm nghẽn và đẩy nhanh các phiếu đang già tuổi.',
      priorityPaths: [staffRoutePaths.kitchen.landing, staffRoutePaths.ops.orders, staffRoutePaths.ops.tables, staffRoutePaths.ops.waitingList],
    };
  }

  if (roleName.includes('manager') || (capabilities.has('reporting.view') && capabilities.has('cashier.shift.manage') && capabilities.has('conversation.manage'))) {
    return {
      roleLabel: 'Branch manager',
      title: 'Cockpit giám sát chi nhánh',
      description: 'Theo dõi việc nóng, readiness, tài chính và snapshot giám sát mà không mất mạch vận hành.',
      priorityPaths: [staffRoutePaths.ops.dashboard, staffRoutePaths.ops.financeReview, staffRoutePaths.ops.cashierShift, staffRoutePaths.admin.reporting],
    };
  }

  if (roleName.includes('supervisor') || (capabilities.has('conversation.manage') && capabilities.has('reservation.manage'))) {
    return {
      roleLabel: 'Supervisor',
      title: 'Trung tâm điều phối ca bận',
      description: 'Giữ rõ thứ tự ưu tiên giữa khách chờ, hội thoại treo, bếp và các điểm nghẽn trên sàn phục vụ.',
      priorityPaths: [staffRoutePaths.ops.waitingList, staffRoutePaths.ops.reservations, staffRoutePaths.ops.conversations, staffRoutePaths.ops.tables],
    };
  }

  return {
    roleLabel: 'Service floor',
    title: 'Trung tâm nhịp phục vụ',
    description: 'Nhìn nhanh khách sắp đến, bàn còn trống, việc nóng trên sàn và các bước tiếp theo để xử lý ngay.',
    priorityPaths: [staffRoutePaths.ops.tables, staffRoutePaths.ops.reservations, staffRoutePaths.ops.waitingList, staffRoutePaths.ops.orders],
  };
}

export function buildShiftHealthModel(
  session: StaffSession,
  currentShift: CashierShiftEnvelope['data'] | null,
): DashboardShiftHealthModel {
  const readiness = session.startup.readiness;
  const branch = session.startup.default_branch;
  const financeBlocked = isStaffCashierShiftActionRequired(session);
  const actions: Array<DashboardShiftHealthAction> = [];

  if (financeBlocked && canManageStaffCashierShift(session)) {
    actions.push({
      label: 'Mở ca thu ngân',
      path: staffRoutePaths.ops.cashierShift,
      tone: 'primary',
    });
  } else if (!isStaffSessionOperatorReady(session)) {
    actions.push({
      label: 'Mở trung tâm mở ca',
      path: staffRoutePaths.access,
      tone: 'primary',
    });
  }

  if (!actions.some((action) => action.path === staffRoutePaths.access)) {
    actions.push({
      label: 'Xem trung tâm mở ca',
      path: staffRoutePaths.access,
      tone: 'secondary',
    });
  }

  if (canManageStaffCashierShift(session) && !actions.some((action) => action.path === staffRoutePaths.ops.cashierShift)) {
    actions.push({
      label: currentShift ? 'Xem ca thu ngân' : 'Mở ca thu ngân',
      path: staffRoutePaths.ops.cashierShift,
      tone: actions.length === 0 ? 'primary' : 'secondary',
    });
  }

  return {
    title: 'Tình trạng ca',
    statusLabel: isStaffSessionOperatorReady(session) ? 'Sẵn sàng vận hành' : 'Cần kiểm tra trước ca',
    statusTone: isStaffSessionOperatorReady(session) ? 'success' : 'warning',
    summary: isStaffSessionOperatorReady(session)
      ? 'Chi nhánh, ca thu ngân và quyền thao tác đang khớp với phiên hiện tại.'
      : 'Phiên hiện tại còn điều kiện cần xử lý trước khi đi sâu vào bàn, đơn hàng hoặc tài chính.',
    metrics: [
      {
        label: 'Chi nhánh',
        value: branch ? `${branch.branch_code} • ${branch.branch_name}` : 'Chưa có chi nhánh',
        tone: readiness.branch === 'ready' ? 'success' : 'warning',
      },
      {
        label: 'Ca thu ngân',
        value: currentShift?.shift_code ?? session.startup.active_cashier_shift?.shift_code ?? translateUiCode(readiness.cashier_shift),
        tone: currentShift ? cashierShiftTone(currentShift.status) : paymentTone(readiness.cashier_shift),
      },
      {
        label: 'Readiness',
        value: translateUiCode(isStaffSessionOperatorReady(session) ? 'ready' : 'action_required'),
        tone: isStaffSessionOperatorReady(session) ? 'success' : 'warning',
      },
      {
        label: 'Quyền thao tác',
        value: `${readiness.granted_capability_count}/${readiness.known_capability_count}`,
        tone: isStaffSessionOperatorReady(session) ? 'success' : 'warning',
      },
    ],
    actions,
  };
}

export function buildUrgentAlerts(args: {
  session: StaffSession;
  tableBoard: StaffTableBoardEnvelope | null;
  waitingList: StaffWaitingListCollectionEnvelope | null;
  kitchenStations: StaffKitchenStationCollectionEnvelope | null;
  financeRows: Array<FinancialReconciliationRow>;
  conversations: StaffConversationCollectionEnvelope | null;
  salesMeta?: SalesMeta;
  operationsMeta?: OperationsMeta;
  inventoryMeta?: InventoryMeta;
}): Array<DashboardAlertModel> {
  const alerts: Array<DashboardAlertModel & { priority: number }> = [];
  const financeSummary = summarizeFinance(args.financeRows);
  const waitingSummary = args.waitingList?.meta?.summary;
  const conversationStats = conversationSummaryStats(args.conversations?.meta?.summary);
  const queuedTickets = (args.kitchenStations?.data ?? []).reduce((sum, station) => sum + station.ticket_counts.queued, 0);
  const unassignedReservations = args.tableBoard?.summary.unassigned_reservation_count ?? args.tableBoard?.unassigned_reservations.length ?? 0;
  const readyToSeat = waitingSummary?.ready_to_seat_count ?? 0;
  const oldestWaitingAge = oldestAgeLabel((args.waitingList?.data ?? []).map((row) => row.requested_at));
  const oldestConversationAge = oldestAgeLabel((args.conversations?.data ?? []).map((row) => row.latest_activity_at ?? row.created_at));
  const oldestFinanceAge = oldestAgeLabel(args.financeRows.map((row) => readFinanceString(row.payment_summary, 'last_payment_activity_at') ?? row.reservation.updated_at));
  const kitchenAgeLabel = oldestAgeLabel((args.kitchenStations?.data ?? []).map((row) => row.updated_at));
  const reportingNeedsAttention = [args.salesMeta, args.operationsMeta, args.inventoryMeta].some((meta) => meta?.snapshot_health?.status === 'degraded');

  if (isStaffCashierShiftActionRequired(args.session) && canManageStaffCashierShift(args.session)) {
    alerts.push({
      key: 'cashier-shift',
      title: 'Ca thu ngân cần mở',
      value: 'Ngay',
      description: 'Luồng thanh toán đang chờ một ca thu ngân hoạt động.',
      path: staffRoutePaths.ops.cashierShift,
      actionLabel: 'Mở ca',
      tone: 'error',
      iconKey: 'cashier',
      groupLabel: 'Xử lý ngay',
      priority: 100,
    });
  }

  if (!hasStaffStartupBranch(args.session)) {
    alerts.push({
      key: 'branch-readiness',
      title: 'Chi nhánh cần xác nhận',
      value: '1',
      description: 'Xác nhận lại chi nhánh trước khi xử lý bàn, đơn hàng hoặc tài chính.',
      path: staffRoutePaths.access,
      actionLabel: 'Kiểm tra',
      tone: 'warning',
      iconKey: 'branch',
      groupLabel: 'Xử lý ngay',
      priority: 96,
    });
  }

  if (readyToSeat > 0) {
    alerts.push({
      key: 'waiting-ready',
      title: 'Khách chờ vào bàn',
      value: String(readyToSeat),
      description: 'Có lượt chờ đã sẵn sàng để gọi vào bàn ngay.',
      path: staffRoutePaths.ops.waitingList,
      actionLabel: 'Mở hàng chờ',
      tone: 'warning',
      iconKey: 'waiting',
      ageLabel: oldestWaitingAge ?? undefined,
      groupLabel: readyToSeat >= 3 ? 'Xử lý ngay' : 'Cần chú ý',
      priority: 92,
    });
  }

  if (financeSummary.outstandingCount > 0) {
    alerts.push({
      key: 'finance-outstanding',
      title: 'Hóa đơn chờ chốt',
      value: String(financeSummary.outstandingCount),
      description: financeSummary.discrepancyCount > 0
        ? 'Có bill còn thiếu hoặc đang có chênh lệch cần kiểm tra.'
        : 'Có bill còn thiếu thanh toán hoặc chưa đủ điều kiện chốt.',
      path: financeSummary.discrepancyCount > 0
        ? `${staffRoutePaths.ops.financeReview}?has_discrepancy=yes`
        : staffRoutePaths.ops.financeReview,
      actionLabel: 'Mở đối soát',
      tone: financeSummary.discrepancyCount > 0 ? 'error' : 'warning',
      iconKey: 'finance',
      ageLabel: oldestFinanceAge ?? undefined,
      groupLabel: financeSummary.discrepancyCount > 0 ? 'Xử lý ngay' : 'Cần chú ý',
      priority: financeSummary.discrepancyCount > 0 ? 91 : 86,
    });
  }

  if (queuedTickets > 0) {
    alerts.push({
      key: 'kitchen-queued',
      title: 'Phiếu bếp mở',
      value: String(queuedTickets),
      description: 'Bếp đang còn hàng chờ, cần kiểm tra trạm nghẽn hoặc món ra chậm.',
      path: `${staffRoutePaths.kitchen.landing}?status=Queued`,
      actionLabel: 'Mở bếp',
      tone: 'warning',
      iconKey: 'kitchen',
      ageLabel: kitchenAgeLabel ?? undefined,
      groupLabel: queuedTickets >= 5 ? 'Xử lý ngay' : 'Cần chú ý',
      priority: 88,
    });
  }

  if (conversationStats.unassigned > 0) {
    alerts.push({
      key: 'conversation-unassigned',
      title: 'Hộp thư cần xử lý',
      value: String(conversationStats.unassigned),
      description: 'Khách đang chờ người nhận xử lý trong hộp thư mở.',
      path: `${staffRoutePaths.ops.conversations}?status=Open&assignment=unassigned`,
      actionLabel: 'Mở hội thoại',
      tone: 'info',
      iconKey: 'conversation',
      ageLabel: oldestConversationAge ?? undefined,
      groupLabel: conversationStats.unassigned >= 3 ? 'Cần chú ý' : 'Theo dõi',
      priority: 76,
    });
  }

  if (unassignedReservations > 0) {
    alerts.push({
      key: 'unassigned-reservations',
      title: 'Đặt bàn chưa gán',
      value: String(unassignedReservations),
      description: 'Có khách đã vào khung phục vụ nhưng chưa được gán bàn rõ ràng.',
      path: `${staffRoutePaths.ops.reservations}?bucket=today`,
      actionLabel: 'Mở đặt bàn',
      tone: 'warning',
      iconKey: 'reservation',
      groupLabel: 'Cần chú ý',
      priority: 74,
    });
  }

  if (reportingNeedsAttention) {
    alerts.push({
      key: 'reporting-health',
      title: 'Snapshot cần kiểm tra',
      value: '1',
      description: 'Một lớp snapshot đang rỗng hoặc xuống chất lượng trong phạm vi hiện tại.',
      path: staffRoutePaths.admin.reporting,
      actionLabel: 'Mở báo cáo',
      tone: 'neutral',
      iconKey: 'reporting',
      groupLabel: 'Theo dõi',
      priority: 40,
    });
  }

  if (alerts.length === 0) {
    return [{
      key: 'stable-shift',
      title: 'Ca đang ổn định',
      value: '0',
      description: 'Hiện chưa có việc nóng vượt ngưỡng trong dashboard này.',
      path: staffRoutePaths.ops.tables,
      actionLabel: 'Xem mặt sàn',
      tone: 'success',
      iconKey: 'stable',
      groupLabel: 'Theo dõi',
    }];
  }

  return alerts
    .sort((left, right) => right.priority - left.priority)
    .slice(0, 4)
    .map((alert) => {
      const { priority, ...nextAlert } = alert;
      void priority;
      return nextAlert;
    });
}

export function buildDashboardKpis(args: {
  tableBoard: StaffTableBoardEnvelope | null;
  waitingList: StaffWaitingListCollectionEnvelope | null;
  kitchenStations: StaffKitchenStationCollectionEnvelope | null;
  financeRows: Array<FinancialReconciliationRow>;
  currentShift: CashierShiftEnvelope['data'] | null;
}): Array<DashboardKpiModel> {
  const tableRows = args.tableBoard?.data ?? [];
  const financeSummary = summarizeFinance(args.financeRows);
  const availableTables = tableRows.filter((row) => row.availability.accepts_new_assignment).length;
  const waitingGuests = estimateWaitingCount(args.waitingList);
  const queuedTickets = (args.kitchenStations?.data ?? []).reduce((sum, station) => (
    sum + station.ticket_counts.queued + station.ticket_counts.fired + station.ticket_counts.ready
  ), 0);
  const shiftRevenue = sumShiftRevenue(args.currentShift);

  return [
    {
      key: 'tables-in-service',
      label: 'Bàn đang phục vụ',
      value: String(args.tableBoard?.summary.active_order_count ?? 0),
      subtext: 'Theo dõi nhịp phục vụ và bàn còn mở đơn.',
      path: staffRoutePaths.ops.tables,
      actionLabel: 'Mở sơ đồ bàn',
      tone: (args.tableBoard?.summary.active_order_count ?? 0) > 0 ? 'warning' : 'default',
      iconKey: 'service',
      trendLabel: availableTables > 0 ? `${availableTables} bàn còn trống` : undefined,
    },
    {
      key: 'tables-available',
      label: 'Bàn trống',
      value: String(availableTables),
      subtext: 'Bàn có thể nhận khách ngay trong chi nhánh hiện tại.',
      path: staffRoutePaths.ops.tables,
      actionLabel: 'Xem bàn trống',
      tone: availableTables > 0 ? 'success' : 'warning',
      iconKey: 'available',
    },
    {
      key: 'waiting-guests',
      label: 'Khách đang chờ',
      value: String(waitingGuests),
      subtext: 'Gồm khách sẵn sàng vào bàn và khách cần gọi lại.',
      path: staffRoutePaths.ops.waitingList,
      actionLabel: 'Mở hàng chờ',
      tone: waitingGuests > 0 ? 'warning' : 'default',
      iconKey: 'waiting',
      trendLabel: `${args.waitingList?.meta?.summary.ready_to_seat_count ?? 0} lượt sẵn sàng`,
    },
    {
      key: 'open-kitchen-tickets',
      label: 'Phiếu bếp mở',
      value: String(queuedTickets),
      subtext: 'Gồm phiếu chờ, đang làm và đã sẵn sàng ra món.',
      path: staffRoutePaths.kitchen.landing,
      actionLabel: 'Mở bếp',
      tone: queuedTickets > 0 ? 'warning' : 'default',
      iconKey: 'kitchen',
      trendLabel: `${(args.kitchenStations?.data ?? []).reduce((sum, station) => sum + station.ticket_counts.queued, 0)} phiếu đang chờ`,
    },
    {
      key: 'bills-awaiting-payment',
      label: 'Hóa đơn chờ thanh toán',
      value: String(financeSummary.outstandingCount),
      subtext: 'Bill còn thiếu hoặc chưa sẵn sàng để chốt đối soát.',
      path: staffRoutePaths.ops.financeReview,
      actionLabel: 'Mở đối soát',
      tone: financeSummary.outstandingCount > 0 ? 'warning' : 'success',
      iconKey: 'finance',
      trendLabel: financeSummary.discrepancyCount > 0 ? `${financeSummary.discrepancyCount} bill có chênh lệch` : undefined,
    },
    {
      key: 'shift-revenue',
      label: 'Doanh thu ca hiện tại',
      value: formatMoney(shiftRevenue),
      subtext: args.currentShift
        ? `Theo ca ${args.currentShift.shift_code} đang hoạt động.`
        : 'Chưa có ca thu ngân mở để ghi nhận thực thu.',
      path: staffRoutePaths.ops.cashierShift,
      actionLabel: 'Mở ca thu ngân',
      tone: shiftRevenue > 0 ? 'success' : 'default',
      iconKey: 'revenue',
    },
  ];
}

export function buildTableBoardSnapshot(
  tableBoard: StaffTableBoardEnvelope | null,
): DashboardTableBoardModel {
  const rows = tableBoard?.data ?? [];
  const availableCount = rows.filter((row) => row.availability.accepts_new_assignment).length;
  const activeCount = rows.filter((row) => row.active_order).length;
  const attentionRows = rows
    .slice()
    .sort((left, right) => tablePriority(right) - tablePriority(left))
    .slice(0, 4);
  const urgency = buildUrgencyLabel((activeCount * 2) + ((tableBoard?.summary.unassigned_reservation_count ?? 0) * 3));

  return {
    title: 'Sàn phục vụ',
    description: 'Mini board để nhìn nhanh bàn đang bận, bàn trống và bàn cần chú ý ngay.',
    path: staffRoutePaths.ops.tables,
    actionLabel: 'Mở sơ đồ bàn',
    urgencyLabel: urgency.label,
    urgencyTone: urgency.tone,
    priorityHint: attentionRows.length > 0
      ? 'Ưu tiên bàn đang mở đơn, bàn có reservation hoặc bàn đã có bước kế tiếp.'
      : 'Hiện chưa có bàn nào vượt ngưỡng chú ý.',
    metrics: [
      { label: 'Đang phục vụ', value: String(tableBoard?.summary.active_order_count ?? 0), tone: 'warning' },
      { label: 'Bàn trống', value: String(availableCount), tone: availableCount > 0 ? 'success' : 'default' },
      { label: 'Đặt bàn chưa gán', value: String(tableBoard?.summary.unassigned_reservation_count ?? 0), tone: 'processing' },
    ],
    boardCells: rows.slice(0, 8).map((row) => ({
      key: `table-cell-${row.table_id}`,
      label: row.table_code,
      meta: row.active_order
        ? `Đơn #${row.active_order.order_id}`
        : row.reservation
          ? row.reservation.reservation_code
          : row.zone ?? 'Sẵn nhận khách',
      stateLabel: translateUiCode(row.board_state),
      stateTone: tableTone(row.board_state),
      path: staffRoutePaths.ops.tables,
    })),
    attentionItems: attentionRows.map((row) => buildTableListItem(row)),
    emptyTitle: 'Mặt sàn đang yên',
    emptyDescription: 'Chưa có bàn nào cần ưu tiên theo dữ liệu hiện tại.',
  };
}

export function buildReservationWaitingSnapshot(
  reservations: StaffReservationLookupCollectionEnvelope | null,
  waitingList: StaffWaitingListCollectionEnvelope | null,
  timeZone?: string,
): DashboardSnapshotModel {
  const reservationRows = reservations?.data ?? [];
  const waitingRows = waitingList?.data ?? [];
  const readyToSeatCount = waitingList?.meta?.summary.ready_to_seat_count ?? 0;
  const followUpCount = waitingList?.meta?.summary.awaiting_customer_follow_up_count ?? 0;
  const oldestWaitingAge = oldestAgeLabel(waitingRows.map((row) => row.requested_at));
  const urgencyLabel = buildUrgencyLabel((readyToSeatCount * 3) + (followUpCount * 2) + (oldestWaitingAge ? 2 : 0));

  return {
    variant: 'guest-flow',
    title: 'Đặt bàn & chờ bàn',
    description: 'Nhóm khách sắp đến, khách đang chờ và các lượt cần gọi lại ngay.',
    path: `${staffRoutePaths.ops.reservations}?bucket=today`,
    actionLabel: 'Mở điều phối khách',
    urgencyLabel: urgencyLabel.label,
    urgencyTone: urgencyLabel.tone,
    priorityHint: oldestWaitingAge
      ? `Lượt chờ già nhất đã ${oldestWaitingAge}. Nên kéo khách vào bàn hoặc gọi lại trước.`
      : 'Hàng chờ hiện chưa có lượt già tuổi.',
    metrics: [
      { label: 'Đặt bàn hôm nay', value: String(reservationRows.length), tone: reservationRows.length > 0 ? 'processing' : 'default' },
      { label: 'Sẵn sàng vào bàn', value: String(readyToSeatCount), tone: readyToSeatCount > 0 ? 'warning' : 'success' },
      { label: 'Cần gọi lại', value: String(followUpCount), tone: followUpCount > 0 ? 'processing' : 'default', hint: oldestWaitingAge ? `Già nhất ${oldestWaitingAge}` : undefined },
    ],
    items: [
      ...reservationRows.slice(0, 2).map((row) => ({
        key: `reservation-${row.reservation_id}`,
        title: row.reservation_code,
        subtitle: row.user?.full_name ?? row.user?.phone ?? 'Khách cần xác nhận',
        meta: `${formatDateTime(row.start_time, timeZone)} • ${row.guest_count} khách`,
        statusLabel: row.status,
        statusTone: reservationTone(row.status),
        path: `${staffRoutePaths.ops.reservations}?bucket=today&reservation=${row.reservation_id}`,
        actionLabel: 'Mở lượt',
      })),
      ...waitingRows.slice(0, 2).map((row) => ({
        key: `waiting-${row.waiting_id}`,
        title: row.guest_name ?? `Lượt chờ #${row.waiting_id}`,
        subtitle: `${row.guest_count} khách • ${translateUiCode(row.current_response_state)}`,
        meta: [translateUiCode(row.orchestration.recommended_action), formatRelativeAge(row.requested_at, { short: false })].join(' • '),
        statusLabel: row.status,
        statusTone: waitingTone(row.status),
        path: staffRoutePaths.ops.waitingList,
        actionLabel: 'Mở hàng chờ',
      })),
    ],
    emptyTitle: 'Không có khách cần điều phối',
    emptyDescription: 'Danh sách đặt bàn và chờ bàn đang yên trong phạm vi hiện tại.',
  };
}

export function buildKitchenSnapshot(stations: StaffKitchenStationCollectionEnvelope | null): DashboardSnapshotModel {
  const rows = stations?.data ?? [];
  const queued = rows.reduce((sum, row) => sum + row.ticket_counts.queued, 0);
  const fired = rows.reduce((sum, row) => sum + row.ticket_counts.fired, 0);
  const ready = rows.reduce((sum, row) => sum + row.ticket_counts.ready, 0);
  const busiestStation = rows
    .slice()
    .sort((left, right) => (right.ticket_counts.queued + right.ticket_counts.ready) - (left.ticket_counts.queued + left.ticket_counts.ready))[0] ?? null;
  const oldestKitchenAge = oldestAgeLabel(rows.map((row) => row.updated_at));
  const urgency = buildUrgencyLabel((queued * 2) + (ready > 0 ? 2 : 0) + (oldestKitchenAge ? 2 : 0));

  return {
    variant: 'queue',
    title: 'Hàng bếp',
    description: 'Theo dõi trạm đang nghẽn, món đang làm và món đã sẵn sàng ra quầy.',
    path: `${staffRoutePaths.kitchen.landing}?status=Queued`,
    actionLabel: 'Mở bếp',
    urgencyLabel: urgency.label,
    urgencyTone: urgency.tone,
    priorityHint: busiestStation
      ? `Trạm cần nhìn đầu tiên: ${busiestStation.name} với ${busiestStation.ticket_counts.queued} phiếu chờ.`
      : 'Hiện chưa có trạm nào vượt ngưỡng chú ý.',
    metrics: [
      { label: 'Chờ chế biến', value: String(queued), tone: queued > 0 ? 'warning' : 'default' },
      { label: 'Đang làm', value: String(fired), tone: fired > 0 ? 'processing' : 'default' },
      { label: 'Sẵn sàng', value: String(ready), tone: ready > 0 ? 'success' : 'default', hint: oldestKitchenAge ? `Dữ liệu ${oldestKitchenAge}` : undefined },
    ],
    items: rows
      .slice()
      .sort((left, right) => (right.ticket_counts.queued + right.ticket_counts.ready) - (left.ticket_counts.queued + left.ticket_counts.ready))
      .slice(0, 4)
      .map((row) => ({
        key: `station-${row.station_id}`,
        title: row.name,
        subtitle: `${row.code} • ${translateUiCode(row.output_mode)}`,
        meta: `${row.ticket_counts.queued} chờ • ${row.ticket_counts.fired} đang làm • ${row.ticket_counts.ready} sẵn sàng`,
        statusLabel: row.ticket_counts.queued > 0 ? 'Queued' : row.ticket_counts.ready > 0 ? 'Ready' : 'Completed',
        statusTone: row.ticket_counts.queued > 0
          ? kitchenTone('Queued')
          : row.ticket_counts.ready > 0
            ? kitchenTone('Ready')
            : kitchenTone('Completed'),
        path: staffRoutePaths.kitchen.landing,
        actionLabel: 'Mở trạm',
      })),
    emptyTitle: 'Bếp đang thông',
    emptyDescription: 'Hiện không có trạm bếp nào giữ hàng chờ đáng chú ý.',
  };
}

export function buildCheckoutSnapshot(financeRows: Array<FinancialReconciliationRow>): DashboardSnapshotModel {
  const summary = summarizeFinance(financeRows);
  const oldestFinanceAge = oldestAgeLabel(financeRows.map((row) => readFinanceString(row.payment_summary, 'last_payment_activity_at') ?? row.reservation.updated_at));
  const urgency = buildUrgencyLabel((summary.discrepancyCount * 4) + (summary.outstandingCount * 2) + (oldestFinanceAge ? 2 : 0));

  return {
    variant: 'finance',
    title: 'Thanh toán & đối soát',
    description: 'Giữ bill còn thiếu, bill có chênh lệch và các dòng cần chốt trong cùng một hàng đợi.',
    path: staffRoutePaths.ops.financeReview,
    actionLabel: 'Mở đối soát',
    urgencyLabel: urgency.label,
    urgencyTone: urgency.tone,
    priorityHint: summary.discrepancyCount > 0
      ? `${summary.discrepancyCount} bill đang có chênh lệch. Nên review trước khi chốt hoặc hoàn tiền.`
      : oldestFinanceAge
        ? `Bill hoạt động lâu nhất đã ${oldestFinanceAge}.`
        : 'Hàng tài chính hiện chưa có bill già tuổi.',
    metrics: [
      { label: 'Chờ chốt', value: String(summary.outstandingCount), tone: summary.outstandingCount > 0 ? 'warning' : 'success' },
      { label: 'Có chênh lệch', value: String(summary.discrepancyCount), tone: summary.discrepancyCount > 0 ? 'error' : 'success' },
      { label: 'Giá trị còn treo', value: formatMoney(summary.outstandingAmount), tone: summary.outstandingAmount > 0 ? 'processing' : 'default', hint: oldestFinanceAge ? `Hoạt động ${oldestFinanceAge}` : 'Giá trị còn thiếu' },
    ],
    items: financeRows.slice(0, 4).map((row) => ({
      key: `finance-${row.reservation.reservation_id}`,
      title: row.reservation.reservation_code,
      subtitle: financeCustomerLabel(row.reservation.customer),
      meta: `Còn thiếu ${formatMoney(readFinanceMetric(row.reconciliation, 'bill_outstanding_amount'), row.reservation.bill_currency ?? 'VND')}`,
      statusLabel: financeFlagLabels(row)[0] ?? row.reservation.status,
      statusTone: financeTone(row),
      path: `${staffRoutePaths.ops.financeReview}?focus=${row.reservation.reservation_id}`,
      actionLabel: 'Mở bill',
    })),
    emptyTitle: 'Không có bill cần chốt',
    emptyDescription: 'Hàng đối soát hiện không có bill nổi bật trong phạm vi đang xem.',
  };
}

export function buildCashierSnapshot(
  session: StaffSession,
  currentShift: CashierShiftEnvelope['data'] | null,
): DashboardCashierSnapshotModel {
  const readiness = session.startup.readiness;
  const financeBlocked = isStaffCashierShiftActionRequired(session);

  if (!currentShift) {
    return {
      title: 'Ca thu ngân',
      description: financeBlocked
        ? 'Thanh toán đang chờ một ca thu ngân hoạt động.'
        : 'Phiên hiện tại chưa có ca thu ngân mở trong phạm vi thao tác.',
      path: staffRoutePaths.ops.cashierShift,
      actionLabel: financeBlocked ? 'Mở ca thu ngân' : 'Mở màn hình ca',
      urgencyLabel: financeBlocked ? 'Xử lý ngay' : 'Theo dõi',
      urgencyTone: financeBlocked ? 'warning' : 'default',
      reviewLabel: financeBlocked ? 'Mở ca trước khi xử lý bill, refund hoặc đối soát.' : 'Có thể mở ca chủ động để sẵn sàng cho giờ cao điểm.',
      metrics: [
        { label: 'Readiness', value: translateUiCode(readiness.cashier_shift), tone: paymentTone(readiness.cashier_shift) },
        { label: 'Yêu cầu ca', value: financeBlocked ? 'Bắt buộc' : 'Không bắt buộc', tone: financeBlocked ? 'warning' : 'default' },
      ],
      notes: financeBlocked
        ? ['Mở ca trước khi tiếp tục thanh toán hoặc đối soát.']
        : ['Có thể mở ca chủ động để giữ luồng tài chính luôn sẵn sàng.'],
    };
  }

  const paymentCount = currentShift.summary?.payments.payment_count ?? 0;
  const urgency = buildUrgencyLabel((paymentCount > 10 ? 3 : 1) + ((currentShift.summary?.methods ?? []).length > 2 ? 2 : 0));

  return {
    title: 'Ca thu ngân',
    description: 'Theo dõi ca đang hoạt động, tiền mặt kỳ vọng và các phương thức đang phát sinh trong ca.',
    path: staffRoutePaths.ops.cashierShift,
    actionLabel: 'Mở ca thu ngân',
    urgencyLabel: urgency.label,
    urgencyTone: urgency.tone,
    reviewLabel: 'Review nhanh doanh thu, số giao dịch và phương thức nổi bật trước các thao tác nhạy cảm.',
    metrics: [
      { label: 'Ca hiện tại', value: currentShift.shift_code, tone: cashierShiftTone(currentShift.status) },
      { label: 'Giao dịch', value: String(paymentCount), tone: paymentCount > 0 ? 'processing' : 'default' },
      { label: 'Doanh thu ca', value: formatMoney(sumShiftRevenue(currentShift), currentShift.currency), tone: 'success' },
    ],
    notes: (currentShift.summary?.methods ?? []).slice(0, 3).map((method) => (
      `${translateUiCode(method.payment_method)} • ${formatMoney(method.net_amount, method.currency)}`
    )),
  };
}

export function buildConversationSnapshot(
  conversations: StaffConversationCollectionEnvelope | null,
  timeZone?: string,
): DashboardSnapshotModel {
  const stats = conversationSummaryStats(conversations?.meta?.summary);
  const rows = conversations?.data ?? [];
  const oldestConversationAge = oldestAgeLabel(rows.map((row) => row.latest_activity_at ?? row.created_at));
  const urgency = buildUrgencyLabel((stats.unassigned * 3) + (stats.total > 3 ? 2 : 0) + (oldestConversationAge ? 2 : 0));

  return {
    variant: 'support',
    title: 'Hộp thư cần xử lý',
    description: 'Giữ hội thoại mở luôn có người nhận xử lý và nhìn thấy ngay các cuộc trò chuyện còn treo.',
    path: `${staffRoutePaths.ops.conversations}?status=Open`,
    actionLabel: 'Mở hộp thư',
    urgencyLabel: urgency.label,
    urgencyTone: urgency.tone,
    priorityHint: stats.unassigned > 0
      ? `${stats.unassigned} hội thoại chưa phân công. Nên giao người giữ trước khi trả lời tiếp.`
      : oldestConversationAge
        ? `Hoạt động gần nhất đã ${oldestConversationAge}.`
        : 'Hiện chưa có hội thoại già tuổi.',
    metrics: [
      { label: 'Đang mở', value: String(stats.total), tone: stats.total > 0 ? 'processing' : 'default' },
      { label: 'Chưa phân công', value: String(stats.unassigned), tone: stats.unassigned > 0 ? 'warning' : 'success' },
      { label: 'Của tôi', value: String(stats.mine), tone: stats.mine > 0 ? 'success' : 'default', hint: oldestConversationAge ? `Già nhất ${oldestConversationAge}` : undefined },
    ],
    items: rows.slice(0, 4).map((row) => ({
      key: row.conversation_id,
      title: conversationTitle(row),
      subtitle: row.latest_message?.message_text ?? 'Chưa có tin nhắn mới.',
      meta: `${formatDateTime(row.latest_activity_at ?? row.created_at, timeZone)} • ${row.branch?.branch_code ?? `#${row.branch_id}`}`,
      statusLabel: row.assignment_state.is_unassigned ? 'Chưa phân công' : row.assignment_state.is_mine ? 'Của tôi' : row.status,
      statusTone: row.assignment_state.is_unassigned ? 'warning' : row.assignment_state.is_mine ? 'success' : conversationTone(row.status),
      path: `${staffRoutePaths.ops.conversations}?status=Open&conversation=${row.conversation_id}`,
      actionLabel: 'Mở hội thoại',
    })),
    emptyTitle: 'Không có hội thoại mở',
    emptyDescription: 'Hộp thư hiện không có hội thoại cần theo dõi trong chi nhánh đang chọn.',
  };
}

export function buildReportingSnapshot(args: {
  salesRows: StaffReportingDailySalesCollectionEnvelope['data'];
  operationsRows: StaffReportingDailyOperationsCollectionEnvelope['data'];
  inventoryRows: StaffReportingDailyInventoryCollectionEnvelope['data'];
  salesMeta?: SalesMeta;
  operationsMeta?: OperationsMeta;
  inventoryMeta?: InventoryMeta;
}): DashboardSnapshotModel {
  const salesSummary = summarizeSales(args.salesRows);
  const operationsSummary = summarizeOperations(args.operationsRows);
  const inventorySummary = summarizeInventory(args.inventoryRows);
  const salesCurrency = args.salesRows[0]?.currency ?? 'VND';
  const degradedCount = [args.salesMeta, args.operationsMeta, args.inventoryMeta].filter((meta) => meta?.snapshot_health?.status === 'degraded').length;
  const urgency = buildUrgencyLabel(degradedCount * 3);

  return {
    variant: 'reporting',
    title: 'Lớp giám sát',
    description: 'Giữ snapshot nhanh cho bán hàng, vận hành và tồn kho trước khi mở báo cáo sâu.',
    path: staffRoutePaths.admin.reporting,
    actionLabel: 'Mở báo cáo',
    urgencyLabel: degradedCount > 0 ? urgency.label : 'Theo dõi',
    urgencyTone: degradedCount > 0 ? urgency.tone : 'default',
    priorityHint: degradedCount > 0
      ? `${degradedCount} nguồn snapshot đang cần kiểm tra chất lượng hoặc độ mới dữ liệu.`
      : 'Các snapshot chính hiện chưa có dấu hiệu xuống chất lượng.',
    metrics: [
      { label: 'Thực thu', value: formatMoney(salesSummary.netPaidAmount, salesCurrency), tone: 'success' },
      { label: 'Hoàn tất', value: String(operationsSummary.completedCount), tone: 'processing' },
      { label: 'Biến động kho', value: String(inventorySummary.movementCount), tone: 'default' },
    ],
    items: [
      {
        key: 'sales',
        title: 'Bán hàng',
        subtitle: snapshotHealthDescription(args.salesMeta),
        statusLabel: snapshotHealthLabel(args.salesMeta),
        statusTone: snapshotHealthTone(args.salesMeta?.snapshot_health?.status),
        meta: `Thực thu ${formatMoney(salesSummary.netPaidAmount, salesCurrency)} • ${salesSummary.invoiceCount} hóa đơn`,
        path: staffRoutePaths.admin.reporting,
        actionLabel: 'Mở bán hàng',
      },
      {
        key: 'operations',
        title: 'Vận hành',
        subtitle: snapshotHealthDescription(args.operationsMeta),
        statusLabel: snapshotHealthLabel(args.operationsMeta),
        statusTone: snapshotHealthTone(args.operationsMeta?.snapshot_health?.status),
        meta: `${operationsSummary.completedCount} lượt hoàn tất • ${operationsSummary.waitingSeatedCount} khách từ hàng chờ`,
        path: staffRoutePaths.admin.reporting,
        actionLabel: 'Mở vận hành',
      },
      {
        key: 'inventory',
        title: 'Tồn kho',
        subtitle: snapshotHealthDescription(args.inventoryMeta),
        statusLabel: snapshotHealthLabel(args.inventoryMeta),
        statusTone: snapshotHealthTone(args.inventoryMeta?.snapshot_health?.status),
        meta: `${inventorySummary.movementCount} biến động • Hao hụt ${inventorySummary.wastageQuantity}`,
        path: staffRoutePaths.admin.reporting,
        actionLabel: 'Mở tồn kho',
      },
    ],
    emptyTitle: 'Chưa có snapshot giám sát',
    emptyDescription: 'Các nguồn snapshot giám sát chưa trả dữ liệu cho phạm vi hiện tại.',
  };
}

function buildTableListItem(row: TableRow): DashboardListItemModel {
  const serviceLabel = row.active_order
    ? `Đơn #${row.active_order.order_id}`
    : row.reservation
      ? row.reservation.reservation_code
      : translateUiCode(row.operational_hints.preferred_action || 'none');

  return {
    key: `table-${row.table_id}`,
    title: row.table_code,
    subtitle: serviceLabel,
    meta: `${row.zone ?? 'Chưa chia khu'} • ${row.capacity.seats ?? 'Không rõ'} chỗ`,
    statusLabel: row.board_state,
    statusTone: tableTone(row.board_state),
    path: staffRoutePaths.ops.tables,
    actionLabel: 'Mở bàn',
  };
}

function financeTone(row: FinancialReconciliationRow): StatusTone {
  if (readFinanceFlag(row, 'has_discrepancy') || readFinanceFlag(row, 'has_over_refund')) {
    return 'error';
  }

  if (readFinanceFlag(row, 'has_bill_outstanding')) {
    return 'warning';
  }

  if (readFinanceFlag(row, 'is_fully_settled')) {
    return 'success';
  }

  return 'default';
}

function financeCustomerLabel(customer: Record<string, unknown>): string {
  return readFinanceString(customer, 'full_name')
    ?? readFinanceString(customer, 'phone')
    ?? 'Khách chưa rõ';
}

function readFinanceString(value: Record<string, unknown>, field: string): string | null {
  const raw = value[field];
  return typeof raw === 'string' && raw.trim() !== '' ? raw : null;
}

function tablePriority(row: TableRow): number {
  if (row.active_order) {
    return 100;
  }

  if (row.reservation) {
    return 80;
  }

  if ((row.operational_hints.preferred_action ?? 'none') !== 'none') {
    return 60;
  }

  if (row.board_state === 'Available') {
    return 10;
  }

  return 0;
}

function estimateWaitingCount(waitingList: StaffWaitingListCollectionEnvelope | null): number {
  const summary = waitingList?.meta?.summary;
  const actionable = (summary?.ready_to_seat_count ?? 0) + (summary?.awaiting_customer_follow_up_count ?? 0);
  return actionable > 0 ? actionable : waitingList?.data.length ?? 0;
}

function sumShiftRevenue(currentShift: CashierShiftEnvelope['data'] | null): number {
  if (!currentShift) {
    return 0;
  }

  return (currentShift.summary?.methods ?? []).reduce((sum, method) => sum + Number(method.net_amount ?? 0), 0);
}

function oldestAgeLabel(values: Array<string | null | undefined>): string | null {
  const timestamps = values
    .map((value) => (value ? new Date(value).getTime() : Number.NaN))
    .filter((value) => Number.isFinite(value));

  if (timestamps.length === 0) {
    return null;
  }

  return formatRelativeAge(Math.min(...timestamps));
}

function buildUrgencyLabel(score: number): {
  label: string;
  tone: StatusTone;
} {
  if (score >= 8) {
    return {
      label: 'Xử lý ngay',
      tone: 'error',
    };
  }

  if (score >= 4) {
    return {
      label: 'Cần chú ý',
      tone: 'warning',
    };
  }

  return {
    label: 'Theo dõi',
    tone: 'default',
  };
}
