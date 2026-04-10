import { beforeEach, describe, expect, it, vi } from 'vitest';
import { STAFF_TOKEN_STORAGE_KEY } from '../../core/auth/storage';
import { notifyStaffAuthFailure } from '../../core/auth/session-events';
import { STAFF_SESSION_EXPIRED_MESSAGE, defaultPathForSession, recommendedPathForSession, useAuthStore } from './auth-store';
import type { StaffSession } from '../../core/auth/storage';

const staffApiMocks = vi.hoisted(() => ({
  getCurrentStaffSession: vi.fn(),
  loginStaff: vi.fn(),
  refreshStaffSession: vi.fn(),
  logoutStaff: vi.fn(),
}));

vi.mock('../../core/api/staff-api', () => staffApiMocks);

function makeSession(capabilities: Array<string>, overrides: Partial<StaffSession> = {}): StaffSession {
  return {
    auth_mode: 'staff_api_key',
    token_type: 'opaque',
    auth_header: 'X-Staff-Key',
    access_token: 'staff-token',
    staff_api_key_id: 1,
    capabilities,
    known_capabilities: capabilities,
    capability_source: 'role',
    startup: {
      default_branch: null,
      active_cashier_shift: null,
      readiness: {
        access: 'ready',
        branch: 'ready',
        cashier_shift: 'not_applicable',
        operator_ready: true,
        requires_cashier_shift: false,
        granted_capability_count: capabilities.length,
        known_capability_count: capabilities.length,
      },
    },
    ...overrides,
  };
}

describe('routing helpers', () => {
  beforeEach(() => {
    localStorage.clear();
    staffApiMocks.getCurrentStaffSession.mockReset();
    staffApiMocks.loginStaff.mockReset();
    staffApiMocks.refreshStaffSession.mockReset();
    staffApiMocks.logoutStaff.mockReset();
    useAuthStore.setState({
      status: 'booting',
      session: null,
      notice: null,
    });
  });

  it('routes authenticated staff to the access hub by default', () => {
    expect(defaultPathForSession(makeSession(['cashier.shift.manage', 'table.board.view', 'waiting_list.manage']))).toBe('/access');
  });

  it('recommends cashier shift first when finance is blocked by startup readiness', () => {
    expect(recommendedPathForSession(makeSession(['cashier.shift.manage', 'settlement.manage'], {
      startup: {
        default_branch: null,
        active_cashier_shift: null,
        readiness: {
          access: 'ready',
          branch: 'ready',
          cashier_shift: 'action_required',
          operator_ready: true,
          requires_cashier_shift: true,
          granted_capability_count: 2,
          known_capability_count: 2,
        },
      },
    }))).toBe('/cashier-shift');
  });

  it('recommends the access hub when the startup branch is missing', () => {
    expect(recommendedPathForSession(makeSession(['table.board.view'], {
      startup: {
        default_branch: null,
        active_cashier_shift: null,
        readiness: {
          access: 'ready',
          branch: 'missing',
          cashier_shift: 'not_applicable',
          operator_ready: false,
          requires_cashier_shift: false,
          granted_capability_count: 1,
          known_capability_count: 1,
        },
      },
    }))).toBe('/access');
  });

  it('recommends the main floor flow once the session is fully ready', () => {
    expect(recommendedPathForSession(makeSession(['cashier.shift.manage', 'table.board.view', 'waiting_list.manage']))).toBe('/tables');
  });

  it('falls back to waiting list when that is the first operational capability', () => {
    expect(recommendedPathForSession(makeSession(['waiting_list.manage']))).toBe('/waiting-list');
  });

  it('falls back to cashier shift when finance shift capability is the only route left', () => {
    expect(recommendedPathForSession(makeSession(['cashier.shift.manage']))).toBe('/cashier-shift');
  });

  it('falls back to conversation inbox when that is the only granted module', () => {
    expect(recommendedPathForSession(makeSession(['conversation.manage']))).toBe('/conversations');
  });

  it('falls back to audit trail when audit is the only granted module', () => {
    expect(recommendedPathForSession(makeSession(['audit.view']))).toBe('/audit-trail');
  });

  it('falls back to reporting when reporting is the only granted module', () => {
    expect(recommendedPathForSession(makeSession(['reporting.view']))).toBe('/reporting');
  });

  it('keeps the stored opaque token during bootstrap when auth/me omits access_token', async () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'persisted-token');
    staffApiMocks.getCurrentStaffSession.mockResolvedValue(makeSession([], { access_token: null }));

    await useAuthStore.getState().bootstrap();

    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBe('persisted-token');
    expect(useAuthStore.getState().status).toBe('authenticated');
  });

  it('setSession promotes the store to authenticated and clears stale notices', () => {
    useAuthStore.setState({
      status: 'anonymous',
      session: null,
      notice: {
        tone: 'error',
        message: 'Expired',
      },
    });

    useAuthStore.getState().setSession(makeSession(['table.board.view']));

    expect(useAuthStore.getState().status).toBe('authenticated');
    expect(useAuthStore.getState().session?.capabilities).toContain('table.board.view');
    expect(useAuthStore.getState().notice).toBeNull();
  });

  it('expire clears the token and leaves an auth notice for the next render', () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'persisted-token');
    useAuthStore.setState({
      status: 'authenticated',
      session: makeSession(['table.board.view']),
      notice: null,
    });

    useAuthStore.getState().expire('Phiên làm việc của nhân viên đã hết hạn. Đăng nhập lại để tiếp tục.');

    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
    expect(useAuthStore.getState().status).toBe('anonymous');
    expect(useAuthStore.getState().session).toBeNull();
    expect(useAuthStore.getState().notice).toEqual({
      tone: 'error',
      message: 'Phiên làm việc của nhân viên đã hết hạn. Đăng nhập lại để tiếp tục.',
    });
  });

  it('expires the current session when a protected request reports a 401', () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'persisted-token');
    useAuthStore.setState({
      status: 'authenticated',
      session: makeSession(['table.board.view']),
      notice: null,
    });

    notifyStaffAuthFailure({
      status: 401,
      path: '/staff/tables/board',
    });

    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
    expect(useAuthStore.getState().status).toBe('anonymous');
    expect(useAuthStore.getState().session).toBeNull();
    expect(useAuthStore.getState().notice).toEqual({
      tone: 'error',
      message: STAFF_SESSION_EXPIRED_MESSAGE,
    });
  });
});
