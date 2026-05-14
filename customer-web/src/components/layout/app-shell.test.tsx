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
  identity: {
    hasGuestSession: false,
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

vi.mock("@/features/auth/hooks", () => ({
  useCustomerIdentity: () => mocks.identity,
}));

vi.mock("@/lib/config/feature-flags", () => ({
  featureFlags: {
    preorder: true,
    waitingList: false,
  },
}));

vi.mock("@/features/branch/branch-selector", () => ({
  SelectedBranchEntry: () => <button type="button">Chọn chi nhánh</button>,
}));

vi.mock("./backend-status-banner", () => ({
  BackendStatusBanner: () => <div data-testid="backend-status-banner" />,
}));

vi.mock("./public-footer", () => ({
  PublicFooter: () => <footer data-testid="public-footer" />,
}));

vi.mock("@/components/ui/sheet", () => ({
  Sheet: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  SheetTrigger: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  SheetContent: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  SheetDescription: ({ children }: { children: ReactNode }) => <div>{children}</div>,
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
    mocks.identity = {
      hasGuestSession: false,
    };
  });

  it("renders customer navigation and guest account entry", () => {
    render(
      <AppShell>
        <div>page</div>
      </AppShell>,
    );

    expect(screen.getByRole("link", { name: "Trang chủ Mộc Sen Bistro" })).toBeInTheDocument();
    expect(screen.getAllByRole("link", { name: "Trang chủ" }).length).toBeGreaterThan(0);
    expect(screen.getAllByRole("link", { name: "Thực đơn" }).length).toBeGreaterThan(0);
    expect(screen.getAllByRole("link", { name: "Đặt bàn" }).length).toBeGreaterThan(0);
    expect(screen.getAllByRole("link", { name: "Lịch đặt" }).length).toBeGreaterThan(0);
    expect(screen.queryByRole("link", { name: "Chờ bàn" })).not.toBeInTheDocument();
    expect(screen.getAllByRole("link", { name: "Tài khoản" }).length).toBeGreaterThan(0);
    expect(screen.queryByRole("button", { name: "Chọn chi nhánh" })).not.toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Đặt trước" })).not.toBeInTheDocument();
    expect(screen.getByLabelText("Điều hướng cuối màn hình")).toBeInTheDocument();
  });

  it("marks the active mobile route and shows authenticated customer state", () => {
    mocks.pathname = "/reservations";
    mocks.auth = {
      isAuthenticated: true,
      profile: { name: "Casey" },
      logout: mocks.logout,
    };
    mocks.identity = {
      hasGuestSession: true,
    };

    render(
      <AppShell>
        <div>page</div>
      </AppShell>,
    );

    expect(screen.getAllByText("Casey").length).toBeGreaterThan(0);

    const quickNav = screen.getByLabelText("Điều hướng cuối màn hình");
    expect(within(quickNav).getByRole("link", { name: "Lịch đặt" })).toHaveAttribute("aria-current", "page");
    expect(within(quickNav).getByRole("link", { name: "Tài khoản" })).toBeInTheDocument();
  });

  it("keeps auth pages free of the mobile bottom navigation", () => {
    mocks.pathname = "/register";

    render(
      <AppShell>
        <div>register page</div>
      </AppShell>,
    );

    expect(screen.queryByLabelText("Điều hướng cuối màn hình")).not.toBeInTheDocument();
  });
});
