import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AccountPage } from "./account-page";

const mocks = vi.hoisted(() => ({
  getLoyalty: vi.fn(),
  listVouchers: vi.fn(),
  customerWebRollout: {
    accountBenefits: {
      enabled: false,
      description:
        "Keep loyalty and voucher data contract-visible behind an explicit rollout flag.",
      liveProofSummary:
        "Loyalty, vouchers, reservation benefits preview, and row-versioned voucher or loyalty mutations have live proof behind the account-benefits rollout flag.",
      disabledTitle: "Benefits are not in this rollout",
      disabledDescription:
        "Loyalty and vouchers stay off by default. Enable the account-benefits rollout flag only for a deliberate QA or Wave 2 pass.",
    },
    privacyRequests: {
      enabled: false,
      description: "Privacy request entry points stay disabled by default and only open when the privacy-tools flag is enabled.",
      disabledTitle: "Privacy tools are not in this rollout",
      disabledDescription:
        "Privacy requests stay off by default and only open during a dedicated QA, UAT, or Wave 2 rollout.",
    },
    dataExport: {
      enabled: false,
      description: "Data export remains an explicit Wave 2 extra and should never become a go-live dependency for booking core.",
      disabledTitle: "Data export is not in this rollout",
      disabledDescription:
        "Data export stays off until the broader privacy rollout is ready. Keep it disabled unless QA or UAT specifically needs export proof.",
    },
  },
}));

vi.mock("@/providers/auth-provider", () => ({
  useAuth: () => ({
    profile: {
      name: "Demo Customer",
    },
  }),
}));

vi.mock("./api", () => ({
  getLoyalty: mocks.getLoyalty,
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
    mocks.listVouchers.mockReset();
    mocks.customerWebRollout.accountBenefits.enabled = false;
    mocks.customerWebRollout.privacyRequests.enabled = false;
    mocks.customerWebRollout.dataExport.enabled = false;
  });

  it("keeps benefits gated and avoids live loyalty or voucher queries when the rollout is off", () => {
    renderAccountPage();

    expect(screen.getByText("Công cụ tài khoản")).toBeInTheDocument();
    expect(screen.getAllByText("Benefits are not in this rollout").length).toBeGreaterThanOrEqual(3);
    expect(screen.getAllByText(/enable the account-benefits rollout flag/i).length).toBeGreaterThanOrEqual(1);
    expect(mocks.getLoyalty).not.toHaveBeenCalled();
    expect(mocks.listVouchers).not.toHaveBeenCalled();
  });

  it("renders benefits as a gated contract shell when the rollout is enabled", async () => {
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
        min_spend: "0.00",
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

    expect(await screen.findByText("Ưu đãi đã sẵn sàng")).toBeInTheDocument();
    expect(screen.getByText(/Bạn có thể xem điểm thưởng, voucher/i)).toBeInTheDocument();
    expect(screen.getByText("Hạng Bronze đang hiển thị")).toBeInTheDocument();
    expect(screen.getAllByText("Voucher chưa đủ điều kiện").length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText("Chưa đủ điều kiện").length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText("Spend more before this voucher can apply.")).toBeInTheDocument();
    expect(screen.getByText(/Thao tác áp dụng hoặc gỡ voucher nằm trong chi tiết lịch đặt/i)).toBeInTheDocument();
  });
});
