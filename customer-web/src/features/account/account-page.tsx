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

      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
        <UpcomingReservationsCard query={upcomingReservationsQuery} />
        <AccountSummaryCard
          name={displayName}
          email={profile?.email ?? loyaltyQuery.data?.user.email ?? null}
          phone={profile?.phone ?? loyaltyQuery.data?.user.phone ?? null}
          benefitsEnabled={accountBenefitsRollout.enabled}
          privacyEnabled={privacyEnabled}
          dataExportEnabled={dataExportEnabled}
        />
      </div>

      <ProfilePreferencesPanel profile={profile} />

      <ExperienceHub
        name={displayName}
        email={profile?.email ?? loyaltyQuery.data?.user.email ?? null}
        phone={profile?.phone ?? loyaltyQuery.data?.user.phone ?? null}
        benefitsEnabled={accountBenefitsRollout.enabled}
        privacyEnabled={privacyEnabled}
        dataExportEnabled={dataExportEnabled}
      />

      {accountBenefitsRollout.enabled ? (
        <section className="grid gap-4 lg:grid-cols-2">
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
        </section>
      ) : null}

      <NotificationPreferencesPanel />
      <FeedbackPanel />
      {privacyEnabled ? <PrivacyPanel /> : null}
    </ResponsivePageShell>
  );
}

function AccountSummaryCard({
  name,
  email,
  phone,
  benefitsEnabled,
  privacyEnabled,
  dataExportEnabled,
}: {
  name: string;
  email: string | null;
  phone: string | null;
  benefitsEnabled: boolean;
  privacyEnabled: boolean;
  dataExportEnabled: boolean;
}) {
  return (
    <AppCard className="p-4">
      <div className="space-y-4">
        <SectionHeader
          eyebrow="Thông tin liên hệ"
          title={name}
          description="Nhà hàng dùng thông tin này để xác nhận lịch đặt và liên hệ khi cần."
        />
        <div className="grid gap-2 text-sm">
          <SummaryRow label="Email" value={email ?? "Chưa cung cấp"} />
          <SummaryRow label="Điện thoại" value={phone ?? "Chưa cung cấp"} />
        </div>
        <div className="grid gap-2">
          <FeatureState label="Ưu đãi" enabled={benefitsEnabled} />
          <FeatureState label="Yêu cầu dữ liệu cá nhân" enabled={privacyEnabled} />
          <FeatureState label="Xuất dữ liệu" enabled={dataExportEnabled} />
        </div>
      </div>
    </AppCard>
  );
}

function ExperienceHub({
  name,
  email,
  phone,
  benefitsEnabled,
  privacyEnabled,
  dataExportEnabled,
}: {
  name: string;
  email: string | null;
  phone: string | null;
  benefitsEnabled: boolean;
  privacyEnabled: boolean;
  dataExportEnabled: boolean;
}) {
  const utilityItems = [
    {
      label: "Thông báo",
      value: "Đang có",
      icon: BellRing,
      tone: "info" as const,
    },
    {
      label: "Ưu đãi",
      value: benefitsEnabled ? "Đang có" : "Sắp ra mắt",
      icon: WalletCards,
      tone: benefitsEnabled ? "success" as const : "neutral" as const,
    },
    {
      label: "Quyền riêng tư",
      value: privacyEnabled ? "Đang có" : "Theo yêu cầu",
      icon: ShieldCheck,
      tone: privacyEnabled ? "success" as const : "neutral" as const,
    },
    {
      label: "Xuất dữ liệu",
      value: dataExportEnabled ? "Đang có" : "Theo yêu cầu",
      icon: UserRound,
      tone: dataExportEnabled ? "success" as const : "neutral" as const,
    },
  ];

  return (
    <section className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]" aria-label="Không gian cá nhân">
      <AppCard className="overflow-hidden p-0">
        <div className="grid gap-0 lg:grid-cols-[minmax(0,1fr)_17rem]">
          <div className="space-y-5 p-5">
            <div className="flex items-start gap-3">
              <span className="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-accent text-accent-foreground">
                <Sparkles className="h-5 w-5" />
              </span>
              <div>
                <h2 className="text-2xl font-semibold tracking-normal">Hồ sơ dùng bữa</h2>
                <p className="mt-1 max-w-xl text-sm text-muted-foreground">
                  Dùng thông tin đã có để đặt bàn nhanh hơn, theo dõi lịch và nhận nhắc hẹn.
                </p>
              </div>
            </div>
            <div className="grid gap-3 md:grid-cols-3">
              <ProfileSignal icon={UserRound} label="Khách hàng" value={name} />
              <ProfileSignal icon={BellRing} label="Liên hệ" value={email ?? phone ?? "Chưa cung cấp"} />
              <ProfileSignal icon={Heart} label="Sở thích" value="Lưu tại thiết bị" />
            </div>
          </div>
          <div className="border-t bg-secondary/35 p-5 lg:border-l lg:border-t-0">
            <p className="font-semibold">Lối tắt thuận tiện</p>
            <div className="mt-3 grid gap-2">
              <ShortcutLink href="/booking" icon={CalendarDays} label="Đặt bàn mới" />
              <ShortcutLink href="/menu" icon={ReceiptText} label="Xem thực đơn" />
              <ShortcutLink href="/reservations" icon={CalendarDays} label="Theo dõi lịch đặt" />
            </div>
          </div>
        </div>
      </AppCard>

      <AppCard className="p-4">
        <div className="mb-4 flex items-center justify-between gap-3">
          <div>
            <h2 className="font-semibold">Tiện ích của tôi</h2>
            <p className="mt-1 text-sm text-muted-foreground">Những mục có thể dùng trong tài khoản.</p>
          </div>
          <StatusPill label="An toàn" tone="success" />
        </div>
        <div className="grid gap-2">
            {utilityItems.map((item) => (
            <ReadinessRow key={item.label} {...item} />
          ))}
        </div>
      </AppCard>
    </section>
  );
}

function ProfileSignal({ icon: Icon, label, value }: { icon: LucideIcon; label: string; value: string }) {
  return (
    <div className="min-h-24 rounded-lg border bg-background p-3">
      <Icon className="h-4 w-4 text-teal-700" />
      <p className="mt-3 text-xs font-medium text-muted-foreground">{label}</p>
      <p className="mt-1 truncate font-semibold">{value}</p>
    </div>
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

function ReadinessRow({
  icon: Icon,
  label,
  value,
  tone,
}: {
  icon: LucideIcon;
  label: string;
  value: string;
  tone: "neutral" | "success" | "warning" | "danger" | "info";
}) {
  return (
    <div className="flex min-h-12 items-center justify-between gap-3 rounded-lg border bg-background px-3 py-2">
      <span className="flex min-w-0 items-center gap-2">
        <Icon className="h-4 w-4 text-teal-700" />
        <span className="truncate text-sm font-medium">{label}</span>
      </span>
      <StatusPill label={value} tone={tone} />
    </div>
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

function SummaryRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-lg bg-secondary/40 px-3 py-2">
      <span className="text-muted-foreground">{label}</span>
      <span className="min-w-0 truncate font-medium">{value}</span>
    </div>
  );
}

function FeatureState({ label, enabled }: { label: string; enabled: boolean }) {
  return (
    <div className="flex items-center justify-between gap-3 text-sm">
      <span>{label}</span>
      <AppBadge>{enabled ? "Đang có" : "Sắp ra mắt"}</AppBadge>
    </div>
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
