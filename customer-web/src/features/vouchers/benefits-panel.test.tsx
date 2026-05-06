import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { BenefitsPanel } from "./benefits-panel";

const mocks = vi.hoisted(() => ({
  getBenefitsPreview: vi.fn(),
  applyVoucher: vi.fn(),
  removeVoucher: vi.fn(),
  redeemLoyaltyPoints: vi.fn(),
  releaseLoyaltyPoints: vi.fn(),
  toastSuccess: vi.fn(),
  customerWebRollout: {
    accountBenefits: {
      enabled: false,
      description: "Keep loyalty and voucher data contract-visible behind an explicit rollout flag.",
      liveProofSummary:
        "Loyalty, vouchers, reservation benefits preview, and row-versioned voucher or loyalty mutations have live proof behind the account-benefits rollout flag.",
      disabledTitle: "Benefits are not in this rollout",
      disabledDescription:
        "Loyalty and vouchers stay off by default. Enable the account-benefits rollout flag only for a deliberate QA or Wave 2 pass.",
    },
  },
}));

vi.mock("./api", () => ({
  getBenefitsPreview: mocks.getBenefitsPreview,
  applyVoucher: mocks.applyVoucher,
  removeVoucher: mocks.removeVoucher,
  redeemLoyaltyPoints: mocks.redeemLoyaltyPoints,
  releaseLoyaltyPoints: mocks.releaseLoyaltyPoints,
}));

vi.mock("sonner", () => ({
  toast: {
    success: mocks.toastSuccess,
  },
}));

vi.mock("@/lib/config/feature-flags", () => ({
  customerWebRollout: mocks.customerWebRollout,
}));

function createPreview() {
  return {
    reservation: {
      reservation_id: 7,
      reservation_code: "RSV-7",
      user_id: 12,
      status: "Reserved",
      row_version: 4,
      bill: {
        subtotal_amount: "80.00",
        manual_discount_amount: "0.00",
        loyalty_discount_amount: "5.00",
        discount_amount: "5.00",
        payable_amount: "75.00",
        currency: "USD",
      },
      loyalty: {
        enabled: true,
        available_points: 120,
        redeemed_points: 0,
        discount_amount: 5,
        redeem_amount_per_point: "1.00",
        earn_amount_per_point: "1.00",
        min_redeem_points: 10,
        max_redeemable_points: 50,
        earn_preview_points: 7,
        earned_points_current: 0,
        can_redeem: false,
        can_release: false,
      },
      user: null,
    },
    available_vouchers: [
      {
        user_voucher_id: 61,
        voucher_id: 12,
        voucher_code: "FIX10",
        description: "10 dollars off",
        discount_type: "FixedAmount",
        discount_value: "10.00",
        min_spend: "100.00",
        free_item: null,
        assigned_at: "2026-04-01T08:00:00Z",
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
        preview_subtotal_amount: "80.00",
        preview_currency: "USD",
        can_apply: false,
        applicability_reason_codes: ["min_spend_not_met"],
        applicability_reasons: ["Spend more before this voucher can apply."],
      },
    ],
  };
}

function renderPanel() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <BenefitsPanel reservationId={7} />
    </QueryClientProvider>,
  );
}

describe("BenefitsPanel", () => {
  beforeEach(() => {
    mocks.getBenefitsPreview.mockReset();
    mocks.applyVoucher.mockReset();
    mocks.removeVoucher.mockReset();
    mocks.redeemLoyaltyPoints.mockReset();
    mocks.releaseLoyaltyPoints.mockReset();
    mocks.toastSuccess.mockReset();
    mocks.customerWebRollout.accountBenefits.enabled = false;
  });

  it("renders a gated placeholder when the benefits rollout flag is off", async () => {
    renderPanel();

    expect(screen.getByText("Benefits are not in this rollout")).toBeInTheDocument();
    expect(screen.getByText(/wave 2 pass/i)).toBeInTheDocument();
    expect(mocks.getBenefitsPreview).not.toHaveBeenCalled();
  });

  it("renders reservation benefits actions plus voucher contract metadata when the rollout flag is on", async () => {
    mocks.customerWebRollout.accountBenefits.enabled = true;
    mocks.getBenefitsPreview.mockResolvedValue(createPreview());

    renderPanel();

    expect(await screen.findByText("Ưu đãi đã sẵn sàng")).toBeInTheDocument();
    expect(await screen.findByText("Ưu đãi đang hiển thị")).toBeInTheDocument();
    expect(screen.getByText(/áp dụng hoặc gỡ ưu đãi/i)).toBeInTheDocument();
    expect(screen.getAllByText("Voucher chưa đủ điều kiện").length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText("Chưa đủ điều kiện").length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText("Spend more before this voucher can apply.")).toBeInTheDocument();
    expect(screen.getByText(/Đơn tối thiểu/i)).toBeInTheDocument();
    expect(screen.getAllByText(/Giảm dự kiến/i).length).toBeGreaterThanOrEqual(2);
  });

  it("shows loyalty guidance when the customer does not yet have enough redeemable points", async () => {
    const preview = createPreview();

    preview.reservation.loyalty.available_points = 8;
    preview.reservation.loyalty.max_redeemable_points = 0;
    mocks.customerWebRollout.accountBenefits.enabled = true;
    mocks.getBenefitsPreview.mockResolvedValue(preview);

    renderPanel();

    expect(await screen.findByText("Cần ít nhất 10 điểm. Hiện tài khoản có 8 điểm.")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Dùng điểm" })).not.toBeInTheDocument();
  });

  it("applies vouchers with the latest reservation row version and refetches benefits", async () => {
    const user = userEvent.setup();
    const preview = createPreview();

    preview.available_vouchers[0].can_apply = true;
    preview.available_vouchers[0].is_usable_now = true;
    mocks.customerWebRollout.accountBenefits.enabled = true;
    mocks.getBenefitsPreview.mockResolvedValue(preview);
    mocks.applyVoucher.mockResolvedValue({
      reservation: {
        ...preview.reservation,
        row_version: 5,
      },
      available_vouchers: [
        {
          ...preview.available_vouchers[0],
          is_currently_applied: true,
        },
      ],
    });

    renderPanel();

    await user.click(await screen.findByRole("button", { name: "Áp dụng voucher" }));

    await waitFor(() => {
      expect(mocks.applyVoucher).toHaveBeenCalledWith(7, 4, "FIX10");
    });
    expect(mocks.toastSuccess).toHaveBeenCalledWith("Đã áp dụng voucher.");
  });

  it("redeems and releases loyalty points with row-versioned mutations", async () => {
    const user = userEvent.setup();
    const preview = createPreview();

    preview.reservation.loyalty.can_redeem = true;
    preview.reservation.loyalty.can_release = true;
    preview.reservation.loyalty.max_redeemable_points = 50;
    const refreshedPreview = {
      ...preview,
      reservation: {
        ...preview.reservation,
        row_version: 5,
        loyalty: {
          ...preview.reservation.loyalty,
          can_redeem: false,
          can_release: true,
          redeemed_points: 25,
        },
      },
    };
    mocks.customerWebRollout.accountBenefits.enabled = true;
    mocks.getBenefitsPreview.mockResolvedValueOnce(preview).mockResolvedValue(refreshedPreview);
    mocks.redeemLoyaltyPoints.mockResolvedValue({
      reservation: {
        ...preview.reservation,
        row_version: 5,
        loyalty: {
          ...preview.reservation.loyalty,
          can_redeem: false,
          can_release: true,
          redeemed_points: 25,
        },
      },
      transactions: [],
    });
    mocks.releaseLoyaltyPoints.mockResolvedValue({
      reservation: {
        ...preview.reservation,
        row_version: 6,
        loyalty: {
          ...preview.reservation.loyalty,
          can_redeem: true,
          can_release: false,
          redeemed_points: 0,
        },
      },
      transactions: [],
    });

    renderPanel();

    expect(await screen.findByText("Có thể dùng từ 10 đến 50 điểm cho lượt đặt này.")).toBeInTheDocument();
    await user.clear(await screen.findByLabelText("Số điểm muốn dùng"));
    await user.type(screen.getByLabelText("Số điểm muốn dùng"), "25");
    await user.click(screen.getByRole("button", { name: "Dùng điểm" }));

    await waitFor(() => {
      expect(mocks.redeemLoyaltyPoints).toHaveBeenCalledWith(7, 4, 25);
    });

    await user.click(await screen.findByRole("button", { name: "Gỡ điểm" }));

    await waitFor(() => {
      expect(mocks.releaseLoyaltyPoints).toHaveBeenCalledWith(7, 5);
    });
  });

  it("refetches benefits when a row-versioned action reports a stale write", async () => {
    const user = userEvent.setup();
    const preview = createPreview();

    preview.available_vouchers[0].can_apply = true;
    preview.available_vouchers[0].is_usable_now = true;
    mocks.customerWebRollout.accountBenefits.enabled = true;
    mocks.getBenefitsPreview.mockResolvedValue(preview);
    mocks.applyVoucher.mockRejectedValue({
      kind: "validation",
      status: 422,
      message: "Validation error.",
      errorCode: "stale_row_version",
      categoryCode: "stale_write",
      requestId: "req-benefits-stale",
      validationErrors: {
        row_version: ["Changed."],
      },
    });

    renderPanel();

    await user.click(await screen.findByRole("button", { name: "Áp dụng voucher" }));

    await waitFor(() => {
      expect(mocks.getBenefitsPreview.mock.calls.length).toBeGreaterThanOrEqual(2);
    });
    expect(await screen.findByText("Thông tin ưu đãi đã thay đổi")).toBeInTheDocument();
  });
});
