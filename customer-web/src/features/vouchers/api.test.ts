import { beforeEach, describe, expect, it, vi } from "vitest";
import { applyVoucher, getBenefitsPreview, listVouchers, redeemLoyaltyPoints, releaseLoyaltyPoints, removeVoucher } from "./api";

const mocks = vi.hoisted(() => ({
  apiCall: vi.fn(),
  idempotentOptions: vi.fn(),
  getV1MeVouchers: vi.fn(),
  getV1ReservationsIdBenefitsPreview: vi.fn(),
  postV1ReservationsIdVoucherApply: vi.fn(),
  postV1ReservationsIdVoucherRemove: vi.fn(),
  postV1ReservationsIdLoyaltyRedeem: vi.fn(),
  postV1ReservationsIdLoyaltyRedeemRelease: vi.fn(),
}));

vi.mock("@/lib/api/sdk-client", () => ({
  apiCall: mocks.apiCall,
  idempotentOptions: mocks.idempotentOptions,
}));

describe("vouchers api adapter", () => {
  beforeEach(() => {
    mocks.apiCall.mockReset();
    mocks.idempotentOptions.mockReset();
    mocks.getV1MeVouchers.mockReset();
    mocks.getV1ReservationsIdBenefitsPreview.mockReset();
    mocks.postV1ReservationsIdVoucherApply.mockReset();
    mocks.postV1ReservationsIdVoucherRemove.mockReset();
    mocks.postV1ReservationsIdLoyaltyRedeem.mockReset();
    mocks.postV1ReservationsIdLoyaltyRedeemRelease.mockReset();

    mocks.idempotentOptions.mockImplementation((scope: string) => ({ idempotencyKey: `idem:${scope}` }));
    mocks.apiCall.mockImplementation(async (operation: (client: unknown) => unknown) =>
      operation({
        getV1MeVouchers: mocks.getV1MeVouchers,
        getV1ReservationsIdBenefitsPreview: mocks.getV1ReservationsIdBenefitsPreview,
        postV1ReservationsIdVoucherApply: mocks.postV1ReservationsIdVoucherApply,
        postV1ReservationsIdVoucherRemove: mocks.postV1ReservationsIdVoucherRemove,
        postV1ReservationsIdLoyaltyRedeem: mocks.postV1ReservationsIdLoyaltyRedeem,
        postV1ReservationsIdLoyaltyRedeemRelease: mocks.postV1ReservationsIdLoyaltyRedeemRelease,
      }),
    );
  });

  it("passes the contract voucher bucket filters and unwraps wallet rows", async () => {
    mocks.getV1MeVouchers.mockResolvedValue({
      data: [
        {
          user_voucher_id: 61,
          voucher_code: "FIX10",
          can_apply: false,
          is_usable_now: false,
          applicability_reason_codes: ["min_spend_not_met"],
          applicability_reasons: ["Spend more before this voucher can apply."],
        },
      ],
    });

    const result = await listVouchers({ bucket: "unused", q: "FIX", per_page: 10, page: 2 });

    expect(mocks.getV1MeVouchers).toHaveBeenCalledWith({ bucket: "unused", q: "FIX", per_page: 10, page: 2 });
    expect(result).toEqual([
      expect.objectContaining({
        user_voucher_id: 61,
        can_apply: false,
        is_usable_now: false,
        applicability_reason_codes: ["min_spend_not_met"],
        applicability_reasons: ["Spend more before this voucher can apply."],
      }),
    ]);
  });

  it("keeps reservation benefits preview applicability fields at the adapter boundary", async () => {
    mocks.getV1ReservationsIdBenefitsPreview.mockResolvedValue({
      data: {
        reservation: {
          reservation_id: 7,
          row_version: 4,
        },
        available_vouchers: [
          {
            user_voucher_id: 61,
            voucher_code: "FIX10",
            can_apply: true,
            is_usable_now: true,
            applicability_reason_codes: [],
            applicability_reasons: [],
          },
        ],
      },
    });

    const result = await getBenefitsPreview(7);

    expect(mocks.getV1ReservationsIdBenefitsPreview).toHaveBeenCalledWith({ id: 7 });
    expect(result.available_vouchers[0]).toEqual(
      expect.objectContaining({
        can_apply: true,
        is_usable_now: true,
        applicability_reason_codes: [],
        applicability_reasons: [],
      }),
    );
  });

  it("keeps voucher apply row-versioned and idempotent without a session contract", async () => {
    mocks.postV1ReservationsIdVoucherApply.mockResolvedValue({
      data: {
        reservation: {
          reservation_id: 7,
          row_version: 5,
        },
        available_vouchers: [],
      },
    });

    const result = await applyVoucher(7, 4, "FIX10");

    expect(mocks.idempotentOptions).toHaveBeenCalledWith("voucher-apply");
    expect(mocks.postV1ReservationsIdVoucherApply).toHaveBeenCalledWith(
      { id: 7 },
      { row_version: 4, voucher_code: "FIX10" },
      { idempotencyKey: "idem:voucher-apply" },
    );
    expect(result.reservation).toEqual({ reservation_id: 7, row_version: 5 });
  });

  it("keeps voucher remove row-versioned and idempotent without a session contract", async () => {
    mocks.postV1ReservationsIdVoucherRemove.mockResolvedValue({
      data: {
        reservation: {
          reservation_id: 7,
          row_version: 6,
        },
        available_vouchers: [],
      },
    });

    const result = await removeVoucher(7, 5);

    expect(mocks.idempotentOptions).toHaveBeenCalledWith("voucher-remove");
    expect(mocks.postV1ReservationsIdVoucherRemove).toHaveBeenCalledWith(
      { id: 7 },
      { row_version: 5 },
      { idempotencyKey: "idem:voucher-remove" },
    );
    expect(result.reservation).toEqual({ reservation_id: 7, row_version: 6 });
  });

  it("keeps loyalty redeem and release row-versioned and idempotent", async () => {
    mocks.postV1ReservationsIdLoyaltyRedeem.mockResolvedValue({
      data: {
        reservation: {
          reservation_id: 7,
          row_version: 7,
        },
        transactions: [],
      },
    });
    mocks.postV1ReservationsIdLoyaltyRedeemRelease.mockResolvedValue({
      data: {
        reservation: {
          reservation_id: 7,
          row_version: 8,
        },
        transactions: [],
      },
    });

    const redeem = await redeemLoyaltyPoints(7, 6, 25);
    const release = await releaseLoyaltyPoints(7, 7);

    expect(mocks.idempotentOptions).toHaveBeenCalledWith("loyalty-redeem");
    expect(mocks.postV1ReservationsIdLoyaltyRedeem).toHaveBeenCalledWith(
      { id: 7 },
      { row_version: 6, points: 25 },
      { idempotencyKey: "idem:loyalty-redeem" },
    );
    expect(mocks.idempotentOptions).toHaveBeenCalledWith("loyalty-release");
    expect(mocks.postV1ReservationsIdLoyaltyRedeemRelease).toHaveBeenCalledWith(
      { id: 7 },
      { row_version: 7 },
      { idempotencyKey: "idem:loyalty-release" },
    );
    expect(redeem.reservation).toEqual({ reservation_id: 7, row_version: 7 });
    expect(release.reservation).toEqual({ reservation_id: 7, row_version: 8 });
  });
});
