import { RestaurantPosApiError } from '../shared/api/sdk';
import type { StaffSession } from '../shared/auth/storage';

export type StaffStartupOverrides = Omit<Partial<StaffSession['startup']>, 'readiness' | 'branch_access' | 'navigation'> & {
  readiness?: Partial<StaffSession['startup']['readiness']>;
  branch_access?: Partial<StaffSession['startup']['branch_access']>;
  navigation?: StaffSession['startup']['navigation'];
};

type StaffSessionOverrides = Omit<Partial<StaffSession>, 'startup'> & {
  startup?: StaffStartupOverrides;
};

const defaultStaffBranch = {
  branch_id: 1,
  branch_code: 'MAIN',
  branch_name: 'Chi nhánh chính',
  timezone: 'Asia/Ho_Chi_Minh',
  currency: 'VND',
  is_default: true,
  is_active: true,
} satisfies NonNullable<StaffSession['startup']['default_branch']>;

const defaultStaffStartupShift = {
  cashier_shift_id: 44,
  branch_id: 1,
  branch: {
    branch_id: 1,
    branch_code: 'MAIN',
    branch_name: 'Chi nhánh chính',
    timezone: 'Asia/Ho_Chi_Minh',
    currency: 'VND',
    is_default: true,
    is_active: true,
  },
  shift_code: 'SHIFT-STAFF-WEB',
  status: 'open',
  currency: 'VND',
  terminal_code: 'POS-01',
  row_version: 7,
  opened_at: '2026-04-07T08:00:00Z',
} satisfies NonNullable<StaffSession['startup']['active_cashier_shift']>;

function resolveFixtureWorkspaces(capabilities: Array<string>): Pick<StaffSession['startup'], 'primary_workspace' | 'available_workspaces'> {
  if (capabilities.includes('*')) {
    return {
      primary_workspace: 'ops',
      available_workspaces: ['ops', 'kitchen', 'admin'],
    };
  }

  const availableWorkspaces: StaffSession['startup']['available_workspaces'] = [];
  const hasAny = (required: Array<string>) => required.some((capability) => capabilities.includes(capability));

  if (hasAny([
    'table.board.view',
    'table.release',
    'reservation.manage',
    'waiting_list.manage',
    'order.manage',
    'settlement.manage',
    'payment.refund',
    'cashier.shift.manage',
    'conversation.manage',
    'voucher.manage',
    'loyalty.view',
    'loyalty.redeem',
    'loyalty.adjust',
  ])) {
    availableWorkspaces.push('ops');
  }

  if (hasAny(['kitchen.manage'])) {
    availableWorkspaces.push('kitchen');
  }

  if (hasAny([
    'audit.view',
    'reporting.view',
    'inventory.manage',
    'menu.manage',
    'settings.manage',
    'privacy.manage',
    'ops.view',
    'ops.health.view',
    'ops.metrics.view',
    'ops.release.view',
    'voucher.master_data.manage',
  ])) {
    availableWorkspaces.push('admin');
  }

  return {
    primary_workspace: availableWorkspaces[0] ?? 'ops',
    available_workspaces: availableWorkspaces,
  };
}

export function buildStaffStartupContext(overrides: StaffStartupOverrides = {}): StaffSession['startup'] {
  const defaultBranch = overrides.default_branch === undefined ? defaultStaffBranch : overrides.default_branch;
  const defaultBranchId = defaultBranch?.branch_id ?? null;
  const activeCashierShift = overrides.active_cashier_shift === undefined
    ? (defaultBranch === null ? null : defaultStaffStartupShift)
    : overrides.active_cashier_shift;

  const startup: StaffSession['startup'] = {
    primary_workspace: overrides.primary_workspace ?? 'ops',
    available_workspaces: overrides.available_workspaces ?? ['ops'],
    default_branch_id: defaultBranchId,
    allowed_branch_ids: defaultBranchId === null ? [] : [defaultBranchId],
    assigned_station_ids: [],
    default_branch: defaultBranch,
    branch_access: {
      accessible_branch_ids: defaultBranchId === null ? [] : [defaultBranchId],
      default_branch_id: defaultBranchId,
      current_branch_id: defaultBranchId,
      has_default_branch_access: defaultBranchId !== null,
      has_multi_branch_access: false,
      branch_selector_enabled: false,
      access_source: defaultBranchId === null ? 'none' : 'default_branch',
      branches_uri: '/api/v1/staff/branches',
    },
    active_cashier_shift: activeCashierShift,
    navigation: {},
    readiness: {
      access: 'ready',
      branch: defaultBranch === null ? 'missing' : 'ready',
      cashier_shift: activeCashierShift ? 'ready' : 'not_applicable',
      operator_ready: true,
      requires_cashier_shift: false,
      granted_capability_count: 0,
      known_capability_count: 0,
    },
  };

  return {
    ...startup,
    ...overrides,
    default_branch_id: overrides.default_branch_id === undefined ? startup.default_branch_id : overrides.default_branch_id,
    allowed_branch_ids: overrides.allowed_branch_ids ?? startup.allowed_branch_ids,
    assigned_station_ids: overrides.assigned_station_ids ?? startup.assigned_station_ids,
    branch_access: {
      ...startup.branch_access,
      ...overrides.branch_access,
    },
    navigation: {
      ...startup.navigation,
      ...overrides.navigation,
    },
    readiness: {
      ...startup.readiness,
      ...overrides.readiness,
    },
  };
}

export function buildStaffSession(overrides: StaffSessionOverrides = {}): StaffSession {
  const capabilities = overrides.capabilities ?? [
    'table.board.view',
    'waiting_list.manage',
    'order.manage',
    'settlement.manage',
    'payment.refund',
    'conversation.manage',
  ];
  const knownCapabilities = overrides.known_capabilities ?? [
    'table.board.view',
    'waiting_list.manage',
    'order.manage',
    'settlement.manage',
    'payment.refund',
    'conversation.manage',
  ];
  const requiresCashierShift = capabilities.includes('*')
    || capabilities.includes('settlement.manage')
    || capabilities.includes('cashier.shift.manage');
  const { startup: startupOverrides, ...sessionOverrides } = overrides;
  const workspaceContract = resolveFixtureWorkspaces(capabilities);
  const startup = buildStaffStartupContext({
    primary_workspace: startupOverrides?.primary_workspace ?? workspaceContract.primary_workspace,
    available_workspaces: startupOverrides?.available_workspaces ?? workspaceContract.available_workspaces,
    ...startupOverrides,
    readiness: {
      access: capabilities.length > 0 ? 'ready' : 'capability_missing',
      branch: 'ready',
      cashier_shift: requiresCashierShift ? 'ready' : 'not_applicable',
      operator_ready: capabilities.length > 0,
      requires_cashier_shift: requiresCashierShift,
      granted_capability_count: capabilities.length,
      known_capability_count: knownCapabilities.length,
      ...startupOverrides?.readiness,
    },
  });

  return {
    auth_mode: 'staff_api_key',
    token_type: 'opaque',
    auth_header: 'X-Staff-Key',
    access_token: 'staff-token',
    staff_api_key_id: 17,
    expires_at_utc: '2026-04-07T10:00:00Z',
    user: {
      user_id: 42,
      username: 'foh.staff',
      full_name: 'Front Desk',
      email: 'foh@example.test',
      phone: '0909000111',
      role_id: 5,
      role_name: 'Staff',
    },
    capabilities,
    known_capabilities: knownCapabilities,
    capability_source: 'role_capabilities',
    startup,
    ...sessionOverrides,
  };
}

export function buildApiError<T>(status: number, payload: T, message = 'API request failed') {
  return new RestaurantPosApiError(message, status, payload);
}
