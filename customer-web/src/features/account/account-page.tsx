"use client";

import { useQuery } from "@tanstack/react-query";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { PrivacyPanel } from "@/features/privacy/privacy-panel";
import { listVouchers } from "@/features/vouchers/api";
import { getBenefitsVisibilityState, getLoyaltyAccountState, getVoucherWalletState } from "@/features/vouchers/state";
import { useAuth } from "@/providers/auth-provider";
import { queryKeys } from "@/lib/api/query-keys";
import { customerWebRollout } from "@/lib/config/feature-flags";
import { formatDateTime } from "@/lib/contracts/format";
import { getLoyalty } from "./api";

export function AccountPage() {
  const { profile } = useAuth();
  const accountBenefitsRollout = customerWebRollout.accountBenefits;
  const privacyRollout = customerWebRollout.privacyRequests;
  const dataExportRollout = customerWebRollout.dataExport;
  const benefitsVisibility = getBenefitsVisibilityState(accountBenefitsRollout);
  const privacyEnabled = privacyRollout.enabled;
  const dataExportEnabled = privacyEnabled && dataExportRollout.enabled;
  const loyaltyQuery = useQuery({
    queryKey: queryKeys.account.loyalty,
    queryFn: getLoyalty,
    enabled: accountBenefitsRollout.enabled,
  });
  const vouchersQuery = useQuery({
    queryKey: queryKeys.account.vouchers,
    queryFn: () => listVouchers({ bucket: "all", per_page: 24 }),
    enabled: accountBenefitsRollout.enabled,
  });
  const loyaltyState = loyaltyQuery.data ? getLoyaltyAccountState(loyaltyQuery.data) : null;
  const voucherWallet = vouchersQuery.data ? getVoucherWalletState(vouchersQuery.data) : null;
  const shellCards = [
    {
      title: "Loyalty",
      badge: accountBenefitsRollout.enabled ? "Contract-visible" : "Gated",
      summary: loyaltyState?.title ?? benefitsVisibility.title,
      description: loyaltyState?.description ?? benefitsVisibility.description,
    },
    {
      title: "Vouchers",
      badge: accountBenefitsRollout.enabled ? "Contract-visible" : "Gated",
      summary: voucherWallet?.title ?? benefitsVisibility.title,
      description: voucherWallet?.description ?? benefitsVisibility.description,
    },
    {
      title: "Privacy requests",
      badge: privacyEnabled ? "Enabled" : "Gated",
      summary: privacyEnabled ? "Visible for this rollout" : privacyRollout.disabledTitle,
      description: privacyEnabled ? privacyRollout.description : privacyRollout.disabledDescription,
    },
    {
      title: "Data export",
      badge: dataExportEnabled ? "Enabled" : "Gated",
      summary: dataExportEnabled ? "Visible for this rollout" : dataExportRollout.disabledTitle,
      description: dataExportEnabled
        ? dataExportRollout.description
        : privacyEnabled
          ? dataExportRollout.disabledDescription
          : "Data export stays behind the broader privacy rollout and does not open before privacy tools are enabled.",
    },
  ];

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-6">
      <section className="mb-5 space-y-2">
        <h1 className="text-4xl font-semibold tracking-normal">Account</h1>
        <p className="text-muted-foreground">
          {profile?.name ?? "Customer"} can review contract-visible account tools here. Wave 2 benefits stay behind an explicit rollout flag and use reservation-level row-version checks for voucher and loyalty actions.
        </p>
      </section>

      <Card className="mb-5 rounded-lg">
        <CardHeader>
          <CardTitle>Account rollout shell</CardTitle>
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
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {shellCards.map((card) => (
              <div key={card.title} className="rounded-lg border p-4">
                <div className="flex items-start justify-between gap-3">
                  <p className="font-medium">{card.title}</p>
                  <Badge variant="outline" className="rounded-md">
                    {card.badge}
                  </Badge>
                </div>
                <p className="mt-3 text-sm font-medium">{card.summary}</p>
                <p className="mt-1 text-sm text-muted-foreground">{card.description}</p>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      <div className="grid gap-5 lg:grid-cols-[1fr_1fr]">
        <Card className="rounded-lg">
          <CardHeader>
            <CardTitle>Loyalty</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {!accountBenefitsRollout.enabled ? <EmptyState title={benefitsVisibility.title} description={benefitsVisibility.description} /> : null}
            {loyaltyQuery.isLoading ? <LoadingBlock label="Loading loyalty" /> : null}
            {loyaltyQuery.error ? <ErrorState error={loyaltyQuery.error} title="Loyalty is unavailable" onRetry={() => loyaltyQuery.refetch()} /> : null}
            {loyaltyState ? (
              <>
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Points</p>
                  <p className="text-3xl font-semibold">{loyaltyState.totalPoints}</p>
                  <p className="mt-2 text-sm text-muted-foreground">{loyaltyState.description}</p>
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="rounded-lg border p-4">
                    <p className="text-sm text-muted-foreground">Current tier</p>
                    <p className="mt-1 font-medium">{loyaltyState.tierLabel ?? "No tier yet"}</p>
                  </div>
                  <div className="rounded-lg border p-4">
                    <p className="text-sm text-muted-foreground">Next threshold</p>
                    <p className="mt-1 font-medium">{loyaltyState.nextTierLabel ?? "No tier upgrade exposed right now"}</p>
                  </div>
                </div>
                {loyaltyState.state === "empty" ? (
                  <EmptyState title={loyaltyState.title} description={loyaltyState.transactionDescription} />
                ) : (
                  <div className="space-y-2">
                    <div>
                      <p className="font-medium">{loyaltyState.transactionTitle}</p>
                      <p className="mt-1 text-sm text-muted-foreground">{loyaltyState.transactionDescription}</p>
                    </div>
                    <div className="grid gap-2 text-sm">
                      {loyaltyQuery.data?.transactions.slice(0, 5).map((transaction) => (
                        <div key={`${transaction.txn_id}-${transaction.created_at}`} className="rounded-lg border p-3">
                          <div className="flex justify-between gap-3">
                            <span>{transaction.txn_type ?? "Transaction"}</span>
                            <span className="font-medium">{transaction.points} pts</span>
                          </div>
                          <p className="mt-1 text-muted-foreground">{formatDateTime(transaction.created_at)}</p>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </>
            ) : null}
          </CardContent>
        </Card>

        <Card className="rounded-lg">
          <CardHeader>
            <CardTitle>Vouchers</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {!accountBenefitsRollout.enabled ? <EmptyState title={benefitsVisibility.title} description={benefitsVisibility.description} /> : null}
            {vouchersQuery.isLoading ? <LoadingBlock label="Loading vouchers" /> : null}
            {vouchersQuery.error ? <ErrorState error={vouchersQuery.error} title="Voucher wallet is unavailable" onRetry={() => vouchersQuery.refetch()} /> : null}
            {voucherWallet ? (
              <>
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Wallet state</p>
                  <p className="text-lg font-semibold">{voucherWallet.title}</p>
                  <p className="mt-2 text-sm text-muted-foreground">{voucherWallet.description}</p>
                </div>
                {voucherWallet.state === "empty" ? (
                  <EmptyState title={voucherWallet.title} description={voucherWallet.description} />
                ) : (
                  <>
                    <div className="grid gap-3 sm:grid-cols-4">
                      <AccountMetricCard label="Available" value={voucherWallet.counts.available} />
                      <AccountMetricCard label="Not eligible" value={voucherWallet.counts.notEligible} />
                      <AccountMetricCard label="Expired" value={voucherWallet.counts.expired} />
                      <AccountMetricCard label="Unavailable" value={voucherWallet.counts.unavailable} />
                    </div>
                    <div className="rounded-lg border border-dashed bg-secondary/20 p-4 text-sm text-muted-foreground">
                      <p className="font-medium text-foreground">Read-only wallet</p>
                      <p className="mt-1">
                        Voucher wallet rows are visible here because the contract exists. Apply and remove actions happen from the reservation benefits panel with the latest reservation row version.
                      </p>
                      <p className="mt-2">{voucherWallet.summary}</p>
                    </div>
                    <div className="space-y-3">
                      {voucherWallet.items.map((item) => (
                        <div key={item.voucher.user_voucher_id} className="rounded-lg border p-4">
                          <div className="flex items-start justify-between gap-3">
                            <div>
                              <p className="font-semibold">{item.voucher.voucher_code}</p>
                              <p className="text-sm text-muted-foreground">{item.voucher.description}</p>
                            </div>
                            <Badge variant="outline" className="rounded-md">
                              {item.badgeLabel}
                            </Badge>
                          </div>
                          <p className="mt-3 text-sm font-medium">{item.title}</p>
                          <p className="mt-1 text-sm text-muted-foreground">{item.description}</p>
                        </div>
                      ))}
                    </div>
                  </>
                )}
              </>
            ) : null}
          </CardContent>
        </Card>

        <div className="lg:col-span-2">
          <PrivacyPanel />
        </div>
      </div>
    </main>
  );
}

function AccountMetricCard({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border p-4">
      <p className="text-sm text-muted-foreground">{label}</p>
      <p className="mt-1 text-2xl font-semibold">{value}</p>
    </div>
  );
}
