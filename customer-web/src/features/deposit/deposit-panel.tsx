"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { StatusBadge } from "@/components/status/status-badge";
import { ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { queryKeys } from "@/lib/api/query-keys";
import { formatMoney, stringValue } from "@/lib/contracts/format";
import type { CustomerDepositPaymentSessionEnvelope } from "@/lib/contracts/generated/restaurantpos-sdk";
import {
  acknowledgeDeposit,
  createDepositPaymentSession,
  getDepositPreview,
  revokeDepositIntent,
  submitDepositIntent,
} from "./api";

export function DepositPanel({ reservationId, rowVersion }: { reservationId: number; rowVersion: number }) {
  const queryClient = useQueryClient();
  const [paymentSession, setPaymentSession] = useState<CustomerDepositPaymentSessionEnvelope | null>(null);
  const depositQuery = useQuery({
    queryKey: queryKeys.reservations.deposit(reservationId),
    queryFn: () => getDepositPreview(reservationId),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: queryKeys.reservations.deposit(reservationId) });
  const acknowledgeMutation = useMutation({ mutationFn: () => acknowledgeDeposit(reservationId, rowVersion), onSuccess: invalidate });
  const intentMutation = useMutation({ mutationFn: () => submitDepositIntent(reservationId, rowVersion), onSuccess: invalidate });
  const revokeMutation = useMutation({ mutationFn: () => revokeDepositIntent(reservationId, rowVersion), onSuccess: invalidate });
  const createSessionMutation = useMutation({
    mutationFn: () => createDepositPaymentSession(reservationId, rowVersion),
    onSuccess(result) {
      setPaymentSession(result);
    },
  });

  const deposit = depositQuery.data?.data.deposit;
  const depositRecord = deposit as Record<string, unknown> | undefined;
  const amount = stringValue(depositRecord, ["amount_due", "required_amount", "amount"]);
  const currency = stringValue(depositRecord, ["currency"]) ?? "USD";
  const status = stringValue(depositRecord, ["status", "deposit_status"]);

  return (
    <Card className="rounded-lg">
      <CardHeader>
        <CardTitle>Deposit</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {depositQuery.isLoading ? <LoadingBlock label="Loading deposit" /> : null}
        {depositQuery.error ? <ErrorState error={depositQuery.error} title="Deposit is unavailable" onRetry={() => depositQuery.refetch()} /> : null}
        {depositQuery.data ? (
          <>
            <div className="flex items-start justify-between gap-3 rounded-lg bg-secondary p-4">
              <div>
                <p className="text-sm text-muted-foreground">Amount due</p>
                <p className="text-2xl font-semibold">{formatMoney(amount, currency)}</p>
              </div>
              <StatusBadge status={status ?? "Pending"} />
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
              <Button type="button" variant="outline" className="rounded-lg" disabled={acknowledgeMutation.isPending} onClick={() => acknowledgeMutation.mutate()}>
                Acknowledge
              </Button>
              <Button type="button" variant="outline" className="rounded-lg" disabled={intentMutation.isPending} onClick={() => intentMutation.mutate()}>
                Submit intent
              </Button>
              <Button type="button" variant="outline" className="rounded-lg" disabled={revokeMutation.isPending} onClick={() => revokeMutation.mutate()}>
                Revoke intent
              </Button>
              <Button type="button" className="rounded-lg" disabled={createSessionMutation.isPending} onClick={() => createSessionMutation.mutate()}>
                Create payment session
              </Button>
            </div>
            {paymentSession ? (
              <div className="rounded-lg border p-4 text-sm">
                <div className="flex items-center justify-between gap-3">
                  <span className="font-medium">Session {paymentSession.data.payment_session.provider_session_code}</span>
                  <StatusBadge status={paymentSession.data.payment_session.session_status} />
                </div>
              </div>
            ) : null}
            {(acknowledgeMutation.error || intentMutation.error || revokeMutation.error || createSessionMutation.error) ? (
              <ErrorState
                error={acknowledgeMutation.error ?? intentMutation.error ?? revokeMutation.error ?? createSessionMutation.error}
                title="Deposit action failed"
              />
            ) : null}
          </>
        ) : null}
      </CardContent>
    </Card>
  );
}
