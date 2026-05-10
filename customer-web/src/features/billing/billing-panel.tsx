"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { OrderTrackingCard } from "@/features/orders/order-tracking-card";
import { getOrderTrackingState } from "@/features/orders/order-tracking";
import { PaymentBreakdown, type PaymentBreakdownLine } from "@/features/payments/payment-breakdown";
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
import { trackCustomerEvent } from "@/lib/analytics/events";
import { queryKeys } from "@/lib/api/query-keys";
import { formatMoney } from "@/lib/contracts/format";
import { asRecord, stringValue } from "@/lib/contracts/loose";
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
    onMutate: () => {
      trackCustomerEvent("payment_attempted", { reservation_id: reservationId, surface: "bill" });
      setPaymentSessionRestoreError(null);
    },
    onSuccess: syncPaymentSession,
    onError: handleConflict,
  });
  const refreshSessionMutation = useMutation({
    mutationFn: () => {
      const session = currentPaymentSession?.payment_session;

      if (!session) {
        throw new Error("Chưa có phiên thanh toán hóa đơn để cập nhật.");
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
        throw new Error("Chưa có phiên thanh toán hóa đơn để xác nhận.");
      }

      return confirmBillPaymentSession(reservationId, session.bill_payment_session_id, session.row_version);
    },
    onMutate: () => setPaymentSessionRestoreError(null),
    onSuccess: syncPaymentSession,
    onError: handleConflict,
  });

  const billSource = (canRequestBillDetail ? billQuery.data?.bill : undefined) ?? billPreviewQuery.data?.bill_preview;
  const activeOrderSource = activeOrderQuery.data?.active_order ?? billPreviewQuery.data?.active_order;
  const billState = parseBillContract(billSource, reservation);
  const activeOrder = parseActiveOrderContract(activeOrderSource);
  const orderTracking = getOrderTrackingState(activeOrderSource);
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
  const billSummaryCopy = getBillSummaryCopy(billSummary.state);
  const activeOrderCopy = getActiveOrderCopy(orderTracking);
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
  const sessionActionPending =
    createSessionMutation.isPending ||
    refreshSessionMutation.isPending ||
    confirmSessionMutation.isPending ||
    paymentSessionQuery.isLoading;
  const loadError = activeOrderQuery.error ?? billPreviewQuery.error ?? (canRequestBillDetail ? billQuery.error : null);
  const loadBoundary = loadError ? getSelfServiceBlockedState("bill", loadError, "Chưa tải được hóa đơn") : null;
  const actionBoundary = sessionActionError
    ? getSelfServiceBlockedState(
        "bill",
        sessionActionError,
        isConflictLikeApiError(sessionActionError) ? "Thông tin hóa đơn đã thay đổi" : "Chưa mở được thanh toán",
      )
    : null;
  const noActionTitle =
    billSummary.state === "settled"
      ? "Hóa đơn đã thanh toán"
      : activeOrderSummary.state === "present"
        ? "Đơn tại bàn đang mở"
        : "Chưa có hóa đơn";
  const billBreakdownLines = buildBillBreakdownLines({
    bill: billSource,
    settlement: billQuery.data?.settlement,
    fallbackTotal: billingPolicy.amount,
    currency: billingPolicy.currency,
  });
  const billBreakdownNote =
    billBreakdownLines.some((line) => line.label === "Phí dịch vụ" || line.label === "Thuế")
      ? undefined
      : "API hóa đơn cho khách hàng hiện chưa tách riêng phí dịch vụ và thuế.";

  useEffect(() => {
    if (!orderTracking.present || orderTracking.terminal || activeOrderQuery.isFetching) {
      return;
    }

    const timer = window.setTimeout(() => {
      activeOrderQuery.refetch();
    }, 15_000);

    return () => window.clearTimeout(timer);
  }, [
    activeOrderQuery,
    activeOrderQuery.isFetching,
    activeOrderQuery.refetch,
    orderTracking.present,
    orderTracking.status,
    orderTracking.terminal,
  ]);

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
        <CardTitle>Hóa đơn và theo dõi món</CardTitle>
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
                  Kiểm tra tổng tiền hiện tại và trạng thái món trước khi mở phiên thanh toán cho khách hàng.
                </p>
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Trạng thái hóa đơn</p>
                  <p className="text-lg font-semibold">{billSummaryCopy.title}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{billSummaryCopy.description}</p>
                  <p className="mt-4 text-2xl font-semibold">{formatMoney(billingPolicy.amount, billingPolicy.currency)}</p>
                </div>
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Đơn tại bàn</p>
                  <p className="font-medium">{activeOrderCopy.label}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{activeOrderCopy.description}</p>
                </div>
              </div>
              <PaymentBreakdown
                title="Chi tiết tổng tiền"
                description="Chỉ hiển thị các trường API hóa đơn cho khách hàng trả về."
                lines={billBreakdownLines}
                note={billBreakdownNote}
              />
              <OrderTrackingCard
                activeOrder={activeOrderSource}
                isRefreshing={activeOrderQuery.isFetching}
                onRefresh={() => activeOrderQuery.refetch()}
              />
              {!billingPolicy.canCreatePaymentSession ? (
                <EmptyState
                  title={noActionTitle}
                  description={billingPolicy.noActionMessage ?? "Hóa đơn chưa sẵn sàng để khách hàng thanh toán trực tuyến."}
                />
              ) : null}
            </section>

            <section className="space-y-3">
              <div>
                <h3 className="text-lg font-semibold">Thanh toán hóa đơn</h3>
                <p className="text-sm text-muted-foreground">
                  Chỉ mở thanh toán trực tuyến khi nhà hàng đã chốt hóa đơn cho khách.
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
                        disabled={sessionActionPending}
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

function buildBillBreakdownLines({
  bill,
  settlement,
  fallbackTotal,
  currency,
}: {
  bill: unknown;
  settlement: unknown;
  fallbackTotal: string | null;
  currency: string;
}): PaymentBreakdownLine[] {
  const billRecord = asRecord(bill);
  const settlementRecord = asRecord(settlement);
  const totals = asRecord(billRecord?.totals) ?? asRecord(billRecord?.financial_summary);
  const resolvedCurrency =
    stringValue(billRecord, ["currency"]) ??
    stringValue(totals, ["currency"]) ??
    stringValue(settlementRecord, ["currency"]) ??
    currency;

  const lineCandidates: Array<PaymentBreakdownLine | null> = [
    createBreakdownLine("Tạm tính", resolvedCurrency, billRecord, totals, ["computed_subtotal_amount", "subtotal_amount", "subtotal"]),
    createBreakdownLine("Giảm giá", resolvedCurrency, billRecord, totals, ["discount_amount", "discount"]),
    createBreakdownLine("Phí dịch vụ", resolvedCurrency, billRecord, totals, ["service_fee_amount", "service_fee"]),
    createBreakdownLine("Thuế", resolvedCurrency, billRecord, totals, ["tax_amount", "tax"]),
    createBreakdownLine("Đặt cọc", resolvedCurrency, billRecord, settlementRecord, ["deposit_applied_amount", "deposit_applied", "deposit_net"]),
    createBreakdownLine("Còn phải trả", resolvedCurrency, billRecord, totals, ["total_due_amount", "total_due", "amount_due", "total"], true) ??
      (fallbackTotal ? { label: "Còn phải trả", amount: fallbackTotal, currency: resolvedCurrency, emphasis: true } : null),
  ];

  return lineCandidates.filter((line): line is PaymentBreakdownLine => line !== null);
}

function createBreakdownLine(
  label: string,
  currency: string,
  primary: Record<string, unknown> | null,
  secondary: Record<string, unknown> | null,
  keys: string[],
  emphasis = false,
): PaymentBreakdownLine | null {
  const amount = stringValue(primary, keys) ?? stringValue(secondary, keys);

  if (amount === null) {
    return null;
  }

  return {
    label,
    amount,
    currency,
    emphasis,
  };
}

function getBillSummaryCopy(state: "available" | "unavailable" | "settled") {
  switch (state) {
    case "settled":
      return {
        title: "Hóa đơn đã thanh toán",
        description: "Hóa đơn này đã thanh toán hoặc không còn số tiền phải trả.",
      };
    case "available":
      return {
        title: "Đã có hóa đơn",
        description: "Nhà hàng đã có thông tin hóa đơn cho lịch đặt này.",
      };
    case "unavailable":
    default:
      return {
        title: "Chưa có hóa đơn cuối",
        description: "Hóa đơn cuối cho lịch đặt này chưa sẵn sàng.",
      };
  }
}

function getActiveOrderCopy(orderTracking: ReturnType<typeof getOrderTrackingState>) {
  if (!orderTracking.present) {
    return {
      label: "Chưa có đơn đang mở",
      description: "Theo dõi món sẽ hiển thị sau khi nhà hàng mở đơn.",
    };
  }

  return {
    label: orderTracking.rawStatus ?? orderTracking.status,
    description: orderTracking.terminal
      ? "Đơn này đã đến trạng thái cuối."
      : "Đơn này vẫn đang được xử lý.",
  };
}
