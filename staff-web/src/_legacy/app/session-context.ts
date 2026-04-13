import { createContext, useContext, useMemo } from 'react';
import { useAuthStore } from './store/auth-store';
import type { StaffSession } from '../core/auth/storage';

export type StaffNoticeTone = 'success' | 'error' | 'warning';

export type StaffSessionContextValue = {
  session: StaffSession | null;
  booting: boolean;
  notice: string | null;
  noticeTone: StaffNoticeTone;
  setAuthenticatedSession: (session: StaffSession | null) => void;
  setNotice: (value: string | null, tone?: StaffNoticeTone) => void;
  clearNotice: () => void;
  refresh: () => Promise<void>;
  logout: () => Promise<void>;
  expire: (message: string) => void;
};

export const StaffSessionContext = createContext<StaffSessionContextValue | null>(null);

export function useStaffSessionStoreBridge(): StaffSessionContextValue {
  const status = useAuthStore((state) => state.status);
  const session = useAuthStore((state) => state.session);
  const notice = useAuthStore((state) => state.notice);
  const refresh = useAuthStore((state) => state.refresh);
  const logout = useAuthStore((state) => state.logout);
  const setSession = useAuthStore((state) => state.setSession);
  const expire = useAuthStore((state) => state.expire);
  const setNotice = useAuthStore((state) => state.setNotice);
  const clearNotice = useAuthStore((state) => state.clearNotice);

  return useMemo<StaffSessionContextValue>(
    () => ({
      session,
      booting: status === 'booting',
      notice: notice?.message ?? null,
      noticeTone: notice?.tone ?? 'success',
      setAuthenticatedSession: (next) => {
        setSession(next);
      },
      setNotice: (value, tone = 'success') => {
        setNotice(value ? { message: value, tone } : null);
      },
      clearNotice: () => {
        clearNotice();
      },
      refresh: async () => {
        await refresh();
      },
      logout: async () => {
        await logout();
      },
      expire: (message) => {
        expire(message);
      },
    }),
    [clearNotice, expire, logout, notice, refresh, session, setNotice, setSession, status],
  );
}

export function useStaffSession(): StaffSessionContextValue {
  const context = useContext(StaffSessionContext);
  const fallback = useStaffSessionStoreBridge();

  return context ?? fallback;
}
