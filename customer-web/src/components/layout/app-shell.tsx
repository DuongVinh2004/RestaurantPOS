"use client";

import { usePathname } from "next/navigation";
import { CalendarDays, Home, ListChecks, ReceiptText, UserRound } from "lucide-react";
import { useState } from "react";
import {
  CustomerBottomNav,
  CustomerHeader,
  type CustomerNavItem,
} from "@/components/customer/layout";
import { useCustomerIdentity } from "@/features/auth/hooks";
import { featureFlags } from "@/lib/config/feature-flags";
import { useAuth } from "@/providers/auth-provider";
import { BackendStatusBanner } from "./backend-status-banner";
import { PublicFooter } from "./public-footer";

const homeNavItem: CustomerNavItem = { href: "/", label: "Trang chủ", icon: Home };
const menuNavItem: CustomerNavItem = { href: "/menu", label: "Thực đơn", icon: ReceiptText };
const bookingNavItem: CustomerNavItem = { href: "/booking", label: "Đặt bàn", icon: CalendarDays };
const reservationsNavItem: CustomerNavItem = { href: "/reservations", label: "Lịch đặt", icon: CalendarDays };
const accountNavItem: CustomerNavItem = { href: "/account", label: "Tài khoản", icon: UserRound };

const primaryNavItems: CustomerNavItem[] = [
  homeNavItem,
  menuNavItem,
  bookingNavItem,
  reservationsNavItem,
  accountNavItem,
];

const waitingListNavItem: CustomerNavItem = { href: "/waiting-list", label: "Chờ bàn", icon: ListChecks };

function shouldShowPublicFooter(pathname: string): boolean {
  return pathname === "/" || pathname.startsWith("/menu") || pathname.startsWith("/booking") || pathname.startsWith("/login");
}

function shouldShowBottomNav(pathname: string): boolean {
  return !pathname.startsWith("/login") && !pathname.startsWith("/register");
}

export function AppShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const auth = useAuth();
  const identity = useCustomerIdentity();
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const navItems = featureFlags.waitingList
    ? [homeNavItem, menuNavItem, bookingNavItem, reservationsNavItem, waitingListNavItem, accountNavItem]
    : primaryNavItems;

  const handleLogout = async () => {
    setIsLoggingOut(true);

    try {
      await auth.logout();
    } finally {
      setIsLoggingOut(false);
    }
  };

  return (
    <div className="min-h-svh bg-background pb-24 md:pb-0">
      <BackendStatusBanner />
      <CustomerHeader
        navItems={navItems}
        pathname={pathname}
        isAuthenticated={auth.isAuthenticated}
        profileName={auth.profile?.name ?? null}
        hasGuestSession={identity.hasGuestSession}
        isLoggingOut={isLoggingOut}
        onLogout={() => void handleLogout()}
      />
      {children}
      {shouldShowPublicFooter(pathname) ? <PublicFooter isAuthenticated={auth.isAuthenticated} /> : null}
      {shouldShowBottomNav(pathname) ? <CustomerBottomNav items={primaryNavItems} pathname={pathname} /> : null}
    </div>
  );
}
