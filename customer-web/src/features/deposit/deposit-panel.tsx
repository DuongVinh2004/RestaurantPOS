"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { PaymentSessionCard } from "@/features/payments/payment-session-card";
import { isConflictLikeApiError } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import { formatMoney } from "@/lib/contracts/format";
import type { ReservationSummary } from "@/lib/contracts/generated/restaurantpos-sdk";
import { getSelfServiceBlockedState } from "@/features/reservations/self-service-boundary";
import {
  acknowledgeDeposit,
  confirmDepositPaymentSession,
  createDepositPaymentSession,
  type DepositPaymentSessionResult,
  type DepositPreview,
  getDepositPreview,
  refreshDepositPaymentSession,
  revokeDepositIntent,
  submitDepositIntent,
} from "./api";
import {
  getDepositPaymentSupportState,
  getDepositPolicy,
  getDepositSummaryState,
  getPaymentSessionPolicy,
  parseDepositContract,
} from "@/features/reservations/state";

export function DepositPanel({
  reservation,
  onReservationChanged,
}: {
  reservation: ReservationSummary;
  onReservationChanged?: (reservation?: ReservationSummary) => void;
}) {
  const queryClient = useQueryClient();
  const [paymentSession, setPaymentSession] = useState<DepositPaymentSessionResult | null>(null);
  const reservationId = reservation.reservation_id;
  const depositQuery = useQuery({
    queryKey: queryKeys.reservations.deposit(reservationId),
    queryFn: () => getDepositPreview(reservationId),
  });

  const refreshWorkspace = async ({ clearSession = false }: { clearSession?: boolean } = {}) => {
    if (clearSession) {
      setPaymentSession(null);
    }

    const refreshed = await depositQuery.refetch();
    onReservationChanged?.(refreshed.data?.reservation);
  };
  const currentRowVersion = depositQuery.data?.reservation.row_version ?? reservation.row_version;
  const syncDepositPreview = async (result: DepositPreview) => {
    queryClient.setQueryData(queryKeys.reservations.deposit(reservationId), result);
    onReservationChanged?.(result.reservation);
  };
  const syncPaymentSession = async (result: DepositPaymentSessionResult) => {
    setPaymentSession(result);
    const nextPolicy = getPaymentSessionPolicy(result.payment_session, { surface: "deposit" });

    if (nextPolicy.terminal) {
      await refreshWorkspace();
    }
  };
  const handleConflict = async (error: unknown) => {
    if (isConflictLikeApiError(error)) {
      await refreshWorkspace({ clearSession: true });
    }
  };
  const acknowledgeMutation = useMutation({
    mutationFn: () => acknowledgeDeposit(reservationId, currentRowVersion),
    onSuccess: syncDepositPreview,
    onError: handleConflict,
  });
  const intentMutation = useMutation({
    mutationFn: () => submitDepositIntent(reservationId, currentRowVersion),
    onSuccess: syncDepositPreview,
    onError: handleConflict,
  });
  const revokeMutation = useMutation({
    mutationFn: () => revokeDepositIntent(reservationId, currentRowVersion),
    onSuccess: syncDepositPreview,
    onError: handleConflict,
  });
  const createSessionMutation = useMutation({
    mutationFn: () => createDepositPaymentSession(reservationId, currentRowVersion),
    onSuccess: syncPaymentSession,
    onError: handleConflict,
  });
  const refreshSessionMutation = useMutation({
    mutationFn: () => {
      const session = paymentSession?.payment_session;

      if (!session) {
        throw new Error("Chưa có phiên thanh toán để cập nhật.");
      }

      return refreshDepositPaymentSession(reservationId, session.deposit_payment_session_id, session.row_version);
    },
    onSuccess: syncPaymentSession,
    onError: handleConflict,
  });
  const confirmSessionMutation = useMutation({
    mutationFn: () => {
      const session = paymentSession?.payment_session;

      if (!session) {
        throw new Error("Chưa có phiên thanh toán để xác nhận.");
      }

      return confirmDepositPaymentSession(reservationId, session.deposit_payment_session_id, session.row_version);
    },
    onSuccess: syncPaymentSession,
    onError: handleConflict,
  });

  const depositReservation = depositQuery.data?.reservation ?? reservation;
  const depositState = parseDepositContract(depositQuery.data?.deposit, depositReservation);
  const depositPolicy = getDepositPolicy(depositReservation, depositState);
  const depositSummary = getDepositSummaryState({
    reservation: depositReservation,
    deposit: depositState,
  });
  const session = paymentSession?.payment_session;
  const sessionPolicy = session ? getPaymentSessionPolicy(session, { surface: "deposit" }) : null;
  const paymentSupport = getDepositPaymentSupportState({
    canCreatePaymentSession: depositPolicy.canCreatePaymentSession,
    deposit: depositState,
  });
  const previewActionError = acknowledgeMutation.error ?? intentMutation.error ?? revokeMutation.error;
  const sessionActionError = createSessionMutation.error ?? refreshSessionMutation.error ?? confirmSessionMutation.error;
  const actionError = previewActionError ?? sessionActionError;
  const loadBoundary = depositQuery.error ? getSelfServiceBlockedState("deposit", depositQuery.error, "Chưa tải được đặt cọc") : null;
  const actionBoundary = actionError
    ? getSelfServiceBlockedState(
        "deposit",
        actionError,
        isConflictLikeApiError(actionError) ? "Thông tin đặt cọc đã thay đổi" : sessionActionError ? "Chưa mở được thanh toán" : "Chưa xử lý được đặt cọc",
      )
    : null;
  const noActionTitle =
    depositSummary.state === "not_required"
      ? "Không cần đặt cọc"
      : depositSummary.state === "paid"
        ? "Đặt cọc đã xử lý"
        : depositSummary.state === "refunded"
          ? "Đặt cọc đã hoàn tiền"
          : "Không cần thao tác đặt cọc";

  useEffect(() => {
    if (!session || !sessionPolicy || sessionPolicy.refreshMode !== "auto" || !sessionPolicy.autoRefreshMs) {
      return;
    }

    if (refreshSessionMutation.isPending || refreshSessionMutation.isError) {
      return;
    }

    const timer = window.setTimeout(() => {
      refreshSessionMutation.mutate();
    }, sessionPolicy.autoRefreshMs);

    return () => window.clearTimeout(timer);
  }, [
    refreshSessionMutation,
    refreshSessionMutation.isError,
    refreshSessionMutation.isPending,
    session,
    session?.deposit_payment_session_id,
    session?.row_version,
    sessionPolicy,
    sessionPolicy?.autoRefreshMs,
    sessionPolicy?.refreshMode,
  ]);

  return (
    <Card className="rounded-lg">
      <CardHeader>
        <CardTitle>Đặt cọc</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {depositQuery.isLoading ? <LoadingBlock label="Đang tải đặt cọc" /> : null}
        {loadBoundary ? (
          loadBoundary.kind === "error" ? (
            <ErrorState error={loadBoundary.error} title={loadBoundary.title} onRetry={() => depositQuery.refetch()} />
          ) : (
            <EmptyState title={loadBoundary.title} description={loadBoundary.description} />
          )
        ) : null}
        {depositQuery.data ? (
          <>
            <section className="space-y-3">
              <div>
                <h3 className="text-lg font-semibold">Tóm tắt đặt cọc</h3>
                <p className="text-sm text-muted-foreground">Xem khoản đặt cọc cần xử lý trước khi thanh toán.</p>
              </div>
              <div className="rounded-lg bg-secondary p-4">
                <div>
                  <p className="text-sm text-muted-foreground">Trạng thái đặt cọc</p>
                  <p className="text-lg font-semibold">{depositSummary.title}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{depositSummary.description}</p>
                </div>
                <div className="mt-4">
                  <p className="text-sm text-muted-foreground">Số tiền cần trả</p>
                  <p className="text-2xl font-semibold">{formatMoney(depositPolicy.amount, depositPolicy.currency)}</p>
                </div>
              </div>
              {depositPolicy.canAcknowledge || depositPolicy.canSubmitIntent || depositPolicy.canRevokeIntent ? (
                <div className="grid gap-2 sm:grid-cols-2">
                  {depositPolicy.canAcknowledge ? (
                    <Button type="button" variant="outline" className="rounded-lg" disabled={acknowledgeMutation.isPending} onClick={() => acknowledgeMutation.mutate()}>
                      Tôi đã hiểu yêu cầu đặt cọc
                    </Button>
                  ) : null}
                  {depositPolicy.canSubmitIntent ? (
                    <Button type="button" variant="outline" className="rounded-lg" disabled={intentMutation.isPending} onClick={() => intentMutation.mutate()}>
                      Tôi sẽ tự thanh toán
                    </Button>
                  ) : null}
                  {depositPolicy.canRevokeIntent ? (
                    <Button type="button" variant="outline" className="rounded-lg" disabled={revokeMutation.isPending} onClick={() => revokeMutation.mutate()}>
                      Hủy tự thanh toán
                    </Button>
                  ) : null}
                </div>
              ) : (
                <EmptyState title={noActionTitle} description={depositPolicy.noActionMessage ?? "Hiện không cần thao tác thêm với đặt cọc."} />
              )}
            </section>

            <section className="space-y-3">
              <div>
                <h3 className="text-lg font-semibold">Thanh toán đặt cọc</h3>
                <p className="text-sm text-muted-foreground">Mở thanh toán khi lịch đặt đã sẵn sàng để khách trả đặt cọc.</p>
              </div>
              {session && sessionPolicy ? (
                <PaymentSessionCard
                  surfaceLabel="Đặt cọc"
                  session={session}
                  policy={sessionPolicy}
                  refreshPending={refreshSessionMutation.isPending}
                  confirmPending={confirmSessionMutation.isPending}
                  onRefresh={() => refreshSessionMutation.mutate()}
                  onConfirm={() => confirmSessionMutation.mutate()}
                />
              ) : (
                <EmptyState
                  title={paymentSupport.title}
                  description={paymentSupport.description}
                  action={
                    depositPolicy.canCreatePaymentSession ? (
                      <Button type="button" className="rounded-lg" disabled={createSessionMutation.isPending} onClick={() => createSessionMutation.mutate()}>
                        {createSessionMutation.isPending ? "Đang mở thanh toán" : "Thanh toán đặt cọc"}
                      </Button>
                    ) : undefined
                  }
                />
              )}
            </section>
            {actionBoundary ? (
              actionBoundary.kind === "error" ? (
                <ErrorState error={actionBoundary.error} title={actionBoundary.title} onRetry={() => refreshWorkspace()} />
              ) : (
                <EmptyState title={actionBoundary.title} description={actionBoundary.description} />
              )
            ) : null}
          </>
        ) : null}
      </CardContent>
    </Card>
  );
}
