import { RestaurantPosApiError } from '../api/sdk';
import type { StaffSession } from '../core/auth/storage';

export function buildStaffSession(overrides: Partial<StaffSession> = {}): StaffSession {
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
  const startup = overrides.startup ?? {
    default_branch: {
      branch_id: 1,
      branch_code: 'MAIN',
      branch_name: 'Chi nhanh chinh',
      timezone: 'Asia/Ho_Chi_Minh',
      currency: 'VND',
      is_default: true,
      is_active: true,
    },
    active_cashier_shift: {
      cashier_shift_id: 44,
      branch_id: 1,
      branch: {
        branch_id: 1,
        branch_code: 'MAIN',
        branch_name: 'Chi nhanh chinh',
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
    },
    readiness: {
      access: capabilities.length > 0 ? 'ready' : 'capability_missing',
      branch: 'ready',
      cashier_shift: requiresCashierShift ? 'ready' : 'not_applicable',
      operator_ready: capabilities.length > 0,
      requires_cashier_shift: requiresCashierShift,
      granted_capability_count: capabilities.length,
      known_capability_count: knownCapabilities.length,
    },
  };

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
    ...overrides,
  };
}

export function buildApiError<T>(status: number, payload: T, message = 'API request failed') {
  return new RestaurantPosApiError(message, status, payload);
}
