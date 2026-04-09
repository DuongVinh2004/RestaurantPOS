import { createContext, useContext } from 'react';
import type { StaffSession } from '../api/client';

export type StaffNoticeTone = 'success' | 'error';

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

export function useStaffSession(): StaffSessionContextValue {
  const context = useContext(StaffSessionContext);

  if (!context) {
    throw new Error('useStaffSession must be used inside StaffSessionProvider.');
  }

  return context;
}
