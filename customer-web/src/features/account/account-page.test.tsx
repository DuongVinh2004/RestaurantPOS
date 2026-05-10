import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AccountPage } from "./account-page";

const mocks = vi.hoisted(() => ({
  getLoyalty: vi.fn(),
  listReservations: vi.fn(),
  listVouchers: vi.fn(),
  customerWebRollout: {
    accountBenefits: {
      enabled: false,
      disabledTitle: "Ưu đãi chưa được bật",
      disabledDescription:
        "Điểm thưởng và voucher chưa được bật mặc định. Chỉ bật cờ ưu đãi tài khoản cho QA hoặc Wave 2.",
    },
    privacyRequests: {
      enabled: false,
      disabledTitle: "Công cụ dữ liệu cá nhân chưa được bật",
      disabledDescription: "Yêu cầu dữ liệu cá nhân mặc định đang tắt và chỉ mở trong rollout riêng.",
    },
    dataExport: {
      enabled: false,
      disabledTitle: "Xuất dữ liệu chưa được bật",
      disabledDescription: "Xuất dữ liệu sẽ tắt cho đến khi rollout quyền riêng tư rộng hơn sẵn sàng.",
    },
  },
}));

vi.mock("@/providers/auth-provider", () => ({
  useAuth: () => ({
    profile: {
      userId: 7,
      name: "Demo Customer",
      email: "demo@example.com",
      phone: "555-0100",
    },
  }),
}));

vi.mock("./api", () => ({
  getLoyalty: mocks.getLoyalty,
}));

vi.mock("@/features/reservations/api", () => ({
  listReservations: mocks.listReservations,
}));

vi.mock("@/features/vouchers/api", () => ({
  listVouchers: mocks.listVouchers,
}));

vi.mock("@/features/privacy/privacy-panel", () => ({
  PrivacyPanel: () => <div data-testid="privacy-panel" />,
}));

vi.mock("@/lib/config/feature-flags", () => ({
  customerWebRollout: mocks.customerWebRollout,
}));

function renderAccountPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <AccountPage />
    </QueryClientProvider>,
  );
}

describe("AccountPage", () => {
  beforeEach(() => {
    mocks.getLoyalty.mockReset();
    mocks.listReservations.mockReset();
    mocks.listVouchers.mockReset();
    mocks.listReservations.mockResolvedValue([]);
    mocks.customerWebRollout.accountBenefits.enabled = false;
    mocks.customerWebRollout.privacyRequests.enabled = false;
    mocks.customerWebRollout.dataExport.enabled = false;
  });

  it("keeps benefits gated and avoids live loyalty or voucher queries when rollout is off", async () => {
    renderAccountPage();

    expect(screen.getByText("Tài khoản khách hàng của bạn")).toBeInTheDocument();
    expect(screen.getAllByText("Ưu đãi chưa được bật").length).toBeGreaterThanOrEqual(2);
    expect(screen.getByText("Tùy chọn nhận thông báo")).toBeInTheDocument();
    expect(screen.getByText("Góp ý sau bữa ăn")).toBeInTheDocument();
    expect(mocks.getLoyalty).not.toHaveBeenCalled();
    expect(mocks.listVouchers).not.toHaveBeenCalled();

    await waitFor(() => {
      expect(mocks.listReservations).toHaveBeenCalledWith("upcoming");
    });
  });

  it("renders loyalty and voucher wallet when account benefits are enabled", async () => {
    mocks.customerWebRollout.accountBenefits.enabled = true;
    mocks.getLoyalty.mockResolvedValue({
      user: {
        user_id: 7,
        full_name: "Demo Customer",
        email: null,
        phone: null,
        total_points: 120,
        current_tier: {
          tier_id: 1,
          tier_code: "BRONZE",
          tier_name: "Bronze",
          min_points: 0,
          points_to_unlock: null,
        },
        next_tier: {
          tier_id: 2,
          tier_code: "SILVER",
          tier_name: "Silver",
          min_points: 200,
          points_to_unlock: 80,
        },
      },
      transactions: [],
    });
    mocks.listVouchers.mockResolvedValue([
      {
        user_voucher_id: 31,
        voucher_id: 10,
        voucher_code: "SAVE10",
        description: "10% off",
        discount_type: "Percentage",
        discount_value: "10.00",
        min_spend: "50.00",
        free_item: null,
        assigned_at: "2026-04-01T09:00:00Z",
        used_at: null,
        used_reservation_id: null,
        starts_at: null,
        expires_at: null,
        is_used: false,
        current_status: "Active",
        is_usable_now: false,
        is_locked: false,
        is_locked_by_other: false,
        locked_until: null,
        row_version: null,
        is_currently_applied: false,
        preview_discount_amount: "10.00",
        preview_subtotal_amount: "100.00",
        preview_currency: "USD",
        can_apply: false,
        applicability_reason_codes: ["min_spend_not_met"],
        applicability_reasons: ["Spend more before this voucher can apply."],
      },
    ]);

    renderAccountPage();

    await waitFor(() => {
      expect(mocks.getLoyalty).toHaveBeenCalledTimes(1);
      expect(mocks.listVouchers).toHaveBeenCalledWith({ bucket: "all", per_page: 24 });
    });

    expect(await screen.findByText("Tóm tắt thành viên")).toBeInTheDocument();
    expect(screen.getByText("Hạng Bronze")).toBeInTheDocument();
    expect(screen.getAllByText("Cần thêm 80 điểm để mở hạng Silver.").length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText("Chưa đủ điều kiện").length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText("Spend more before this voucher can apply.")).toBeInTheDocument();
    expect(screen.getByText(/Chỉ áp dụng ở lịch đặt/i)).toBeInTheDocument();
    expect(screen.getByText(/Chi tiêu tối thiểu/i)).toBeInTheDocument();
    expect(screen.getByText(/Giảm dự kiến/i)).toBeInTheDocument();
  });
});
