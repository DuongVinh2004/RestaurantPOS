import type { SurfaceRolloutDecision } from "@/lib/config/support-matrix";
import type { CustomerLoyaltySummary, CustomerReservationBenefitsPreview, CustomerVoucher } from "@/lib/contracts/generated/restaurantpos-sdk";

export type BenefitAvailabilityState = "available" | "unavailable" | "expired" | "not_eligible" | "gated" | "empty";

export type BenefitsVisibilityState = {
  state: "gated" | "contract_visible";
  badgeLabel: string;
  title: string;
  description: string;
};

export type LoyaltyAccountState = {
  state: Extract<BenefitAvailabilityState, "available" | "empty">;
  title: string;
  description: string;
  totalPoints: number;
  transactionTitle: string;
  transactionDescription: string;
  tierLabel: string | null;
  nextTierLabel: string | null;
};

export type VoucherWalletItemState = {
  voucher: CustomerVoucher;
  state: Extract<BenefitAvailabilityState, "available" | "unavailable" | "expired" | "not_eligible">;
  badgeLabel: string;
  title: string;
  description: string;
};

export type VoucherWalletState = {
  state: BenefitAvailabilityState;
  title: string;
  description: string;
  summary: string;
  counts: {
    available: number;
    unavailable: number;
    expired: number;
    notEligible: number;
  };
  items: VoucherWalletItemState[];
};

export type ReservationBenefitsState = {
  state: Extract<BenefitAvailabilityState, "available" | "expired" | "not_eligible" | "empty">;
  title: string;
  description: string;
  actionTitle: string;
  actionDescription: string;
  loyaltyTitle: string;
  loyaltyDescription: string;
  voucherTitle: string;
  voucherDescription: string;
  hasVisibleLoyalty: boolean;
  hasVisibleVouchers: boolean;
  voucherWallet: VoucherWalletState;
};

export function getBenefitsVisibilityState(rollout: SurfaceRolloutDecision): BenefitsVisibilityState {
  if (!rollout.enabled) {
    return {
      state: "gated",
      badgeLabel: "Chưa bật",
      title: rollout.disabledTitle,
      description: rollout.disabledDescription,
    };
  }

  return {
    state: "contract_visible",
    badgeLabel: "Đã bật",
    title: "Ưu đãi đã sẵn sàng",
    description: "Bạn có thể xem điểm thưởng, voucher và các ưu đãi áp dụng cho lịch đặt.",
  };
}

export function getLoyaltyAccountState(summary: CustomerLoyaltySummary): LoyaltyAccountState {
  const totalPoints = summary.user.total_points;
  const hasTransactions = summary.transactions.length > 0;
  const tierLabel = summary.user.current_tier ? `Hạng ${summary.user.current_tier.tier_name}` : null;
  const nextTier =
    summary.user.next_tier && typeof summary.user.next_tier.points_to_unlock === "number"
      ? `Cần ${summary.user.next_tier.points_to_unlock} điểm để mở hạng ${summary.user.next_tier.tier_name}.`
      : null;

  if (totalPoints <= 0 && !hasTransactions) {
    return {
      state: "empty",
      title: "Chưa có điểm thưởng",
      description: "Tài khoản này chưa có điểm thưởng hoặc hoạt động điểm gần đây.",
      totalPoints,
      transactionTitle: "Chưa có hoạt động điểm",
      transactionDescription: "Các giao dịch điểm gần đây sẽ hiển thị sau khi nhà hàng ghi nhận.",
      tierLabel,
      nextTierLabel: nextTier,
    };
  }

  return {
    state: "available",
    title: tierLabel ? `${tierLabel} đang hiển thị` : "Đã có điểm thưởng",
    description: nextTier ?? "Điểm thưởng và hoạt động gần đây đang hiển thị cho tài khoản này.",
    totalPoints,
    transactionTitle: hasTransactions ? "Hoạt động điểm gần đây" : "Chưa có hoạt động gần đây",
    transactionDescription: hasTransactions
      ? "Các thay đổi điểm gần đây đang hiển thị tại đây."
      : "Bạn có điểm thưởng, nhưng chưa có giao dịch gần đây để xem.",
    tierLabel,
    nextTierLabel: nextTier,
  };
}

export function getVoucherWalletState(vouchers: CustomerVoucher[]): VoucherWalletState {
  if (vouchers.length === 0) {
    return {
      state: "empty",
      title: "Chưa có voucher",
      description: "Tài khoản này hiện chưa có voucher để xem.",
      summary: "Chưa có ưu đãi",
      counts: {
        available: 0,
        unavailable: 0,
        expired: 0,
        notEligible: 0,
      },
      items: [],
    };
  }

  const items = vouchers.map(getVoucherWalletItemState);
  const counts = items.reduce(
    (accumulator, item) => {
      if (item.state === "available") accumulator.available += 1;
      if (item.state === "unavailable") accumulator.unavailable += 1;
      if (item.state === "expired") accumulator.expired += 1;
      if (item.state === "not_eligible") accumulator.notEligible += 1;

      return accumulator;
    },
    {
      available: 0,
      unavailable: 0,
      expired: 0,
      notEligible: 0,
    },
  );

  if (counts.available > 0) {
    return {
      state: "available",
      title: "Ví voucher đang hiển thị",
      description: "Các voucher của tài khoản đang hiển thị tại đây.",
      summary: summarizeVoucherCounts(counts),
      counts,
      items,
    };
  }

  if (counts.notEligible > 0 && counts.expired === 0 && counts.unavailable === 0) {
    return {
      state: "not_eligible",
      title: "Voucher chưa đủ điều kiện",
      description: "Voucher đang hiển thị, nhưng hiện chưa thể dùng cho lượt ghé này.",
      summary: summarizeVoucherCounts(counts),
      counts,
      items,
    };
  }

  if (counts.expired > 0 && counts.available === 0 && counts.notEligible === 0 && counts.unavailable === 0) {
    return {
      state: "expired",
      title: "Chỉ còn voucher hết hạn",
      description: "Các voucher này vẫn hiển thị để tham khảo, nhưng đã hết hạn.",
      summary: summarizeVoucherCounts(counts),
      counts,
      items,
    };
  }

  return {
    state: "unavailable",
    title: "Chưa có voucher dùng được",
    description: "Voucher đang hiển thị, nhưng chưa có voucher nào dùng được ngay.",
    summary: summarizeVoucherCounts(counts),
    counts,
    items,
  };
}

export function getReservationBenefitsState(preview: CustomerReservationBenefitsPreview): ReservationBenefitsState {
  const voucherWallet = getVoucherWalletState(preview.available_vouchers);
  const loyalty = preview.reservation.loyalty;
  const hasVisibleLoyalty = loyalty.enabled;
  const hasVisibleVouchers = preview.available_vouchers.length > 0;
  const canReviewLoyalty = loyalty.available_points > 0 || loyalty.redeemed_points > 0 || loyalty.earned_points_current > 0;
  const loyaltyState: Extract<BenefitAvailabilityState, "available" | "not_eligible" | "empty"> =
    !loyalty.enabled ? "empty" : loyalty.can_redeem || loyalty.can_release || canReviewLoyalty ? "available" : "not_eligible";

  if (!hasVisibleLoyalty && !hasVisibleVouchers) {
    return {
      state: "empty",
      title: "Chưa có ưu đãi",
      description: "Hiện chưa có điểm thưởng hoặc voucher cho lịch đặt này.",
      actionTitle: "Ưu đãi chưa cần thao tác",
      actionDescription: "Nhà hàng chưa bật thao tác ưu đãi cho lịch đặt này.",
      loyaltyTitle: "Chưa có điểm thưởng áp dụng",
      loyaltyDescription: "Lịch đặt này chưa có ưu đãi điểm thưởng đang hoạt động.",
      voucherTitle: voucherWallet.title,
      voucherDescription: voucherWallet.description,
      hasVisibleLoyalty,
      hasVisibleVouchers,
      voucherWallet,
    };
  }

  const state =
    loyaltyState === "available" || voucherWallet.state === "available"
      ? "available"
      : voucherWallet.state === "expired"
        ? "expired"
        : "not_eligible";

  return {
    state,
    title:
      state === "available"
        ? "Ưu đãi đang hiển thị"
        : state === "expired"
          ? "Chỉ còn ưu đãi hết hạn"
          : "Ưu đãi chưa đủ điều kiện",
    description:
      state === "available"
        ? "Điểm thưởng và voucher đang hiển thị cho lịch đặt này."
        : state === "expired"
          ? "Lịch đặt vẫn hiển thị lịch sử ưu đãi, nhưng chỉ còn voucher đã hết hạn."
          : "Ưu đãi đang hiển thị, nhưng hiện chưa có thao tác khách hàng có thể làm.",
    actionTitle: "Thao tác ưu đãi",
    actionDescription: "Bạn chỉ nên áp dụng hoặc gỡ ưu đãi khi nhà hàng cho phép trên lịch đặt này.",
    loyaltyTitle:
      loyaltyState === "available"
        ? "Điểm thưởng đang hiển thị"
        : loyaltyState === "not_eligible"
          ? "Điểm thưởng chưa đủ điều kiện"
          : "Chưa có điểm thưởng áp dụng",
    loyaltyDescription:
      loyaltyState === "available"
        ? loyalty.can_redeem || loyalty.can_release
          ? "Bạn có thể thao tác điểm thưởng cho lịch đặt này."
          : "Bạn có thể xem điểm thưởng, nhưng hiện chưa có thao tác đổi hoặc gỡ điểm."
        : loyaltyState === "not_eligible"
          ? "Điểm thưởng đang hiển thị, nhưng hiện chưa thể dùng cho lịch đặt này."
          : "Lịch đặt này chưa có ưu đãi điểm thưởng đang hoạt động.",
    voucherTitle: voucherWallet.title,
    voucherDescription: voucherWallet.description,
    hasVisibleLoyalty,
    hasVisibleVouchers,
    voucherWallet,
  };
}

export function getVoucherWalletItemState(voucher: CustomerVoucher): VoucherWalletItemState {
  if (isVoucherExpired(voucher)) {
    return {
      voucher,
      state: "expired",
      badgeLabel: "Hết hạn",
      title: "Đã hết hạn",
      description: voucher.expires_at
        ? `Voucher này đã hết hạn lúc ${voucher.expires_at}.`
        : "Voucher này chỉ còn để tham khảo vì đã hết hạn.",
    };
  }

  if (voucher.is_used || voucher.used_at || containsStatus(voucher.current_status, "used")) {
    return {
      voucher,
      state: "unavailable",
      badgeLabel: "Không khả dụng",
      title: "Đã dùng",
      description: voucher.used_reservation_id
        ? `Voucher này đã được dùng cho lịch đặt #${voucher.used_reservation_id}.`
        : "Voucher này đã được dùng và không còn khả dụng.",
    };
  }

  if (voucher.is_locked_by_other) {
    return {
      voucher,
      state: "unavailable",
      badgeLabel: "Không khả dụng",
      title: "Đang giữ cho lịch đặt khác",
      description: "Voucher này đang được giữ cho một lịch đặt khác.",
    };
  }

  if (voucher.is_locked) {
    return {
      voucher,
      state: "unavailable",
      badgeLabel: "Không khả dụng",
      title: "Đang tạm giữ",
      description: voucher.locked_until
        ? `Voucher này đang được giữ đến ${voucher.locked_until}.`
        : "Voucher này đang được tạm giữ.",
    };
  }

  if (voucher.is_currently_applied) {
    return {
      voucher,
      state: "available",
      badgeLabel: "Có thể dùng",
      title: "Đã áp dụng",
      description: "Voucher này đang được áp dụng cho lịch đặt và có thể gỡ khi nhà hàng cho phép.",
    };
  }

  if (voucher.can_apply || voucher.is_usable_now) {
    return {
      voucher,
      state: "available",
      badgeLabel: "Có thể dùng",
      title: "Có thể dùng",
      description: "Voucher này hiện đủ điều kiện để dùng.",
    };
  }

  return {
    voucher,
    state: "not_eligible",
    badgeLabel: "Chưa đủ điều kiện",
    title: "Chưa đủ điều kiện",
    description:
      voucher.applicability_reasons.find((reason) => reason.trim() !== "") ??
      "Voucher này đang hiển thị trong ví, nhưng hiện chưa thể dùng.",
  };
}

function summarizeVoucherCounts(counts: VoucherWalletState["counts"]): string {
  const parts = [
    counts.available > 0 ? `${counts.available} có thể dùng` : null,
    counts.notEligible > 0 ? `${counts.notEligible} chưa đủ điều kiện` : null,
    counts.expired > 0 ? `${counts.expired} hết hạn` : null,
    counts.unavailable > 0 ? `${counts.unavailable} không khả dụng` : null,
  ].filter((value): value is string => value !== null);

  return parts.length > 0 ? parts.join(" - ") : "Chưa có ưu đãi";
}

function isVoucherExpired(voucher: CustomerVoucher): boolean {
  if (containsStatus(voucher.current_status, "expired")) {
    return true;
  }

  if (!voucher.expires_at) {
    return false;
  }

  const timestamp = Date.parse(voucher.expires_at);

  return Number.isFinite(timestamp) && timestamp <= Date.now();
}

function containsStatus(status: string | null | undefined, fragment: string): boolean {
  return status?.toLowerCase().includes(fragment.toLowerCase()) ?? false;
}
