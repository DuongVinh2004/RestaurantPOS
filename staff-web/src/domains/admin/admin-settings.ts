import type { GetV1AdminSettingsBranchesQueryParams } from '../../shared/api/sdk';

export type AdminBranchFilterState = {
  query: string;
  activeOnly: boolean;
};

type BranchLike = {
  branch_id: number;
  is_default: boolean;
  is_active: boolean;
  business_hours: Array<{ periods: Array<{ start_time: string; end_time: string }> }>;
  closure_windows: Array<unknown>;
  booking_policy: Record<string, unknown>;
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
    title: 'Branch registry',
    description: 'Default branch, activity state, and branch import-export stay in this ownership lane.',
    backendSurface: '/admin/settings/branches, /admin/settings/branches/export, /admin/settings/branches/import',
    workflowLabel: 'Import / export',
  },
  {
    key: 'table-config',
    title: 'Table and zone config',
    description: 'Tables, zones, and dining-room imports stay separate from live floor control.',
    backendSurface: '/admin/restaurant/tables, /admin/restaurant/zones',
    workflowLabel: 'Import / export',
  },
  {
    key: 'kitchen-routing',
    title: 'Kitchen routing',
    description: 'Stations and category-route ownership belongs to admin settings instead of the live kitchen shell.',
    backendSurface: '/admin/kitchen/stations, /admin/kitchen/stations/{station_id}/category-routes',
    workflowLabel: 'Read / write',
  },
  {
    key: 'finance-settings',
    title: 'Finance and snapshot controls',
    description: 'Tax profile and reporting snapshot rebuild stay in back-office settings.',
    backendSurface: '/admin/settings/finance/tax-profile, /admin/settings/reporting/snapshots/rebuild',
    workflowLabel: 'Dry run / commit',
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

export function dayOfWeekLabel(dayOfWeek: number): string {
  return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][dayOfWeek] ?? `Day ${dayOfWeek}`;
}

export function formatBusinessPeriods(periods: Array<{ start_time: string; end_time: string }>): string {
  if (periods.length === 0) {
    return 'Closed';
  }

  return periods.map((period) => `${period.start_time} - ${period.end_time}`).join(', ');
}

export function branchReservationLeadLabel(branch: BranchLike): string {
  const reservation = asRecord(branch.booking_policy.reservation);
  const leadMinutes = readNumber(reservation?.min_lead_time_minutes);

  return leadMinutes === null ? 'Not set' : `${leadMinutes} min`;
}

export function branchSameDayCutoffLabel(branch: BranchLike): string {
  const reservation = asRecord(branch.booking_policy.reservation);
  return typeof reservation?.same_day_cutoff_time === 'string' && reservation.same_day_cutoff_time !== ''
    ? reservation.same_day_cutoff_time
    : 'Not set';
}

export function branchWaitingListLabel(branch: BranchLike): string {
  const waitingList = asRecord(branch.booking_policy.waiting_list);
  const enabled = waitingList?.enabled;

  if (typeof enabled !== 'boolean') {
    return 'Not set';
  }

  return enabled ? 'Enabled' : 'Disabled';
}

function normalizedString(value: string): string | undefined {
  const normalized = value.trim();
  return normalized === '' ? undefined : normalized;
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
