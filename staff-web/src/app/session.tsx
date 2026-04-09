import { useEffect, useMemo, useState, type ReactNode } from 'react';
import {
  clearStaffSession,
  formatApiError,
  getCurrentStaffSession,
  getStaffToken,
  logoutStaff,
  refreshStaffSession,
  type StaffSession,
} from '../api/client';
import { isUnauthorized } from '../api/client';
import { StaffSessionContext, type StaffSessionContextValue } from './session-context';

export function StaffSessionProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<StaffSession | null>(null);
  const [booting, setBooting] = useState(true);
  const [notice, setNotice] = useState<string | null>(null);
  const [noticeTone, setNoticeTone] = useState<'success' | 'error'>('success');

  useEffect(() => {
    let active = true;

    async function bootstrap() {
      if (!getStaffToken()) {
        if (active) {
          setBooting(false);
        }
        return;
      }

      try {
        const current = await getCurrentStaffSession();
        if (active) {
          setSession(current);
        }
      } catch (error) {
        if (active) {
          if (isUnauthorized(error)) {
            clearStaffSession();
            setSession(null);
            setNotice('Phien staff da het han. Dang nhap lai de tiep tuc.');
            setNoticeTone('error');
          } else {
            setNotice(formatApiError(error, 'Khong khoi phuc duoc staff session. Thu lai.'));
            setNoticeTone('error');
          }
        }
      } finally {
        if (active) {
          setBooting(false);
        }
      }
    }

    void bootstrap();

    return () => {
      active = false;
    };
  }, []);

  const value = useMemo<StaffSessionContextValue>(
    () => ({
      session,
      booting,
      notice,
      noticeTone,
      setAuthenticatedSession: (next) => {
        setSession(next);
      },
      setNotice: (value, tone = 'success') => {
        setNotice(value);
        setNoticeTone(tone);
      },
      clearNotice: () => {
        setNotice(null);
        setNoticeTone('success');
      },
      refresh: async () => {
        const next = await refreshStaffSession();
        setSession(next);
      },
      logout: async () => {
        await logoutStaff();
        setSession(null);
        setNotice(null);
        setNoticeTone('success');
      },
      expire: (message: string) => {
        clearStaffSession();
        setSession(null);
        setNotice(message);
        setNoticeTone('error');
      },
    }),
    [booting, notice, noticeTone, session],
  );

  return <StaffSessionContext.Provider value={value}>{children}</StaffSessionContext.Provider>;
}
