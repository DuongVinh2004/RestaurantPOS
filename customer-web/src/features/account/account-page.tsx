"use client";

import { useQuery } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { PrivacyPanel } from "@/features/privacy/privacy-panel";
import { listVouchers } from "@/features/vouchers/api";
import { useAuth } from "@/providers/auth-provider";
import { queryKeys } from "@/lib/api/query-keys";
import { featureFlags } from "@/lib/config/feature-flags";
import { formatDateTime } from "@/lib/contracts/format";
import { getLoyalty } from "./api";

export function AccountPage() {
  const { profile } = useAuth();
  const loyaltyQuery = useQuery({
    queryKey: queryKeys.account.loyalty,
    queryFn: getLoyalty,
  });
  const vouchersQuery = useQuery({
    queryKey: queryKeys.account.vouchers,
    queryFn: listVouchers,
    enabled: featureFlags.vouchers,
  });

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-6">
      <section className="mb-5">
        <h1 className="text-4xl font-semibold tracking-normal">Account</h1>
        <p className="mt-2 text-muted-foreground">
          {profile?.name ?? "Customer"} can review loyalty, vouchers, exports, and privacy requests here.
        </p>
      </section>

      <div className="grid gap-5 lg:grid-cols-[1fr_1fr]">
        <Card className="rounded-lg">
          <CardHeader>
            <CardTitle>Loyalty</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {loyaltyQuery.isLoading ? <LoadingBlock label="Loading loyalty" /> : null}
            {loyaltyQuery.error ? <ErrorState error={loyaltyQuery.error} title="Loyalty is unavailable" onRetry={() => loyaltyQuery.refetch()} /> : null}
            {loyaltyQuery.data ? (
              <>
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Points</p>
                  <p className="text-3xl font-semibold">{loyaltyQuery.data.data.user.total_points}</p>
                </div>
                <div className="grid gap-2 text-sm">
                  {loyaltyQuery.data.data.transactions.slice(0, 5).map((transaction) => (
                    <div key={`${transaction.txn_id}-${transaction.created_at}`} className="rounded-lg border p-3">
                      <div className="flex justify-between gap-3">
                        <span>{transaction.txn_type ?? "Transaction"}</span>
                        <span className="font-medium">{transaction.points} pts</span>
                      </div>
                      <p className="mt-1 text-muted-foreground">{formatDateTime(transaction.created_at)}</p>
                    </div>
                  ))}
                </div>
              </>
            ) : null}
          </CardContent>
        </Card>

        <Card className="rounded-lg">
          <CardHeader>
            <CardTitle>Vouchers</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {!featureFlags.vouchers ? (
              <EmptyState title="Vouchers are not available" description="This flow is disabled for the current rollout." />
            ) : null}
            {vouchersQuery.isLoading ? <LoadingBlock label="Loading vouchers" /> : null}
            {vouchersQuery.error ? <ErrorState error={vouchersQuery.error} title="Vouchers are unavailable" onRetry={() => vouchersQuery.refetch()} /> : null}
            {vouchersQuery.data?.data.length === 0 ? <EmptyState title="No active vouchers" description="Usable vouchers will appear here." /> : null}
            {vouchersQuery.data?.data.map((voucher) => (
              <div key={voucher.user_voucher_id} className="rounded-lg border p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-semibold">{voucher.voucher_code}</p>
                    <p className="text-sm text-muted-foreground">{voucher.description}</p>
                  </div>
                  <Badge variant="outline" className="rounded-md">{voucher.current_status}</Badge>
                </div>
              </div>
            ))}
          </CardContent>
        </Card>

        <div className="lg:col-span-2">
          <PrivacyPanel />
        </div>
      </div>
    </main>
  );
}
