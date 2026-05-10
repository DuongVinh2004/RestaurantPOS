"use client";

import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { AppButton } from "@/components/customer/ui";
import { useCustomerSession } from "@/features/auth/hooks";
import { buildCustomerAuthNext, buildCustomerLoginHref } from "@/lib/auth/navigation";
import { allowsCustomerSessionAccess, getCustomerRouteAccess } from "@/lib/auth/route-access";
import { storeCustomerReturnToAction } from "@/lib/auth/return-to-action";
import { getSessionRestoreDisplay } from "@/lib/api/errors";
import { useAuth } from "@/providers/auth-provider";

export function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { authError, isAuthenticated, isBootstrapping, logout, retryBootstrap } = useAuth();
  const { isSessionReady, sessionId, continueAsGuest } = useCustomerSession();
  const nextPath = buildCustomerAuthNext(pathname, searchParams);
  const routeAccess = getCustomerRouteAccess(pathname);
  const routeAllowsCustomerSession = allowsCustomerSessionAccess(routeAccess);
  const canUseCustomerSession = routeAllowsCustomerSession && Boolean(sessionId);

  if (isBootstrapping) {
    return (
      <main className="mx-auto flex min-h-[70svh] w-full max-w-4xl flex-col gap-4 px-4 py-8">
        <Skeleton className="h-8 w-40" />
        <Skeleton className="h-40 w-full" />
        <Skeleton className="h-24 w-full" />
      </main>
    );
  }

  if (!isAuthenticated && routeAllowsCustomerSession && !isSessionReady) {
    return (
      <main className="mx-auto flex min-h-[70svh] w-full max-w-4xl flex-col gap-4 px-4 py-8">
        <Skeleton className="h-8 w-40" />
        <Skeleton className="h-40 w-full" />
        <Skeleton className="h-24 w-full" />
      </main>
    );
  }

  if (authError) {
    const restoreDisplay = getSessionRestoreDisplay(authError);

    return (
      <main className="mx-auto flex min-h-[70svh] w-full max-w-md items-center px-4 py-10">
        <Card className="w-full rounded-lg">
          <CardHeader>
            <CardTitle>{restoreDisplay.title}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <p className="text-sm text-muted-foreground">{restoreDisplay.message}</p>
            {restoreDisplay.retryHint ? <p className="text-sm text-muted-foreground">{restoreDisplay.retryHint}</p> : null}
            <div className="flex flex-col gap-3 sm:flex-row">
              <AppButton
                type="button"
                className="flex-1"
                onClick={restoreDisplay.primaryAction === "retry" ? retryBootstrap : () => void logout({ nextPath })}
              >
                {restoreDisplay.primaryAction === "retry" ? "Kiểm tra lại" : "Đăng nhập"}
              </AppButton>
              <AppButton type="button" variant="outline" className="flex-1" onClick={() => void logout()}>
                Đặt lại phiên
              </AppButton>
            </div>
          </CardContent>
        </Card>
      </main>
    );
  }

  if (!isAuthenticated && canUseCustomerSession) {
    return children;
  }

  if (!isAuthenticated) {
    if (allowsCustomerSessionAccess(routeAccess)) {
      return (
        <main className="mx-auto flex min-h-[70svh] w-full max-w-md items-center px-4 py-10">
          <Card className="w-full rounded-lg">
            <CardHeader>
              <CardTitle>{routeAccess.title}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <p className="text-sm text-muted-foreground">{routeAccess.description}</p>
              <div className="flex flex-col gap-3 sm:flex-row">
                <AppButton type="button" className="flex-1" onClick={continueAsGuest}>
                  Tiếp tục với tư cách khách
                </AppButton>
                <AppButton asChild variant="outline" className="flex-1">
                  <Link
                    href={buildCustomerLoginHref(nextPath)}
                    onClick={() => storeCustomerReturnToAction({ href: nextPath, label: routeAccess.title })}
                  >
                    Đăng nhập
                  </Link>
                </AppButton>
              </div>
            </CardContent>
          </Card>
        </main>
      );
    }

    return (
      <main className="mx-auto flex min-h-[70svh] w-full max-w-md items-center px-4 py-10">
        <Card className="w-full rounded-lg">
          <CardHeader>
            <CardTitle>{routeAccess.title}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <p className="text-sm text-muted-foreground">{routeAccess.description}</p>
            <AppButton asChild className="w-full">
              <Link
                href={buildCustomerLoginHref(nextPath)}
                onClick={() => storeCustomerReturnToAction({ href: nextPath, label: routeAccess.title })}
              >
                Đăng nhập
              </Link>
            </AppButton>
          </CardContent>
        </Card>
      </main>
    );
  }

  return children;
}
