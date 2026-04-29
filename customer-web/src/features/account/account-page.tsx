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
      title: "Điểm thưởng",
      badge: accountBenefitsRollout.enabled ? "Đã bật" : "Chưa bật",
      summary: loyaltyState?.title ?? benefitsVisibility.title,
      description: loyaltyState?.description ?? benefitsVisibility.description,
    },
    {
      title: "Voucher",
      badge: accountBenefitsRollout.enabled ? "Đã bật" : "Chưa bật",
      summary: voucherWallet?.title ?? benefitsVisibility.title,
      description: voucherWallet?.description ?? benefitsVisibility.description,
    },
    {
      title: "Yêu cầu dữ liệu cá nhân",
      badge: privacyEnabled ? "Đã bật" : "Chưa bật",
      summary: privacyEnabled ? "Có thể gửi yêu cầu" : privacyRollout.disabledTitle,
      description: privacyEnabled ? "Bạn có thể gửi yêu cầu về dữ liệu cá nhân khi nhà hàng bật tính năng này." : privacyRollout.disabledDescription,
    },
    {
      title: "Xuất dữ liệu",
      badge: dataExportEnabled ? "Đã bật" : "Chưa bật",
      summary: dataExportEnabled ? "Có thể xem dữ liệu xuất" : dataExportRollout.disabledTitle,
      description: dataExportEnabled
        ? "Bản xuất dữ liệu tài khoản sẽ hiển thị tại đây."
        : privacyEnabled
          ? dataExportRollout.disabledDescription
          : "Xuất dữ liệu chỉ mở sau khi công cụ dữ liệu cá nhân được bật.",
    },
  ];

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-6">
      <section className="mb-5 space-y-2">
        <h1 className="text-4xl font-semibold tracking-normal">Tài khoản</h1>
        <p className="text-muted-foreground">
          {profile?.name ?? "Khách hàng"} có thể xem điểm thưởng, voucher và các công cụ dữ liệu cá nhân khi nhà hàng bật cho tài khoản.
        </p>
      </section>

      <Card className="mb-5 rounded-lg">
        <CardHeader>
          <CardTitle>Công cụ tài khoản</CardTitle>
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
            <CardTitle>Điểm thưởng</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {!accountBenefitsRollout.enabled ? <EmptyState title={benefitsVisibility.title} description={benefitsVisibility.description} /> : null}
            {loyaltyQuery.isLoading ? <LoadingBlock label="Đang tải điểm thưởng" /> : null}
            {loyaltyQuery.error ? <ErrorState error={loyaltyQuery.error} title="Chưa tải được điểm thưởng" onRetry={() => loyaltyQuery.refetch()} /> : null}
            {loyaltyState ? (
              <>
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Điểm</p>
                  <p className="text-3xl font-semibold">{loyaltyState.totalPoints}</p>
                  <p className="mt-2 text-sm text-muted-foreground">{loyaltyState.description}</p>
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="rounded-lg border p-4">
                    <p className="text-sm text-muted-foreground">Hạng hiện tại</p>
                    <p className="mt-1 font-medium">{loyaltyState.tierLabel ?? "Chưa có hạng"}</p>
                  </div>
                  <div className="rounded-lg border p-4">
                    <p className="text-sm text-muted-foreground">Mốc tiếp theo</p>
                    <p className="mt-1 font-medium">{loyaltyState.nextTierLabel ?? "Chưa có mốc nâng hạng"}</p>
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
                            <span>{transaction.txn_type ?? "Giao dịch"}</span>
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
            <CardTitle>Voucher</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {!accountBenefitsRollout.enabled ? <EmptyState title={benefitsVisibility.title} description={benefitsVisibility.description} /> : null}
            {vouchersQuery.isLoading ? <LoadingBlock label="Đang tải voucher" /> : null}
            {vouchersQuery.error ? <ErrorState error={vouchersQuery.error} title="Chưa tải được ví voucher" onRetry={() => vouchersQuery.refetch()} /> : null}
            {voucherWallet ? (
              <>
                <div className="rounded-lg bg-secondary p-4">
                  <p className="text-sm text-muted-foreground">Trạng thái ví</p>
                  <p className="text-lg font-semibold">{voucherWallet.title}</p>
                  <p className="mt-2 text-sm text-muted-foreground">{voucherWallet.description}</p>
                </div>
                {voucherWallet.state === "empty" ? (
                  <EmptyState title={voucherWallet.title} description={voucherWallet.description} />
                ) : (
                  <>
                    <div className="grid gap-3 sm:grid-cols-4">
                      <AccountMetricCard label="Có thể dùng" value={voucherWallet.counts.available} />
                      <AccountMetricCard label="Chưa đủ điều kiện" value={voucherWallet.counts.notEligible} />
                      <AccountMetricCard label="Hết hạn" value={voucherWallet.counts.expired} />
                      <AccountMetricCard label="Không khả dụng" value={voucherWallet.counts.unavailable} />
                    </div>
                    <div className="rounded-lg border border-dashed bg-secondary/20 p-4 text-sm text-muted-foreground">
                      <p className="font-medium text-foreground">Ví chỉ để xem</p>
                      <p className="mt-1">
                        Voucher sẽ hiển thị tại đây. Thao tác áp dụng hoặc gỡ voucher nằm trong chi tiết lịch đặt khi nhà hàng bật tính năng.
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
