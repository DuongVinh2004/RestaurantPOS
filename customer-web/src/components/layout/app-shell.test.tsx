import { render, screen, within } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AppShell } from "./app-shell";

const mocks = vi.hoisted(() => ({
  pathname: "/",
  logout: vi.fn(),
  auth: {
    isAuthenticated: false,
    profile: null as { name?: string } | null,
    logout: vi.fn(),
  },
}));

vi.mock("next/link", () => ({
  default: ({ children, href, ...props }: { children: ReactNode; href: string }) => <a href={href} {...props}>{children}</a>,
}));

vi.mock("next/navigation", () => ({
  usePathname: () => mocks.pathname,
}));

vi.mock("@/providers/auth-provider", () => ({
  useAuth: () => mocks.auth,
}));

vi.mock("@/lib/config/feature-flags", () => ({
  featureFlags: {
    waitingList: false,
  },
  customerWebRollout: {
    waitingList: {
      enabled: false,
    },
  },
}));

vi.mock("./backend-status-banner", () => ({
  BackendStatusBanner: () => <div data-testid="backend-status-banner" />,
}));

vi.mock("@/components/ui/sheet", () => ({
  Sheet: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  SheetTrigger: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  SheetContent: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  SheetHeader: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  SheetTitle: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));

describe("AppShell", () => {
  beforeEach(() => {
    mocks.pathname = "/";
    mocks.logout.mockReset();
    mocks.auth = {
      isAuthenticated: false,
      profile: null,
      logout: mocks.logout,
    };
  });

  it("keeps rollout-disabled waiting list links out of the shell", () => {
    render(
      <AppShell>
        <div>page</div>
      </AppShell>,
    );

    expect(screen.queryByRole("link", { name: "Danh sách chờ" })).not.toBeInTheDocument();
    expect(screen.getAllByRole("link", { name: "Đăng nhập" }).length).toBeGreaterThan(0);
    expect(screen.queryByLabelText("Điều hướng nhanh")).not.toBeInTheDocument();
  });

  it("shows quick mobile navigation for authenticated customers", () => {
    mocks.pathname = "/reservations";
    mocks.auth = {
      isAuthenticated: true,
      profile: { name: "Casey" },
      logout: mocks.logout,
    };

    render(
      <AppShell>
        <div>page</div>
      </AppShell>,
    );

    expect(screen.getAllByText("Casey")).toHaveLength(2);

    const quickNav = screen.getByLabelText("Điều hướng nhanh");
    expect(within(quickNav).getByRole("link", { name: "Lịch đặt" })).toBeInTheDocument();
    expect(within(quickNav).getByRole("link", { name: "Tài khoản" })).toBeInTheDocument();
  });
});
