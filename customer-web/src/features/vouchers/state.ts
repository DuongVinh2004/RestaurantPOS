import { getSupportMatrixEntryById, type SurfaceRolloutDecision } from "@/lib/config/support-matrix";
import type { CustomerLoyaltySummary, CustomerReservationBenefitsPreview, CustomerVoucher } from "@/lib/contracts/generated/restaurantpos-sdk";

const benefitsSupport = getSupportMatrixEntryById("account-benefits");

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
      badgeLabel: "Gated",
      title: rollout.disabledTitle,
      description: rollout.disabledDescription,
    };
  }

  return {
    state: "contract_visible",
    badgeLabel: "Contract-visible",
    title: "Benefits rollout proof enabled",
    description:
      rollout.liveProofSummary ||
      "Benefits data and reservation-level actions use live customer contract routes inside this gated rollout.",
  };
}

export function getLoyaltyAccountState(summary: CustomerLoyaltySummary): LoyaltyAccountState {
  const totalPoints = summary.user.total_points;
  const hasTransactions = summary.transactions.length > 0;
  const tierLabel = summary.user.current_tier ? `${summary.user.current_tier.tier_name} tier` : null;
  const nextTier =
    summary.user.next_tier && typeof summary.user.next_tier.points_to_unlock === "number"
      ? `${summary.user.next_tier.points_to_unlock} points to unlock ${summary.user.next_tier.tier_name}.`
      : null;

  if (totalPoints <= 0 && !hasTransactions) {
    return {
      state: "empty",
      title: "No loyalty balance yet",
      description: "This account does not have loyalty points or recent loyalty activity yet.",
      totalPoints,
      transactionTitle: "No loyalty activity yet",
      transactionDescription: "Recent loyalty transactions will appear here after the restaurant records them in backend runtime.",
      tierLabel,
      nextTierLabel: nextTier,
    };
  }

  return {
    state: "available",
    title: tierLabel ? `${tierLabel} visible` : "Loyalty balance visible",
    description: nextTier ?? "Loyalty points and recent activity are visible for this account.",
    totalPoints,
    transactionTitle: hasTransactions ? "Recent loyalty activity" : "No recent loyalty activity",
    transactionDescription: hasTransactions
      ? "Recent point movements are visible here for review."
      : "Your loyalty balance is visible, but there are no recent transactions to review yet.",
    tierLabel,
    nextTierLabel: nextTier,
  };
}

export function getVoucherWalletState(vouchers: CustomerVoucher[]): VoucherWalletState {
  if (vouchers.length === 0) {
    return {
      state: "empty",
      title: "No vouchers in this wallet",
      description: "This account does not have any loyalty or voucher benefits to review right now.",
      summary: "No active benefits",
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
      title: "Voucher wallet visible",
      description: "Voucher wallet entries are visible here. Reservation-level voucher actions stay behind the account-benefits rollout.",
      summary: summarizeVoucherCounts(counts),
      counts,
      items,
    };
  }

  if (counts.notEligible > 0 && counts.expired === 0 && counts.unavailable === 0) {
    return {
      state: "not_eligible",
      title: "Vouchers not eligible right now",
      description: "Voucher wallet entries are visible, but the current backend applicability rules do not allow them right now.",
      summary: summarizeVoucherCounts(counts),
      counts,
      items,
    };
  }

  if (counts.expired > 0 && counts.available === 0 && counts.notEligible === 0 && counts.unavailable === 0) {
    return {
      state: "expired",
      title: "Only expired vouchers remain",
      description: "These voucher wallet entries are still visible for reference, but their validity window already ended.",
      summary: summarizeVoucherCounts(counts),
      counts,
      items,
    };
  }

  return {
    state: "unavailable",
    title: "No voucher is usable right now",
    description: "Voucher wallet entries are visible, but none of them is currently usable from customer-web.",
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
      title: "No active benefits",
      description: "No loyalty or voucher benefits are available for this reservation right now.",
      actionTitle: "Contract-visible rollout path",
      actionDescription:
        benefitsSupport?.liveProofSummary ??
        "Benefits remain behind an explicit rollout gate until QA enables the account-benefits surface.",
      loyaltyTitle: "No loyalty benefit visible",
      loyaltyDescription: "This reservation does not expose an active loyalty benefit right now.",
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
        ? "Benefits visible in contract"
        : state === "expired"
          ? "Only expired benefits remain"
          : "Benefits not eligible right now",
    description:
      state === "available"
        ? "Loyalty points and voucher previews are visible for this reservation. Available actions stay row-versioned and idempotent."
        : state === "expired"
          ? "The reservation still exposes benefit history, but only expired voucher states remain."
          : "Benefits are visible for this reservation, but the current loyalty or voucher rules do not allow a customer action.",
    actionTitle: "Row-versioned benefit actions",
    actionDescription:
      benefitsSupport?.liveProofSummary ??
      "Voucher and loyalty actions remain gated by rollout flag, customer owner scope, idempotency, and the latest reservation row version.",
    loyaltyTitle:
      loyaltyState === "available"
        ? "Loyalty visible"
        : loyaltyState === "not_eligible"
          ? "Loyalty not eligible right now"
          : "No loyalty benefit visible",
    loyaltyDescription:
      loyaltyState === "available"
        ? loyalty.can_redeem || loyalty.can_release
          ? "The backend exposes loyalty actions for this reservation."
          : "The backend exposes loyalty balances for this reservation, but no redeem or release action is currently available."
        : loyaltyState === "not_eligible"
          ? "Loyalty is visible for this reservation, but the current rules do not allow a customer action right now."
          : "This reservation does not expose an active loyalty benefit right now.",
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
      badgeLabel: "Expired",
      title: "Expired",
      description: voucher.expires_at
        ? `This voucher expired at ${voucher.expires_at}.`
        : "This voucher is visible for reference only because its expiry window already ended.",
    };
  }

  if (voucher.is_used || voucher.used_at || containsStatus(voucher.current_status, "used")) {
    return {
      voucher,
      state: "unavailable",
      badgeLabel: "Unavailable",
      title: "Already used",
      description: voucher.used_reservation_id
        ? `This voucher was already used on reservation #${voucher.used_reservation_id}.`
        : "This voucher has already been used and is no longer available.",
    };
  }

  if (voucher.is_locked_by_other) {
    return {
      voucher,
      state: "unavailable",
      badgeLabel: "Unavailable",
      title: "Locked on another reservation",
      description: "The backend reports that this voucher is locked by a different reservation right now.",
    };
  }

  if (voucher.is_locked) {
    return {
      voucher,
      state: "unavailable",
      badgeLabel: "Unavailable",
      title: "Temporarily locked",
      description: voucher.locked_until
        ? `This voucher stays locked until ${voucher.locked_until}.`
        : "The backend reports that this voucher is temporarily locked right now.",
    };
  }

  if (voucher.is_currently_applied) {
    return {
      voucher,
      state: "available",
      badgeLabel: "Available",
      title: "Already linked",
      description: "This voucher is already linked from backend runtime and can be removed while the rollout is enabled.",
    };
  }

  if (voucher.can_apply || voucher.is_usable_now) {
    return {
      voucher,
      state: "available",
      badgeLabel: "Available",
      title: "Available to review",
      description: "This voucher is visible in contract and currently passes the backend applicability checks.",
    };
  }

  return {
    voucher,
    state: "not_eligible",
    badgeLabel: "Not eligible",
    title: "Not eligible right now",
    description:
      voucher.applicability_reasons.find((reason) => reason.trim() !== "") ??
      "This voucher is visible in the wallet, but the current backend rules do not allow it right now.",
  };
}

function summarizeVoucherCounts(counts: VoucherWalletState["counts"]): string {
  const parts = [
    counts.available > 0 ? `${counts.available} available` : null,
    counts.notEligible > 0 ? `${counts.notEligible} not eligible` : null,
    counts.expired > 0 ? `${counts.expired} expired` : null,
    counts.unavailable > 0 ? `${counts.unavailable} unavailable` : null,
  ].filter((value): value is string => value !== null);

  return parts.length > 0 ? parts.join(" - ") : "No active benefits";
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
