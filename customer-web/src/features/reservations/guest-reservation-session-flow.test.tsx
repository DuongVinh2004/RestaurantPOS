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
  return formatLocalDateTimeInput(
    new Date(Date.now() + hoursFromNow * 60 * 60 * 1000),
  );
}

function futureIso(hoursFromNow: number): string {
  return new Date(
    Date.now() + hoursFromNow * 60 * 60 * 1000,
  ).toISOString();
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

function createMenuItem(overrides: Record<string, unknown> = {}) {
  return {
    item_id: 101,
    category_id: 1,
    category_name: "Khai vị",
    code: "GOI-CUON",
    name: "Gỏi cuốn",
    description: null,
    img_url: null,
    is_available: true,
    price: {
      price_id: 3,
      amount: "120000.00",
      currency: "VND",
      effective_from: null,
      effective_to: null,
    },
    preorder: {
      enabled: true,
      cutoff_minutes: 30,
      quota_per_day: 20,
      requires_preview_validation: true,
    },
    created_at: null,
    updated_at: null,
    ...overrides,
  };
}

const mocks = vi.hoisted(() => ({
  cancelTableHold: vi.fn(),
  createReservationWithPreorderDraft: vi.fn(),
  getTableHold: vi.fn(),
  isReservationPreorderPersistenceError: vi.fn().mockReturnValue(false),
  listMenuItems: vi.fn(),
  logout: vi.fn(),
  pathname: "/reservations/new",
  previewMenuPreorder: vi.fn(),
  push: vi.fn(),
  refreshTableHold: vi.fn(),
  retryBootstrap: vi.fn(),
  searchParams: createHoldSearchParams(),
  toastError: vi.fn(),
  toastSuccess: vi.fn(),
  customerWebRollout: {
    preorder: {
      enabled: true,
      disabledTitle: "Món đặt trước chưa được bật",
      disabledDescription: "Nhà hàng chưa bật món đặt trước cho khách hàng.",
    },
  },
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
    error: mocks.toastError,
    success: mocks.toastSuccess,
  },
}));

vi.mock("@/features/preorder/reservation-create-flow", () => ({
  createReservationWithPreorderDraft: mocks.createReservationWithPreorderDraft,
  isReservationPreorderPersistenceError:
    mocks.isReservationPreorderPersistenceError,
}));

vi.mock("@/features/menu/api", () => ({
  listMenuItems: mocks.listMenuItems,
  previewMenuPreorder: mocks.previewMenuPreorder,
}));

vi.mock("@/lib/config/feature-flags", () => ({
  customerWebRollout: mocks.customerWebRollout,
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
    mocks.createReservationWithPreorderDraft.mockReset();
    mocks.getTableHold.mockReset();
    mocks.isReservationPreorderPersistenceError.mockReset();
    mocks.isReservationPreorderPersistenceError.mockReturnValue(false);
    mocks.listMenuItems.mockReset();
    mocks.logout.mockReset();
    mocks.previewMenuPreorder.mockReset();
    mocks.push.mockReset();
    mocks.refreshTableHold.mockReset();
    mocks.retryBootstrap.mockReset();
    mocks.searchParams = createHoldSearchParams();
    mocks.toastError.mockReset();
    mocks.toastSuccess.mockReset();
    mocks.customerWebRollout.preorder.enabled = true;
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
    mocks.listMenuItems.mockResolvedValue([createMenuItem()]);
    mocks.previewMenuPreorder.mockResolvedValue({
      totals: {
        quantity: 2,
        subtotal: "240000.00",
        currency: "VND",
      },
      warnings: [],
      policy: {
        message: "Nhà hàng sẽ xác nhận lại số lượng trước khi lưu.",
      },
    });
    mocks.createReservationWithPreorderDraft.mockResolvedValue({
      reservation: {
        reservation_id: 501,
        row_version: 1,
        status: "Confirmed",
      },
      preorder: null,
    });
  });

  it("lets an unauthenticated held session open reservation create and submit the held reservation with preorder draft", async () => {
    const user = userEvent.setup();

    renderFlow();

    expect(await screen.findByText("Mã giữ bàn")).toBeInTheDocument();
    expect(screen.getByText("hold-guest-123")).toBeInTheDocument();
    expect(screen.queryByText("Đăng nhập để tiếp tục")).not.toBeInTheDocument();

    await user.type(screen.getByLabelText("Tên khách"), "Guest Booker");
    await user.type(screen.getByLabelText("Số điện thoại"), "5550100");
    await user.type(screen.getByLabelText("Email"), "guest@example.test");
    await user.type(screen.getByLabelText("Ghi chú"), "Window seat");
    await user.clear(await screen.findByLabelText("Số lượng Gỏi cuốn"));
    await user.type(screen.getByLabelText("Số lượng Gỏi cuốn"), "2");
    await user.click(screen.getByRole("button", { name: "Xem trước món" }));
    await screen.findByText("Bản xem trước món đặt trước");
    await user.click(screen.getByRole("button", { name: "Tạo lịch đặt" }));

    await waitFor(() => {
      expect(mocks.createReservationWithPreorderDraft).toHaveBeenCalledWith({
        reservationInput: expect.objectContaining({
          guest_name: "Guest Booker",
          guest_phone: "5550100",
          guest_email: "guest@example.test",
          hold_id: "hold-guest-123",
          table_ids: [7, 8],
          guest_count: 4,
          duration_minutes: 90,
          notes: "Window seat",
        }),
        preorderItems: [{ item_id: 101, quantity: 2 }],
      });
    });
    expect(mocks.push).toHaveBeenCalledWith("/reservations/501");
  }, 15_000);
});
