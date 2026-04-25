import { create } from 'zustand';
import { formatStaffFacingApiError, isApiStatus } from '../../shared/api/errors';
import {
  canAttemptStaffBrowserSessionRefresh,
  getCurrentStaffSession,
  loginStaff,
  logoutStaff,
  refreshStaffSession,
} from '../../shared/api/staff-auth-api';
import { registerStaffAuthFailureHandler } from '../../shared/auth/session-events';
import { readStoredStaffToken, writeStoredStaffToken, type StaffSession } from '../../shared/auth/storage';
import { resolveDefaultStaffPath, resolveRecommendedStaffPath } from '../router/session-paths';
import { useFlowStore } from './flow-store';
import { useWorkspaceStore } from './workspace-store';

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
      if (!readStoredStaffToken() && canAttemptStaffBrowserSessionRefresh()) {
        try {
          const session = await refreshStaffSession();
          get().setSession(session);
          return;
        } catch (error) {
          if (isApiStatus(error, 401) || isApiStatus(error, 419)) {
            get().expire(STAFF_SESSION_EXPIRED_MESSAGE);
            return;
          }

          useFlowStore.getState().syncSessionContext(null);
          useWorkspaceStore.getState().syncSession(null);
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
          return;
        }
      }

      if (!readStoredStaffToken()) {
        useFlowStore.getState().syncSessionContext(null);
        useWorkspaceStore.getState().syncSession(null);
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
        useWorkspaceStore.getState().syncSession(null);
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
    useWorkspaceStore.getState().syncSession(null);
    set({
      session: null,
      lastSessionSyncAt: null,
      status: 'anonymous',
      notice: null,
    });
  },
  setSession: (session) => {
    useFlowStore.getState().syncSessionContext(session);
    useWorkspaceStore.getState().syncSession(session);
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
    useWorkspaceStore.getState().syncSession(null);
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
  return resolveDefaultStaffPath(session);
}

export function recommendedPathForSession(session: StaffSession | null): string {
  return resolveRecommendedStaffPath(session);
}
