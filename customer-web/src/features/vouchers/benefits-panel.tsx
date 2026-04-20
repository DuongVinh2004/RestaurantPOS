"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { isConflictLikeApiError } from "@/lib/api/errors";
import { customerWebRollout } from "@/lib/config/feature-flags";
import { formatMoney } from "@/lib/contracts/format";
import { queryKeys } from "@/lib/api/query-keys";
import { getSelfServiceBlockedState } from "@/features/reservations/self-service-boundary";
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
      toast.success("Voucher applied.");
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
      toast.success("Voucher removed.");
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
      toast.success("Loyalty points redeemed.");
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
      toast.success("Loyalty redemption released.");
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
          <CardTitle>Benefits</CardTitle>
        </CardHeader>
        <CardContent>
          <EmptyState title={benefitsVisibility.title} description={benefitsVisibility.description} />
        </CardContent>
      </Card>
    );
  }

  const loadBoundary = benefitsQuery.error ? getSelfServiceBlockedState("benefits", benefitsQuery.error, "Benefits are unavailable") : null;
  const actionError =
    voucherApplyMutation.error ?? voucherRemoveMutation.error ?? loyaltyRedeemMutation.error ?? loyaltyReleaseMutation.error;
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

  return (
    <Card className="rounded-lg">
      <CardHeader>
        <CardTitle>Benefits</CardTitle>
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
        {benefitsQuery.isLoading ? <LoadingBlock label="Loading benefits" /> : null}
        {loadBoundary ? (
          loadBoundary.kind === "error" ? (
            <ErrorState error={loadBoundary.error} title={loadBoundary.title} onRetry={() => benefitsQuery.refetch()} />
          ) : (
            <EmptyState title={loadBoundary.title} description={loadBoundary.description} />
          )
        ) : null}
        {benefitsQuery.data ? (() => {
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
                    {benefitsState.state.replace(/_/g, " ")}
                  </Badge>
                </div>
                <p className="mt-3 text-sm font-medium">{benefitsState.actionTitle}</p>
                <p className="mt-1 text-sm text-muted-foreground">{benefitsState.actionDescription}</p>
              </div>

              <div className="grid gap-3 sm:grid-cols-3">
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Available points</p>
                  <p className="text-2xl font-semibold">{benefitsQuery.data.reservation.loyalty.available_points}</p>
                </div>
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Redeemable now</p>
                  <p className="text-2xl font-semibold">{benefitsQuery.data.reservation.loyalty.max_redeemable_points}</p>
                </div>
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Preview savings</p>
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
                      <Label htmlFor="loyalty-redeem-points">Points to redeem</Label>
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
                        {loyaltyRedeemMutation.isPending ? "Redeeming points" : "Redeem points"}
                      </Button>
                    </div>
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
                      {loyaltyReleaseMutation.isPending ? "Releasing points" : "Release points"}
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
                            {voucherRemoveMutation.isPending ? "Removing voucher" : "Remove voucher"}
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
                            {voucherApplyMutation.isPending ? "Applying voucher" : "Apply voucher"}
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
                  title={isConflictLikeApiError(actionError) ? "Benefits details changed" : "Benefits action failed"}
                  onRetry={() => benefitsQuery.refetch()}
                />
              ) : null}
            </div>
          );
        })() : null}
      </CardContent>
    </Card>
  );
}
