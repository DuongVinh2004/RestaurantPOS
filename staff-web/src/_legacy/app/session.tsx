import { useEffect, type ReactNode } from 'react';
import { StaffSessionContext, useStaffSessionStoreBridge } from './session-context';
import { useAuthStore } from './store/auth-store';

export function StaffSessionProvider({ children }: { children: ReactNode }) {
  const bootstrap = useAuthStore((state) => state.bootstrap);
  const value = useStaffSessionStoreBridge();

  useEffect(() => {
    void bootstrap();
  }, [bootstrap]);

  return <StaffSessionContext.Provider value={value}>{children}</StaffSessionContext.Provider>;
}
