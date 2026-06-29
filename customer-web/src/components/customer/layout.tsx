"use client";

import Link from "next/link";
import { useState, type ReactNode } from "react";
import type { LucideIcon } from "lucide-react";
import { CalendarCheck2, ChevronDown, ChevronRight, LogOut, Menu as MenuIcon, ShieldCheck, UserRound } from "lucide-react";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { Separator } from "@/components/ui/separator";
import { AppButton, StatusPill } from "./ui";
import { customerBrand } from "@/lib/brand/customer-brand";
import { cn } from "@/lib/utils";

export type CustomerNavItem = {
  href: string;
  label: string;
  icon: LucideIcon;
};

export function ResponsivePageShell({
  children,
  className,
  size = "wide",
}: {
  children: ReactNode;
  className?: string;
  size?: "narrow" | "wide";
}) {
  return (
    <main
      className={cn(
        "mx-auto w-full px-4 pt-7 pb-28 sm:py-9",
        size === "wide" ? "max-w-7xl" : "max-w-3xl",
        className,
      )}
    >
      {children}
    </main>
  );
}

export function CustomerHeader({
  navItems,
  pathname,
  isAuthenticated,
  profileName,
  hasGuestSession,
  isLoggingOut,
  onLogout,
}: {
  navItems: CustomerNavItem[];
  pathname: string;
  isAuthenticated: boolean;
  profileName: string | null;
  hasGuestSession: boolean;
  isLoggingOut: boolean;
  onLogout: () => void;
}) {
  const [sheetOpen, setSheetOpen] = useState(false);
  const activeItem = getActiveNavItem(navItems, pathname);

  return (
    <header className="sticky top-0 z-30 border-b bg-background/70 backdrop-blur-2xl shadow-sm transition-all">
      <div className="mx-auto flex min-h-[4.5rem] w-full max-w-7xl items-center justify-between gap-3 px-4 py-3">
        <Link href="/" className="group flex min-w-0 items-center gap-3 leading-tight" aria-label={`Trang chủ ${customerBrand.appName}`}>
          <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground">
            {customerBrand.monogram}
          </span>
          <span className="min-w-0">
            <span className="block text-[1.25rem] font-bold tracking-normal text-foreground">
              {customerBrand.appName}
            </span>
            <span className="hidden text-xs font-medium text-muted-foreground sm:block">
              {activeItem ? activeItem.label : customerBrand.tagline}
            </span>
          </span>
        </Link>

        <nav className="hidden min-w-0 items-center gap-3 xl:flex" aria-label="Điều hướng chính">
          <CustomerNavLinks items={navItems} pathname={pathname} />
        </nav>

        <div className="hidden min-w-0 items-center gap-2 lg:flex">
          <SessionEntry
            isAuthenticated={isAuthenticated}
            profileName={profileName}
            hasGuestSession={hasGuestSession}
            isLoggingOut={isLoggingOut}
            onLogout={onLogout}
          />
        </div>

        <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
          <SheetTrigger asChild>
            <AppButton type="button" variant="outline" size="icon" className="xl:hidden" aria-label="Mở điều hướng">
              <MenuIcon className="h-5 w-5" />
            </AppButton>
          </SheetTrigger>
          <SheetContent className="w-[88vw] p-0">
            <SheetHeader className="px-4 py-4 text-left">
              <SheetTitle>{customerBrand.appName}</SheetTitle>
              <SheetDescription>Chọn mục bạn muốn mở cho bữa ăn sắp tới.</SheetDescription>
            </SheetHeader>
            <Separator />
            <div className="space-y-5 p-4">
              <div className="rounded-lg border bg-secondary/45 p-4">
                <div className="flex items-start gap-3">
                  <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                  <div className="space-y-1">
                    <p className="text-sm font-semibold">{customerBrand.visitLabel}</p>
                    <p className="text-sm text-muted-foreground">
                      Theo dõi đặt bàn, thực đơn và thông tin liên hệ trong cùng một nơi.
                    </p>
                  </div>
                </div>
              </div>
              <CustomerNavLinks items={navItems} pathname={pathname} onNavigate={() => setSheetOpen(false)} stacked />
              <Separator />
              <SessionEntry
                isAuthenticated={isAuthenticated}
                profileName={profileName}
                hasGuestSession={hasGuestSession}
                isLoggingOut={isLoggingOut}
                onLogout={() => {
                  setSheetOpen(false);
                  onLogout();
                }}
                mobile
              />
            </div>
          </SheetContent>
        </Sheet>
      </div>
    </header>
  );
}

export function CustomerBottomNav({
  items,
  pathname,
  className,
}: {
  items: CustomerNavItem[];
  pathname: string;
  className?: string;
}) {
  return (
    <nav
      aria-label="Điều hướng cuối màn hình"
      className={cn("fixed inset-x-0 bottom-0 z-50 border-t bg-background/70 px-3 pb-[calc(env(safe-area-inset-bottom)+1rem)] pt-2 backdrop-blur-2xl md:hidden transition-all", className)}
    >
      <div className="mx-auto grid max-w-[360px] grid-cols-5 items-center gap-1 rounded-full border border-black/5 dark:border-white/5 bg-background/95 p-1.5 shadow-2xl backdrop-blur-xl">
        {items.map((item) => {
          const Icon = item.icon;
          const active = isActivePath(pathname, item.href);
          const central = item.href === "/booking";

          return (
            <Link
              key={`${item.href}:${item.label}`}
              href={item.href}
              aria-current={active ? "page" : undefined}
              className={cn(
                "flex h-12 w-full flex-col items-center justify-center gap-0.5 rounded-full px-1 text-[10px] font-semibold transition-all duration-300",
                central
                  ? "bg-primary text-primary-foreground h-14 w-14 -translate-y-4 shadow-lg mx-auto"
                  : active
                    ? "text-primary"
                    : "text-muted-foreground hover:bg-accent hover:text-foreground",
              )}
            >
              <Icon className={cn("h-5 w-5 mb-0.5", active && !central && "fill-primary/20")} />
              {!central && <span className="text-center leading-tight truncate w-full">{item.label}</span>}
            </Link>
          );
        })}
      </div>
    </nav>
  );
}

function CustomerNavLinks({
  items,
  pathname,
  onNavigate,
  stacked = false,
}: {
  items: CustomerNavItem[];
  pathname: string;
  onNavigate?: () => void;
  stacked?: boolean;
}) {
  return (
    <div className={cn("flex gap-1", stacked ? "flex-col" : "items-center")}>
      {items.map((item) => {
        const Icon = item.icon;
        const active = isActivePath(pathname, item.href);

        return (
          <Link
            key={`${item.href}:${item.label}`}
            href={item.href}
            onClick={onNavigate}
            aria-current={active ? "page" : undefined}
            className={cn(
              "relative flex min-h-11 shrink-0 items-center gap-2 text-sm font-semibold transition",
              stacked
                ? "rounded-lg border px-3 text-foreground hover:border-primary/40 hover:bg-secondary/45"
                : "rounded-none border-b-2 border-transparent px-2.5 text-muted-foreground hover:text-foreground",
              active && (stacked ? "border-primary bg-primary/5 text-primary" : "border-primary text-primary"),
            )}
          >
            <Icon className="h-4 w-4" />
            <span className="whitespace-nowrap">{item.label}</span>
            {stacked ? <ChevronRight className="ml-auto h-4 w-4 text-muted-foreground" /> : null}
          </Link>
        );
      })}
    </div>
  );
}

function SessionEntry({
  isAuthenticated,
  profileName,
  hasGuestSession,
  isLoggingOut,
  mobile = false,
  onLogout,
}: {
  isAuthenticated: boolean;
  profileName: string | null;
  hasGuestSession: boolean;
  isLoggingOut: boolean;
  mobile?: boolean;
  onLogout: () => void;
}) {
  if (isAuthenticated) {
    return (
      <div className={cn("flex items-center gap-2", mobile && "grid gap-2")}>
        <div className={cn("flex min-w-0 items-center gap-2 rounded-lg border bg-secondary/35 px-3 py-2", mobile && "w-full")}>
          <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
            <CalendarCheck2 className="h-4 w-4" />
          </span>
          <span className={cn("flex min-w-0 flex-col", mobile ? "text-left" : "text-right")}>
            <span className="max-w-40 truncate text-sm font-medium">{profileName ?? "Khách hàng"}</span>
            <span className="text-xs text-muted-foreground">Đã đăng nhập</span>
          </span>
        </div>
        <AppButton type="button" variant="outline" disabled={isLoggingOut} onClick={onLogout} className={cn(mobile && "w-full")}>
          <LogOut className="h-4 w-4 mr-2" />
          <span>{isLoggingOut ? "Đang đăng xuất" : "Đăng xuất"}</span>
        </AppButton>
      </div>
    );
  }

  return (
    <div className={cn("flex items-center gap-2", mobile && "grid gap-2")}>
      <StatusPill label={hasGuestSession ? "Phiên khách" : "Khách"} tone="info" />
      <AppButton asChild variant="outline" className={cn("gap-2", mobile && "w-full")}>
        <Link href="/login">
          <UserRound className="h-4 w-4" />
          Tài khoản
          <ChevronDown className="h-4 w-4" />
        </Link>
      </AppButton>
    </div>
  );
}

export function isActivePath(pathname: string, href: string) {
  if (href === "/") {
    return pathname === "/";
  }

  if (href === "/menu") {
    return pathname === "/menu" || pathname.startsWith("/menu/");
  }

  if (href === "/booking") {
    return pathname === "/booking";
  }

  return pathname === href || pathname.startsWith(`${href}/`);
}

function getActiveNavItem(items: CustomerNavItem[], pathname: string): CustomerNavItem | null {
  return items.find((item) => isActivePath(pathname, item.href)) ?? null;
}
