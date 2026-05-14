import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { storeCustomerBookingDraft } from "@/features/booking/booking-draft-storage";
import { readStoredPendingReservationPreorderDraft } from "@/features/preorder/reservation-draft-storage";
import { readLocalPreorderCart, writeLocalPreorderCart } from "@/features/preorder/local-cart";
import { ReservationCreatePage } from "./reservation-create-page";

function formatLocalDateTimeInput(date: Date): string {
  const pad = (value: number) => String(value).padStart(2, "0");

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function futureLocalDateTime(minutesFromNow: number): string {
  return formatLocalDateTimeInput(new Date(Date.now() + minutesFromNow * 60_000));
}

function futureIso(minutesFromNow: number): string {
  return new Date(Date.now() + minutesFromNow * 60_000).toISOString();
}

function createHoldSearchParams(overrides: Record<string, string> = {}): URLSearchParams {
  return new URLSearchParams({
    hold_id: "hold-123",
    hold_status: "Holding",
    hold_expires_at: futureIso(30),
    tables: "7,8",
    start_time: futureLocalDateTime(480),
    duration_minutes: "90",
    guest_count: "4",
    branch_id: "1",
    ...overrides,
  });
}

function createHold(overrides: Record<string, unknown> = {}) {
  return {
    hold_id: "hold-123",
    expire_at: futureIso(30),
    hold_status: "Holding",
    row_version: 2,
    start_time: new Date(Date.now() + 480 * 60_000).toISOString(),
    end_time: new Date(Date.now() + 570 * 60_000).toISOString(),
    duration_minutes: 90,
    tables: [{ table_id: 7 }, { table_id: 8 }],
    ...overrides,
  };
}

function createReservation(overrides: Record<string, unknown> = {}) {
  return {
    reservation_id: 501,
    reservation_code: "RSV-501",
    status: "Confirmed",
    row_version: 1,
    guest_count: 4,
    ...overrides,
  };
}

const mocks = vi.hoisted(() => ({
  createReservation: vi.fn(),
  createTableHold: vi.fn(),
  getTableHold: vi.fn(),
  push: vi.fn(),
  replace: vi.fn(),
  refreshTableHold: vi.fn(),
  searchParams: createHoldSearchParams(),
  searchAvailableTables: vi.fn(),
  authState: {
    isAuthenticated: false,
    profile: null as null | { name: string; email: string | null; phone: string | null },
  },
  toastError: vi.fn(),
  toastSuccess: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  useRouter: () => ({
    push: mocks.push,
    replace: mocks.replace,
  }),
  useSearchParams: () => mocks.searchParams,
}));

vi.mock("sonner", () => ({
  toast: {
    error: mocks.toastError,
    success: mocks.toastSuccess,
  },
}));

vi.mock("@/features/table-booking/api", () => ({
  createTableHold: mocks.createTableHold,
  getTableHold: mocks.getTableHold,
  refreshTableHold: mocks.refreshTableHold,
  searchAvailableTables: mocks.searchAvailableTables,
}));

vi.mock("./api", () => ({
  createReservation: mocks.createReservation,
}));

vi.mock("@/providers/auth-provider", () => ({
  useAuth: () => mocks.authState,
}));

function renderPage() {
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
      <ReservationCreatePage />
    </QueryClientProvider>,
  );
}

async function fillGuestContact(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByLabelText("Tên khách"), "Demo Customer");
  await user.type(screen.getByLabelText("Số điện thoại"), "5550100");
}

describe("ReservationCreatePage", () => {
  beforeEach(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
    ensureCustomerSessionId();
    mocks.createReservation.mockReset();
    mocks.createTableHold.mockReset();
    mocks.getTableHold.mockReset();
    mocks.push.mockReset();
    mocks.replace.mockReset();
    mocks.refreshTableHold.mockReset();
    mocks.searchAvailableTables.mockReset();
    mocks.toastError.mockReset();
    mocks.toastSuccess.mockReset();
    mocks.authState.isAuthenticated = false;
    mocks.authState.profile = null;
    mocks.searchParams = createHoldSearchParams();
    mocks.getTableHold.mockResolvedValue(createHold());
    mocks.refreshTableHold.mockResolvedValue(createHold({ expire_at: futureIso(40), row_version: 3 }));
    mocks.searchAvailableTables.mockResolvedValue({
      tables: [{ table_id: 7 }, { table_id: 8 }],
      meta: null,
    });
    mocks.createTableHold.mockResolvedValue(createHold({ hold_id: "hold-recovered", expire_at: futureIso(30), row_version: 1 }));
    mocks.createReservation.mockResolvedValue(createReservation());
  });

  it("shows clear hold status and locks held visit details", async () => {
    const holdStartTime = mocks.searchParams.get("start_time") as string;

    renderPage();

    const holdNotice = await screen.findByRole("region", {
      name: "Trạng thái giữ bàn",
    });
    expect(await within(holdNotice).findByText("Bàn đang được giữ cho bạn.")).toBeInTheDocument();
    expect(within(holdNotice).getByText("Bàn đã chọn")).toBeInTheDocument();
    expect(within(holdNotice).getByText("Bàn 7, Bàn 8")).toBeInTheDocument();
    expect(within(holdNotice).getByText("Số khách")).toBeInTheDocument();
    expect(within(holdNotice).getByText("4 khách")).toBeInTheDocument();
    expect(screen.getByDisplayValue(holdStartTime)).toBeDisabled();
    expect(screen.getByDisplayValue("90")).toBeDisabled();
    expect(screen.getByDisplayValue("4")).toBeDisabled();
    expect(screen.getByText("Bạn có thể chọn món trước sau khi đặt bàn thành công.")).toBeInTheDocument();
  });

  it("uses authenticated profile without requiring contact re-entry", async () => {
    const user = userEvent.setup();
    mocks.authState.isAuthenticated = true;
    mocks.authState.profile = {
      name: "Casey Nguyen",
      email: "casey@example.test",
      phone: null,
    };

    renderPage();

    await screen.findByRole("region", { name: "Trạng thái giữ bàn" });
    expect(screen.getByText(/Đang dùng thông tin từ tài khoản Casey Nguyen/i)).toBeInTheDocument();
    expect(screen.getByDisplayValue("Casey Nguyen")).toBeInTheDocument();
    expect(screen.getByDisplayValue("casey@example.test")).toBeInTheDocument();

    await user.click(screen.getAllByRole("button", { name: "Xác nhận đặt bàn" })[0]);

    await waitFor(() => {
      expect(mocks.createReservation).toHaveBeenCalledWith(
        expect.objectContaining({
          guest_name: "Casey Nguyen",
          guest_phone: "",
          guest_email: "casey@example.test",
          hold_id: "hold-123",
          table_ids: [7, 8],
        }),
      );
    });
    expect(mocks.push).toHaveBeenCalledWith("/reservations/501");
  });

  it("refreshes a near-expiry hold before creating the reservation", async () => {
    const user = userEvent.setup();
    const nearHold = createHold({ expire_at: futureIso(1), row_version: 4 });
    const refreshedHold = createHold({ expire_at: futureIso(20), row_version: 5 });

    mocks.searchParams = createHoldSearchParams({ hold_expires_at: futureIso(1) });
    mocks.getTableHold.mockResolvedValue(nearHold);
    mocks.refreshTableHold.mockResolvedValue(refreshedHold);

    renderPage();

    await screen.findByRole("region", { name: "Trạng thái giữ bàn" });
    await fillGuestContact(user);
    await user.click(screen.getAllByRole("button", { name: "Xác nhận đặt bàn" })[0]);

    await waitFor(() => {
      expect(mocks.refreshTableHold).toHaveBeenCalledWith("hold-123", 4);
      expect(mocks.createReservation).toHaveBeenCalledWith(expect.objectContaining({ hold_id: "hold-123" }));
    });
  });

  it("recovers an expired hold once when the same table is still available", async () => {
    mocks.searchParams = createHoldSearchParams({ hold_expires_at: futureIso(-1) });
    mocks.getTableHold.mockResolvedValue(createHold({ expire_at: futureIso(-1), hold_status: "Expired", row_version: 4 }));
    mocks.createTableHold.mockResolvedValue(createHold({ hold_id: "hold-recovered", expire_at: futureIso(30), row_version: 1 }));

    renderPage();

    expect((await screen.findAllByText("Đã giữ lại bàn cho bạn.")).length).toBeGreaterThan(0);
    expect(mocks.searchAvailableTables).toHaveBeenCalledWith(
      expect.objectContaining({ branch_id: 1, duration_minutes: 90, guest_count: 4 }),
    );
    expect(mocks.createTableHold).toHaveBeenCalledWith(
      expect.objectContaining({ branch_id: 1, duration_minutes: 90, guest_count: 4 }),
      [7, 8],
    );
    expect(mocks.replace).toHaveBeenCalledWith(expect.stringContaining("hold_id=hold-recovered"), { scroll: false });
  });

  it("keeps customer draft when expired hold cannot recover", async () => {
    mocks.searchParams = createHoldSearchParams({ hold_expires_at: futureIso(-1) });
    storeCustomerBookingDraft({
      branch_id: 1,
      start_time: mocks.searchParams.get("start_time"),
      duration_minutes: 90,
      guest_count: 4,
      guest_name: "Lan Nguyen",
      guest_phone: "0909000000",
      notes: "Gần cửa sổ",
      selected_table_ids: [7, 8],
      hold_id: "hold-123",
      hold_expires_at: futureIso(-1),
      hold_row_version: 4,
    });
    mocks.getTableHold.mockResolvedValue(createHold({ expire_at: futureIso(-1), hold_status: "Expired", row_version: 4 }));
    mocks.searchAvailableTables.mockResolvedValue({ tables: [], meta: null });

    renderPage();

    expect(await screen.findByText("Chưa có bàn đang giữ")).toBeInTheDocument();
    expect(screen.getByDisplayValue("Lan Nguyen")).toBeInTheDocument();
    expect(screen.getByDisplayValue("0909000000")).toBeInTheDocument();
    expect(screen.getByDisplayValue("Gần cửa sổ")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Chọn bàn" })).toHaveAttribute("href", "/booking");
  });

  it("maps hold conflict to friendly customer copy and preserves form data", async () => {
    const user = userEvent.setup();
    const conflictError = {
      kind: "conflict",
      status: 409,
      message: "table conflict",
      errorCode: "table_hold_conflict",
      categoryCode: "resource_conflict",
      validationErrors: { table_ids: ["overlap"] },
    };

    mocks.createReservation.mockRejectedValue(conflictError);
    mocks.searchAvailableTables.mockResolvedValue({ tables: [], meta: null });

    renderPage();

    await screen.findByRole("region", { name: "Trạng thái giữ bàn" });
    await fillGuestContact(user);
    await user.type(screen.getByLabelText("Ghi chú"), "Sinh nhật");
    await user.click(screen.getAllByRole("button", { name: "Xác nhận đặt bàn" })[0]);

    expect(await screen.findByText("Bàn này vừa có khách khác chọn.")).toBeInTheDocument();
    expect(screen.getByDisplayValue("Demo Customer")).toBeInTheDocument();
    expect(screen.getByDisplayValue("5550100")).toBeInTheDocument();
    expect(screen.getByDisplayValue("Sinh nhật")).toBeInTheDocument();
  });

  it("does not require preorder preview and stores cart as post-reservation draft", async () => {
    const user = userEvent.setup();
    const sessionId = ensureCustomerSessionId();

    writeLocalPreorderCart({
      version: 1,
      session_id: sessionId,
      branch_id: 1,
      serve_timing: "when_arrived",
      serve_note: "",
      updated_at: new Date().toISOString(),
      items: [
        {
          item_id: 101,
          name: "Gỏi cuốn",
          quantity: 2,
          note: "",
          price_amount: "120000.00",
          currency: "VND",
          image_url: null,
          is_available: true,
          preorder_enabled: true,
          updated_at: new Date().toISOString(),
        },
      ],
    });

    renderPage();

    await screen.findByText(/Mộc Sen đang giữ 2 món trong giỏ/i);
    await fillGuestContact(user);
    await user.click(screen.getAllByRole("button", { name: "Xác nhận đặt bàn" })[0]);

    await waitFor(() => {
      expect(mocks.createReservation).toHaveBeenCalledTimes(1);
    });
    expect(readStoredPendingReservationPreorderDraft(501)).toMatchObject({
      reservation_id: 501,
      failure_stage: "post_reservation",
      items: [{ item_id: 101, quantity: 2 }],
    });
    expect(readLocalPreorderCart(sessionId, 1).items).toHaveLength(0);
    expect(mocks.push).toHaveBeenCalledWith("/reservations/501?next=preorder#preorder");
  });

  it("revalidates hold state when the tab regains focus", async () => {
    renderPage();

    await screen.findByRole("region", { name: "Trạng thái giữ bàn" });
    expect((await screen.findAllByText("Bàn đang được giữ cho bạn.")).length).toBeGreaterThan(0);
    mocks.getTableHold.mockClear();
    fireEvent.focus(window);

    await waitFor(() => {
      expect(mocks.getTableHold).toHaveBeenCalledWith("hold-123");
    });
  });
});
