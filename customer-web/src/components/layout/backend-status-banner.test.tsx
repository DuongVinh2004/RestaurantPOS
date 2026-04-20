import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { BackendStatusBanner } from "./backend-status-banner";

const mocks = vi.hoisted(() => ({
  featureFlags: {
    showDevBackendStatus: true,
  },
  customerWebRollout: {
    devMockAdapter: {
      liveProofSummary: "Mock adapters are local or UAT-only diagnostics and never count as production-ready runtime proof.",
    },
    depositSelfPay: {
      liveProofSummary:
        "Deposit self-pay is live-ready against the backend contract. Current local UAT proof uses the simulated provider, so production PSP configuration remains a separate release prerequisite.",
    },
  },
  runtimeDiagnostics: {
    baseUrl: "http://127.0.0.1:8000",
    usingDevMocks: false,
    hasCustomerToken: false,
    sessionId: null,
  },
  publicEnvDiagnostics: {
    rolloutFlagsUsingAliases: [],
  },
  getApiBaseUrlRuntimeDiagnostics: vi.fn(),
  checkBackendHealth: vi.fn(),
}));

vi.mock("@/lib/config/feature-flags", () => ({
  featureFlags: mocks.featureFlags,
  customerWebRollout: mocks.customerWebRollout,
}));

vi.mock("@/lib/config/env", () => ({
  publicEnvDiagnostics: mocks.publicEnvDiagnostics,
  getApiBaseUrlRuntimeDiagnostics: mocks.getApiBaseUrlRuntimeDiagnostics,
}));

vi.mock("@/lib/api/sdk-client", () => ({
  checkBackendHealth: mocks.checkBackendHealth,
  getApiRuntimeDiagnostics: () => mocks.runtimeDiagnostics,
}));

function renderBanner() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <BackendStatusBanner />
    </QueryClientProvider>,
  );
}

describe("BackendStatusBanner", () => {
  beforeEach(() => {
    mocks.featureFlags.showDevBackendStatus = true;
    mocks.runtimeDiagnostics.baseUrl = "http://127.0.0.1:8000";
    mocks.runtimeDiagnostics.usingDevMocks = false;
    mocks.publicEnvDiagnostics.rolloutFlagsUsingAliases = [];
    mocks.getApiBaseUrlRuntimeDiagnostics.mockReset();
    mocks.getApiBaseUrlRuntimeDiagnostics.mockReturnValue({
      apiHost: "127.0.0.1",
      appHost: "localhost",
      apiLooksLocal: true,
      appLooksLocal: true,
      likelyWrongForCurrentHost: false,
    });
    mocks.checkBackendHealth.mockReset();
    mocks.checkBackendHealth.mockResolvedValue({
      ok: true,
      status: 200,
      requestId: "req-health-ok",
      checkedUrl: "http://127.0.0.1:8000/api/v1/health",
      baseUrl: "http://127.0.0.1:8000",
      usingDevMocks: false,
    });
  });

  it("stays hidden when backend diagnostics are disabled", () => {
    mocks.featureFlags.showDevBackendStatus = false;

    renderBanner();

    expect(screen.queryByText("Live API is not reachable")).not.toBeInTheDocument();
    expect(mocks.checkBackendHealth).not.toHaveBeenCalled();
  });

  it("shows a QA warning when local mock mode is enabled", async () => {
    mocks.runtimeDiagnostics.usingDevMocks = true;

    renderBanner();

    expect(await screen.findByText("Local mock mode is on")).toBeInTheDocument();
    expect(screen.getByText(/mock responses instead of the live API/i)).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Retry" })).not.toBeInTheDocument();
  });

  it("shows an operator warning when the API base URL points at a local runtime from a non-local host", async () => {
    mocks.getApiBaseUrlRuntimeDiagnostics.mockReturnValue({
      apiHost: "127.0.0.1",
      appHost: "uat.customer-web.example",
      apiLooksLocal: true,
      appLooksLocal: false,
      likelyWrongForCurrentHost: true,
    });

    renderBanner();

    expect(await screen.findByText("API base URL looks wrong for this environment")).toBeInTheDocument();
    expect(screen.getByText(/NEXT_PUBLIC_API_BASE_URL still points to http:\/\/127\.0\.0\.1:8000/i)).toBeInTheDocument();
  });

  it("shows a retryable banner when the health probe reports a backend failure", async () => {
    const user = userEvent.setup();

    mocks.checkBackendHealth.mockResolvedValue({
      ok: false,
      status: 503,
      requestId: "req-health-1",
      checkedUrl: "http://127.0.0.1:8000/api/v1/health",
      baseUrl: "http://127.0.0.1:8000",
      usingDevMocks: false,
    });

    renderBanner();

    expect(await screen.findByText("Live API is not reachable")).toBeInTheDocument();
    expect(screen.getByText("Status 503")).toBeInTheDocument();
    expect(screen.getByText("Request ID: req-health-1")).toBeInTheDocument();
    expect(screen.getByText("http://127.0.0.1:8000/api/v1/health")).toBeInTheDocument();
    expect(screen.getByText(/browser only checked the health URL/i)).toBeInTheDocument();
    expect(screen.queryByText(/fresh canonical UAT pack/i)).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Retry" }));

    await waitFor(() => {
      expect(mocks.checkBackendHealth).toHaveBeenCalledTimes(2);
    });
  });
});
