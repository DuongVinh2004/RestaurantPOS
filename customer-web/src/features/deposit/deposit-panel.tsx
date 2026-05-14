"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { PaymentBreakdown } from "@/features/payments/payment-breakdown";
import { PaymentSessionCard } from "@/features/payments/payment-session-card";
import {
  clearStoredCustomerPaymentSession,
  readStoredCustomerPaymentSession,
  storeCustomerPaymentSession,
} from "@/features/payments/session-storage";
import { getSelfServiceBlockedState } from "@/features/reservations/self-service-boundary";
import {
  getDepositPaymentSupportState,
  getDepositPolicy,
  getDepositSummaryState,
  getPaymentSessionPolicy,
  parseDepositContract,
} from "@/features/reservations/state";
import {
  customerFriendlyDepositMessage,
  isConflictLikeApiError,
  normalizeApiError,
} from "@/lib/api/errors";
import { trackCustomerEvent } from "@/lib/analytics/events";
import { queryKeys } from "@/lib/api/query-keys";
import { formatMoney } from "@/lib/contracts/format";
import type { ReservationSummary } from "@/lib/contracts/generated/restaurantpos-sdk";
import {
  acknowledgeDeposit,
  confirmDepositPaymentSession,
  createDepositPaymentSession,
  type DepositPaymentSessionResult,
  type DepositPreview,
  getDepositPaymentSession,
  getDepositPreview,
  refreshDepositPaymentSession,
  revokeDepositIntent,
  submitDepositIntent,
} from "./api";

export function DepositPanel({
  reservation,
  onReservationChanged,
}: {
  reservation: ReservationSummary;
  onReservationChanged?: (reservation?: ReservationSummary) => void;
}) {
  const queryClient = useQueryClient();
  const reservationId = reservation.reservation_id;
  const [paymentSession, setPaymentSession] = useState<DepositPaymentSessionResult | null>(null);
  const [storedSessionId, setStoredSessionId] = useState<number | null>(
    () => readStoredCustomerPaymentSession("deposit", reservationId)?.session_id ?? null,
  );
  const [paymentSessionRestoreError, setPaymentSessionRestoreError] = useState<unknown>(null);
  const depositQuery = useQuery({
    queryKey: queryKeys.reservations.deposit(reservationId),
    queryFn: () => getDepositPreview(reservationId),
  });
  const paymentSessionQuery = useQuery({
    queryKey: queryKeys.reservations.depositPaymentSession(reservationId, storedSessionId),
    queryFn: async () => {
      setPaymentSessionRestoreError(null);

      try {
        const result = await getDepositPaymentSession(reservationId, storedSessionId as number);
        const restoredPolicy = getPaymentSessionPolicy(result.payment_session, { surface: "deposit" });

        if (restoredPolicy.terminal) {
          clearStoredCustomerPaymentSession("deposit", reservationId);
          setStoredSessionId(null);
          setPaymentSession(result);
        }

        return result;
      } catch (error) {
        setPaymentSessionRestoreError(error);
        const normalized = normalizeApiError(error);

        if (normalized.kind === "unauthorized" || normalized.kind === "forbidden" || normalized.kind === "not_found") {
          clearStoredCustomerPaymentSession("deposit", reservationId);
          setStoredSessionId(null);
          setPaymentSession(null);
        }

        throw error;
      }
    },
    enabled: storedSessionId !== null && paymentSession === null,
  });

  const clearStoredSession = () => {
    clearStoredCustomerPaymentSession("deposit", reservationId);
    setStoredSessionId(null);
  };

  const refreshWorkspace = async ({ clearSession = false }: { clearSession?: boolean } = {}) => {
    if (clearSession) {
      setPaymentSession(null);
      clearStoredSession();
    }

    setPaymentSessionRestoreError(null);
    const refreshed = await depositQuery.refetch();
    onReservationChanged?.(refreshed.data?.reservation);
  };

  const currentRowVersion =
    depositQuery.data?.reservation?.row_version ?? reservation.row_version;
  const currentPaymentSession = paymentSession ?? paymentSessionQuery.data ?? null;

  const syncDepositPreview = async (result: DepositPreview) => {
    queryClient.setQueryData(queryKeys.reservations.deposit(reservationId), result);
    onReservationChanged?.(result.reservation);
  };

  const syncPaymentSession = async (result: DepositPaymentSessionResult) => {
    setPaymentSession(result);
    setPaymentSessionRestoreError(null);
    storeCustomerPaymentSession("deposit", reservationId, result.payment_session.deposit_payment_session_id);
    setStoredSessionId(result.payment_session.deposit_payment_session_id);

    const nextPolicy = getPaymentSessionPolicy(result.payment_session, { surface: "deposit" });

    if (nextPolicy.terminal) {
      clearStoredSession();
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
    onMutate: () => {
      trackCustomerEvent("payment_attempted", { reservation_id: reservationId, surface: "deposit" });
      setPaymentSessionRestoreError(null);
    },
    onSuccess: syncPaymentSession,
    onError: handleConflict,
  });
  const refreshSessionMutation = useMutation({
    mutationFn: () => {
      const session = currentPaymentSession?.payment_session;

      if (!session) {
        throw new Error("Chưa có phiên thanh toán để cập nhật.");
      }

      return refreshDepositPaymentSession(reservationId, session.deposit_payment_session_id, session.row_version);
    },
    onMutate: () => setPaymentSessionRestoreError(null),
    onSuccess: syncPaymentSession,
    onError: handleConflict,
  });
  const confirmSessionMutation = useMutation({
    mutationFn: () => {
      const session = currentPaymentSession?.payment_session;

      if (!session) {
        throw new Error("Chưa có phiên thanh toán để xác nhận.");
      }

      return confirmDepositPaymentSession(reservationId, session.deposit_payment_session_id, session.row_version);
    },
    onMutate: () => setPaymentSessionRestoreError(null),
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
  const depositSummaryCopy = getDepositSummaryCopy(depositSummary.state);
  const session = currentPaymentSession?.payment_session;
  const sessionPolicy = session ? getPaymentSessionPolicy(session, { surface: "deposit" }) : null;
  const paymentSupport = getDepositPaymentSupportState({
    canCreatePaymentSession: depositPolicy.canCreatePaymentSession,
    deposit: depositState,
  });
  const previewActionError = acknowledgeMutation.error ?? intentMutation.error ?? revokeMutation.error;
  const sessionActionError =
    paymentSessionRestoreError ?? createSessionMutation.error ?? refreshSessionMutation.error ?? confirmSessionMutation.error;
  const previewActionPending =
    acknowledgeMutation.isPending ||
    intentMutation.isPending ||
    revokeMutation.isPending;
  const sessionActionPending =
    createSessionMutation.isPending ||
    refreshSessionMutation.isPending ||
    confirmSessionMutation.isPending ||
    paymentSessionQuery.isLoading;
  const anyActionPending = previewActionPending || sessionActionPending;
  const actionError = previewActionError ?? sessionActionError;
  const loadBoundary = depositQuery.error
    ? getSelfServiceBlockedState("deposit", depositQuery.error, "Chưa tải được đặt cọc")
    : null;
  const actionBoundary = actionError
    ? getSelfServiceBlockedState(
        "deposit",
        actionError,
        isConflictLikeApiError(actionError)
          ? "Thông tin đặt cọc đã thay đổi"
          : sessionActionError
            ? "Chưa xử lý được phiên thanh toán đặt cọc"
            : "Chưa xử lý được đặt cọc",
      )
    : null;
  const noDepositActionTitle = getDepositNoActionTitle(depositSummary.state);
  const depositBreakdownLines = [
    {
      label: "Đặt cọc cần trả",
      amount: depositPolicy.amount,
      currency: depositPolicy.currency,
    },
    {
      label: "Đặt cọc còn lại",
      amount: depositPolicy.outstandingAmount ?? depositPolicy.amount,
      currency: depositPolicy.currency,
      emphasis: true,
    },
  ];

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
        {paymentSessionQuery.isLoading && storedSessionId !== null && paymentSession === null ? (
          <LoadingBlock label="Đang khôi phục phiên thanh toán đặt cọc" />
        ) : null}
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
                <p className="text-sm text-muted-foreground">
                  Đơn món đặt trước này có thể cần đặt cọc để Mộc Sen chuẩn bị nguyên liệu đúng giờ. Số tiền cọc sẽ được trừ vào hóa đơn khi thanh toán tại nhà hàng.
                </p>
              </div>
              <div className="rounded-lg bg-secondary p-4">
                <div>
                  <p className="mt-1 text-sm text-muted-foreground">{depositSummary.description}</p>
                  <p className="text-lg font-semibold">{depositSummaryCopy.title}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{depositSummaryCopy.description}</p>
                </div>
                <div className="mt-4">
                  <p className="text-sm text-muted-foreground">Số tiền cần trả</p>
                  <p className="text-2xl font-semibold">
                    {formatMoney(depositPolicy.amount, depositPolicy.currency)}
                  </p>
                </div>
              </div>
              <PaymentBreakdown
                title="Chi tiết đặt cọc"
                description="Các khoản đặt cọc hiện có trong lịch đặt của bạn."
                lines={depositBreakdownLines}
              />
              {depositPolicy.canAcknowledge || depositPolicy.canSubmitIntent || depositPolicy.canRevokeIntent ? (
                <div className="grid gap-2 sm:grid-cols-2">
                  {depositPolicy.canAcknowledge ? (
                    <Button
                      type="button"
                      variant="outline"
                      className="rounded-lg"
                      disabled={anyActionPending}
                      onClick={() => acknowledgeMutation.mutate()}
                    >
                      Tôi đã hiểu yêu cầu đặt cọc
                    </Button>
                  ) : null}
                  {depositPolicy.canSubmitIntent ? (
                    <Button
                      type="button"
                      variant="outline"
                      className="rounded-lg"
                      disabled={anyActionPending}
                      onClick={() => intentMutation.mutate()}
                    >
                      Tôi sẽ tự thanh toán
                    </Button>
                  ) : null}
                  {depositPolicy.canRevokeIntent ? (
                    <Button
                      type="button"
                      variant="outline"
                      className="rounded-lg"
                      disabled={anyActionPending}
                      onClick={() => revokeMutation.mutate()}
                    >
                      Hủy tự thanh toán
                    </Button>
                  ) : null}
                </div>
              ) : (
                <EmptyState
                  title={noDepositActionTitle}
                  description={depositPolicy.noActionMessage ?? "Hiện không cần thao tác thêm với đặt cọc."}
                />
              )}
            </section>

            <section className="space-y-3">
              <div>
                <h3 className="text-lg font-semibold">Thanh toán đặt cọc</h3>
                <p className="text-sm text-muted-foreground">
                  Mở phiên thanh toán từ hệ thống đặt cọc hiện có khi Mộc Sen yêu cầu đặt cọc cho món đặt trước.
                </p>
              </div>
              {session && sessionPolicy ? (
                <div className="space-y-3">
                  {sessionPolicy.lifecycle === "failed" || sessionPolicy.lifecycle === "expired" ? (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                      Thanh toán cọc chưa thành công. Đặt bàn của bạn vẫn được giữ. Món đặt trước chưa được xác nhận chuẩn bị.
                    </div>
                  ) : null}
                  <PaymentSessionCard
                    surfaceLabel="Đặt cọc"
                    session={session}
                    policy={sessionPolicy}
                    refreshPending={refreshSessionMutation.isPending}
                    confirmPending={confirmSessionMutation.isPending}
                    onRefresh={() => refreshSessionMutation.mutate()}
                    onConfirm={() => confirmSessionMutation.mutate()}
                  />
                </div>
              ) : (
                <EmptyState
                  title={paymentSupport.title}
                  description={paymentSupport.description}
                  action={
                    depositPolicy.canCreatePaymentSession ? (
                      <Button
                        type="button"
                        className="rounded-lg"
                        disabled={anyActionPending}
                        onClick={() => createSessionMutation.mutate()}
                      >
                        {createSessionMutation.isPending ? "Đang mở thanh toán" : "Thanh toán đặt cọc"}
                      </Button>
                    ) : undefined
                  }
                />
              )}
            </section>
            {actionBoundary ? (
              actionBoundary.kind === "error" ? (
                <Alert variant="destructive" className="rounded-lg">
                  <AlertDescription className="space-y-3">
                    <p>{customerFriendlyDepositMessage(actionBoundary.error)}</p>
                    {sessionActionError ? (
                      <p>Thanh toán cọc chưa thành công. Đặt bàn của bạn vẫn được giữ. Món đặt trước chưa được xác nhận chuẩn bị.</p>
                    ) : null}
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      className="rounded-lg bg-background"
                      onClick={() => {
                    setPaymentSessionRestoreError(null);

                    if (storedSessionId !== null && paymentSession === null) {
                      paymentSessionQuery.refetch();
                      return;
                    }

                    refreshWorkspace();
                      }}
                    >
                      {sessionActionError ? "Thanh toán lại" : "Tải lại trạng thái"}
                    </Button>
                  </AlertDescription>
                </Alert>
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

function getDepositSummaryCopy(state: "pending" | "paid" | "refunded" | "not_required") {
  switch (state) {
    case "not_required":
      return {
        title: "Không cần đặt cọc",
        description: "Lịch đặt này chưa cần đặt cọc. Bạn có thể chọn món tại nhà hàng hoặc chọn món trước nếu muốn.",
      };
    case "paid":
      return {
        title: "Đặt cọc đã xử lý",
        description: "Khoản đặt cọc đã được ghi nhận cho lịch đặt này.",
      };
    case "refunded":
      return {
        title: "Đặt cọc đã hoàn",
        description: "Khoản hoàn đặt cọc đã được ghi nhận cho lịch đặt này.",
      };
    case "pending":
    default:
      return {
        title: "Cần xử lý đặt cọc",
        description: "Nếu món đặt trước cần đặt cọc, Mộc Sen sẽ dùng phiên thanh toán an toàn bên dưới.",
      };
  }
}

function getDepositNoActionTitle(state: "pending" | "paid" | "refunded" | "not_required"): string {
  switch (state) {
    case "not_required":
      return "Không cần đặt cọc";
    case "paid":
      return "Đặt cọc đã xử lý";
    case "refunded":
      return "Đặt cọc đã hoàn";
    case "pending":
    default:
      return "Chưa có thao tác đặt cọc";
  }
}
