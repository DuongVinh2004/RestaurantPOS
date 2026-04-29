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
        "Deposit self-pay is contract-visible and runtime-conditional. Simulated-provider or local UAT proof does not make it part of the day-1 launch promise.",
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

    expect(screen.queryByText("Chưa kết nối được API")).not.toBeInTheDocument();
    expect(mocks.checkBackendHealth).not.toHaveBeenCalled();
  });

  it("shows a QA warning when local mock mode is enabled", async () => {
    mocks.runtimeDiagnostics.usingDevMocks = true;

    renderBanner();

    expect(await screen.findByText("Đang dùng dữ liệu mô phỏng")).toBeInTheDocument();
    expect(screen.getByText(/dữ liệu mô phỏng thay vì API thật/i)).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Thử lại" })).not.toBeInTheDocument();
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

    expect(await screen.findByText("Địa chỉ API có thể chưa đúng")).toBeInTheDocument();
    expect(screen.getByText(/địa chỉ API đang trỏ tới http:\/\/127\.0\.0\.1:8000/i)).toBeInTheDocument();
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

    expect(await screen.findByText("Chưa kết nối được API")).toBeInTheDocument();
    expect(screen.getByText("Trạng thái 503")).toBeInTheDocument();
    expect(screen.getByText("Mã hỗ trợ: req-health-1")).toBeInTheDocument();
    expect(screen.getByText("http://127.0.0.1:8000/api/v1/health")).toBeInTheDocument();
    expect(screen.getByText(/Trình duyệt chỉ kiểm tra đường dẫn sức khỏe/i)).toBeInTheDocument();
    expect(screen.queryByText(/fresh canonical UAT pack/i)).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Thử lại" }));

    await waitFor(() => {
      expect(mocks.checkBackendHealth).toHaveBeenCalledTimes(2);
    });
  });
});
