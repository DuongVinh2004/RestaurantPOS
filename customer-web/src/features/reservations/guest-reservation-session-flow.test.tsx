import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ProtectedRoute } from "@/components/layout/protected-route";
import { clearStoredCustomerAuth, ensureCustomerSessionId } from "@/lib/auth/storage";
import { ReservationCreatePage } from "./reservation-create-page";

function formatLocalDateTimeInput(date: Date): string {
  const pad = (value: number) => String(value).padStart(2, "0");

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function futureLocalDateTime(hoursFromNow: number): string {
  return formatLocalDateTimeInput(new Date(Date.now() + hoursFromNow * 60 * 60 * 1000));
}

function futureIso(hoursFromNow: number): string {
  return new Date(Date.now() + hoursFromNow * 60 * 60 * 1000).toISOString();
}

function createHoldSearchParams(): URLSearchParams {
  return new URLSearchParams({
    hold_id: "hold-guest-123",
    hold_status: "Holding",
    hold_expires_at: futureIso(2),
    tables: "7,8",
    start_time: futureLocalDateTime(8),
    duration_minutes: "90",
    guest_count: "4",
  });
}

const mocks = vi.hoisted(() => ({
  cancelTableHold: vi.fn(),
  createReservation: vi.fn(),
  getTableHold: vi.fn(),
  logout: vi.fn(),
  pathname: "/reservations/new",
  push: vi.fn(),
  refreshTableHold: vi.fn(),
  retryBootstrap: vi.fn(),
  searchParams: createHoldSearchParams(),
  toastSuccess: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  usePathname: () => mocks.pathname,
  useRouter: () => ({
    push: mocks.push,
  }),
  useSearchParams: () => mocks.searchParams,
}));

vi.mock("@/providers/auth-provider", () => ({
  useAuth: () => ({
    authError: null,
    isAuthenticated: false,
    isBootstrapping: false,
    logout: mocks.logout,
    markAuthenticated: vi.fn(),
    profile: null,
    retryBootstrap: mocks.retryBootstrap,
    session: null,
  }),
}));

vi.mock("sonner", () => ({
  toast: {
    success: mocks.toastSuccess,
  },
}));

vi.mock("./api", () => ({
  createReservation: mocks.createReservation,
}));

vi.mock("@/features/table-booking/api", () => ({
  cancelTableHold: mocks.cancelTableHold,
  getTableHold: mocks.getTableHold,
  refreshTableHold: mocks.refreshTableHold,
}));

function renderFlow() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
      mutations: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <ProtectedRoute>
        <ReservationCreatePage />
      </ProtectedRoute>
    </QueryClientProvider>,
  );
}

describe("guest hold to reservation session flow", () => {
  beforeEach(() => {
    clearStoredCustomerAuth();
    window.localStorage.clear();
    window.sessionStorage.clear();
    ensureCustomerSessionId();
    mocks.cancelTableHold.mockReset();
    mocks.createReservation.mockReset();
    mocks.getTableHold.mockReset();
    mocks.logout.mockReset();
    mocks.push.mockReset();
    mocks.refreshTableHold.mockReset();
    mocks.retryBootstrap.mockReset();
    mocks.searchParams = createHoldSearchParams();
    mocks.toastSuccess.mockReset();
    mocks.getTableHold.mockResolvedValue({
      hold_id: "hold-guest-123",
      expire_at: futureIso(2),
      hold_status: "Holding",
      row_version: 2,
      start_time: new Date(Date.now() + 8 * 60 * 60 * 1000).toISOString(),
      end_time: new Date(Date.now() + 9.5 * 60 * 60 * 1000).toISOString(),
      duration_minutes: 90,
      tables: [{ table_id: 7 }, { table_id: 8 }],
    });
    mocks.createReservation.mockResolvedValue({
      reservation_id: 501,
      row_version: 1,
      status: "Confirmed",
    });
  });

  it("lets an unauthenticated held session open reservation create and submit the held reservation", async () => {
    const user = userEvent.setup();

    renderFlow();

    expect(await screen.findByText(/Using table hold hold-guest-123/i)).toBeInTheDocument();
    expect(screen.queryByText("Sign in to continue")).not.toBeInTheDocument();

    await user.type(screen.getByLabelText("Guest name"), "Guest Booker");
    await user.type(screen.getByLabelText("Phone"), "5550100");
    await user.type(screen.getByLabelText("Email"), "guest@example.test");
    await user.type(screen.getByLabelText("Notes"), "Window seat");
    await user.click(screen.getByRole("button", { name: "Create reservation" }));

    await waitFor(() => {
      expect(mocks.createReservation).toHaveBeenCalledWith(
        expect.objectContaining({
          guest_name: "Guest Booker",
          guest_phone: "5550100",
          guest_email: "guest@example.test",
          hold_id: "hold-guest-123",
          table_ids: [7, 8],
          guest_count: 4,
          duration_minutes: 90,
          notes: "Window seat",
        }),
      );
    });
    expect(mocks.push).toHaveBeenCalledWith("/reservations/501");
  });
});
