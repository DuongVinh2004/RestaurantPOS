import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { HomePage } from "./home-page";

const mocks = vi.hoisted(() => ({
  branchSelection: {
    branches: [],
    selectedBranch: {
      branchId: 1,
      branchCode: "main",
      branchName: "Chi nhánh chính",
      timezone: "Asia/Bangkok",
      isOpen: true,
      statusReason: null,
      statusLabel: "Đang mở cửa",
      todayHoursLabel: "09:00 - 22:00",
      weeklyHours: [],
      address: "1 Main Street",
      phone: "+84123456789",
      phoneDisplay: "0123 456 789",
      directionsUrl: "https://maps.example.test",
    },
    selectedBranchId: 1,
    isLoading: false,
    error: null,
    locationPermission: "idle",
    locationMessage: null,
    selectBranch: vi.fn(),
    findNearMe: vi.fn(),
    refetch: vi.fn(),
  },
  identity: {
    isBootstrapping: false,
    isAuthenticated: false,
    isKnownCustomer: false,
    displayName: "Guest",
    customerToken: null,
    sessionId: null as string | null,
    hasGuestSession: false,
  },
  session: {
    sessionId: null as string | null,
    hasGuestSession: false,
    continueAsGuest: vi.fn(),
    refreshSessionState: vi.fn(),
  },
  featureFlags: {
    preorder: true,
    waitingList: false,
  },
  customerWebRollout: {
    accountBenefits: {
      enabled: false,
    },
  },
  getLoyalty: vi.fn(),
  listReservations: vi.fn(),
  trackCustomerEvent: vi.fn(),
}));

vi.mock("next/link", () => ({
  default: ({ children, href, ...props }: { children: ReactNode; href: string }) => <a href={href} {...props}>{children}</a>,
}));

vi.mock("@/features/branch/hooks", () => ({
  useBranchSelection: () => mocks.branchSelection,
}));

vi.mock("@/features/branch/branch-selector", () => ({
  BranchDetailCard: ({ branch }: { branch: { branchName: string } }) => <section data-testid="branch-detail">{branch.branchName}</section>,
  BranchSelector: () => <section data-testid="branch-selector">Chọn chi nhánh</section>,
  SelectedBranchEntry: () => <button type="button">Chi nhánh chính</button>,
}));

vi.mock("@/features/auth/hooks", () => ({
  useCustomerIdentity: () => mocks.identity,
  useCustomerSession: () => mocks.session,
}));

vi.mock("@/features/account/api", () => ({
  getLoyalty: mocks.getLoyalty,
}));

vi.mock("@/features/vouchers/state", () => ({
  getLoyaltyAccountState: () => null,
}));

vi.mock("@/lib/analytics/events", () => ({
  trackCustomerEvent: mocks.trackCustomerEvent,
}));

vi.mock("@/lib/config/feature-flags", () => ({
  featureFlags: mocks.featureFlags,
  customerWebRollout: mocks.customerWebRollout,
}));

vi.mock("@/features/menu/api", () => ({
  listMenuItems: () => Promise.resolve([]),
}));

vi.mock("@/features/reservations/api", () => ({
  listReservations: mocks.listReservations,
}));

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <HomePage />
    </QueryClientProvider>,
  );
}

describe("HomePage", () => {
  beforeEach(() => {
    mocks.identity.isAuthenticated = false;
    mocks.identity.isKnownCustomer = false;
    mocks.identity.displayName = "Guest";
    mocks.identity.hasGuestSession = false;
    mocks.session.sessionId = null;
    mocks.session.hasGuestSession = false;
    mocks.session.continueAsGuest.mockReset();
    mocks.featureFlags.preorder = true;
    mocks.featureFlags.waitingList = false;
    mocks.customerWebRollout.accountBenefits.enabled = false;
    mocks.getLoyalty.mockReset();
    mocks.listReservations.mockReset();
    mocks.listReservations.mockResolvedValue([]);
    mocks.trackCustomerEvent.mockReset();
  });

  it("renders the customer first screen and primary restaurant actions", () => {
    renderPage();

    expect(screen.getByRole("heading", { name: "Chọn món ngon, giữ bàn đúng giờ" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /^Xem thực đơn$/ })).toHaveAttribute("href", "/menu");
    expect(screen.getByRole("link", { name: /^Đặt bàn ngay$/ })).toHaveAttribute("href", "/booking");
    expect(screen.getByRole("button", { name: "Chi nhánh chính" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /^Tìm bàn$/ })).toHaveAttribute("href", "/booking?guest_count=2&date=today&time=19%3A00");
  });

  it("starts a guest session from the personal session panel", async () => {
    const user = userEvent.setup();

    renderPage();

    await user.click(screen.getByRole("button", { name: /Tiếp tục như khách/ }));

    expect(mocks.session.continueAsGuest).toHaveBeenCalledTimes(1);
  });

  it("shows account actions for a signed-in customer", () => {
    mocks.identity.isAuthenticated = true;
    mocks.identity.isKnownCustomer = true;
    mocks.identity.displayName = "Casey";
    mocks.identity.hasGuestSession = true;
    mocks.session.sessionId = "session-1";
    mocks.session.hasGuestSession = true;

    renderPage();

    expect(screen.getByText(/Xin chào, Casey/)).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /Lịch đặt/ })).toHaveAttribute("href", "/reservations");
    expect(screen.queryByRole("button", { name: /Tiếp tục như khách/ })).not.toBeInTheDocument();
  });
});
