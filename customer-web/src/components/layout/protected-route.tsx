"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/providers/auth-provider";

export function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { isAuthenticated, isBootstrapping } = useAuth();

  if (isBootstrapping) {
    return (
      <main className="mx-auto flex min-h-[70svh] w-full max-w-4xl flex-col gap-4 px-4 py-8">
        <Skeleton className="h-8 w-40" />
        <Skeleton className="h-40 w-full" />
        <Skeleton className="h-24 w-full" />
      </main>
    );
  }

  if (!isAuthenticated) {
    const next = encodeURIComponent(pathname || "/reservations");

    return (
      <main className="mx-auto flex min-h-[70svh] w-full max-w-md items-center px-4 py-10">
        <Card className="w-full rounded-lg">
          <CardHeader>
            <CardTitle>Sign in to continue</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <p className="text-sm text-muted-foreground">
              Your session is needed for reservations, bills, waiting list updates, and privacy tools.
            </p>
            <Button asChild className="w-full rounded-lg">
              <Link href={`/login?next=${next}`}>Sign in</Link>
            </Button>
          </CardContent>
        </Card>
      </main>
    );
  }

  return children;
}
