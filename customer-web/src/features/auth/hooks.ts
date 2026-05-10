"use client";

import { useCallback, useMemo, useSyncExternalStore } from "react";
import { ensureCustomerSessionId, getCustomerSessionId, getStoredCustomerAuth } from "@/lib/auth/storage";
import { useAuth } from "@/providers/auth-provider";

export type CustomerSessionState = {
  sessionId: string | null;
  hasGuestSession: boolean;
  isSessionReady: boolean;
  continueAsGuest: () => string;
  refreshSessionState: () => void;
};

const sessionPendingSnapshot = "__restaurantpos_session_pending__";
const customerSessionListeners = new Set<() => void>();

function subscribeCustomerSession(listener: () => void) {
  customerSessionListeners.add(listener);

  return () => {
    customerSessionListeners.delete(listener);
  };
}

function emitCustomerSessionChange() {
  for (const listener of customerSessionListeners) {
    listener();
  }
}

export function useCustomerSession(): CustomerSessionState {
  const sessionSnapshot = useSyncExternalStore(
    subscribeCustomerSession,
    getCustomerSessionId,
    () => sessionPendingSnapshot,
  );
  const isSessionReady = sessionSnapshot !== sessionPendingSnapshot;
  const sessionId = isSessionReady ? sessionSnapshot : null;

  const refreshSessionState = useCallback(() => {
    emitCustomerSessionChange();
  }, []);

  const continueAsGuest = useCallback(() => {
    const nextSessionId = ensureCustomerSessionId();

    emitCustomerSessionChange();
    return nextSessionId;
  }, []);

  return {
    sessionId,
    hasGuestSession: Boolean(sessionId),
    isSessionReady,
    continueAsGuest,
    refreshSessionState,
  };
}

export type CustomerIdentityState = {
  isBootstrapping: boolean;
  isAuthenticated: boolean;
  isKnownCustomer: boolean;
  displayName: string;
  customerToken: string | null;
  sessionId: string | null;
  hasGuestSession: boolean;
};

export function useCustomerIdentity(): CustomerIdentityState {
  const auth = useAuth();
  const customerSession = useCustomerSession();
  const customerToken = getStoredCustomerAuth().customerToken;

  return useMemo(
    () => ({
      isBootstrapping: auth.isBootstrapping,
      isAuthenticated: auth.isAuthenticated,
      isKnownCustomer: Boolean(auth.isAuthenticated && auth.profile),
      displayName: auth.profile?.name?.trim() || "Guest",
      customerToken,
      sessionId: customerSession.sessionId,
      hasGuestSession: customerSession.hasGuestSession,
    }),
    [
      auth.isAuthenticated,
      auth.isBootstrapping,
      auth.profile,
      customerSession.hasGuestSession,
      customerSession.sessionId,
      customerToken,
    ],
  );
}
