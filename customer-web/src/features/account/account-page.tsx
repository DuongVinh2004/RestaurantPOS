"use client";

import Link from "next/link";
import { useQuery, type UseQueryResult } from "@tanstack/react-query";
import { BellRing, CalendarDays, Heart, ReceiptText, ShieldCheck, Sparkles, UserRound, WalletCards, type LucideIcon } from "lucide-react";
import { AppBadge, AppButton, AppCard, EmptyState, ErrorState, SectionHeader, StatusPill } from "@/components/customer/ui";
import { ResponsivePageShell } from "@/components/customer/layout";
import { LoadingBlock } from "@/components/states/state-blocks";
import { StatusBadge } from "@/components/status/status-badge";
import { FeedbackPanel } from "@/features/feedback/feedback-panel";
import { NotificationPreferencesPanel } from "@/features/notifications/notification-preferences-panel";
import { PrivacyPanel } from "@/features/privacy/privacy-panel";
import { listReservations } from "@/features/reservations/api";
import { listVouchers } from "@/features/vouchers/api";
import { getBenefitsVisibilityState, getLoyaltyAccountState, getVoucherWalletState } from "@/features/vouchers/state";
import { useAuth } from "@/providers/auth-provider";
import { queryKeys } from "@/lib/api/query-keys";
import { customerWebRollout } from "@/lib/config/feature-flags";
import { formatDateTime } from "@/lib/contracts/format";
import { getLoyalty } from "./api";
import { ProfilePreferencesPanel } from "./profile-preferences-panel";

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
  const upcomingReservationsQuery = useQuery({
    queryKey: queryKeys.reservations.list("upcoming"),
    queryFn: () => listReservations("upcoming"),
  });
  const vouchersQuery = useQuery({
    queryKey: queryKeys.account.vouchers,
    queryFn: () => listVouchers({ bucket: "all", per_page: 24 }),
    enabled: accountBenefitsRollout.enabled,
  });

  const loyaltyState = loyaltyQuery.data ? getLoyaltyAccountState(loyaltyQuery.data) : null;
  const voucherWallet = vouchersQuery.data ? getVoucherWalletState(vouchersQuery.data) : null;
  const displayName = loyaltyQuery.data?.user.full_name ?? profile?.name ?? "Khách hàng";

  return (
    <ResponsivePageShell className="space-y-6">
      <section className="space-y-2">
        <AppBadge>Tài khoản</AppBadge>
        <h1 className="text-4xl font-semibold tracking-normal">Hồ sơ của tôi</h1>
        <p className="max-w-3xl text-muted-foreground">
          Quản lý lịch đặt, thông tin liên hệ và sở thích dùng bữa của bạn.
        </p>
      </section>

      <div className="grid gap-6 lg:grid-cols-[280px_1fr] items-start">
        {/* Sidebar */}
        <aside className="space-y-6">
          {/* Profile Card */}
          <AppCard className="p-5 text-center">
            <div className="flex flex-col items-center">
              <div className="flex h-16 w-16 items-center justify-center rounded-full bg-teal-100 text-2xl font-bold text-teal-800">
                {displayName.slice(0, 2).toUpperCase()}
              </div>
              <h2 className="mt-3 text-lg font-semibold">{displayName}</h2>
              {profile?.email || profile?.phone ? (
                <p className="mt-1 text-xs text-muted-foreground">
                  {profile.email ?? profile.phone}
                </p>
              ) : null}
            </div>

            {/* Quick Actions / Shortcuts */}
            <div className="mt-5 border-t pt-4 space-y-2 text-left">
              <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Lối tắt
              </p>
              <ShortcutLink href="/booking" icon={CalendarDays} label="Đặt bàn mới" />
              <ShortcutLink href="/menu" icon={ReceiptText} label="Xem thực đơn" />
            </div>
          </AppCard>
        </aside>

        {/* Main Content Area */}
        <div className="space-y-6">
          <UpcomingReservationsCard query={upcomingReservationsQuery} />

          {accountBenefitsRollout.enabled ? (
            <div className="grid gap-6 md:grid-cols-2">
              <LoyaltyCard
                enabled={accountBenefitsRollout.enabled}
                visibilityTitle={benefitsVisibility.title}
                visibilityDescription={benefitsVisibility.description}
                query={loyaltyQuery}
                state={loyaltyState}
              />
              <VoucherWalletCard
                enabled={accountBenefitsRollout.enabled}
                visibilityTitle={benefitsVisibility.title}
                visibilityDescription={benefitsVisibility.description}
                query={vouchersQuery}
                wallet={voucherWallet}
              />
            </div>
          ) : null}

          <ProfilePreferencesPanel profile={profile} />

          <div className="grid gap-6 md:grid-cols-2">
            <NotificationPreferencesPanel />
            <FeedbackPanel />
          </div>

          {privacyEnabled ? <PrivacyPanel /> : null}
        </div>
      </div>
    </ResponsivePageShell>
  );
}

function ShortcutLink({ href, icon: Icon, label }: { href: string; icon: LucideIcon; label: string }) {
  return (
    <Link href={href} className="flex min-h-11 items-center justify-between gap-3 rounded-lg border bg-background px-3 py-2 text-sm font-semibold transition hover:border-primary/35">
      <span className="flex items-center gap-2">
        <Icon className="h-4 w-4 text-teal-700" />
        {label}
      </span>
      <span className="text-muted-foreground">Mở</span>
    </Link>
  );
}

function UpcomingReservationsCard({
  query,
}: {
  query: UseQueryResult<Awaited<ReturnType<typeof listReservations>>, Error>;
}) {
  return (
    <AppCard className="p-4">
      <div className="space-y-4">
        <SectionHeader
          eyebrow="Lịch đặt"
          title="Lịch đặt sắp tới"
          description="Các lượt ghé sắp tới của bạn sẽ hiển thị tại đây."
          action={
            <AppButton asChild variant="outline">
              <Link href="/reservations">Xem tất cả</Link>
            </AppButton>
          }
        />
        {query.isLoading ? <LoadingBlock label="Đang tải lịch đặt sắp tới" /> : null}
        {query.error ? (
          <ErrorState error={query.error} title="Chưa tải được lịch đặt sắp tới" onRetry={() => query.refetch()} />
        ) : null}
        {!query.isLoading && !query.error && (query.data?.length ?? 0) === 0 ? (
          <EmptyState
            title="Chưa có lịch đặt sắp tới"
            description="Đặt bàn mới khi bạn sẵn sàng đến nhà hàng."
            action={
              <AppButton asChild>
                <Link href="/booking">Đặt bàn</Link>
              </AppButton>
            }
          />
        ) : null}
        <div className="grid gap-3">
          {(query.data ?? []).slice(0, 3).map((reservation) => (
            <Link
              key={reservation.reservation_id}
              href={`/reservations/${reservation.reservation_id}`}
              className="flex flex-col gap-3 rounded-lg border p-4 transition hover:border-primary/50 hover:bg-secondary/30 sm:flex-row sm:items-center sm:justify-between"
            >
              <div>
                <p className="font-semibold">{reservation.reservation_code}</p>
                <p className="mt-1 text-sm text-muted-foreground">
                  {formatDateTime(reservation.start_time ?? reservation.booking_time ?? null)} - {reservation.guest_count ?? "Chưa rõ"} khách
                </p>
              </div>
              <StatusBadge status={reservation.status} />
            </Link>
          ))}
        </div>
      </div>
    </AppCard>
  );
}

function LoyaltyCard({
  enabled,
  visibilityTitle,
  visibilityDescription,
  query,
  state,
}: {
  enabled: boolean;
  visibilityTitle: string;
  visibilityDescription: string;
  query: UseQueryResult<Awaited<ReturnType<typeof getLoyalty>>, Error>;
  state: ReturnType<typeof getLoyaltyAccountState> | null;
}) {
  return (
    <AppCard className="p-4">
      <div className="space-y-4">
        <SectionHeader eyebrow="Điểm thưởng" title="Tóm tắt thành viên" description={visibilityDescription} />
        {!enabled ? <EmptyState title={visibilityTitle} description={visibilityDescription} /> : null}
        {query.isLoading ? <LoadingBlock label="Đang tải điểm thưởng" /> : null}
        {query.error ? <ErrorState error={query.error} title="Chưa tải được điểm thưởng" onRetry={() => query.refetch()} /> : null}
        {state ? (
          <>
            <div className="rounded-lg bg-secondary p-4">
              <p className="text-sm text-muted-foreground">Điểm hiện có</p>
              <p className="text-3xl font-semibold">{state.totalPoints}</p>
              <p className="mt-2 text-sm text-muted-foreground">{state.description}</p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <MetricCard label="Hạng hiện tại" value={state.tierLabel ?? "Chưa có hạng"} />
              <MetricCard label="Hạng tiếp theo" value={state.nextTierLabel ?? "Chưa có hạng tiếp theo"} />
            </div>
            {state.state === "empty" ? (
              <EmptyState title={state.title} description={state.transactionDescription} />
            ) : (
              <div className="space-y-2">
                <div>
                  <p className="font-medium">{state.transactionTitle}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{state.transactionDescription}</p>
                </div>
                <div className="grid gap-2 text-sm">
                  {query.data?.transactions.slice(0, 5).map((transaction) => (
                    <div key={`${transaction.txn_id}-${transaction.created_at}`} className="rounded-lg border p-3">
                      <div className="flex justify-between gap-3">
                        <span>{transaction.txn_type ?? "Thay đổi điểm"}</span>
                        <span className="font-medium">{transaction.points} điểm</span>
                      </div>
                      <p className="mt-1 text-muted-foreground">{formatDateTime(transaction.created_at)}</p>
                    </div>
                  ))}
                </div>
              </div>
            )}
            <div className="rounded-lg border border-dashed bg-secondary/25 p-4 text-sm text-muted-foreground">
              Quyền lợi thành viên và quà sinh nhật sẽ hiển thị khi nhà hàng bổ sung cho tài khoản của bạn.
            </div>
          </>
        ) : null}
      </div>
    </AppCard>
  );
}

function VoucherWalletCard({
  enabled,
  visibilityTitle,
  visibilityDescription,
  query,
  wallet,
}: {
  enabled: boolean;
  visibilityTitle: string;
  visibilityDescription: string;
  query: UseQueryResult<Awaited<ReturnType<typeof listVouchers>>, Error>;
  wallet: ReturnType<typeof getVoucherWalletState> | null;
}) {
  return (
    <AppCard className="p-4">
      <div className="space-y-4">
        <SectionHeader eyebrow="Mã ưu đãi" title="Mã ưu đãi của tôi" description={visibilityDescription} />
        {!enabled ? <EmptyState title={visibilityTitle} description={visibilityDescription} /> : null}
        {query.isLoading ? <LoadingBlock label="Đang tải ví voucher" /> : null}
        {query.error ? <ErrorState error={query.error} title="Chưa tải được ví voucher" onRetry={() => query.refetch()} /> : null}
        {wallet ? (
          <>
            <div className="rounded-lg bg-secondary p-4">
              <p className="text-sm text-muted-foreground">Trạng thái ví</p>
              <p className="text-lg font-semibold">{wallet.title}</p>
              <p className="mt-2 text-sm text-muted-foreground">{wallet.description}</p>
            </div>
            {wallet.state === "empty" ? (
              <EmptyState title={wallet.title} description={wallet.description} />
            ) : (
              <>
                <div className="grid gap-3 sm:grid-cols-4">
                  <MetricCard label="Dùng được" value={wallet.counts.available} />
                  <MetricCard label="Chưa đủ điều kiện" value={wallet.counts.notEligible} />
                  <MetricCard label="Hết hạn" value={wallet.counts.expired} />
                  <MetricCard label="Chưa dùng được" value={wallet.counts.unavailable} />
                </div>
                <div className="rounded-lg border border-dashed bg-secondary/20 p-4 text-sm text-muted-foreground">
                  <p className="font-medium text-foreground">Chỉ áp dụng ở lịch đặt</p>
                  <p className="mt-1">
                    Áp dụng hoặc gỡ mã ưu đãi trong chi tiết lịch đặt khi nhà hàng cho phép.
                  </p>
                  <p className="mt-2">{wallet.summary}</p>
                </div>
                <div className="space-y-3">
                  {wallet.items.map((item) => (
                    <div key={item.voucher.user_voucher_id} className="rounded-lg border p-4">
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className="font-semibold">{item.voucher.voucher_code}</p>
                          <p className="text-sm text-muted-foreground">{item.voucher.description}</p>
                        </div>
                        <AppBadge>{item.badgeLabel}</AppBadge>
                      </div>
                      <p className="mt-3 text-sm font-medium">{item.title}</p>
                      <p className="mt-1 text-sm text-muted-foreground">{item.description}</p>
                      {item.detailLines.length > 0 ? (
                        <div className="mt-3 space-y-1 text-xs text-muted-foreground">
                          {item.detailLines.map((line, index) => (
                            <p key={`${item.voucher.user_voucher_id}-${index}`}>{line}</p>
                          ))}
                        </div>
                      ) : null}
                    </div>
                  ))}
                </div>
              </>
            )}
          </>
        ) : null}
      </div>
    </AppCard>
  );
}



function MetricCard({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="rounded-lg border p-4">
      <p className="text-sm text-muted-foreground">{label}</p>
      <p className="mt-1 text-2xl font-semibold">{value}</p>
    </div>
  );
}
