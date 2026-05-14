"use client";

import Image from "next/image";
import Link from "next/link";
import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowRight,
  CalendarDays,
  Clock3,
  Heart,
  MapPin,
  ReceiptText,
  Search,
  Sparkles,
  UsersRound,
  type LucideIcon,
} from "lucide-react";
import { AppButton, AppCard, EmptyState, PriceText, SectionHeader, StatusPill } from "@/components/customer/ui";
import { ResponsivePageShell } from "@/components/customer/layout";
import { SelectedBranchEntry } from "@/features/branch/branch-selector";
import { useBranchSelection } from "@/features/branch/hooks";
import { useCustomerIdentity, useCustomerSession } from "@/features/auth/hooks";
import { listMenuItems } from "@/features/menu/api";
import { MenuItemImage } from "@/features/menu/menu-item-image";
import { listReservations } from "@/features/reservations/api";
import { queryKeys } from "@/lib/api/query-keys";
import { customerBrand } from "@/lib/brand/customer-brand";
import { featureFlags } from "@/lib/config/feature-flags";
import { formatDateTime } from "@/lib/contracts/format";
import { displayMenuText } from "@/lib/i18n/customer-display";
import { trackCustomerEvent } from "@/lib/analytics/events";
import { cn } from "@/lib/utils";

const heroPlateUrl = "/customer-web/hero-plate.jpg";

const foodRail = [
  {
    src: "/customer-web/rail-seafood.jpg",
    alt: "Món tôm sốt rau củ",
    label: "Món được yêu thích",
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

type HomeAction = {
  label: string;
  description: string;
  href: string;
  icon: LucideIcon;
  tone: string;
};

type PlannerOption = {
  key: string;
  label: string;
};

const guestOptions: PlannerOption[] = [
  { key: "2", label: "2 người" },
  { key: "4", label: "4 người" },
  { key: "6", label: "6 người" },
];

const dateOptions: PlannerOption[] = [
  { key: "today", label: "Hôm nay" },
  { key: "tomorrow", label: "Ngày mai" },
];

const timeOptions: PlannerOption[] = [
  { key: "19:00", label: "19:00" },
  { key: "20:00", label: "20:00" },
];

export function HomePage() {
  const identity = useCustomerIdentity();
  const customerSession = useCustomerSession();
  const branchSelection = useBranchSelection();
  const selectedBranch = branchSelection.selectedBranch;
  const displayName = identity.isKnownCustomer ? identity.displayName : "bạn";
  const hasCustomerContext = identity.isAuthenticated || identity.hasGuestSession || customerSession.hasGuestSession;

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

  const quickActions: HomeAction[] = [
    {
      label: "Đặt bàn ngay",
      description: "Tìm bàn trống cho bữa ăn của bạn.",
      href: "/booking",
      icon: CalendarDays,
      tone: "text-primary",
    },
    {
      label: "Xem thực đơn",
      description: "Chọn món trước khi đến.",
      href: "/menu",
      icon: ReceiptText,
      tone: "text-teal-700",
    },
    {
      label: "Lịch đặt",
      description: "Xem lại lượt ghé của bạn.",
      href: "/reservations",
      icon: Clock3,
      tone: "text-amber-700",
    },
    {
      label: "Tài khoản",
      description: "Thông tin liên hệ và sở thích.",
      href: "/account",
      icon: Heart,
      tone: "text-rose-700",
    },
  ];

  return (
    <main>
      <section className="border-b bg-background">
        <div className="mx-auto grid w-full max-w-[1410px] gap-5 px-4 pb-6 pt-6 lg:min-h-[610px] lg:grid-cols-[270px_minmax(380px,470px)_minmax(360px,420px)_220px] lg:items-start lg:pt-8">
          <div className="relative hidden h-[29rem] overflow-hidden rounded-lg bg-secondary lg:block">
            <Image
              src={heroPlateUrl}
              alt="Đĩa cá hồi và rau tươi"
              fill
              className="object-cover"
              priority
              sizes="270px"
            />
          </div>

          <div className="space-y-6 lg:pt-14">
            <div className="space-y-4">
              <div className="inline-flex items-center gap-2 rounded-lg border bg-secondary/50 px-3 py-2 text-sm font-semibold text-teal-800">
                <Sparkles className="h-4 w-4" />
                {identity.isKnownCustomer ? `Xin chào, ${displayName}` : customerBrand.tagline}
              </div>
              <h1 className="max-w-[31rem] text-[2.55rem] font-bold leading-[1.16] tracking-normal text-foreground sm:text-[3rem]">
                Chọn món ngon, giữ bàn đúng giờ
              </h1>
              <p className="max-w-xl text-base leading-7 text-muted-foreground sm:text-lg">
                Xem thực đơn hôm nay, chọn chi nhánh gần bạn và giữ bàn chỉ trong vài bước.
              </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <AppButton asChild className="min-h-14 text-base">
                <Link href="/booking" onClick={() => trackCustomerEvent("homepage_cta_clicked", { source: "hero_booking" })}>
                  <CalendarDays className="h-5 w-5" />
                  Đặt bàn ngay
                </Link>
              </AppButton>
              <AppButton asChild variant="outline" className="min-h-14 text-base">
                <Link href="/menu" onClick={() => trackCustomerEvent("homepage_cta_clicked", { source: "hero_menu" })}>
                  <ReceiptText className="h-5 w-5" />
                  Xem thực đơn
                </Link>
              </AppButton>
            </div>

            <form action="/menu" className="rounded-lg border bg-card p-3 shadow-[var(--restaurant-shadow)]" role="search">
              <label htmlFor="home-menu-search" className="mb-2 block text-sm font-semibold">
                Tìm món trong thực đơn
              </label>
              <div className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                <div className="relative min-w-0">
                  <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <input
                    id="home-menu-search"
                    name="q"
                    type="search"
                    className="min-h-11 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none transition focus-visible:ring-2 focus-visible:ring-ring"
                    placeholder="Tìm phở, cơm, nước uống..."
                  />
                </div>
                <AppButton type="submit" variant="outline" className="min-h-11">
                  Tìm món
                </AppButton>
              </div>
            </form>

            {!identity.isKnownCustomer ? (
              <div className="rounded-lg border bg-secondary/35 p-4">
                <p className="text-sm text-muted-foreground">
                  Bạn có thể đặt bàn nhanh như khách hoặc{" "}
                  <Link className="font-semibold text-teal-700 hover:text-primary" href="/login">
                    đăng nhập
                  </Link>{" "}
                  để xem lại lịch đặt.
                </p>
                {!customerSession.hasGuestSession ? (
                  <AppButton type="button" variant="ghost" size="sm" className="mt-2 px-0 text-teal-700 hover:bg-transparent" onClick={customerSession.continueAsGuest}>
                    Tiếp tục như khách
                    <ArrowRight className="h-4 w-4" />
                  </AppButton>
                ) : null}
              </div>
            ) : null}
          </div>

          <div className="space-y-4">
            <QuickBookingCard />

            <AppCard className="p-4">
              <section className="space-y-3" aria-label="Chọn chi nhánh">
                <div className="flex items-center gap-3">
                  <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                    <MapPin className="h-5 w-5" />
                  </span>
                  <div>
                    <h2 className="font-semibold">Bạn muốn dùng bữa ở chi nhánh nào?</h2>
                    <p className="text-sm text-muted-foreground">Chi nhánh đang chọn sẽ dùng cho thực đơn và đặt bàn.</p>
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
            </AppCard>

            {nextReservation || hasCustomerContext ? (
              <NextVisitPanel
                nextReservation={nextReservation}
                isLoading={upcomingReservationsQuery.isLoading}
                branchName={selectedBranch?.branchName ?? customerBrand.fallbackRestaurantName}
              />
            ) : null}
          </div>

          <div className="hidden space-y-4 lg:block">
            {foodRail.map((item) => (
              <figure key={item.src} className={cn("relative overflow-hidden rounded-lg border bg-secondary shadow-[var(--restaurant-shadow)]", item.className)}>
                <Image src={item.src} alt={item.alt} fill className="object-cover" sizes="220px" />
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
          <h2 className="text-base font-bold">Bạn muốn làm gì?</h2>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {quickActions.map((action) => (
              <QuickActionCard key={`${action.href}-${action.label}`} action={action} />
            ))}
          </div>
        </section>

        <section className="space-y-4" aria-label="Món được yêu thích hôm nay">
          <SectionHeader
            title="Món được yêu thích hôm nay"
            description="Gợi ý nhanh từ thực đơn đang phục vụ để bạn dễ chọn trước khi đặt bàn."
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
                <div key={index} className="h-40 animate-pulse rounded-lg bg-secondary" />
              ))}
            </div>
          ) : null}

          {!featuredMenuQuery.isLoading && featuredItems.length === 0 ? (
            <EmptyState
              title="Thực đơn đang được cập nhật"
              description="Bạn vẫn có thể mở trang thực đơn để xem món đang phục vụ tại chi nhánh đã chọn."
              action={
                <AppButton asChild variant="outline">
                  <Link href="/menu">Mở thực đơn</Link>
                </AppButton>
              }
            />
          ) : null}

          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {featuredItems.map((item) => (
              <Link
                key={item.item_id}
                href={featureFlags.menuItemDetail ? `/menu/${item.item_id}` : "/menu"}
                className="group overflow-hidden rounded-lg border bg-card transition hover:border-primary/40 hover:shadow-[var(--restaurant-shadow)]"
              >
                <MenuItemImage item={item} className="aspect-[4/3]" priority={false} />
                <div className="space-y-3 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <h3 className="line-clamp-2 font-semibold">{displayMenuText(item.name, "Món")}</h3>
                    <PriceText amount={item.price.amount} currency={item.price.currency} className="shrink-0 text-primary" />
                  </div>
                  <div className="flex items-center justify-between gap-3 text-sm">
                    <span className="line-clamp-1 text-muted-foreground">{displayMenuText(item.category_name, "Gợi ý")}</span>
                    <span className="inline-flex items-center gap-1 font-semibold text-teal-700">
                      Chi tiết
                      <ArrowRight className="h-4 w-4 transition group-hover:translate-x-0.5" />
                    </span>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        </section>
      </ResponsivePageShell>
    </main>
  );
}

function QuickBookingCard() {
  const [guestCount, setGuestCount] = useState("2");
  const [dateKey, setDateKey] = useState("today");
  const [time, setTime] = useState("19:00");

  const bookingHref = useMemo(() => {
    const params = new URLSearchParams({
      guest_count: guestCount,
      date: dateKey,
      time,
    });

    return `/booking?${params.toString()}`;
  }, [dateKey, guestCount, time]);

  return (
    <AppCard className="p-4">
      <div className="space-y-4">
        <div>
          <p className="text-sm font-semibold text-teal-700">Giữ bàn nhanh</p>
          <h2 className="mt-1 text-xl font-semibold tracking-normal">Bạn đi mấy người?</h2>
          <p className="mt-1 text-sm text-muted-foreground">Chọn vài thông tin cơ bản rồi tìm bàn trống.</p>
        </div>

        <OptionGroup label="Số khách" options={guestOptions} value={guestCount} onChange={setGuestCount} />
        <OptionGroup label="Ngày đến" options={dateOptions} value={dateKey} onChange={setDateKey} />
        <OptionGroup label="Giờ đến" options={timeOptions} value={time} onChange={setTime} />

        <AppButton asChild className="w-full">
          <Link href={bookingHref} onClick={() => trackCustomerEvent("homepage_cta_clicked", { source: "quick_booking" })}>
            <UsersRound className="h-4 w-4" />
            Tìm bàn
          </Link>
        </AppButton>
      </div>
    </AppCard>
  );
}

function OptionGroup({
  label,
  options,
  value,
  onChange,
}: {
  label: string;
  options: PlannerOption[];
  value: string;
  onChange: (value: string) => void;
}) {
  return (
    <div className="space-y-2">
      <p className="text-sm font-medium">{label}</p>
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
    </div>
  );
}

function NextVisitPanel({
  nextReservation,
  isLoading,
  branchName,
}: {
  nextReservation: Awaited<ReturnType<typeof listReservations>>[number] | null;
  isLoading: boolean;
  branchName: string;
}) {
  const visitTime = nextReservation?.start_time ?? nextReservation?.booking_time ?? null;

  return (
    <AppCard className="p-4">
      <section aria-label="Lượt ghé sắp tới">
        <div className="mb-3 flex items-center justify-between gap-3">
          <h2 className="font-semibold text-teal-700">Lượt ghé sắp tới</h2>
          <StatusPill label="Lịch đặt" tone="info" />
        </div>
        {isLoading ? (
          <div className="h-24 animate-pulse rounded-lg bg-secondary" aria-label="Đang tải lượt ghé sắp tới" />
        ) : nextReservation ? (
          <div className="space-y-3">
            <div className="flex items-start gap-3">
              <div className="grid h-[4.75rem] w-[4.75rem] shrink-0 place-items-center rounded-lg border bg-secondary text-center">
                <CalendarDays className="h-5 w-5 text-teal-700" />
                <span className="block text-xs text-muted-foreground">Lượt ghé</span>
              </div>
              <div className="min-w-0 space-y-1">
                <p className="text-lg font-bold">{formatDateTime(visitTime)}</p>
                <p className="text-sm text-muted-foreground">{nextReservation.guest_count ?? "Chưa rõ"} khách</p>
                <p className="truncate text-sm text-muted-foreground">{branchName}</p>
              </div>
            </div>
            <AppButton asChild variant="outline" className="w-full">
              <Link href={`/reservations/${nextReservation.reservation_id}`}>
                Xem lượt ghé
                <ArrowRight className="h-4 w-4" />
              </Link>
            </AppButton>
          </div>
        ) : (
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">Bạn chưa có lịch đặt sắp tới.</p>
            <AppButton asChild variant="outline" className="w-full">
              <Link href="/booking">
                Đặt bàn mới
                <ArrowRight className="h-4 w-4" />
              </Link>
            </AppButton>
          </div>
        )}
      </section>
    </AppCard>
  );
}

function QuickActionCard({ action }: { action: HomeAction }) {
  const Icon = action.icon;

  return (
    <Link
      href={action.href}
      className="group flex min-h-16 items-center justify-between gap-3 rounded-lg border bg-card p-3 transition hover:border-primary/35 hover:shadow-[var(--restaurant-shadow)]"
    >
      <span className="flex min-w-0 items-center gap-3">
        <span className={cn("grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-secondary", action.tone)}>
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
