import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { PrivacyPanel } from "./privacy-panel";

const mocks = vi.hoisted(() => ({
  customerWebRollout: {
    privacyRequests: {
      enabled: false,
      disabledTitle: "Công cụ dữ liệu cá nhân chưa được bật",
      disabledDescription:
        "Yêu cầu dữ liệu cá nhân mặc định đang tắt và chỉ mở trong rollout QA, UAT hoặc Wave 2 riêng.",
    },
    dataExport: {
      enabled: false,
      disabledTitle: "Xuất dữ liệu chưa được bật",
      disabledDescription:
        "Xuất dữ liệu sẽ tắt cho đến khi rollout quyền riêng tư rộng hơn sẵn sàng. Chỉ bật khi QA hoặc UAT cần kiểm chứng xuất dữ liệu.",
    },
  },
  listPrivacyRequests: vi.fn(),
  getDataExport: vi.fn(),
  createPrivacyRequest: vi.fn(),
}));

vi.mock("@/lib/config/feature-flags", () => ({
  customerWebRollout: mocks.customerWebRollout,
}));

vi.mock("./api", () => ({
  listPrivacyRequests: mocks.listPrivacyRequests,
  getDataExport: mocks.getDataExport,
  createPrivacyRequest: mocks.createPrivacyRequest,
}));

function renderPanel() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
      mutations: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <PrivacyPanel />
    </QueryClientProvider>,
  );
}

describe("PrivacyPanel", () => {
  beforeEach(() => {
    mocks.customerWebRollout.privacyRequests.enabled = false;
    mocks.customerWebRollout.dataExport.enabled = false;
    mocks.listPrivacyRequests.mockReset();
    mocks.getDataExport.mockReset();
    mocks.createPrivacyRequest.mockReset();
  });

  it("renders a disabled rollout state when privacy tools are off", () => {
    renderPanel();

    expect(screen.getByText("Công cụ dữ liệu cá nhân chưa được bật")).toBeInTheDocument();
    expect(screen.getByText(/QA, UAT hoặc Wave 2 riêng/i)).toBeInTheDocument();
    expect(mocks.listPrivacyRequests).not.toHaveBeenCalled();
    expect(mocks.getDataExport).not.toHaveBeenCalled();
  });

  it("keeps data export disabled while allowing privacy requests when only privacy tools are enabled", async () => {
    mocks.customerWebRollout.privacyRequests.enabled = true;
    mocks.listPrivacyRequests.mockResolvedValue([]);

    renderPanel();

    await waitFor(() => {
      expect(mocks.listPrivacyRequests).toHaveBeenCalledTimes(1);
    });

    expect(await screen.findByText("Xuất dữ liệu chưa được bật")).toBeInTheDocument();
    expect(screen.getByText("Yêu cầu ẩn danh hóa")).toBeInTheDocument();
    expect(screen.getByText(/Dữ liệu thanh toán, hóa đơn, audit và tranh chấp có thể được giữ lại/i)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Yêu cầu xem xét ẩn danh hóa" })).toBeInTheDocument();
    expect(mocks.getDataExport).not.toHaveBeenCalled();
  });

  it("loads data export only when both privacy and data export flags are enabled", async () => {
    mocks.customerWebRollout.privacyRequests.enabled = true;
    mocks.customerWebRollout.dataExport.enabled = true;
    mocks.listPrivacyRequests.mockResolvedValue([]);
    mocks.getDataExport.mockResolvedValue({
      customer: {
        user_id: 7,
      },
    });

    renderPanel();

    await waitFor(() => {
      expect(mocks.listPrivacyRequests).toHaveBeenCalledTimes(1);
      expect(mocks.getDataExport).toHaveBeenCalledTimes(1);
    });

    expect(await screen.findByText("Bản xuất dữ liệu khách hàng mới nhất đã có từ API quyền riêng tư.")).toBeInTheDocument();
  });

  it("submits anonymization requests and refreshes the request lifecycle", async () => {
    const user = userEvent.setup();

    mocks.customerWebRollout.privacyRequests.enabled = true;
    mocks.listPrivacyRequests.mockResolvedValue([]);
    mocks.createPrivacyRequest.mockResolvedValue({
      request: {
        customer_privacy_request_id: 12,
        request_type: "anonymize",
        status: "requested",
        requested_at: "2026-04-19T10:00:00Z",
      },
      created: true,
    });

    renderPanel();

    await user.type(await screen.findByRole("textbox", { name: "Ghi chú tùy chọn" }), "Vui lòng ẩn danh hóa tài khoản của tôi.");
    await user.click(screen.getByRole("button", { name: "Yêu cầu xem xét ẩn danh hóa" }));

    await waitFor(() => {
      expect(mocks.createPrivacyRequest).toHaveBeenCalledWith("Vui lòng ẩn danh hóa tài khoản của tôi.");
      expect(mocks.listPrivacyRequests.mock.calls.length).toBeGreaterThanOrEqual(2);
    });
  });
});
