"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
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
  createBillPaymentSession,
  confirmBillPaymentSession,
  getActiveOrder,
  getBill,
  getBillPreview,
  refreshBillPaymentSession,
  type BillPaymentSessionResult,
} from "./api";
import {
  getBillPaymentSupportState,
  getBillingPolicy,
  getBillSummaryState,
  getPaymentSessionPolicy,
  parseActiveOrderContract,
  parseBillContract,
} from "@/features/reservations/state";

export function BillingPanel({
  reservation,
  onReservationChanged,
}: {
  reservation: ReservationSummary;
  onReservationChanged?: (reservation?: ReservationSummary) => void;
}) {
  const [paymentSession, setPaymentSession] = useState<BillPaymentSessionResult | null>(null);
  const reservationId = reservation.reservation_id;
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
  const refreshWorkspace = async ({ clearSession = false }: { clearSession?: boolean } = {}) => {
    if (clearSession) {
      setPaymentSession(null);
    }

    await Promise.all([
      activeOrderQuery.refetch(),
      billPreviewQuery.refetch(),
      canRequestBillDetail ? billQuery.refetch() : Promise.resolve(),
    ]);
    onReservationChanged?.();
  };
  const syncPaymentSession = async (result: BillPaymentSessionResult) => {
    setPaymentSession(result);
    const nextPolicy = getPaymentSessionPolicy(result.payment_session, { surface: "bill" });

    if (nextPolicy.terminal) {
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
    onSuccess: syncPaymentSession,
    onError: handleConflict,
  });
  const refreshSessionMutation = useMutation({
    mutationFn: () => {
      const session = paymentSession?.payment_session;

      if (!session) {
        throw new Error("No payment session is available to refresh.");
      }

      return refreshBillPaymentSession(reservationId, session.bill_payment_session_id, session.row_version);
    },
    onSuccess: syncPaymentSession,
    onError: handleConflict,
  });
  const confirmSessionMutation = useMutation({
    mutationFn: () => {
      const session = paymentSession?.payment_session;

      if (!session) {
        throw new Error("No payment session is available to confirm.");
      }

      return confirmBillPaymentSession(reservationId, session.bill_payment_session_id, session.row_version);
    },
    onSuccess: syncPaymentSession,
    onError: handleConflict,
  });

  const billState = parseBillContract((canRequestBillDetail ? billQuery.data?.bill : undefined) ?? billPreviewQuery.data?.bill_preview, reservation);
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
  const session = paymentSession?.payment_session;
  const sessionPolicy = session ? getPaymentSessionPolicy(session, { surface: "bill" }) : null;
  const paymentSupport = getBillPaymentSupportState({
    bill: billState,
    canCreatePaymentSession: billingPolicy.canCreatePaymentSession,
    hasBill: billingPolicy.hasBill,
    hasActiveOrder: billingPolicy.hasActiveOrder,
  });
  const sessionActionError = createSessionMutation.error ?? refreshSessionMutation.error ?? confirmSessionMutation.error;
  const actionError = sessionActionError;
  const loadError = activeOrderQuery.error ?? billPreviewQuery.error ?? (canRequestBillDetail ? billQuery.error : null);
  const loadBoundary = loadError ? getSelfServiceBlockedState("bill", loadError, "Bill is unavailable") : null;
  const actionBoundary = actionError
    ? getSelfServiceBlockedState("bill", actionError, isConflictLikeApiError(actionError) ? "Bill details changed" : "Payment session failed")
    : null;
  const noActionTitle =
    billSummary.state === "settled"
      ? "Bill settled"
      : billingPolicy.activeOrderSummary.state === "present"
        ? "Active order in progress"
        : "Bill unavailable";

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
        <CardTitle>Bill and active order</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {activeOrderQuery.isLoading || billPreviewQuery.isLoading || (canRequestBillDetail && billQuery.isLoading) ? <LoadingBlock label="Loading bill" /> : null}
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
                <h3 className="text-lg font-semibold">Bill preview</h3>
                <p className="text-sm text-muted-foreground">Review the current bill and dine-in state before opening or refreshing a payment session.</p>
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Bill status</p>
                  <p className="text-lg font-semibold">{billSummary.title}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{billSummary.description}</p>
                  <p className="mt-4 text-2xl font-semibold">{formatMoney(billingPolicy.amount, billingPolicy.currency)}</p>
                </div>
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Active order</p>
                  <p className="font-medium">{billingPolicy.activeOrderSummary.label}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{billingPolicy.activeOrderSummary.description}</p>
                </div>
              </div>
              {!billingPolicy.canCreatePaymentSession ? (
                <EmptyState title={noActionTitle} description={billingPolicy.noActionMessage ?? "The bill is not ready for payment yet."} />
              ) : null}
            </section>

            <section className="space-y-3">
              <div>
                <h3 className="text-lg font-semibold">Payment session lifecycle</h3>
                <p className="text-sm text-muted-foreground">Open a payment session only when the backend exposes a supported runtime path for this bill.</p>
              </div>
              {session && sessionPolicy ? (
                <PaymentSessionCard
                  surfaceLabel="Bill"
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
                      <Button type="button" className="rounded-lg" disabled={createSessionMutation.isPending} onClick={() => createSessionMutation.mutate()}>
                        {createSessionMutation.isPending ? "Opening payment" : "Continue to bill payment"}
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
