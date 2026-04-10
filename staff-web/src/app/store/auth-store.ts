import { create } from 'zustand';
import { formatApiError, isApiStatus } from '../../core/api/errors';
import { getCurrentStaffSession, loginStaff, logoutStaff, refreshStaffSession } from '../../core/api/staff-api';
import { registerStaffAuthFailureHandler } from '../../core/auth/session-events';
import { readStoredStaffToken, writeStoredStaffToken, type StaffSession } from '../../core/auth/storage';
import { useFlowStore } from './flow-store';

export type AuthNotice = {
  tone: 'success' | 'error' | 'warning';
  message: string;
} | null;

type AuthState = {
  status: 'booting' | 'authenticated' | 'anonymous';
  session: StaffSession | null;
  notice: AuthNotice;
  bootstrap: () => Promise<void>;
  login: (payload: { identifier: string; password: string; deviceName: string }) => Promise<StaffSession>;
  refresh: () => Promise<StaffSession>;
  logout: () => Promise<void>;
  setSession: (session: StaffSession | null) => void;
  expire: (message: string) => void;
  setNotice: (notice: AuthNotice) => void;
  clearNotice: () => void;
};

let bootstrapPromise: Promise<void> | null = null;
export const STAFF_SESSION_EXPIRED_MESSAGE = 'Phiên làm việc của nhân viên đã hết hạn. Đăng nhập lại để tiếp tục.';

export const useAuthStore = create<AuthState>((set, get) => ({
  status: 'booting',
  session: null,
  notice: null,
  bootstrap: async () => {
    if (bootstrapPromise) {
      return bootstrapPromise;
    }

    bootstrapPromise = (async () => {
      if (!readStoredStaffToken()) {
        useFlowStore.getState().syncSessionContext(null);
        set({
          session: null,
          status: 'anonymous',
          notice: null,
        });
        return;
      }

      try {
        const session = await getCurrentStaffSession();
        get().setSession(session);
      } catch (error) {
        if (isApiStatus(error, 401)) {
          get().expire(STAFF_SESSION_EXPIRED_MESSAGE);
          return;
        }

        useFlowStore.getState().syncSessionContext(null);
        set({
          session: null,
          status: 'anonymous',
          notice: {
            tone: 'error',
            message: formatApiError(error, 'KhÃ´ng thá»ƒ khÃ´i phá»¥c phiÃªn lÃ m viá»‡c cá»§a nhÃ¢n viÃªn.'),
          },
        });
      }
    })().finally(() => {
      bootstrapPromise = null;
    });

    return bootstrapPromise;
  },
  login: async ({ identifier, password, deviceName }) => {
    const session = await loginStaff({
      identifier,
      password,
      device_name: deviceName,
    });

    get().setSession(session);

    return session;
  },
  refresh: async () => {
    const session = await refreshStaffSession();

    get().setSession(session);
    set({
      notice: {
        tone: 'success',
        message: 'ÄÃ£ lÃ m má»›i phiÃªn lÃ m viá»‡c cá»§a nhÃ¢n viÃªn.',
      },
    });

    return session;
  },
  logout: async () => {
    await logoutStaff();
    useFlowStore.getState().syncSessionContext(null);
    set({
      session: null,
      status: 'anonymous',
      notice: null,
    });
  },
  setSession: (session) => {
    useFlowStore.getState().syncSessionContext(session);
    set({
      session,
      status: session ? 'authenticated' : 'anonymous',
      notice: null,
    });
  },
  expire: (message) => {
    writeStoredStaffToken(null);
    useFlowStore.getState().syncSessionContext(null);
    set({
      session: null,
      status: 'anonymous',
      notice: {
        tone: 'error',
        message,
      },
    });
  },
  setNotice: (notice) => set({ notice }),
  clearNotice: () => set({ notice: null }),
}));

registerStaffAuthFailureHandler((failure) => {
  if (failure.status !== 401) {
    return;
  }

  const state = useAuthStore.getState();
  if (state.status === 'anonymous') {
    return;
  }

  state.expire(STAFF_SESSION_EXPIRED_MESSAGE);
});

export function defaultPathForSession(session: StaffSession | null): string {
  if (!session) {
    return '/login';
  }

  return '/access';
}

export function recommendedPathForSession(session: StaffSession | null): string {
  if (!session) {
    return '/login';
  }

  if (
    session.startup.readiness.access !== 'ready'
    || session.startup.readiness.branch !== 'ready'
    || !session.startup.readiness.operator_ready
  ) {
    return '/access';
  }

  if (
    session.startup.readiness.requires_cashier_shift
    && session.startup.readiness.cashier_shift === 'action_required'
    && session.capabilities.includes('cashier.shift.manage')
  ) {
    return '/cashier-shift';
  }

  if (session.capabilities.includes('table.board.view')) {
    return '/tables';
  }

  if (session.capabilities.includes('reservation.manage')) {
    return '/reservations';
  }

  if (session.capabilities.includes('waiting_list.manage')) {
    return '/waiting-list';
  }

  if (session.capabilities.includes('order.manage')) {
    return '/orders';
  }

  if (session.capabilities.includes('kitchen.manage')) {
    return '/kitchen';
  }

  if (session.capabilities.includes('settlement.manage')) {
    return '/checkout';
  }

  if (session.capabilities.includes('cashier.shift.manage')) {
    return '/cashier-shift';
  }

  if (session.capabilities.includes('conversation.manage')) {
    return '/conversations';
  }

  if (session.capabilities.includes('audit.view')) {
    return '/audit-trail';
  }

  if (session.capabilities.includes('reporting.view')) {
    return '/reporting';
  }

  return '/access';
}
