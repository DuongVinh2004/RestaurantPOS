"use client";

import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import type { CustomerAuthSessionEnvelope } from "@/lib/contracts/generated/restaurantpos-sdk";
import { customerProfileFromSession, type CustomerProfile } from "@/lib/auth/session";
import { clearStoredCustomerAuth, getCustomerToken, getStoredCustomerAuth } from "@/lib/auth/storage";
import { normalizeApiError } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import { fetchCurrentCustomer, logoutCustomer } from "@/features/auth/api";

type AuthContextValue = {
  isBootstrapping: boolean;
  isAuthenticated: boolean;
  profile: CustomerProfile | null;
  session: CustomerAuthSessionEnvelope | null;
  markAuthenticated: (session: CustomerAuthSessionEnvelope) => void;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [hasToken, setHasToken] = useState(false);
  const [isReady, setIsReady] = useState(false);

  useEffect(() => {
    queueMicrotask(() => {
      getStoredCustomerAuth();
      setHasToken(Boolean(getCustomerToken()));
      setIsReady(true);
    });
  }, []);

  const currentQuery = useQuery({
    queryKey: queryKeys.auth.current,
    queryFn: fetchCurrentCustomer,
    enabled: isReady && hasToken,
  });

  useEffect(() => {
    const normalized = currentQuery.error ? normalizeApiError(currentQuery.error) : null;

    if (normalized?.kind === "unauthorized") {
      clearStoredCustomerAuth();
      queueMicrotask(() => {
        setHasToken(false);
        queryClient.removeQueries({ queryKey: queryKeys.auth.current });
      });
    }
  }, [currentQuery.error, queryClient]);

  const value = useMemo<AuthContextValue>(() => {
    const session = currentQuery.data ?? null;

    return {
      isBootstrapping: !isReady || (hasToken && currentQuery.isLoading),
      isAuthenticated: Boolean(hasToken && session),
      profile: customerProfileFromSession(session),
      session,
      markAuthenticated(nextSession) {
        queryClient.setQueryData(queryKeys.auth.current, nextSession);
        setHasToken(true);
      },
      async logout() {
        try {
          if (getCustomerToken()) {
            await logoutCustomer();
          }
        } finally {
          clearStoredCustomerAuth();
          setHasToken(false);
          queryClient.clear();
          router.push("/login");
        }
      },
    };
  }, [currentQuery.data, currentQuery.isLoading, hasToken, isReady, queryClient, router]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used inside AuthProvider.");
  }

  return context;
}
