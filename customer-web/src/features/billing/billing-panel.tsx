"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { PaymentSessionCard } from "@/features/payments/payment-session-card";
import {
  clearStoredCustomerPaymentSession,
  readStoredCustomerPaymentSession,
  storeCustomerPaymentSession,
} from "@/features/payments/session-storage";
import { getSelfServiceBlockedState } from "@/features/reservations/self-service-boundary";
import {
  getActiveOrderSummaryState,
  getBillPaymentSupportState,
  getBillingPolicy,
  getBillSummaryState,
  getPaymentSessionPolicy,
  parseActiveOrderContract,
  parseBillContract,
} from "@/features/reservations/state";
import { isConflictLikeApiError, normalizeApiError } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import { formatMoney } from "@/lib/contracts/format";
import type { ReservationSummary } from "@/lib/contracts/generated/restaurantpos-sdk";
import {
  createBillPaymentSession,
  confirmBillPaymentSession,
  getActiveOrder,
  getBill,
  getBillPaymentSession,
  getBillPreview,
  refreshBillPaymentSession,
  type BillPaymentSessionResult,
} from "./api";

export function BillingPanel({
  reservation,
  onReservationChanged,
}: {
  reservation: ReservationSummary;
  onReservationChanged?: (reservation?: ReservationSummary) => void;
}) {
  const reservationId = reservation.reservation_id;
  const [paymentSession, setPaymentSession] = useState<BillPaymentSessionResult | null>(null);
  const [storedSessionId, setStoredSessionId] = useState<number | null>(
    () => readStoredCustomerPaymentSession("bill", reservationId)?.session_id ?? null,
  );
  const [paymentSessionRestoreError, setPaymentSessionRestoreError] = useState<unknown>(null);
  const activeOrderQuery = useQuery({
    queryKey: queryKeys.reservations.activeOrder(reservationId),
    queryFn: () => getActiveOrder(reservationId),
  });
  const billPreviewQuery = useQuery({
    queryKey: queryKeys.reservations.billPreview(reservationId),
    queryFn: () => getBillPreview(reservationId),
  });
  const canRequestBillDetail = reservation.status === "Reserved" || reservation.status === "Completed";
  const billQuery = useQuery({
    queryKey: queryKeys.reservations.bill(reservationId),
    queryFn: () => getBill(reservationId),
    enabled: canRequestBillDetail,
  });
  const paymentSessionQuery = useQuery({
    queryKey: queryKeys.reservations.billPaymentSession(reservationId, storedSessionId),
    queryFn: async () => {
      setPaymentSessionRestoreError(null);

      try {
        const result = await getBillPaymentSession(reservationId, storedSessionId as number);
        const restoredPolicy = getPaymentSessionPolicy(result.payment_session, { surface: "bill" });

        if (restoredPolicy.terminal) {
          clearStoredCustomerPaymentSession("bill", reservationId);
          setStoredSessionId(null);
          setPaymentSession(result);
        }

        return result;
      } catch (error) {
        setPaymentSessionRestoreError(error);
        const normalized = normalizeApiError(error);

        if (normalized.kind === "unauthorized" || normalized.kind === "forbidden" || normalized.kind === "not_found") {
          clearStoredCustomerPaymentSession("bill", reservationId);
          setStoredSessionId(null);
          setPaymentSession(null);
        }

        throw error;
      }
    },
    enabled: storedSessionId !== null && paymentSession === null,
  });

  const clearStoredSession = () => {
    clearStoredCustomerPaymentSession("bill", reservationId);
    setStoredSessionId(null);
  };

  const refreshWorkspace = async ({ clearSession = false }: { clearSession?: boolean } = {}) => {
    if (clearSession) {
      setPaymentSession(null);
      clearStoredSession();
    }

    setPaymentSessionRestoreError(null);
    await Promise.all([
      activeOrderQuery.refetch(),
      billPreviewQuery.refetch(),
      canRequestBillDetail ? billQuery.refetch() : Promise.resolve(),
    ]);
    onReservationChanged?.();
  };
  const currentPaymentSession = paymentSession ?? paymentSessionQuery.data ?? null;

  const syncPaymentSession = async (result: BillPaymentSessionResult) => {
    setPaymentSession(result);
    setPaymentSessionRestoreError(null);
    storeCustomerPaymentSession("bill", reservationId, result.payment_session.bill_payment_session_id);
    setStoredSessionId(result.payment_session.bill_payment_session_id);

    const nextPolicy = getPaymentSessionPolicy(result.payment_session, { surface: "bill" });

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

  const createSessionMutation = useMutation({
    mutationFn: () => createBillPaymentSession(reservationId, reservation.row_version),
    onMutate: () => setPaymentSessionRestoreError(null),
    onSuccess: syncPaymentSession,
    onError: handleConflict,
  });
  const refreshSessionMutation = useMutation({
    mutationFn: () => {
      const session = currentPaymentSession?.payment_session;

      if (!session) {
        throw new Error("Chưa có phiên thanh toán để cập nhật.");
      }

      return refreshBillPaymentSession(reservationId, session.bill_payment_session_id, session.row_version);
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

      return confirmBillPaymentSession(reservationId, session.bill_payment_session_id, session.row_version);
    },
    onMutate: () => setPaymentSessionRestoreError(null),
    onSuccess: syncPaymentSession,
    onError: handleConflict,
  });

  const billState = parseBillContract(
    (canRequestBillDetail ? billQuery.data?.bill : undefined) ?? billPreviewQuery.data?.bill_preview,
    reservation,
  );
  const activeOrder = parseActiveOrderContract(activeOrderQuery.data?.active_order ?? billPreviewQuery.data?.active_order);
  const billingPolicy = getBillingPolicy({
    reservation,
    bill: billState,
    activeOrder,
  });
  const billSummary = getBillSummaryState({
    reservation,
    bill: billState,
  });
  const activeOrderSummary = getActiveOrderSummaryState(activeOrder);
  const session = currentPaymentSession?.payment_session;
  const sessionPolicy = session ? getPaymentSessionPolicy(session, { surface: "bill" }) : null;
  const paymentSupport = getBillPaymentSupportState({
    bill: billState,
    canCreatePaymentSession: billingPolicy.canCreatePaymentSession,
    hasBill: billingPolicy.hasBill,
    hasActiveOrder: billingPolicy.hasActiveOrder,
  });
  const sessionActionError =
    paymentSessionRestoreError ?? createSessionMutation.error ?? refreshSessionMutation.error ?? confirmSessionMutation.error;
  const loadError = activeOrderQuery.error ?? billPreviewQuery.error ?? (canRequestBillDetail ? billQuery.error : null);
  const loadBoundary = loadError ? getSelfServiceBlockedState("bill", loadError, "Chưa tải được hóa đơn") : null;
  const actionBoundary = sessionActionError
    ? getSelfServiceBlockedState(
        "bill",
        sessionActionError,
        isConflictLikeApiError(sessionActionError) ? "Hóa đơn đã thay đổi" : "Chưa mở được thanh toán",
      )
    : null;
  const noActionTitle =
    billSummary.state === "settled"
      ? "Hóa đơn đã thanh toán"
      : activeOrderSummary.state === "present"
        ? "Đơn tại bàn đang mở"
        : "Chưa có hóa đơn";

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
    session?.bill_payment_session_id,
    session?.row_version,
    sessionPolicy,
    sessionPolicy?.autoRefreshMs,
    sessionPolicy?.refreshMode,
  ]);

  return (
    <Card className="rounded-lg">
      <CardHeader>
        <CardTitle>Hóa đơn và món đang dùng</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {activeOrderQuery.isLoading || billPreviewQuery.isLoading || (canRequestBillDetail && billQuery.isLoading) ? (
          <LoadingBlock label="Đang tải hóa đơn" />
        ) : null}
        {paymentSessionQuery.isLoading && storedSessionId !== null && paymentSession === null ? (
          <LoadingBlock label="Đang khôi phục phiên thanh toán hóa đơn" />
        ) : null}
        {loadBoundary ? (
          loadBoundary.kind === "error" ? (
            <ErrorState
              error={loadBoundary.error}
              title={loadBoundary.title}
              onRetry={() => {
                activeOrderQuery.refetch();
                billPreviewQuery.refetch();
                if (canRequestBillDetail) {
                  billQuery.refetch();
                }
              }}
            />
          ) : (
            <EmptyState title={loadBoundary.title} description={loadBoundary.description} />
          )
        ) : null}
        {billQuery.data || billPreviewQuery.data ? (
          <>
            <section className="space-y-3">
              <div>
                <h3 className="text-lg font-semibold">Tóm tắt hóa đơn</h3>
                <p className="text-sm text-muted-foreground">
                  Xem số tiền hiện tại và trạng thái đơn tại bàn trước khi thanh toán.
                </p>
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Trạng thái hóa đơn</p>
                  <p className="text-lg font-semibold">{billSummary.title}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{billSummary.description}</p>
                  <p className="mt-4 text-2xl font-semibold">{formatMoney(billingPolicy.amount, billingPolicy.currency)}</p>
                </div>
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Đơn tại bàn</p>
                  <p className="font-medium">{activeOrderSummary.label}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{activeOrderSummary.description}</p>
                </div>
              </div>
              {!billingPolicy.canCreatePaymentSession ? (
                <EmptyState
                  title={noActionTitle}
                  description={billingPolicy.noActionMessage ?? "Hóa đơn chưa sẵn sàng để thanh toán."}
                />
              ) : null}
            </section>

            <section className="space-y-3">
              <div>
                <h3 className="text-lg font-semibold">Thanh toán hóa đơn</h3>
                <p className="text-sm text-muted-foreground">
                  Chỉ mở thanh toán khi nhà hàng đã chốt hóa đơn sẵn sàng cho khách.
                </p>
              </div>
              {session && sessionPolicy ? (
                <PaymentSessionCard
                  surfaceLabel="Hóa đơn"
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
                    billingPolicy.canCreatePaymentSession ? (
                      <Button
                        type="button"
                        className="rounded-lg"
                        disabled={createSessionMutation.isPending}
                        onClick={() => createSessionMutation.mutate()}
                      >
                        {createSessionMutation.isPending ? "Đang mở thanh toán" : "Thanh toán hóa đơn"}
                      </Button>
                    ) : undefined
                  }
                />
              )}
            </section>
            {actionBoundary ? (
              actionBoundary.kind === "error" ? (
                <ErrorState
                  error={actionBoundary.error}
                  title={actionBoundary.title}
                  onRetry={() => {
                    setPaymentSessionRestoreError(null);

                    if (storedSessionId !== null && paymentSession === null) {
                      paymentSessionQuery.refetch();
                      return;
                    }

                    refreshWorkspace();
                  }}
                />
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
