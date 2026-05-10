"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowRight,
  BellRing,
  CalendarDays,
  CheckCircle2,
  ChefHat,
  Clock3,
  CreditCard,
  ListChecks,
  MapPin,
  ReceiptText,
  ShieldCheck,
  Sparkles,
  Tag,
  UsersRound,
  UserRound,
  Utensils,
  WalletCards,
  type LucideIcon,
} from "lucide-react";
import { AppButton, AppCard, PriceText, SectionHeader, StatusPill } from "@/components/customer/ui";
import { ResponsivePageShell } from "@/components/customer/layout";
import { SelectedBranchEntry } from "@/features/branch/branch-selector";
import { useBranchSelection } from "@/features/branch/hooks";
import { useCustomerIdentity, useCustomerSession } from "@/features/auth/hooks";
import { listMenuItems } from "@/features/menu/api";
import { MenuItemImage } from "@/features/menu/menu-item-image";
import { listReservations } from "@/features/reservations/api";
import { queryKeys } from "@/lib/api/query-keys";
import { customerWebRollout, featureFlags } from "@/lib/config/feature-flags";
import { formatDateTime } from "@/lib/contracts/format";
import { displayMenuText } from "@/lib/i18n/customer-display";
import { trackCustomerEvent } from "@/lib/analytics/events";
import { cn } from "@/lib/utils";

const heroPlateUrl = "/customer-web/hero-plate.jpg";

const foodRail = [
  {
    src: "/customer-web/rail-seafood.jpg",
    alt: "Món tôm sốt rau củ",
    label: "Gợi ý cho bạn",
    className: "h-56",
  },
  {
    src: "/customer-web/rail-drink.jpg",
    alt: "Ly nước mát trên bàn",
    label: "Đồ uống",
    className: "h-[12.5rem]",
  },
  {
    src: "/customer-web/rail-dessert.jpg",
    alt: "Món tráng miệng tiramisu",
    label: "Tráng miệng",
    className: "h-[8.5rem]",
  },
];

type QuickAction = {
  label: string;
  description: string;
  href: string;
  icon: LucideIcon;
  accent: string;
};

type PlannerOption = {
  key: string;
  label: string;
};

const occasionOptions: PlannerOption[] = [
  { key: "quick", label: "Ăn nhanh" },
  { key: "date", label: "Hẹn hò" },
  { key: "family", label: "Gia đình" },
  { key: "celebrate", label: "Kỷ niệm" },
];

const partyOptions: PlannerOption[] = [
  { key: "2", label: "2 người" },
  { key: "4", label: "3-4 người" },
  { key: "6", label: "5-6 người" },
  { key: "8", label: "Nhóm lớn" },
];

const budgetOptions: PlannerOption[] = [
  { key: "light", label: "Gọn nhẹ" },
  { key: "balanced", label: "Cân bằng" },
  { key: "signature", label: "Đặc biệt" },
];

export function HomePage() {
  const identity = useCustomerIdentity();
  const customerSession = useCustomerSession();
  const branchSelection = useBranchSelection();
  const selectedBranch = branchSelection.selectedBranch;
  const displayName = identity.isKnownCustomer ? identity.displayName : "bạn";
  const hasCustomerContext = identity.isAuthenticated || identity.hasGuestSession || customerSession.hasGuestSession;
  const sessionLabel = identity.isKnownCustomer
    ? identity.displayName
    : customerSession.hasGuestSession
      ? "Phiên khách đã sẵn sàng"
      : "Có thể xem trước như khách";

  const featuredMenuQuery = useQuery({
    queryKey: queryKeys.menu.items({ q: null, categoryId: null, preorderOnly: null }),
    queryFn: () => listMenuItems({ q: null, categoryId: null, preorderOnly: null }),
    staleTime: 60_000,
  });
  const upcomingReservationsQuery = useQuery({
    queryKey: queryKeys.reservations.list("upcoming"),
    queryFn: () => listReservations("upcoming"),
    enabled: hasCustomerContext,
    staleTime: 30_000,
  });
  const featuredItems = (featuredMenuQuery.data ?? []).filter((item) => item.is_available).slice(0, 4);
  const nextReservation = upcomingReservationsQuery.data?.[0] ?? null;

  const quickActions: QuickAction[] = [
    {
      label: "Đặt bàn ngay",
      description: "Chọn ngày, giờ.",
      href: "/booking",
      icon: CalendarDays,
      accent: "text-primary",
    },
    {
      label: "Chờ bàn",
      description: featureFlags.waitingList ? "Khi đang kín chỗ." : "Tùy nhà hàng bật.",
      href: "/waiting-list",
      icon: UsersRound,
      accent: "text-teal-700",
    },
    {
      label: "Lịch đặt",
      description: "Theo dõi lượt ghé.",
      href: "/reservations",
      icon: Clock3,
      accent: "text-amber-700",
    },
    {
      label: "Thực đơn",
      description: "Xem món đang phục vụ.",
      href: "/menu",
      icon: ReceiptText,
      accent: "text-emerald-700",
    },
    {
      label: "Ưu đãi",
      description: customerWebRollout.accountBenefits?.enabled ? "Xem quyền lợi." : "Đang khóa rollout.",
      href: "/account",
      icon: Tag,
      accent: "text-teal-700",
    },
    {
      label: "Tài khoản",
      description: "Sở thích và thông báo.",
      href: "/account",
      icon: UserRound,
      accent: "text-primary",
    },
  ];

  return (
    <main>
      <section className="border-b bg-background">
        <div className="mx-auto grid min-h-[620px] w-full max-w-[1410px] gap-5 px-4 pb-6 pt-8 lg:grid-cols-[250px_420px_minmax(390px,430px)_240px] lg:items-start">
          <div className="relative hidden h-[28rem] overflow-hidden rounded-lg bg-secondary lg:block">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={heroPlateUrl} alt="Đĩa cá hồi và rau tươi" className="h-full w-full object-cover" />
          </div>

          <div className="space-y-6 lg:pt-14">
            <div className="space-y-4">
              <div className="inline-flex items-center gap-2 rounded-lg border bg-secondary/50 px-3 py-2 text-sm font-semibold text-teal-800">
                <Sparkles className="h-4 w-4" />
                {identity.isKnownCustomer ? `Xin chào, ${displayName}` : "Trải nghiệm đặt bàn cá nhân"}
              </div>
              <h1 className="max-w-[27rem] text-[2.55rem] font-bold leading-[1.16] tracking-normal text-foreground sm:text-[3rem]">
                Bạn đang muốn trải nghiệm ẩm thực gì?
              </h1>
              <p className="max-w-xl text-base leading-7 text-muted-foreground sm:text-lg">
                Chọn chi nhánh yêu thích, xem thực đơn, giữ bàn và theo dõi lượt ghé trong một màn hình được tối ưu cho điện thoại.
              </p>
            </div>

            <div className="grid gap-3 xl:grid-cols-2">
              <AppButton asChild className="min-h-14 text-base">
                <Link href="/menu" onClick={() => trackCustomerEvent("homepage_cta_clicked", { source: "hero_menu" })}>
                  <ReceiptText className="h-5 w-5" />
                  Xem thực đơn
                </Link>
              </AppButton>
              <AppButton asChild variant="outline" className="min-h-14 text-base">
                <Link href="/booking" onClick={() => trackCustomerEvent("homepage_cta_clicked", { source: "hero_booking" })}>
                  <CalendarDays className="h-5 w-5" />
                  Giữ bàn
                </Link>
              </AppButton>
            </div>

            <div className="grid gap-3">
              {!identity.isKnownCustomer ? (
                <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-4 text-xs text-muted-foreground" aria-hidden="true">
                  <span className="h-px bg-border" />
                  <span>hoặc</span>
                  <span className="h-px bg-border" />
                </div>
              ) : null}
              {!identity.isKnownCustomer ? (
                <AppButton
                  type="button"
                  variant="outline"
                  className="min-h-14 border-teal-500 text-teal-700 hover:bg-teal-50"
                  onClick={customerSession.continueAsGuest}
                >
                  <UserRound className="h-5 w-5" />
                  Tiếp tục với tư cách khách
                </AppButton>
              ) : null}
              <p className="text-sm text-muted-foreground">
                {identity.isKnownCustomer ? (
                  <>
                    Hồ sơ của <span className="font-semibold text-foreground">{displayName}</span> đã sẵn sàng cho đặt bàn và theo dõi lịch.
                  </>
                ) : (
                  <>
                    Đã có tài khoản?{" "}
                    <Link className="font-semibold text-teal-700 hover:text-primary" href="/login">
                      Đăng nhập
                    </Link>
                    <ArrowRight className="ml-1 inline h-3.5 w-3.5 align-[-2px] text-teal-700" />
                  </>
                )}
              </p>
            </div>
          </div>

          <AppCard className="p-0">
            <div className="space-y-4 p-4">
              <section className="space-y-3" aria-label="Chọn chi nhánh">
                <div className="flex items-center gap-3">
                  <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                    <MapPin className="h-5 w-5" />
                  </span>
                  <div>
                    <h2 className="font-semibold">Chi nhánh của lượt ghé</h2>
                    <p className="text-sm text-muted-foreground">Dùng cho thực đơn và đặt bàn.</p>
                  </div>
                </div>
                <SelectedBranchEntry className="w-full justify-between" />
                {selectedBranch ? (
                  <div className="space-y-2 text-sm">
                    <p className="flex items-start gap-2 text-muted-foreground">
                      <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-teal-700" />
                      <span>{selectedBranch.address}</span>
                    </p>
                    <p className="font-medium text-teal-700">• {selectedBranch.statusLabel}</p>
                  </div>
                ) : null}
              </section>

              <NextVisitPanel
                sessionLabel={sessionLabel}
                nextReservation={nextReservation}
                enabled={hasCustomerContext}
                isLoading={upcomingReservationsQuery.isLoading}
                branchName={selectedBranch?.branchName ?? "RestaurantPOS"}
              />

              <section className="border-t pt-4" aria-label="Công cụ tiện ích">
                <div className="grid gap-2">
                  <StatusRow icon={BellRing} label="Nhắc lịch" value="Cài trong tài khoản" href="/account" />
                  <StatusRow icon={ShieldCheck} label="Dữ liệu cá nhân" value={customerWebRollout.privacyRequests?.enabled ? "Đã bật" : "Chưa bật"} href="/account" />
                  <StatusRow icon={CreditCard} label="Thanh toán" value={customerWebRollout.billAndActiveOrder?.enabled ? "Sẵn sàng" : "Theo nhà hàng"} href="/reservations" />
                </div>
              </section>
            </div>
          </AppCard>

          <div className="hidden space-y-4 lg:block">
            {foodRail.map((item) => (
              <figure key={item.src} className={cn("relative overflow-hidden rounded-lg border bg-secondary shadow-[var(--restaurant-shadow)]", item.className)}>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={item.src} alt={item.alt} className="h-full w-full object-cover" />
                <figcaption className="absolute bottom-3 right-3 rounded-md bg-background/92 px-3 py-1 text-xs font-semibold text-foreground shadow-sm">
                  {item.label}
                </figcaption>
              </figure>
            ))}
          </div>
        </div>
      </section>

      <ResponsivePageShell className="space-y-8 py-6 sm:py-7">
        <section className="grid gap-3 lg:grid-cols-[8rem_minmax(0,1fr)] lg:items-center" aria-label="Thao tác nhanh">
          <h2 className="text-base font-bold">Thao tác nhanh</h2>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            {quickActions.map((action) => (
              <QuickActionCard key={`${action.href}-${action.label}`} action={action} />
            ))}
          </div>
        </section>

        <VisitPlanner />

        <section className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
          <section className="space-y-4" aria-label="Món ngon gợi ý">
            <SectionHeader
              title="Món ngon gợi ý"
              description="Danh sách lấy từ thực đơn đang phục vụ, giúp khách mở nhanh món phù hợp trước khi giữ bàn."
              action={
                <AppButton asChild variant="ghost" className="text-teal-700">
                  <Link href="/menu">
                    Xem thực đơn đầy đủ
                    <ArrowRight className="h-4 w-4" />
                  </Link>
                </AppButton>
              }
            />

            {featuredMenuQuery.isLoading ? (
              <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4" aria-busy="true">
                {Array.from({ length: 4 }).map((_, index) => (
                  <div key={index} className="h-32 animate-pulse rounded-lg bg-secondary" />
                ))}
              </div>
            ) : null}

            {!featuredMenuQuery.isLoading && featuredItems.length === 0 ? (
              <div className="rounded-lg border border-dashed bg-secondary/35 p-5 text-sm text-muted-foreground">
                Mở thực đơn để xem món đang phục vụ tại chi nhánh đã chọn.
              </div>
            ) : null}

            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
              {featuredItems.map((item) => (
                <Link
                  key={item.item_id}
                  href={`/menu/${item.item_id}`}
                  className="group grid min-h-32 grid-cols-[7rem_minmax(0,1fr)_2.5rem] overflow-hidden rounded-lg border bg-card transition hover:border-primary/40 hover:shadow-[var(--restaurant-shadow)]"
                >
                  <MenuItemImage item={item} className="h-full min-h-32" />
                  <div className="min-w-0 p-3">
                    <p className="line-clamp-2 font-semibold">{displayMenuText(item.name, "Món")}</p>
                    <p className="mt-2 text-primary">
                      <PriceText amount={item.price.amount} currency={item.price.currency} />
                    </p>
                  </div>
                  <span className="m-3 grid h-8 w-8 place-items-center rounded-md border text-primary transition group-hover:bg-primary group-hover:text-primary-foreground">
                    <ArrowRight className="h-4 w-4" />
                  </span>
                </Link>
              ))}
            </div>
          </section>

          <ExperienceReadinessPanel />
        </section>
      </ResponsivePageShell>
    </main>
  );
}

function NextVisitPanel({
  sessionLabel,
  nextReservation,
  enabled,
  isLoading,
  branchName,
}: {
  sessionLabel: string;
  nextReservation: Awaited<ReturnType<typeof listReservations>>[number] | null;
  enabled: boolean;
  isLoading: boolean;
  branchName: string;
}) {
  const visitTime = nextReservation?.start_time ?? nextReservation?.booking_time ?? null;

  return (
    <section className="border-t pt-4" aria-label="Lượt ghé sắp tới">
      <div className="mb-3 flex items-center justify-between gap-3">
        <h2 className="font-semibold text-teal-700">Sắp tới</h2>
        <StatusPill label={sessionLabel} tone="info" />
      </div>
      <div className="rounded-lg border bg-background p-3">
        {isLoading ? (
          <div className="space-y-3">
            <div className="h-24 animate-pulse rounded-lg bg-secondary" aria-label="Đang tải lượt ghé sắp tới" />
            <AppButton asChild variant="outline" className="w-full">
              <Link href="/reservations">
                Xem tất cả lịch đặt
                <ArrowRight className="h-4 w-4" />
              </Link>
            </AppButton>
          </div>
        ) : nextReservation ? (
          <div className="flex items-start gap-3">
            <div className="grid h-[4.75rem] w-[4.75rem] shrink-0 place-items-center rounded-lg border bg-secondary text-center">
              <CalendarDays className="h-5 w-5 text-teal-700" />
              <span className="block text-xs text-muted-foreground">Lượt ghé</span>
            </div>
            <div className="min-w-0 space-y-1">
              <p className="text-lg font-bold">{formatDateTime(visitTime)}</p>
              <p className="text-sm text-muted-foreground">{nextReservation.guest_count ?? "Chưa rõ"} khách</p>
              <p className="truncate text-sm text-muted-foreground">{branchName}</p>
              <AppButton asChild variant="ghost" size="sm" className="min-h-9 px-0 text-teal-700 hover:bg-transparent hover:text-primary">
                <Link href={`/reservations/${nextReservation.reservation_id}`}>
                  Xem chi tiết lịch đặt
                  <ArrowRight className="h-4 w-4" />
                </Link>
              </AppButton>
            </div>
          </div>
        ) : (
          <div className="space-y-3">
            <div className="flex items-start gap-3">
              <CalendarDays className="mt-1 h-5 w-5 text-teal-700" />
              <div>
                <p className="font-semibold">{enabled ? "Chưa có lượt ghé sắp tới" : "Bắt đầu với một phiên khách"}</p>
                <p className="mt-1 text-sm text-muted-foreground">
                  {enabled
                    ? "Bạn có thể giữ bàn mới hoặc xem thực đơn trước khi đến."
                    : "Tiếp tục với tư cách khách để giữ draft đặt bàn trên thiết bị này."}
                </p>
              </div>
            </div>
            <AppButton asChild variant="outline" className="w-full">
              <Link href="/reservations">
                Xem tất cả lịch đặt
                <ArrowRight className="h-4 w-4" />
              </Link>
            </AppButton>
          </div>
        )}
      </div>
    </section>
  );
}

function VisitPlanner() {
  const [occasion, setOccasion] = useState("date");
  const [partySize, setPartySize] = useState("2");
  const [budget, setBudget] = useState("balanced");

  const plan = useMemo(() => {
    const occasionLabel = occasionOptions.find((option) => option.key === occasion)?.label ?? "Lượt ghé";
    const partyLabel = partyOptions.find((option) => option.key === partySize)?.label ?? "2 người";
    const budgetLabel = budgetOptions.find((option) => option.key === budget)?.label ?? "Cân bằng";
    const menuHint =
      occasion === "celebrate"
        ? "Ưu tiên món chia sẻ, món chính nổi bật và tráng miệng."
        : occasion === "quick"
          ? "Ưu tiên món phục vụ nhanh và đồ uống dễ chọn."
          : occasion === "family"
            ? "Ưu tiên món dễ chia, ít rủi ro dị ứng và bàn rộng."
            : "Ưu tiên bàn yên tĩnh, món signature và đồ uống nhẹ.";

    return {
      title: `${occasionLabel} · ${partyLabel}`,
      description: `${budgetLabel}. ${menuHint}`,
    };
  }, [budget, occasion, partySize]);

  return (
    <section className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]" aria-label="Lên kế hoạch lượt ghé">
      <div className="space-y-4">
        <SectionHeader
          title="Lên kế hoạch lượt ghé"
          description="Chọn vài sở thích nhanh để mở đúng thao tác tiếp theo. Phần này chỉ lưu trên giao diện và không gửi dữ liệu khi bạn chưa xác nhận."
        />
        <div className="grid gap-3 md:grid-cols-3">
          <PlannerGroup title="Dịp" options={occasionOptions} value={occasion} onChange={setOccasion} />
          <PlannerGroup title="Số khách" options={partyOptions} value={partySize} onChange={setPartySize} />
          <PlannerGroup title="Ngân sách" options={budgetOptions} value={budget} onChange={setBudget} />
        </div>
      </div>

      <AppCard className="p-4">
        <div className="space-y-4">
          <div className="flex items-start gap-3">
            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-accent text-accent-foreground">
              <Sparkles className="h-5 w-5" />
            </span>
            <div>
              <h3 className="font-semibold">{plan.title}</h3>
              <p className="mt-1 text-sm text-muted-foreground">{plan.description}</p>
            </div>
          </div>
          <div className="grid gap-2">
            <AppButton asChild>
              <Link href="/booking">
                <CalendarDays className="h-4 w-4" />
                Tìm bàn phù hợp
              </Link>
            </AppButton>
            <AppButton asChild variant="outline">
              <Link href="/menu">
                <Utensils className="h-4 w-4" />
                Xem món nên chọn
              </Link>
            </AppButton>
          </div>
        </div>
      </AppCard>
    </section>
  );
}

function PlannerGroup({
  title,
  options,
  value,
  onChange,
}: {
  title: string;
  options: PlannerOption[];
  value: string;
  onChange: (value: string) => void;
}) {
  return (
    <AppCard className="p-3">
      <p className="mb-3 text-sm font-semibold">{title}</p>
      <div className="flex flex-wrap gap-2">
        {options.map((option) => (
          <AppButton
            key={option.key}
            type="button"
            size="sm"
            variant={value === option.key ? "default" : "outline"}
            className="min-h-9 px-3"
            aria-pressed={value === option.key}
            onClick={() => onChange(option.key)}
          >
            {option.label}
          </AppButton>
        ))}
      </div>
    </AppCard>
  );
}

function ExperienceReadinessPanel() {
  const features = [
    {
      title: "Đặt bàn",
      description: "Tìm bàn, giữ bàn tạm thời và tạo lịch đặt.",
      icon: CalendarDays,
      enabled: true,
      href: "/booking",
    },
    {
      title: "Món đặt trước",
      description: customerWebRollout.preorder?.enabled ? "Có thể lưu cùng lịch đặt." : customerWebRollout.preorder?.disabledDescription ?? "Đang khóa rollout.",
      icon: ChefHat,
      enabled: Boolean(customerWebRollout.preorder?.enabled),
      href: "/menu",
    },
    {
      title: "Danh sách chờ",
      description: customerWebRollout.waitingList?.enabled ? "Khách có thể tạo yêu cầu chờ bàn." : customerWebRollout.waitingList?.disabledDescription ?? "Chưa mở cho khách.",
      icon: ListChecks,
      enabled: Boolean(customerWebRollout.waitingList?.enabled),
      href: "/waiting-list",
    },
    {
      title: "Ưu đãi",
      description: customerWebRollout.accountBenefits?.enabled ? "Điểm thưởng và voucher đã bật." : customerWebRollout.accountBenefits?.disabledDescription ?? "Đang khóa rollout.",
      icon: WalletCards,
      enabled: Boolean(customerWebRollout.accountBenefits?.enabled),
      href: "/account",
    },
  ];

  return (
    <AppCard className="h-fit p-4">
      <div className="mb-4 flex items-center justify-between gap-3">
        <div>
          <h2 className="font-semibold">Trạng thái trải nghiệm</h2>
          <p className="mt-1 text-sm text-muted-foreground">Chỉ mở thao tác đã có contract an toàn.</p>
        </div>
        <StatusPill label="Live-safe" tone="success" />
      </div>
      <div className="grid gap-3">
        {features.map((feature) => {
          const Icon = feature.icon;

          return (
            <Link
              key={feature.title}
              href={feature.href}
              className="group flex min-h-20 items-start gap-3 rounded-lg border bg-background p-3 transition hover:border-primary/35 hover:bg-secondary/35"
            >
              <span className={cn("grid h-9 w-9 shrink-0 place-items-center rounded-lg", feature.enabled ? "bg-accent text-accent-foreground" : "bg-secondary text-muted-foreground")}>
                <Icon className="h-4 w-4" />
              </span>
              <span className="min-w-0 flex-1">
                <span className="flex items-center justify-between gap-2">
                  <span className="font-semibold">{feature.title}</span>
                  {feature.enabled ? <CheckCircle2 className="h-4 w-4 text-teal-700" /> : <Clock3 className="h-4 w-4 text-muted-foreground" />}
                </span>
                <span className="mt-1 line-clamp-2 text-sm text-muted-foreground">{feature.description}</span>
              </span>
            </Link>
          );
        })}
      </div>
    </AppCard>
  );
}

function StatusRow({
  icon: Icon,
  label,
  value,
  href,
}: {
  icon: LucideIcon;
  label: string;
  value: string;
  href: string;
}) {
  return (
    <Link href={href} className="flex min-h-11 items-center justify-between gap-3 rounded-lg border bg-background px-3 py-2 text-sm transition hover:border-primary/35">
      <span className="flex min-w-0 items-center gap-2">
        <Icon className="h-4 w-4 shrink-0 text-teal-700" />
        <span className="font-medium">{label}</span>
      </span>
      <span className="truncate text-muted-foreground">{value}</span>
    </Link>
  );
}

function QuickActionCard({ action }: { action: QuickAction }) {
  const Icon = action.icon;

  return (
    <Link
      href={action.href}
      className="group flex min-h-16 items-center justify-between gap-3 rounded-lg border bg-card p-3 transition hover:border-primary/35 hover:shadow-[var(--restaurant-shadow)]"
    >
      <span className="flex min-w-0 items-center gap-3">
        <span className={cn("grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-secondary", action.accent)}>
          <Icon className="h-5 w-5" />
        </span>
        <span className="min-w-0">
          <span className="block text-sm font-semibold">{action.label}</span>
          <span className="mt-0.5 block text-xs text-muted-foreground">{action.description}</span>
        </span>
      </span>
      <ArrowRight className="h-4 w-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-primary" />
    </Link>
  );
}
