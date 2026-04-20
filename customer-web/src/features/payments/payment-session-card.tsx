"use client";

import { Button } from "@/components/ui/button";
import { StatusBadge } from "@/components/status/status-badge";
import { formatDateTime, formatMoney } from "@/lib/contracts/format";
import type { CustomerBillPaymentSession, CustomerDepositPaymentSession } from "@/lib/contracts/generated/restaurantpos-sdk";
import type { PaymentSessionPolicy } from "@/features/reservations/state";

type PaymentSessionCardProps = {
  surfaceLabel: string;
  session: CustomerDepositPaymentSession | CustomerBillPaymentSession;
  policy: PaymentSessionPolicy;
  refreshPending: boolean;
  confirmPending: boolean;
  onRefresh: () => void;
  onConfirm: () => void;
};

function SessionTile({
  eyebrow,
  title,
  description,
  status,
}: {
  eyebrow: string;
  title: string;
  description: string;
  status?: string | null;
}) {
  return (
    <div className="rounded-lg bg-secondary/50 p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm text-muted-foreground">{eyebrow}</p>
          <p className="mt-1 font-semibold">{title}</p>
          <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        </div>
        {status ? <StatusBadge status={status} /> : null}
      </div>
    </div>
  );
}

function SessionMeta({
  label,
  value,
}: {
  label: string;
  value: string | null;
}) {
  if (!value) {
    return null;
  }

  return (
    <div className="rounded-lg border border-dashed p-3">
      <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
      <p className="mt-1 text-sm">{value}</p>
    </div>
  );
}

export function PaymentSessionCard({
  surfaceLabel,
  session,
  policy,
  refreshPending,
  confirmPending,
  onRefresh,
  onConfirm,
}: PaymentSessionCardProps) {
  const amountLabel = session.amount ? formatMoney(session.amount, session.currency ?? "USD") : null;
  const expiryLabel = session.provider_expires_at ? formatDateTime(session.provider_expires_at) : null;
  const lastCheckedLabel = session.last_reconciled_at ? formatDateTime(session.last_reconciled_at) : null;

  return (
    <div className="space-y-4 rounded-lg border p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm text-muted-foreground">{surfaceLabel} payment session</p>
          <p className="mt-1 text-lg font-semibold">{policy.title}</p>
          <p className="mt-1 text-sm text-muted-foreground">{policy.description}</p>
        </div>
        <StatusBadge status={session.session_status} />
      </div>

      {policy.statusMessage ? (
        <div className="rounded-lg bg-secondary/40 p-3 text-sm text-muted-foreground">{policy.statusMessage}</div>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-3">
        <SessionTile
          eyebrow="Final status"
          title={policy.settlementTitle}
          description={policy.settlementDescription}
          status={session.settlement_status}
        />
        <SessionTile eyebrow="Refresh" title={policy.refreshTitle} description={policy.refreshDescription} />
        <SessionTile eyebrow="Provider support" title={policy.providerSupport.title} description={policy.providerSupport.description} />
      </div>

      <div className="grid gap-3 sm:grid-cols-3">
        <SessionMeta label="Reference" value={session.provider_session_code} />
        <SessionMeta label="Amount" value={amountLabel} />
        <SessionMeta label="Provider expiry" value={expiryLabel} />
        <SessionMeta label="Last checked" value={lastCheckedLabel} />
      </div>

      {policy.canRefresh || policy.canConfirm ? (
        <div className="flex flex-wrap gap-2">
          {policy.canRefresh ? (
            <Button type="button" variant="outline" className="rounded-lg" disabled={refreshPending} onClick={onRefresh}>
              {refreshPending ? "Refreshing status" : "Refresh status"}
            </Button>
          ) : null}
          {policy.canConfirm ? (
            <Button type="button" className="rounded-lg" disabled={confirmPending} onClick={onConfirm}>
              {confirmPending ? "Confirming payment" : "Confirm payment"}
            </Button>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
