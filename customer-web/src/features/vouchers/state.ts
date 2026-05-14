import { formatDateTime, formatMoney } from "@/lib/contracts/format";
import type { SurfaceRolloutDecision } from "@/lib/config/support-matrix";
import type {
  CustomerLoyaltySummary,
  CustomerReservationBenefitsPreview,
  CustomerVoucher,
} from "@/lib/contracts/generated/restaurantpos-sdk";

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
  detailLines: string[];
  nearExpiry: boolean;
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
      badgeLabel: "Sắp ra mắt",
      title: rollout.disabledTitle,
      description: rollout.disabledDescription,
    };
  }

  return {
    state: "contract_visible",
    badgeLabel: "Đang có",
    title: "Ưu đãi đang có",
    description: "Bạn có thể xem điểm thưởng, voucher và ưu đãi áp dụng cho lịch đặt này.",
  };
}

export function getLoyaltyAccountState(summary: CustomerLoyaltySummary): LoyaltyAccountState {
  const totalPoints = summary.user.total_points;
  const hasTransactions = summary.transactions.length > 0;
  const tierLabel = summary.user.current_tier ? `Hạng ${summary.user.current_tier.tier_name}` : null;
  const nextTier =
    summary.user.next_tier && typeof summary.user.next_tier.points_to_unlock === "number"
      ? `Cần thêm ${summary.user.next_tier.points_to_unlock} điểm để mở hạng ${summary.user.next_tier.tier_name}.`
      : null;

  if (totalPoints <= 0 && !hasTransactions) {
    return {
      state: "empty",
      title: "Chưa có điểm thưởng",
      description: "Tài khoản này chưa có điểm thưởng hoặc hoạt động điểm gần đây.",
      totalPoints,
      transactionTitle: "Chưa có hoạt động điểm",
      transactionDescription: "Thay đổi điểm gần đây sẽ hiển thị sau khi nhà hàng ghi nhận.",
      tierLabel,
      nextTierLabel: nextTier,
    };
  }

  return {
    state: "available",
    title: tierLabel ? `${tierLabel} đang hoạt động` : "Đã có điểm thưởng",
    description: nextTier ?? "Điểm thưởng và hoạt động điểm gần đây đang khả dụng cho tài khoản này.",
    totalPoints,
    transactionTitle: hasTransactions ? "Hoạt động điểm gần đây" : "Chưa có hoạt động gần đây",
    transactionDescription: hasTransactions
      ? "Các thay đổi điểm gần đây hiển thị bên dưới."
      : "Tài khoản có điểm, nhưng chưa có giao dịch điểm gần đây.",
    tierLabel,
    nextTierLabel: nextTier,
  };
}

export function getVoucherWalletState(vouchers: CustomerVoucher[]): VoucherWalletState {
  if (vouchers.length === 0) {
    return {
      state: "empty",
      title: "Chưa có voucher",
      description: "Tài khoản này hiện chưa có voucher để hiển thị.",
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
      expired: 0,
      notEligible: 0,
      unavailable: 0,
    },
  );

  if (counts.available > 0) {
    return {
      state: "available",
      title: "Ví voucher",
      description: "Voucher dùng được và voucher chưa hoạt động được liệt kê bên dưới.",
      summary: summarizeVoucherCounts(counts),
      counts,
      items,
    };
  }

  if (counts.notEligible > 0 && counts.expired === 0 && counts.unavailable === 0) {
    return {
      state: "not_eligible",
      title: "Voucher cần thêm điều kiện",
      description: "Các voucher này đang hiển thị, nhưng chưa có voucher nào dùng được trong ngữ cảnh hiện tại.",
      summary: summarizeVoucherCounts(counts),
      counts,
      items,
    };
  }

  if (counts.expired > 0 && counts.available === 0 && counts.notEligible === 0 && counts.unavailable === 0) {
    return {
      state: "expired",
      title: "Chỉ còn voucher hết hạn",
      description: "Các voucher này chỉ hiển thị để tham khảo vì đã hết hạn.",
      summary: summarizeVoucherCounts(counts),
      counts,
      items,
    };
  }

  return {
    state: "unavailable",
    title: "Chưa có voucher dùng được",
    description: "Voucher đang hiển thị, nhưng hiện chưa có voucher nào dùng được.",
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
      actionTitle: "Chưa cần thao tác ưu đãi",
      actionDescription: "Bạn chưa cần áp dụng hoặc gỡ ưu đãi ở bước này.",
      loyaltyTitle: "Chưa có ưu đãi điểm thưởng",
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
        ? "Có ưu đãi khả dụng"
        : state === "expired"
          ? "Chỉ còn ưu đãi hết hạn"
          : "Ưu đãi cần thêm điều kiện",
    description:
      state === "available"
        ? "Điểm thưởng và voucher đang hiển thị cho lịch đặt này."
        : state === "expired"
          ? "Ưu đãi hết hạn vẫn hiển thị để tham khảo."
          : "Ưu đãi đang hiển thị, nhưng hiện chưa có thao tác cho khách hàng.",
    actionTitle: "Thao tác ưu đãi",
    actionDescription: "Chỉ áp dụng hoặc gỡ ưu đãi khi lịch đặt hiện tại cho phép.",
    loyaltyTitle:
      loyaltyState === "available"
        ? "Có điểm thưởng khả dụng"
        : loyaltyState === "not_eligible"
          ? "Điểm thưởng cần thêm điều kiện"
          : "Chưa có ưu đãi điểm thưởng",
    loyaltyDescription:
      loyaltyState === "available"
        ? loyalty.can_redeem || loyalty.can_release
          ? "Bạn có thể quản lý điểm thưởng cho lịch đặt này."
          : "Bạn có thể xem điểm thưởng, nhưng chưa có thao tác điểm."
        : loyaltyState === "not_eligible"
          ? "Điểm thưởng đang hiển thị, nhưng chưa thể dùng cho lịch đặt này."
          : "Lịch đặt này chưa có ưu đãi điểm thưởng đang hoạt động.",
    voucherTitle: voucherWallet.title,
    voucherDescription: voucherWallet.description,
    hasVisibleLoyalty,
    hasVisibleVouchers,
    voucherWallet,
  };
}

export function getVoucherWalletItemState(voucher: CustomerVoucher): VoucherWalletItemState {
  const detailLines = getVoucherDetailLines(voucher);
  const nearExpiry = isVoucherNearExpiry(voucher);

  if (isVoucherExpired(voucher)) {
    return {
      voucher,
      state: "expired",
      badgeLabel: "Hết hạn",
      title: "Hết hạn",
      description: voucher.expires_at
        ? `Voucher này đã hết hạn lúc ${formatDateTime(voucher.expires_at)}.`
        : "Voucher này đã hết hạn và chỉ hiển thị để tham khảo.",
      detailLines,
      nearExpiry: false,
    };
  }

  if (voucher.is_used || voucher.used_at || containsStatus(voucher.current_status, "used")) {
    return {
      voucher,
      state: "unavailable",
      badgeLabel: "Chưa khả dụng",
      title: "Đã sử dụng",
      description: voucher.used_reservation_id
        ? `Voucher này đã dùng cho lịch đặt #${voucher.used_reservation_id}.`
        : "Voucher này đã được sử dụng.",
      detailLines,
      nearExpiry,
    };
  }

  if (voucher.is_locked_by_other) {
    return {
      voucher,
      state: "unavailable",
      badgeLabel: "Chưa khả dụng",
      title: "Đang giữ cho lịch đặt khác",
      description: "Voucher này đang được giữ bởi một lịch đặt khác.",
      detailLines,
      nearExpiry,
    };
  }

  if (voucher.is_locked) {
    return {
      voucher,
      state: "unavailable",
      badgeLabel: "Chưa khả dụng",
      title: "Đang tạm giữ",
      description: voucher.locked_until
        ? `Voucher này được giữ đến ${formatDateTime(voucher.locked_until)}.`
        : "Voucher này đang được tạm giữ.",
      detailLines,
      nearExpiry,
    };
  }

  if (voucher.is_currently_applied) {
    return {
      voucher,
      state: "available",
      badgeLabel: nearExpiry ? "Sắp hết hạn" : "Đã áp dụng",
      title: "Đã áp dụng",
      description: "Voucher này đã áp dụng cho lịch đặt và có thể gỡ khi lịch đặt hiện tại cho phép.",
      detailLines,
      nearExpiry,
    };
  }

  if (voucher.can_apply || voucher.is_usable_now) {
    return {
      voucher,
      state: "available",
      badgeLabel: nearExpiry ? "Sắp hết hạn" : "Dùng được",
      title: "Sẵn sàng sử dụng",
      description: nearExpiry
        ? "Voucher này có thể dùng ngay và sắp hết hạn."
        : "Voucher này hiện đáp ứng các điều kiện sử dụng.",
      detailLines,
      nearExpiry,
    };
  }

  return {
    voucher,
    state: "not_eligible",
    badgeLabel: "Chưa đủ điều kiện",
    title: "Chưa đủ điều kiện",
    description:
      voucher.applicability_reasons.find((reason) => reason.trim() !== "") ??
      "Voucher này đang hiển thị trong ví, nhưng chưa thể sử dụng.",
    detailLines,
    nearExpiry,
  };
}

export function getVoucherDetailLines(voucher: CustomerVoucher): string[] {
  const detailLines: string[] = [];

  if (voucher.starts_at) {
    detailLines.push(`Bắt đầu ${formatDateTime(voucher.starts_at)}.`);
  }

  if (voucher.expires_at) {
    detailLines.push(`Hết hạn ${formatDateTime(voucher.expires_at)}.`);
  }

  if (isVoucherNearExpiry(voucher)) {
    detailLines.push("Sắp hết hạn: hãy dùng voucher sớm nếu phù hợp với lượt ghé của bạn.");
  }

  if (voucher.min_spend) {
    detailLines.push(`Chi tiêu tối thiểu ${formatMoneyWithOptionalCurrency(voucher.min_spend, voucher.preview_currency)}.`);
  }

  if (voucher.preview_discount_amount) {
    detailLines.push(`Giảm dự kiến ${formatMoneyWithOptionalCurrency(voucher.preview_discount_amount, voucher.preview_currency)}.`);
  }

  if (voucher.free_item) {
    detailLines.push(`Tặng món ${voucher.free_item.item_name} x${voucher.free_item.quantity}.`);
  }

  if ((voucher.is_locked || voucher.is_locked_by_other) && voucher.locked_until) {
    detailLines.push(`Đang giữ đến ${formatDateTime(voucher.locked_until)}.`);
  }

  return detailLines;
}

function summarizeVoucherCounts(counts: VoucherWalletState["counts"]): string {
  const parts = [
    counts.available > 0 ? `${counts.available} dùng được` : null,
    counts.notEligible > 0 ? `${counts.notEligible} chưa đủ điều kiện` : null,
    counts.expired > 0 ? `${counts.expired} hết hạn` : null,
    counts.unavailable > 0 ? `${counts.unavailable} chưa khả dụng` : null,
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

function isVoucherNearExpiry(voucher: CustomerVoucher): boolean {
  if (!voucher.expires_at || isVoucherExpired(voucher)) {
    return false;
  }

  const timestamp = Date.parse(voucher.expires_at);
  const sevenDaysMs = 7 * 24 * 60 * 60 * 1000;

  return Number.isFinite(timestamp) && timestamp - Date.now() <= sevenDaysMs;
}

function containsStatus(status: string | null | undefined, fragment: string): boolean {
  return status?.toLowerCase().includes(fragment.toLowerCase()) ?? false;
}

function formatMoneyWithOptionalCurrency(amount: string | number, currency: string | null): string {
  return currency ? formatMoney(amount, currency) : String(amount);
}
