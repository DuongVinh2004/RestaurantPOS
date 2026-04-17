import { beforeEach, describe, expect, it, vi } from 'vitest';
import { StaffApiError } from '../../shared/api/http';
import type { StaffSession } from '../../shared/auth/storage';
import { STAFF_TOKEN_STORAGE_KEY } from '../../shared/auth/storage';
import { notifyStaffAuthFailure } from '../../shared/auth/session-events';
import { buildStaffSession, type StaffStartupOverrides } from '../../test/fixtures';
import { staffRoutePaths } from '../router/workspace-paths';
import { STAFF_SESSION_EXPIRED_MESSAGE, defaultPathForSession, recommendedPathForSession, useAuthStore } from './auth-store';
import { useWorkspaceStore } from './workspace-store';

const staffApiMocks = vi.hoisted(() => ({
  getCurrentStaffSession: vi.fn(),
  loginStaff: vi.fn(),
  refreshStaffSession: vi.fn(),
  logoutStaff: vi.fn(),
}));

vi.mock('../../shared/api/staff-auth-api', () => staffApiMocks);

const initialWorkspaceState = useWorkspaceStore.getState();

type StaffSessionOverrides = Omit<Partial<StaffSession>, 'startup'> & {
  startup?: StaffStartupOverrides;
};

function makeSession(capabilities: Array<string>, overrides: StaffSessionOverrides = {}): StaffSession {
  const { startup: startupOverrides, ...sessionOverrides } = overrides;

  return buildStaffSession({
    ...sessionOverrides,
    staff_api_key_id: 1,
    capabilities,
    known_capabilities: capabilities,
    startup: {
      default_branch: null,
      active_cashier_shift: null,
      ...startupOverrides,
      readiness: {
        access: 'ready',
        branch: 'ready',
        cashier_shift: 'not_applicable',
        operator_ready: true,
        requires_cashier_shift: false,
        granted_capability_count: capabilities.length,
        known_capability_count: capabilities.length,
        ...startupOverrides?.readiness,
      },
    },
  });
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
    useWorkspaceStore.setState(initialWorkspaceState, true);
  });

  it('routes authenticated staff to the access hub by default', () => {
    expect(defaultPathForSession(makeSession(['cashier.shift.manage', 'table.board.view', 'waiting_list.manage']))).toBe(staffRoutePaths.access);
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
    }))).toBe(staffRoutePaths.ops.cashierShift);
  });

  it('recommends the access hub when startup readiness is incomplete', () => {
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
    }))).toBe(staffRoutePaths.access);
  });

  it('recommends the dashboard once the session is fully ready', () => {
    expect(recommendedPathForSession(makeSession(['cashier.shift.manage', 'table.board.view', 'waiting_list.manage']))).toBe(staffRoutePaths.ops.dashboard);
  });

  it('lands directly in the canonical admin workspace when admin is the only available workspace', () => {
    expect(recommendedPathForSession(makeSession(['reporting.view']))).toBe(staffRoutePaths.admin.landing);
  });

  it('keeps the stored opaque token during bootstrap when auth/me omits access_token', async () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'persisted-token');
    staffApiMocks.getCurrentStaffSession.mockResolvedValue(makeSession([], { access_token: null }));

    await useAuthStore.getState().bootstrap();

    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBe('persisted-token');
    expect(useAuthStore.getState().status).toBe('authenticated');
  });

  it('hydrates workspace state during bootstrap from the restored staff session', async () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'persisted-token');
    staffApiMocks.getCurrentStaffSession.mockResolvedValue(makeSession(['kitchen.manage']));

    await useAuthStore.getState().bootstrap();

    expect(useWorkspaceStore.getState()).toMatchObject({
      activeWorkspace: 'kitchen',
      availableWorkspaces: ['kitchen'],
      primaryWorkspace: 'kitchen',
    });
  });

  it('keeps the stored token when bootstrap fails with a forbidden response', async () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'persisted-token');
    staffApiMocks.getCurrentStaffSession.mockRejectedValue(new StaffApiError(403, {
      error_code: 'forbidden',
      message: 'Forbidden.',
      required_capability: 'settlement.manage',
      request_id: 'req-auth-store-403',
    }, 'Forbidden'));

    await useAuthStore.getState().bootstrap();

    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBe('persisted-token');
    expect(useAuthStore.getState().status).toBe('anonymous');
    expect(useAuthStore.getState().session).toBeNull();
    expect(useWorkspaceStore.getState()).toMatchObject({
      activeWorkspace: null,
      availableWorkspaces: [],
      primaryWorkspace: null,
    });
    expect(useAuthStore.getState().notice).toEqual({
      tone: 'error',
      message: 'Không thể khôi phục phiên làm việc. Hãy thử làm mới hoặc đăng nhập lại.',
    });
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

  it('setSession syncs workspace state from the current staff session', () => {
    useAuthStore.getState().setSession(makeSession(['reservation.manage', 'audit.view']));

    expect(useWorkspaceStore.getState()).toMatchObject({
      activeWorkspace: 'ops',
      availableWorkspaces: ['ops', 'admin'],
      primaryWorkspace: 'ops',
    });
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
    expect(useWorkspaceStore.getState()).toMatchObject({
      activeWorkspace: null,
      availableWorkspaces: [],
      primaryWorkspace: null,
    });
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
