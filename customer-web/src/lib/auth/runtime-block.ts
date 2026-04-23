import { classifySessionRestoreError, type SessionRestoreError } from "@/lib/api/errors";
import { getApiBaseUrlRuntimeDiagnostics } from "@/lib/config/env";

export function getCustomerAuthRuntimeBlock(apiBaseUrl: string, appHost: string | null | undefined): SessionRestoreError | null {
  const diagnostics = getApiBaseUrlRuntimeDiagnostics(apiBaseUrl, appHost);

  if (!diagnostics.likelyWrongForCurrentHost) {
    return null;
  }

  return classifySessionRestoreError({
    kind: "backend_unavailable",
    status: null,
    message: `This app is running on ${diagnostics.appHost}, but NEXT_PUBLIC_API_BASE_URL still points to ${apiBaseUrl}. Update the customer-web runtime configuration to the correct API host and reload the page.`,
    errorCode: "api_base_url_misconfigured",
    categoryCode: "api_base_url_misconfigured",
    requestId: null,
    validationErrors: null,
  });
}
