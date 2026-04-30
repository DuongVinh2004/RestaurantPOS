import type { GetV1AdminSettingsBranchesQueryParams } from '../../shared/api/sdk';
import type { AdminRestaurantTableQuery } from '../../shared/api/staff-api';

export type AdminBranchFilterState = {
  query: string;
  activeOnly: boolean;
};

export type AdminTableFilterState = {
  query: string;
  status: string;
  zone: string;
  includeDeleted: boolean;
  branchIdInput: string;
};

type BranchLike = {
  branch_id: number;
  is_default: boolean;
  is_active: boolean;
  business_hours: Array<{ periods: Array<{ start_time: string; end_time: string }> }>;
  closure_windows: Array<unknown>;
  booking_policy: Record<string, unknown>;
};

type RestaurantTableLike = {
  status: string;
  is_active: boolean;
  is_allocatable: boolean;
  branch_id: number | null;
  usage?: {
    has_active_operational_links: boolean;
  };
};

export type AdminSettingsSurface = {
  key: string;
  title: string;
  description: string;
  backendSurface: string;
  workflowLabel: string;
};

export const adminSettingsSurfaces: Array<AdminSettingsSurface> = [
  {
    key: 'branch-registry',
    title: 'Danh bạ chi nhánh',
    description: 'Chi nhánh mặc định, trạng thái hoạt động và luồng nhập/xuất chi nhánh nằm trong khu vực này.',
    backendSurface: '/admin/settings/branches, /admin/settings/branches/export, /admin/settings/branches/import',
    workflowLabel: 'Nhập / xuất',
  },
  {
    key: 'table-config',
    title: 'Bàn và khu vực',
    description: 'Bàn, khu vực và dữ liệu phòng ăn được quản trị tách khỏi màn vận hành sàn đang chạy.',
    backendSurface: '/admin/restaurant/tables, /admin/restaurant/zones',
    workflowLabel: 'Nhập / xuất',
  },
  {
    key: 'kitchen-routing',
    title: 'Tuyến bếp',
    description: 'Trạm bếp và tuyến món thuộc cấu hình quản trị, không chỉnh trực tiếp trong màn bếp live.',
    backendSurface: '/admin/kitchen/stations, /admin/kitchen/stations/{station_id}/category-routes',
    workflowLabel: 'Xem / chỉnh sửa',
  },
  {
    key: 'finance-settings',
    title: 'Tài chính và snapshot báo cáo',
    description: 'Hồ sơ thuế và thao tác dựng lại snapshot báo cáo nằm ở khu vực back-office.',
    backendSurface: '/admin/settings/finance/tax-profile, /admin/settings/reporting/snapshots/rebuild',
    workflowLabel: 'Chạy thử / ghi nhận',
  },
];

export function buildAdminBranchesQuery(
  filters: AdminBranchFilterState,
): GetV1AdminSettingsBranchesQueryParams {
  return {
    q: normalizedString(filters.query),
    is_active: filters.activeOnly ? true : undefined,
  };
}

export function buildAdminRestaurantTableQuery(
  filters: AdminTableFilterState,
  fallbackBranchId: number | null,
): AdminRestaurantTableQuery {
  return {
    q: normalizedString(filters.query),
    status: normalizedString(filters.status),
    zone: normalizedString(filters.zone),
    include_deleted: filters.includeDeleted ? true : undefined,
    branch_id: parsePositiveInteger(filters.branchIdInput) ?? fallbackBranchId ?? undefined,
  };
}

export function pickAdminBranchId<TBranch extends BranchLike>(
  branches: Array<TBranch>,
  currentBranchId: number | null,
  preferredBranchId: number | null,
): number | null {
  if (preferredBranchId !== null && branches.some((branch) => branch.branch_id === preferredBranchId)) {
    return preferredBranchId;
  }

  if (currentBranchId !== null && branches.some((branch) => branch.branch_id === currentBranchId)) {
    return currentBranchId;
  }

  return branches[0]?.branch_id ?? null;
}

export function summarizeAdminBranches<TBranch extends BranchLike>(branches: Array<TBranch>) {
  return {
    total: branches.length,
    active: branches.filter((branch) => branch.is_active).length,
    defaults: branches.filter((branch) => branch.is_default).length,
    withClosures: branches.filter((branch) => branch.closure_windows.length > 0).length,
    withBusinessHours: branches.filter((branch) => branch.business_hours.some((day) => day.periods.length > 0)).length,
  };
}

export function summarizeAdminTables<TTable extends RestaurantTableLike>(tables: Array<TTable>) {
  return {
    total: tables.length,
    active: tables.filter((table) => table.is_active).length,
    allocatable: tables.filter((table) => table.is_allocatable).length,
    operationallyLinked: tables.filter((table) => table.usage?.has_active_operational_links === true).length,
    branchScoped: new Set(tables.map((table) => table.branch_id).filter((branchId) => branchId !== null)).size,
  };
}

export function adminTableStatusTone(status: string): 'success' | 'warning' | 'error' | 'processing' | 'default' {
  switch (status) {
    case 'Available':
      return 'success';
    case 'Reserved':
    case 'Occupied':
      return 'processing';
    case 'Blocked':
    case 'Maintenance':
      return 'warning';
    default:
      return 'default';
  }
}

export function adminTableStatusLabel(status: string): string {
  switch (status) {
    case 'Available':
      return 'Bàn trống';
    case 'Reserved':
      return 'Đã đặt';
    case 'Occupied':
      return 'Đang phục vụ';
    case 'Blocked':
      return 'Đang khóa';
    case 'Maintenance':
      return 'Bảo trì';
    default:
      return status || 'Không rõ';
  }
}

export function dayOfWeekLabel(dayOfWeek: number): string {
  return ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'][dayOfWeek] ?? `Ngày ${dayOfWeek}`;
}

export function formatBusinessPeriods(periods: Array<{ start_time: string; end_time: string }>): string {
  if (periods.length === 0) {
    return 'Đóng cửa';
  }

  return periods.map((period) => `${period.start_time} - ${period.end_time}`).join(', ');
}

export function branchReservationLeadLabel(branch: BranchLike): string {
  const reservation = asRecord(branch.booking_policy.reservation);
  const leadMinutes = readNumber(reservation?.min_lead_time_minutes);

  return leadMinutes === null ? 'Chưa thiết lập' : `${leadMinutes} phút`;
}

export function branchSameDayCutoffLabel(branch: BranchLike): string {
  const reservation = asRecord(branch.booking_policy.reservation);
  return typeof reservation?.same_day_cutoff_time === 'string' && reservation.same_day_cutoff_time !== ''
    ? reservation.same_day_cutoff_time
    : 'Chưa thiết lập';
}

export function branchWaitingListLabel(branch: BranchLike): string {
  const waitingList = asRecord(branch.booking_policy.waiting_list);
  const enabled = waitingList?.enabled;

  if (typeof enabled !== 'boolean') {
    return 'Chưa thiết lập';
  }

  return enabled ? 'Đang bật' : 'Đang tắt';
}

function normalizedString(value: string): string | undefined {
  const normalized = value.trim();
  return normalized === '' ? undefined : normalized;
}

function parsePositiveInteger(value: string): number | null {
  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

function readNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  return null;
}
