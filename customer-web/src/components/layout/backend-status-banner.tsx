"use client";

import { useQuery } from "@tanstack/react-query";
import { WifiOff } from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { featureFlags } from "@/lib/config/feature-flags";
import { queryKeys } from "@/lib/api/query-keys";
import { checkBackendHealth } from "@/lib/api/sdk-client";

export function BackendStatusBanner() {
  const healthQuery = useQuery({
    queryKey: queryKeys.backend.health,
    queryFn: () => checkBackendHealth(),
    enabled: featureFlags.showDevBackendStatus,
    refetchOnWindowFocus: false,
    retry: false,
  });

  if (!featureFlags.showDevBackendStatus || healthQuery.isLoading || healthQuery.data?.ok) {
    return null;
  }

  return (
    <Alert className="rounded-none border-x-0 border-t-0 bg-amber-50 text-amber-950">
      <WifiOff className="h-4 w-4" />
      <AlertTitle>Backend is not reachable</AlertTitle>
      <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <span>
          Live API paths are still active. Start the Laravel runtime, or enable local dev mocks only when you need an
          offline UI pass.
        </span>
        <Button type="button" variant="outline" size="sm" className="w-fit rounded-lg" onClick={() => healthQuery.refetch()}>
          Retry
        </Button>
      </AlertDescription>
    </Alert>
  );
}
