import { create } from 'zustand';
import { formatStaffFacingApiError, isApiStatus } from '../../core/api/errors';
import { getCurrentStaffSession, loginStaff, logoutStaff, refreshStaffSession } from '../../core/api/staff-auth-api';
import { registerStaffAuthFailureHandler } from '../../core/auth/session-events';
import { readStoredStaffToken, writeStoredStaffToken, type StaffSession } from '../../core/auth/storage';
import { requiresStaffAccessGate, shouldRedirectToStaffCashierShift } from '../../core/auth/startup';
import { useFlowStore } from './flow-store';

export type AuthNotice = {
  tone: 'success' | 'error' | 'warning';
  message: string;
} | null;

type AuthState = {
  status: 'booting' | 'authenticated' | 'anonymous';
  session: StaffSession | null;
  lastSessionSyncAt: number | null;
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
  lastSessionSyncAt: null,
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
          lastSessionSyncAt: null,
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
          lastSessionSyncAt: null,
          status: 'anonymous',
          notice: {
            tone: 'error',
            message: formatStaffFacingApiError(
              error,
              'Không thể khôi phục phiên làm việc. Hãy thử làm mới hoặc đăng nhập lại.',
            ),
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
        message: 'Đã làm mới phiên làm việc của nhân viên.',
      },
    });

    return session;
  },
  logout: async () => {
    await logoutStaff();
    useFlowStore.getState().syncSessionContext(null);
    set({
      session: null,
      lastSessionSyncAt: null,
      status: 'anonymous',
      notice: null,
    });
  },
  setSession: (session) => {
    useFlowStore.getState().syncSessionContext(session);
    set({
      session,
      lastSessionSyncAt: session ? Date.now() : null,
      status: session ? 'authenticated' : 'anonymous',
      notice: null,
    });
  },
  expire: (message) => {
    writeStoredStaffToken(null);
    useFlowStore.getState().syncSessionContext(null);
    set({
      session: null,
      lastSessionSyncAt: null,
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

  if (requiresStaffAccessGate(session)) {
    return '/access';
  }

  if (shouldRedirectToStaffCashierShift(session)) {
    return '/cashier-shift';
  }

  return '/dashboard';
}
