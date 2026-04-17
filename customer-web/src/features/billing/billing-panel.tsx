"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { StatusBadge } from "@/components/status/status-badge";
import { ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { queryKeys } from "@/lib/api/query-keys";
import { formatMoney, stringValue } from "@/lib/contracts/format";
import type { CustomerBillPaymentSessionEnvelope } from "@/lib/contracts/generated/restaurantpos-sdk";
import { createBillPaymentSession, getActiveOrder, getBill, getBillPreview } from "./api";

export function BillingPanel({ reservationId, rowVersion }: { reservationId: number; rowVersion: number }) {
  const [paymentSession, setPaymentSession] = useState<CustomerBillPaymentSessionEnvelope | null>(null);
  const activeOrderQuery = useQuery({
    queryKey: queryKeys.reservations.activeOrder(reservationId),
    queryFn: () => getActiveOrder(reservationId),
  });
  const billPreviewQuery = useQuery({
    queryKey: queryKeys.reservations.billPreview(reservationId),
    queryFn: () => getBillPreview(reservationId),
  });
  const billQuery = useQuery({
    queryKey: queryKeys.reservations.bill(reservationId),
    queryFn: () => getBill(reservationId),
  });
  const createSessionMutation = useMutation({
    mutationFn: () => createBillPaymentSession(reservationId, rowVersion),
    onSuccess(result) {
      setPaymentSession(result);
    },
  });

  const bill = billQuery.data?.data.bill ?? billPreviewQuery.data?.data.bill_preview;
  const billRecord = bill as Record<string, unknown> | undefined;
  const amount = stringValue(billRecord, ["total", "payable_amount", "amount_due"]);
  const currency = stringValue(billRecord, ["currency"]) ?? "USD";

  return (
    <Card className="rounded-lg">
      <CardHeader>
        <CardTitle>Bill</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {activeOrderQuery.isLoading || billPreviewQuery.isLoading || billQuery.isLoading ? <LoadingBlock label="Loading bill" /> : null}
        {activeOrderQuery.error || billPreviewQuery.error || billQuery.error ? (
          <ErrorState
            error={activeOrderQuery.error ?? billPreviewQuery.error ?? billQuery.error}
            title="Bill is unavailable"
            onRetry={() => {
              activeOrderQuery.refetch();
              billPreviewQuery.refetch();
              billQuery.refetch();
            }}
          />
        ) : null}
        {billQuery.data || billPreviewQuery.data ? (
          <>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="rounded-lg bg-secondary p-4">
                <p className="text-sm text-muted-foreground">Current bill</p>
                <p className="text-2xl font-semibold">{formatMoney(amount, currency)}</p>
              </div>
              <div className="rounded-lg bg-secondary p-4">
                <p className="text-sm text-muted-foreground">Active order</p>
                <p className="font-medium">{activeOrderQuery.data?.data.active_order ? "Open" : "No active order"}</p>
              </div>
            </div>
            <Button type="button" className="rounded-lg" disabled={createSessionMutation.isPending} onClick={() => createSessionMutation.mutate()}>
              {createSessionMutation.isPending ? "Creating payment session" : "Create bill payment session"}
            </Button>
            {paymentSession ? (
              <div className="rounded-lg border p-4 text-sm">
                <div className="flex items-center justify-between gap-3">
                  <span className="font-medium">Session {paymentSession.data.payment_session.provider_session_code}</span>
                  <StatusBadge status={paymentSession.data.payment_session.session_status} />
                </div>
              </div>
            ) : null}
            {createSessionMutation.error ? <ErrorState error={createSessionMutation.error} title="Payment session failed" /> : null}
          </>
        ) : null}
      </CardContent>
    </Card>
  );
}
