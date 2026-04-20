"use client";

import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import type { CustomerAuthSessionEnvelope } from "@/lib/contracts/generated/restaurantpos-sdk";
import { customerProfileFromSession, type CustomerProfile } from "@/lib/auth/session";
import { clearStoredCustomerAuth, getCustomerToken, getStoredCustomerAuth, syncStoredCustomerAuthSession } from "@/lib/auth/storage";
import {
  classifySessionRestoreError,
  hasExpiredSessionTimestamp,
  type SessionRestoreError,
} from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import { bootstrapCustomerSession, logoutCustomer } from "@/features/auth/api";

type AuthContextValue = {
  isBootstrapping: boolean;
  isAuthenticated: boolean;
  profile: CustomerProfile | null;
  session: CustomerAuthSessionEnvelope | null;
  authError: SessionRestoreError | null;
  markAuthenticated: (session: CustomerAuthSessionEnvelope) => void;
  retryBootstrap: () => void;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [hasToken, setHasToken] = useState(false);
  const [isReady, setIsReady] = useState(false);
  const [restoreError, setRestoreError] = useState<SessionRestoreError | null>(null);
  const [storedExpiry, setStoredExpiry] = useState<string | null>(null);

  useEffect(() => {
    queueMicrotask(() => {
      const storedAuth = getStoredCustomerAuth();
      const expired = Boolean(storedAuth.customerToken) && hasExpiredSessionTimestamp(storedAuth.expiresAtUtc);

      setStoredExpiry(storedAuth.expiresAtUtc);

      if (expired) {
        clearStoredCustomerAuth();
        setHasToken(false);
        setRestoreError(
          classifySessionRestoreError(
            {
              kind: "unauthorized",
              status: 401,
              message: "Your saved sign-in has expired.",
              errorCode: "session_expired",
              categoryCode: "authentication_required",
              requestId: null,
              validationErrors: null,
            },
            { expiresAtUtc: storedAuth.expiresAtUtc },
          ),
        );
        setIsReady(true);
        return;
      }

      setHasToken(Boolean(storedAuth.customerToken ?? getCustomerToken()));
      setIsReady(true);
    });
  }, []);

  const currentQuery = useQuery({
    queryKey: queryKeys.auth.current,
    queryFn: bootstrapCustomerSession,
    enabled: isReady && hasToken,
    retry: false,
  });

  const normalizedError = currentQuery.error
    ? classifySessionRestoreError(currentQuery.error, { expiresAtUtc: storedExpiry })
    : null;

  useEffect(() => {
    if (!normalizedError) {
      return;
    }

    queueMicrotask(() => {
      setRestoreError(normalizedError);
    });

    if (normalizedError.kind === "unauthorized") {
      clearStoredCustomerAuth();
      queueMicrotask(() => {
        setHasToken(false);
        setStoredExpiry(null);
        queryClient.removeQueries({ queryKey: queryKeys.auth.current });
      });
    }
  }, [normalizedError, queryClient]);

  useEffect(() => {
    if (currentQuery.data) {
      queueMicrotask(() => {
        const stored = syncStoredCustomerAuthSession(currentQuery.data);
        setRestoreError(null);
        setStoredExpiry(stored.expiresAtUtc);
        setHasToken(Boolean(stored.customerToken));
      });
    }
  }, [currentQuery.data]);

  const session = currentQuery.data ?? null;

  const value: AuthContextValue = {
    isBootstrapping: !isReady || (hasToken && (currentQuery.isLoading || currentQuery.isRefetching)),
    isAuthenticated: Boolean(hasToken && session && !restoreError),
    profile: customerProfileFromSession(session),
    session,
    authError: restoreError,
    markAuthenticated(nextSession) {
      const stored = syncStoredCustomerAuthSession(nextSession);
      queryClient.setQueryData(queryKeys.auth.current, nextSession);
      setRestoreError(null);
      setStoredExpiry(stored.expiresAtUtc);
      setHasToken(Boolean(stored.customerToken ?? getCustomerToken()));
    },
    retryBootstrap() {
      void currentQuery.refetch();
    },
    async logout() {
      try {
        if (getCustomerToken()) {
          await logoutCustomer();
        }
      } finally {
        clearStoredCustomerAuth();
        setHasToken(false);
        setStoredExpiry(null);
        setRestoreError(null);
        queryClient.clear();
        router.push("/login");
      }
    },
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used inside AuthProvider.");
  }

  return context;
}
