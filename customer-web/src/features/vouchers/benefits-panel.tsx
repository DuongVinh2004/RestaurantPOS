"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { getSelfServiceBlockedState } from "@/features/reservations/self-service-boundary";
import { isConflictLikeApiError, normalizeApiError } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import { customerWebRollout } from "@/lib/config/feature-flags";
import { formatMoney } from "@/lib/contracts/format";
import type { ReservationBenefitsPreview } from "./api";
import { applyVoucher, getBenefitsPreview, redeemLoyaltyPoints, releaseLoyaltyPoints, removeVoucher } from "./api";
import { getBenefitsVisibilityState, getReservationBenefitsState } from "./state";

export function BenefitsPanel({ reservationId }: { reservationId: number }) {
  const queryClient = useQueryClient();
  const [redeemPointsInput, setRedeemPointsInput] = useState("");
  const benefitsRollout = customerWebRollout.accountBenefits;
  const benefitsVisibility = getBenefitsVisibilityState(benefitsRollout);
  const benefitsQuery = useQuery({
    queryKey: queryKeys.reservations.benefits(reservationId),
    queryFn: () => getBenefitsPreview(reservationId),
    enabled: benefitsRollout.enabled,
  });

  const syncBenefitsPreview = (next: Partial<ReservationBenefitsPreview>) => {
    queryClient.setQueryData<ReservationBenefitsPreview>(queryKeys.reservations.benefits(reservationId), (current) =>
      current ? { ...current, ...next } : current,
    );
    void queryClient.invalidateQueries({ queryKey: queryKeys.reservations.benefits(reservationId) });
    void queryClient.invalidateQueries({ queryKey: queryKeys.reservations.detail(reservationId) });
    void queryClient.invalidateQueries({ queryKey: queryKeys.account.loyalty, refetchType: "inactive" });
    void queryClient.invalidateQueries({ queryKey: queryKeys.account.vouchers, refetchType: "inactive" });
  };

  const refreshBenefitsOnConflict = (error: unknown) => {
    if (isConflictLikeApiError(error)) {
      void benefitsQuery.refetch();
    }
  };

  const voucherApplyMutation = useMutation({
    mutationFn: ({ rowVersion, voucherCode }: { rowVersion: number; voucherCode: string }) =>
      applyVoucher(reservationId, rowVersion, voucherCode),
    onSuccess(result) {
      toast.success("Đã áp dụng voucher.");
      syncBenefitsPreview({
        reservation: result.reservation,
        available_vouchers: result.available_vouchers,
      });
    },
    onError: refreshBenefitsOnConflict,
  });

  const voucherRemoveMutation = useMutation({
    mutationFn: ({ rowVersion }: { rowVersion: number }) => removeVoucher(reservationId, rowVersion),
    onSuccess(result) {
      toast.success("Đã gỡ voucher.");
      syncBenefitsPreview({
        reservation: result.reservation,
        available_vouchers: result.available_vouchers,
      });
    },
    onError: refreshBenefitsOnConflict,
  });

  const loyaltyRedeemMutation = useMutation({
    mutationFn: ({ rowVersion, points }: { rowVersion: number; points: number }) =>
      redeemLoyaltyPoints(reservationId, rowVersion, points),
    onSuccess(result) {
      toast.success("Đã dùng điểm thưởng.");
      setRedeemPointsInput("");
      syncBenefitsPreview({
        reservation: result.reservation,
      });
    },
    onError: refreshBenefitsOnConflict,
  });

  const loyaltyReleaseMutation = useMutation({
    mutationFn: ({ rowVersion }: { rowVersion: number }) => releaseLoyaltyPoints(reservationId, rowVersion),
    onSuccess(result) {
      toast.success("Đã gỡ điểm thưởng.");
      syncBenefitsPreview({
        reservation: result.reservation,
      });
    },
    onError: refreshBenefitsOnConflict,
  });

  if (!benefitsRollout.enabled) {
    return (
      <Card className="rounded-lg">
        <CardHeader>
          <CardTitle>Ưu đãi</CardTitle>
        </CardHeader>
        <CardContent>
          <EmptyState title={benefitsVisibility.title} description={benefitsVisibility.description} />
        </CardContent>
      </Card>
    );
  }

  const loadBoundary = benefitsQuery.error ? getSelfServiceBlockedState("benefits", benefitsQuery.error, "Chưa tải được ưu đãi") : null;
  const actionError =
    voucherApplyMutation.error ?? voucherRemoveMutation.error ?? loyaltyRedeemMutation.error ?? loyaltyReleaseMutation.error;
  const actionErrorState = actionError ? normalizeApiError(actionError) : null;
  const actionPending =
    voucherApplyMutation.isPending ||
    voucherRemoveMutation.isPending ||
    loyaltyRedeemMutation.isPending ||
    loyaltyReleaseMutation.isPending;
  const currentRowVersion = benefitsQuery.data?.reservation.row_version ?? null;
  const loyalty = benefitsQuery.data?.reservation.loyalty ?? null;
  const defaultRedeemPoints = loyalty
    ? Math.max(loyalty.min_redeem_points, Math.min(loyalty.max_redeemable_points, loyalty.available_points))
    : 0;
  const parsedRedeemPoints = Number(redeemPointsInput || defaultRedeemPoints);
  const canSubmitRedeem =
    currentRowVersion !== null &&
    loyalty !== null &&
    loyalty.can_redeem &&
    Number.isFinite(parsedRedeemPoints) &&
    parsedRedeemPoints >= loyalty.min_redeem_points &&
    parsedRedeemPoints <= loyalty.max_redeemable_points;
  const redeemGuidance = loyalty
    ? getRedeemGuidance({
        loyalty,
        inputValue: redeemPointsInput,
        parsedPoints: parsedRedeemPoints,
      })
    : null;

  return (
    <Card className="rounded-lg">
      <CardHeader>
        <CardTitle>Ưu đãi</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="rounded-lg border border-dashed bg-secondary/20 p-4 text-sm">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p className="font-medium">{benefitsVisibility.title}</p>
              <p className="mt-1 text-muted-foreground">{benefitsVisibility.description}</p>
            </div>
            <Badge variant="outline" className="rounded-md">
              {benefitsVisibility.badgeLabel}
            </Badge>
          </div>
        </div>

        {benefitsQuery.isLoading ? <LoadingBlock label="Đang tải ưu đãi" /> : null}
        {loadBoundary ? (
          loadBoundary.kind === "error" ? (
            <ErrorState error={loadBoundary.error} title={loadBoundary.title} onRetry={() => benefitsQuery.refetch()} />
          ) : (
            <EmptyState title={loadBoundary.title} description={loadBoundary.description} />
          )
        ) : null}

        {benefitsQuery.data
          ? (() => {
              const benefitsState = getReservationBenefitsState(benefitsQuery.data);

              return (
                <div className="space-y-3">
                  <div className="rounded-lg border p-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <p className="font-medium">{benefitsState.title}</p>
                        <p className="mt-1 text-sm text-muted-foreground">{benefitsState.description}</p>
                      </div>
                      <Badge variant="outline" className="rounded-md">
                        {benefitsState.state === "available"
                          ? "Có thể dùng"
                          : benefitsState.state === "expired"
                            ? "Hết hạn"
                            : benefitsState.state === "not_eligible"
                              ? "Chưa đủ điều kiện"
                              : "Chưa có"}
                      </Badge>
                    </div>
                    <p className="mt-3 text-sm font-medium">{benefitsState.actionTitle}</p>
                    <p className="mt-1 text-sm text-muted-foreground">{benefitsState.actionDescription}</p>
                  </div>

                  <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-lg bg-secondary p-4">
                      <p className="text-sm text-muted-foreground">Điểm hiện có</p>
                      <p className="text-2xl font-semibold">{benefitsQuery.data.reservation.loyalty.available_points}</p>
                    </div>
                    <div className="rounded-lg bg-secondary p-4">
                      <p className="text-sm text-muted-foreground">Có thể dùng</p>
                      <p className="text-2xl font-semibold">{benefitsQuery.data.reservation.loyalty.max_redeemable_points}</p>
                    </div>
                    <div className="rounded-lg bg-secondary p-4">
                      <p className="text-sm text-muted-foreground">Giảm dự kiến</p>
                      <p className="text-2xl font-semibold">
                        {formatMoney(benefitsQuery.data.reservation.bill.discount_amount, benefitsQuery.data.reservation.bill.currency)}
                      </p>
                    </div>
                  </div>

                  <div className="grid gap-3 lg:grid-cols-2">
                    <div className="rounded-lg border p-4">
                      <p className="font-medium">{benefitsState.loyaltyTitle}</p>
                      <p className="mt-1 text-sm text-muted-foreground">{benefitsState.loyaltyDescription}</p>

                      {loyalty?.can_redeem ? (
                        <div className="mt-4 space-y-2">
                          <Label htmlFor="loyalty-redeem-points">Số điểm muốn dùng</Label>
                          <Input
                            id="loyalty-redeem-points"
                            type="number"
                            min={loyalty.min_redeem_points}
                            max={loyalty.max_redeemable_points}
                            className="min-h-11 rounded-lg"
                            placeholder={String(defaultRedeemPoints)}
                            value={redeemPointsInput}
                            onChange={(event) => setRedeemPointsInput(event.target.value)}
                          />
                          {redeemGuidance ? (
                            <p className={redeemGuidance.tone === "destructive" ? "text-sm text-destructive" : "text-sm text-muted-foreground"}>
                              {redeemGuidance.message}
                            </p>
                          ) : null}
                          <Button
                            type="button"
                            variant="outline"
                            className="rounded-lg"
                            disabled={actionPending || !canSubmitRedeem}
                            onClick={() => {
                              if (currentRowVersion === null || !canSubmitRedeem) {
                                return;
                              }

                              loyaltyRedeemMutation.mutate({
                                rowVersion: currentRowVersion,
                                points: parsedRedeemPoints,
                              });
                            }}
                          >
                            {loyaltyRedeemMutation.isPending ? "Đang dùng điểm" : "Dùng điểm"}
                          </Button>
                        </div>
                      ) : redeemGuidance ? (
                        <p className={redeemGuidance.tone === "destructive" ? "mt-4 text-sm text-destructive" : "mt-4 text-sm text-muted-foreground"}>
                          {redeemGuidance.message}
                        </p>
                      ) : null}

                      {loyalty?.can_release ? (
                        <Button
                          type="button"
                          variant="outline"
                          className="mt-4 rounded-lg"
                          disabled={actionPending || currentRowVersion === null}
                          onClick={() => {
                            if (currentRowVersion === null) {
                              return;
                            }

                            loyaltyReleaseMutation.mutate({ rowVersion: currentRowVersion });
                          }}
                        >
                          {loyaltyReleaseMutation.isPending ? "Đang gỡ điểm" : "Gỡ điểm"}
                        </Button>
                      ) : null}
                    </div>

                    <div className="rounded-lg border p-4">
                      <p className="font-medium">{benefitsState.voucherTitle}</p>
                      <p className="mt-1 text-sm text-muted-foreground">{benefitsState.voucherDescription}</p>
                    </div>
                  </div>

                  {benefitsState.voucherWallet.state === "empty" ? (
                    <EmptyState title={benefitsState.voucherWallet.title} description={benefitsState.voucherWallet.description} />
                  ) : (
                    <div className="space-y-3">
                      <div className="rounded-lg bg-secondary/30 p-4 text-sm text-muted-foreground">{benefitsState.voucherWallet.summary}</div>
                      <div className="grid gap-3 sm:grid-cols-2">
                        {benefitsState.voucherWallet.items.map((item) => (
                          <div key={item.voucher.user_voucher_id} className="rounded-lg border p-4">
                            <div className="flex items-start justify-between gap-3">
                              <div>
                                <p className="font-medium">{item.voucher.voucher_code}</p>
                                <p className="text-sm text-muted-foreground">{item.voucher.description}</p>
                              </div>
                              <Badge variant="outline" className="rounded-md">
                                {item.badgeLabel}
                              </Badge>
                            </div>
                            <p className="mt-3 text-sm font-medium">{item.title}</p>
                            <p className="mt-1 text-sm text-muted-foreground">{item.description}</p>
                            {item.detailLines.length > 0 ? (
                              <div className="mt-3 space-y-1 text-xs text-muted-foreground">
                                {item.detailLines.map((line, index) => (
                                  <p key={`${item.voucher.user_voucher_id}-${index}`}>{line}</p>
                                ))}
                              </div>
                            ) : null}

                            {item.voucher.is_currently_applied ? (
                              <Button
                                type="button"
                                variant="outline"
                                className="mt-4 rounded-lg"
                                disabled={actionPending || currentRowVersion === null}
                                onClick={() => {
                                  if (currentRowVersion === null) {
                                    return;
                                  }

                                  voucherRemoveMutation.mutate({ rowVersion: currentRowVersion });
                                }}
                              >
                                {voucherRemoveMutation.isPending ? "Đang gỡ voucher" : "Gỡ voucher"}
                              </Button>
                            ) : item.voucher.can_apply || item.voucher.is_usable_now ? (
                              <Button
                                type="button"
                                variant="outline"
                                className="mt-4 rounded-lg"
                                disabled={actionPending || currentRowVersion === null}
                                onClick={() => {
                                  if (currentRowVersion === null) {
                                    return;
                                  }

                                  voucherApplyMutation.mutate({
                                    rowVersion: currentRowVersion,
                                    voucherCode: item.voucher.voucher_code,
                                  });
                                }}
                              >
                                {voucherApplyMutation.isPending ? "Đang áp dụng" : "Áp dụng voucher"}
                              </Button>
                            ) : null}
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {actionError ? (
                    <ErrorState
                      error={actionError}
                      title={
                        isConflictLikeApiError(actionError)
                          ? "Thông tin ưu đãi đã thay đổi"
                          : actionErrorState?.kind === "validation"
                            ? "Ưu đãi chưa hợp lệ"
                            : "Chưa xử lý được ưu đãi"
                      }
                      onRetry={() => benefitsQuery.refetch()}
                    />
                  ) : null}
                </div>
              );
            })()
          : null}
      </CardContent>
    </Card>
  );
}

function getRedeemGuidance({
  loyalty,
  inputValue,
  parsedPoints,
}: {
  loyalty: NonNullable<ReservationBenefitsPreview["reservation"]["loyalty"]>;
  inputValue: string;
  parsedPoints: number;
}): { message: string; tone: "muted" | "destructive" } | null {
  const hasManualInput = inputValue.trim() !== "";

  if (hasManualInput && !Number.isFinite(parsedPoints)) {
    return {
      message: "Nhập số điểm hợp lệ trước khi gửi yêu cầu dùng điểm.",
      tone: "destructive",
    };
  }

  if (hasManualInput && parsedPoints < loyalty.min_redeem_points) {
    return {
      message: `Cần ít nhất ${loyalty.min_redeem_points} điểm để dùng cho lịch đặt này.`,
      tone: "destructive",
    };
  }

  if (hasManualInput && parsedPoints > loyalty.max_redeemable_points) {
    return {
      message: `Chỉ có thể dùng tối đa ${loyalty.max_redeemable_points} điểm cho lịch đặt này.`,
      tone: "destructive",
    };
  }

  if (loyalty.can_redeem) {
    return {
      message: `Có thể dùng từ ${loyalty.min_redeem_points} đến ${loyalty.max_redeemable_points} điểm cho lượt đặt này.`,
      tone: "muted",
    };
  }

  if (loyalty.available_points < loyalty.min_redeem_points) {
    return {
      message: `Cần ít nhất ${loyalty.min_redeem_points} điểm. Hiện tài khoản có ${loyalty.available_points} điểm.`,
      tone: "muted",
    };
  }

  if (loyalty.max_redeemable_points <= 0) {
    return {
      message: "Lịch đặt này hiện chưa có hạn mức phù hợp để dùng điểm thưởng.",
      tone: "muted",
    };
  }

  if (loyalty.redeemed_points > 0 && loyalty.can_release) {
    return {
      message: `Đã giữ ${loyalty.redeemed_points} điểm cho lịch đặt này. Bạn có thể gỡ điểm nếu cần đổi lại.`,
      tone: "muted",
    };
  }

  return {
    message: "Điểm thưởng đang hiển thị, nhưng nhà hàng chưa cho phép dùng điểm ở bước này.",
    tone: "muted",
  };
}
