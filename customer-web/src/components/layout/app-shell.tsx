"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { CalendarDays, ListChecks, Menu as MenuIcon, ReceiptText, UserRound } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { Separator } from "@/components/ui/separator";
import { useAuth } from "@/providers/auth-provider";
import { cn } from "@/lib/utils";
import { BackendStatusBanner } from "./backend-status-banner";

const navItems = [
  { href: "/", label: "Menu", icon: ReceiptText },
  { href: "/booking", label: "Book", icon: CalendarDays },
  { href: "/reservations", label: "Reservations", icon: CalendarDays },
  { href: "/waiting-list", label: "Wait list", icon: ListChecks },
  { href: "/account", label: "Account", icon: UserRound },
];

function NavLinks({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = usePathname();

  return (
    <nav className="flex flex-col gap-1 sm:flex-row sm:items-center">
      {navItems.map((item) => {
        const active = item.href === "/" ? pathname === "/" : pathname.startsWith(item.href);
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
  const { isAuthenticated, profile, logout } = useAuth();

  return (
    <div className="min-h-svh bg-background">
      <BackendStatusBanner />
      <header className="sticky top-0 z-30 border-b bg-background/95 backdrop-blur">
        <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-3 px-4">
          <Link href="/" className="flex flex-col leading-tight">
            <span className="text-base font-semibold tracking-normal">RestaurantPOS</span>
            <span className="text-xs text-muted-foreground">Customer</span>
          </Link>

          <div className="hidden md:block">
            <NavLinks />
          </div>

          <div className="hidden items-center gap-2 md:flex">
            {isAuthenticated ? (
              <>
                <span className="max-w-40 truncate text-sm text-muted-foreground">{profile?.name ?? "Customer"}</span>
                <Button type="button" variant="outline" className="rounded-lg" onClick={logout}>
                  Sign out
                </Button>
              </>
            ) : (
              <Button asChild className="rounded-lg">
                <Link href="/login">Sign in</Link>
              </Button>
            )}
          </div>

          <Sheet>
            <SheetTrigger asChild>
              <Button type="button" variant="outline" size="icon" className="rounded-lg md:hidden" aria-label="Open menu">
                <MenuIcon className="h-5 w-5" />
              </Button>
            </SheetTrigger>
            <SheetContent className="w-[86vw] p-0">
              <SheetHeader className="px-4 py-4 text-left">
                <SheetTitle>RestaurantPOS</SheetTitle>
              </SheetHeader>
              <Separator />
              <div className="space-y-5 p-4">
                <NavLinks />
                <Separator />
                {isAuthenticated ? (
                  <Button type="button" variant="outline" className="w-full rounded-lg" onClick={logout}>
                    Sign out
                  </Button>
                ) : (
                  <Button asChild className="w-full rounded-lg">
                    <Link href="/login">Sign in</Link>
                  </Button>
                )}
              </div>
            </SheetContent>
          </Sheet>
        </div>
      </header>
      {children}
    </div>
  );
}
