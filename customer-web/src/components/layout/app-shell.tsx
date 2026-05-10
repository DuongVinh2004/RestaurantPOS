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
import { useAuth } from "@/providers/auth-provider";
import { BackendStatusBanner } from "./backend-status-banner";
import { PublicFooter } from "./public-footer";

const navItems: CustomerNavItem[] = [
  { href: "/", label: "Trang chủ", icon: Home },
  { href: "/menu", label: "Thực đơn", icon: ReceiptText },
  { href: "/booking", label: "Đặt bàn", icon: CalendarDays },
  { href: "/reservations", label: "Lịch đặt", icon: CalendarDays },
  { href: "/waiting-list", label: "Chờ bàn", icon: ListChecks },
  { href: "/account", label: "Tài khoản", icon: UserRound },
];

const bottomNavItems: CustomerNavItem[] = [
  { href: "/", label: "Trang chủ", icon: Home },
  { href: "/menu", label: "Thực đơn", icon: ReceiptText },
  { href: "/booking", label: "Đặt bàn", icon: CalendarDays },
  { href: "/reservations", label: "Lịch đặt", icon: CalendarDays },
  { href: "/account", label: "Tài khoản", icon: UserRound },
];

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
      {shouldShowBottomNav(pathname) ? <CustomerBottomNav items={bottomNavItems} pathname={pathname} /> : null}
    </div>
  );
}
