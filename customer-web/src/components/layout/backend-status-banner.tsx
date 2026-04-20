"use client";

import { useQuery } from "@tanstack/react-query";
import { AlertTriangle, WifiOff } from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { getApiErrorDisplay, normalizeApiError } from "@/lib/api/errors";
import { customerWebRollout, featureFlags } from "@/lib/config/feature-flags";
import { getApiBaseUrlRuntimeDiagnostics, publicEnvDiagnostics } from "@/lib/config/env";
import { queryKeys } from "@/lib/api/query-keys";
import { checkBackendHealth, getApiRuntimeDiagnostics } from "@/lib/api/sdk-client";

type BackendStatusBannerState = {
  tone: "warning" | "danger";
  title: string;
  message: string;
  guidance: string[];
  showRetry: boolean;
};

export function BackendStatusBanner() {
  const diagnostics = getApiRuntimeDiagnostics();
  const healthQuery = useQuery({
    queryKey: queryKeys.backend.health,
    queryFn: () => checkBackendHealth(),
    enabled: featureFlags.showDevBackendStatus,
    refetchOnWindowFocus: false,
    retry: false,
  });

  if (!featureFlags.showDevBackendStatus) {
    return null;
  }

  const appHost = typeof window === "undefined" ? null : window.location.hostname;
  const bannerState = resolveBackendStatusBannerState({
    appHost,
    baseUrl: diagnostics.baseUrl,
    usingDevMocks: diagnostics.usingDevMocks,
    healthResult: healthQuery.data,
    healthError: healthQuery.error,
  });

  if (!bannerState && (healthQuery.isLoading || healthQuery.data?.ok)) {
    return null;
  }

  if (!bannerState) {
    return null;
  }

  const normalizedError = healthQuery.error ? normalizeApiError(healthQuery.error) : null;
  const errorDisplay = healthQuery.error ? getApiErrorDisplay(healthQuery.error) : null;
  const healthStatus = healthQuery.data?.status ?? normalizedError?.status ?? null;
  const statusLabel = healthStatus === null ? null : `Status ${healthStatus}`;
  const requestIdLabel = healthQuery.data?.requestId ? `Request ID: ${healthQuery.data.requestId}` : errorDisplay?.requestIdLabel ?? null;
  const checkedUrl = healthQuery.data?.checkedUrl ?? `${diagnostics.baseUrl.replace(/\/+$/, "")}/api/v1/health`;
  const alertClassName =
    bannerState.tone === "danger"
      ? "rounded-none border-x-0 border-t-0 border-rose-200 bg-rose-50 text-rose-950"
      : "rounded-none border-x-0 border-t-0 bg-amber-50 text-amber-950";
  const Icon = bannerState.tone === "danger" ? WifiOff : AlertTriangle;

  return (
    <Alert className={alertClassName}>
      <Icon className="h-4 w-4" />
      <AlertTitle>{bannerState.title}</AlertTitle>
      <AlertDescription className="flex flex-col gap-3">
        <div className="space-y-2">
          <p>{bannerState.message}</p>
          <div className="space-y-1 text-sm">
            {bannerState.guidance.map((item) => (
              <p key={item}>{item}</p>
            ))}
          </div>
          <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs font-medium">
            <span>{diagnostics.usingDevMocks ? "Mode: Mock responses" : "Mode: API requests"}</span>
            <span className="font-mono">{checkedUrl}</span>
            {statusLabel ? <span>{statusLabel}</span> : null}
            {requestIdLabel ? <span>{requestIdLabel}</span> : null}
          </div>
        </div>
        {bannerState.showRetry ? (
          <Button type="button" variant="outline" size="sm" className="w-fit rounded-lg" onClick={() => healthQuery.refetch()}>
            Retry
          </Button>
        ) : null}
      </AlertDescription>
    </Alert>
  );
}

export function resolveBackendStatusBannerState({
  appHost,
  baseUrl,
  usingDevMocks,
  healthResult,
  healthError,
}: {
  appHost: string | null;
  baseUrl: string;
  usingDevMocks: boolean;
  healthResult: Awaited<ReturnType<typeof checkBackendHealth>> | undefined;
  healthError: unknown;
}): BackendStatusBannerState | null {
  const baseUrlDiagnostics = getApiBaseUrlRuntimeDiagnostics(baseUrl, appHost);
  const aliasWarnings = publicEnvDiagnostics.rolloutFlagsUsingAliases;
  const normalizedError = healthError ? normalizeApiError(healthError) : null;
  const errorDisplay = healthError ? getApiErrorDisplay(healthError) : null;

  if (usingDevMocks) {
    return {
      tone: "warning",
      title: "Local mock mode is on",
      message:
        "This browser is serving mock responses instead of the live API. Use it only for local or controlled UAT UI checks, not for rollout or release proof.",
      guidance: [
        "Turn off NEXT_PUBLIC_ENABLE_DEV_MOCKS before QA, UAT, or release proof.",
        customerWebRollout.devMockAdapter.liveProofSummary,
      ],
      showRetry: false,
    };
  }

  if (baseUrlDiagnostics.likelyWrongForCurrentHost) {
    return {
      tone: "warning",
      title: "API base URL looks wrong for this environment",
      message: `This app is running on ${baseUrlDiagnostics.appHost}, but NEXT_PUBLIC_API_BASE_URL still points to ${baseUrl}. A local API base URL only works when the browser and Laravel runtime are on the same machine.`,
      guidance: [
        "Set NEXT_PUBLIC_API_BASE_URL to the correct QA or UAT API host, then reload the app.",
        "This banner cannot verify UAT manifest freshness or payment fixtures. Run the live runtime preflight for that proof.",
      ],
      showRetry: Boolean(healthError || (healthResult && !healthResult.ok)),
    };
  }

  if (healthResult && !healthResult.ok) {
    return {
      tone: "danger",
      title: "Live API is not reachable",
      message:
        `${errorDisplay?.message ?? "The backend health check failed."} ${
          errorDisplay?.retryHint ?? "Confirm the API is running and reachable from this environment."
        }`.trim(),
      guidance: [
        "Check NEXT_PUBLIC_API_BASE_URL first, then confirm the backend runtime is healthy for this environment.",
        "This browser only checked the health URL, response status, and request id. Use the live runtime preflight for UAT pack and payment prerequisite proof.",
      ],
      showRetry: true,
    };
  }

  if (normalizedError) {
    return {
      tone: "danger",
      title: "Live API health check failed",
      message:
        `${errorDisplay?.message ?? normalizedError.message} ${
          errorDisplay?.retryHint ?? "Confirm the backend is reachable from this environment."
        }`.trim(),
      guidance: [
        "Check NEXT_PUBLIC_API_BASE_URL first, then confirm the backend runtime is healthy for this environment.",
        "This browser cannot prove UAT pack freshness or payment fixture readiness. Use the live runtime preflight before calling payment flows live proof.",
      ],
      showRetry: true,
    };
  }

  if (aliasWarnings.length > 0) {
    return {
      tone: "warning",
      title: "Compatibility rollout env names are still in use",
      message: `This build still reads ${aliasWarnings.join(", ")}. QA and UAT can continue, but new work should use the NEXT_PUBLIC_FEATURE_* names so rollout behavior stays predictable and fail-safe.`,
      guidance: [
        "Compatibility aliases do not widen support beyond the support matrix, but they should be retired from active configs.",
        "Alias cleanup is a config hygiene warning only; it is not evidence for payment, waiting-list, or benefits runtime readiness.",
      ],
      showRetry: false,
    };
  }

  return null;
}
