import { describe, expect, it } from "vitest";
import type { CustomerLoyaltySummary, CustomerReservationBenefitsPreview, CustomerVoucher } from "@/lib/contracts/generated/restaurantpos-sdk";
import { getLoyaltyAccountState, getReservationBenefitsState, getVoucherWalletItemState, getVoucherWalletState } from "./state";

function createVoucher(overrides: Partial<CustomerVoucher> = {}): CustomerVoucher {
  return {
    user_voucher_id: 1,
    voucher_id: 2,
    voucher_code: "SAVE10",
    description: "Save 10",
    discount_type: "FixedAmount",
    discount_value: "10.00",
    min_spend: "50.00",
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
    applicability_reason_codes: [],
    applicability_reasons: [],
    ...overrides,
  };
}

function createLoyaltySummary(overrides: Partial<CustomerLoyaltySummary> = {}): CustomerLoyaltySummary {
  return {
    user: {
      user_id: 7,
      full_name: "Demo Customer",
      email: null,
      phone: null,
      total_points: 0,
      current_tier: null,
      next_tier: null,
    },
    transactions: [],
    ...overrides,
  };
}

function createBenefitsPreview(overrides: Partial<CustomerReservationBenefitsPreview> = {}): CustomerReservationBenefitsPreview {
  return {
    reservation: {
      reservation_id: 7,
      reservation_code: "RSV-7",
      user_id: 4,
      status: "Reserved",
      row_version: 3,
      bill: {
        subtotal_amount: "100.00",
        manual_discount_amount: "0.00",
        loyalty_discount_amount: "0.00",
        discount_amount: "0.00",
        payable_amount: "100.00",
        currency: "USD",
      },
      loyalty: {
        enabled: false,
        available_points: 0,
        redeemed_points: 0,
        discount_amount: 0,
        redeem_amount_per_point: "1.00",
        earn_amount_per_point: "1.00",
        min_redeem_points: 10,
        max_redeemable_points: 0,
        earn_preview_points: 0,
        earned_points_current: 0,
        can_redeem: false,
        can_release: false,
      },
      user: null,
    },
    available_vouchers: [],
    ...overrides,
  };
}

describe("benefits state helpers", () => {
  it("treats an empty loyalty wallet as empty", () => {
    const state = getLoyaltyAccountState(createLoyaltySummary());

    expect(state.state).toBe("empty");
    expect(state.title).toBe("Chưa có điểm thưởng");
  });

  it("classifies wallet vouchers as not eligible from applicability reasons", () => {
    const item = getVoucherWalletItemState(
      createVoucher({
        can_apply: false,
        is_usable_now: false,
        applicability_reasons: ["Spend more before this voucher can apply."],
      }),
    );

    expect(item.state).toBe("not_eligible");
    expect(item.description).toMatch(/spend more/i);
  });

  it("classifies an all-expired wallet as expired", () => {
    const wallet = getVoucherWalletState([
      createVoucher({
        current_status: "Expired",
        expires_at: "2026-04-01T09:00:00Z",
      }),
    ]);

    expect(wallet.state).toBe("expired");
    expect(wallet.title).toBe("Chỉ còn voucher hết hạn");
  });

  it("warns when an available voucher is close to expiry", () => {
    const item = getVoucherWalletItemState(
      createVoucher({
        can_apply: true,
        expires_at: new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toISOString(),
      }),
    );

    expect(item.state).toBe("available");
    expect(item.nearExpiry).toBe(true);
    expect(item.detailLines.join(" ")).toMatch(/Sắp hết hạn/i);
  });

  it("marks reservation benefits as visible but without forced fake actions", () => {
    const state = getReservationBenefitsState(
      createBenefitsPreview({
        reservation: {
          reservation_id: 7,
          reservation_code: "RSV-7",
          user_id: 4,
          status: "Reserved",
          row_version: 3,
          bill: {
            subtotal_amount: "100.00",
            manual_discount_amount: "0.00",
            loyalty_discount_amount: "5.00",
            discount_amount: "5.00",
            payable_amount: "95.00",
            currency: "USD",
          },
          loyalty: {
            enabled: true,
            available_points: 100,
            redeemed_points: 0,
            discount_amount: 5,
            redeem_amount_per_point: "1.00",
            earn_amount_per_point: "1.00",
            min_redeem_points: 10,
            max_redeemable_points: 50,
            earn_preview_points: 5,
            earned_points_current: 0,
            can_redeem: false,
            can_release: false,
          },
          user: null,
        },
        available_vouchers: [
          createVoucher({
            applicability_reasons: ["Spend more before this voucher can apply."],
          }),
        ],
      }),
    );

    expect(state.state).toBe("available");
    expect(state.actionTitle).toBe("Thao tác ưu đãi");
    expect(state.actionDescription).toMatch(/áp dụng hoặc gỡ ưu đãi/i);
    expect(state.voucherWallet.state).toBe("not_eligible");
  });
});
