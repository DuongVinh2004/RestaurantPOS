"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { CalendarDays, ListChecks, Menu as MenuIcon, ReceiptText, UserRound, type LucideIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { Separator } from "@/components/ui/separator";
import { customerWebRollout } from "@/lib/config/feature-flags";
import { useAuth } from "@/providers/auth-provider";
import { cn } from "@/lib/utils";
import { BackendStatusBanner } from "./backend-status-banner";

type NavItem = {
  href: string;
  label: string;
  icon: LucideIcon;
};

const coreNavItems: NavItem[] = [
  { href: "/", label: "Thực đơn", icon: ReceiptText },
  { href: "/booking", label: "Đặt bàn", icon: CalendarDays },
  { href: "/reservations", label: "Lịch đặt", icon: CalendarDays },
  { href: "/account", label: "Tài khoản", icon: UserRound },
];

const quickNavItems: NavItem[] = [
  { href: "/", label: "Thực đơn", icon: ReceiptText },
  { href: "/booking", label: "Đặt bàn", icon: CalendarDays },
  { href: "/reservations", label: "Lịch đặt", icon: CalendarDays },
  { href: "/account", label: "Tài khoản", icon: UserRound },
];

function getNavItems(): NavItem[] {
  return customerWebRollout.waitingList.enabled
    ? [...coreNavItems.slice(0, 3), { href: "/waiting-list", label: "Danh sách chờ", icon: ListChecks }, coreNavItems[3]]
    : coreNavItems;
}

function isActivePath(pathname: string, href: string) {
  if (href === "/") {
    return pathname === "/" || pathname.startsWith("/menu");
  }

  return pathname.startsWith(href);
}

function NavLinks({ items, onNavigate }: { items: NavItem[]; onNavigate?: () => void }) {
  const pathname = usePathname();

  return (
    <nav className="flex flex-col gap-1 sm:flex-row sm:items-center">
      {items.map((item) => {
        const active = isActivePath(pathname, item.href);
        const Icon = item.icon;

        return (
          <Link
            key={item.href}
            href={item.href}
            onClick={onNavigate}
            className={cn(
              "flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium text-muted-foreground transition hover:bg-accent hover:text-foreground",
              active && "bg-foreground text-background hover:bg-foreground hover:text-background",
            )}
          >
            <Icon className="h-4 w-4" />
            {item.label}
          </Link>
        );
      })}
    </nav>
  );
}

export function AppShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { isAuthenticated, profile, logout } = useAuth();
  const [sheetOpen, setSheetOpen] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const navItems = getNavItems();
  const showPublicFooter =
    pathname === "/" || pathname.startsWith("/booking") || pathname.startsWith("/menu") || pathname.startsWith("/login");

  const handleLogout = async () => {
    setSheetOpen(false);
    setIsLoggingOut(true);

    try {
      await logout();
    } finally {
      setIsLoggingOut(false);
    }
  };

  return (
    <div className={cn("min-h-svh bg-background", isAuthenticated && "pb-24 md:pb-0")}>
      <BackendStatusBanner />
      <header className="sticky top-0 z-30 border-b bg-background/92 backdrop-blur">
        <div className="mx-auto flex min-h-[4.5rem] w-full max-w-6xl items-center justify-between gap-3 px-4 py-3">
          <Link href="/" className="flex flex-col leading-tight">
              <span className="flex items-center gap-2 text-base font-semibold tracking-normal">
                <span className="h-2.5 w-2.5 rounded-full bg-primary" />
                RestaurantPOS
              </span>
            <span className="text-xs text-muted-foreground">Dành cho khách nhà hàng</span>
          </Link>

          <div className="hidden md:block">
            <NavLinks items={navItems} />
          </div>

          <div className="hidden items-center gap-2 md:flex">
            {isAuthenticated ? (
              <>
                <div className="flex flex-col text-right">
                  <span className="max-w-40 truncate text-sm font-medium">{profile?.name ?? "Khách hàng"}</span>
                  <span className="text-xs text-muted-foreground">Đã đăng nhập</span>
                </div>
                <Button type="button" variant="outline" className="rounded-lg" disabled={isLoggingOut} onClick={() => void handleLogout()}>
                  {isLoggingOut ? "Đang đăng xuất" : "Đăng xuất"}
                </Button>
              </>
            ) : (
              <Button asChild className="rounded-lg">
                <Link href="/login">Đăng nhập</Link>
              </Button>
            )}
          </div>

          <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
            <SheetTrigger asChild>
              <Button type="button" variant="outline" size="icon" className="rounded-lg md:hidden" aria-label="Mở menu">
                <MenuIcon className="h-5 w-5" />
              </Button>
            </SheetTrigger>
            <SheetContent className="w-[86vw] p-0">
              <SheetHeader className="px-4 py-4 text-left">
                <SheetTitle>RestaurantPOS</SheetTitle>
              </SheetHeader>
              <Separator />
              <div className="space-y-5 p-4">
                <div className="space-y-1">
                  <p className="text-sm font-medium">{isAuthenticated ? profile?.name ?? "Khách hàng" : "Truy cập khách hàng"}</p>
                  <p className="text-sm text-muted-foreground">
                    {isAuthenticated
                      ? "Xem lịch đặt, hóa đơn và tài khoản từ một nơi."
                      : "Xem thực đơn, tìm bàn và đăng nhập khi cần xem chi tiết lịch đặt."}
                  </p>
                </div>
                <NavLinks items={navItems} onNavigate={() => setSheetOpen(false)} />
                <Separator />
                {isAuthenticated ? (
                  <Button type="button" variant="outline" className="w-full rounded-lg" disabled={isLoggingOut} onClick={() => void handleLogout()}>
                    {isLoggingOut ? "Đang đăng xuất" : "Đăng xuất"}
                  </Button>
                ) : (
                  <Button asChild className="w-full rounded-lg" onClick={() => setSheetOpen(false)}>
                    <Link href="/login">Đăng nhập</Link>
                  </Button>
                )}
              </div>
            </SheetContent>
          </Sheet>
        </div>
      </header>
      {children}
      {showPublicFooter ? (
        <footer className="border-t bg-background/80">
          <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-6 md:flex-row md:items-center md:justify-between">
            <div className="space-y-1">
              <p className="font-medium">Chọn món, đặt bàn và thanh toán rõ ràng hơn.</p>
              <p className="max-w-2xl text-sm text-muted-foreground">
                Kiểm tra bàn trống, xem lại lịch đặt và theo dõi đặt cọc hoặc hóa đơn trong cùng một luồng khách hàng.
              </p>
            </div>
            <div className="flex flex-col gap-2 sm:flex-row">
              <Button asChild variant="outline" className="rounded-lg">
                <Link href="/booking">Tìm bàn</Link>
              </Button>
              <Button asChild className="rounded-lg">
                <Link href={isAuthenticated ? "/reservations" : "/login"}>{isAuthenticated ? "Mở lịch đặt" : "Đăng nhập"}</Link>
              </Button>
            </div>
          </div>
        </footer>
      ) : null}
      {isAuthenticated ? (
        <nav aria-label="Điều hướng nhanh" className="fixed inset-x-0 bottom-0 z-30 border-t bg-background/95 px-2 py-2 backdrop-blur md:hidden">
          <div className="mx-auto grid max-w-md grid-cols-4 gap-1">
            {quickNavItems.map((item) => {
              const Icon = item.icon;
              const active = isActivePath(pathname, item.href);

              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={cn(
                    "flex min-h-14 flex-col items-center justify-center gap-1 rounded-lg px-2 text-[11px] font-medium transition",
                    active ? "bg-foreground text-background" : "text-muted-foreground hover:bg-accent hover:text-foreground",
                  )}
                >
                  <Icon className="h-4 w-4" />
                  <span>{item.label}</span>
                </Link>
              );
            })}
          </div>
        </nav>
      ) : null}
    </div>
  );
}
