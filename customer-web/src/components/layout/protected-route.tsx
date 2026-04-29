"use client";

import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { buildCustomerAuthNext, buildCustomerLoginHref } from "@/lib/auth/navigation";
import { allowsCustomerSessionAccess, getCustomerRouteAccess } from "@/lib/auth/route-access";
import { getCustomerSessionId } from "@/lib/auth/storage";
import { getSessionRestoreDisplay } from "@/lib/api/errors";
import { useAuth } from "@/providers/auth-provider";

export function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { authError, isAuthenticated, isBootstrapping, logout, retryBootstrap } = useAuth();
  const nextPath = buildCustomerAuthNext(pathname, searchParams);
  const routeAccess = getCustomerRouteAccess(pathname);
  const hasCustomerSession = Boolean(getCustomerSessionId());
  const canUseCustomerSession = allowsCustomerSessionAccess(routeAccess) && hasCustomerSession;

  if (isBootstrapping) {
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
            <p className="text-sm text-muted-foreground">
              {restoreDisplay.message}
            </p>
            {restoreDisplay.retryHint ? <p className="text-sm text-muted-foreground">{restoreDisplay.retryHint}</p> : null}
            <div className="flex flex-col gap-3 sm:flex-row">
              <Button
                type="button"
                className="flex-1 rounded-lg"
                onClick={restoreDisplay.primaryAction === "retry" ? retryBootstrap : () => void logout({ nextPath })}
              >
                {restoreDisplay.primaryAction === "retry" ? "Kiểm tra lại phiên" : "Đến trang đăng nhập"}
              </Button>
              <Button type="button" variant="outline" className="flex-1 rounded-lg" onClick={() => void logout()}>
                Đặt lại phiên
              </Button>
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
                <Button asChild className="flex-1 rounded-lg">
                  <Link href="/booking">Tìm bàn</Link>
                </Button>
                <Button asChild variant="outline" className="flex-1 rounded-lg">
                  <Link href={buildCustomerLoginHref(nextPath)}>Đăng nhập</Link>
                </Button>
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
            <Button asChild className="w-full rounded-lg">
              <Link href={buildCustomerLoginHref(nextPath)}>Đăng nhập</Link>
            </Button>
          </CardContent>
        </Card>
      </main>
    );
  }

  return children;
}
