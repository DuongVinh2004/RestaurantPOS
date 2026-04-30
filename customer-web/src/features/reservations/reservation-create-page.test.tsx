import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
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

function createHoldSearchParams(overrides: Record<string, string> = {}): URLSearchParams {
  return new URLSearchParams({
    hold_id: "hold-123",
    hold_status: "Holding",
    hold_expires_at: futureIso(2),
    tables: "7,8",
    start_time: futureLocalDateTime(8),
    duration_minutes: "90",
    guest_count: "4",
    ...overrides,
  });
}

const mocks = vi.hoisted(() => ({
  createReservation: vi.fn(),
  getTableHold: vi.fn(),
  push: vi.fn(),
  refreshTableHold: vi.fn(),
  searchParams: createHoldSearchParams(),
}));

vi.mock("next/navigation", () => ({
  useRouter: () => ({
    push: mocks.push,
  }),
  useSearchParams: () => mocks.searchParams,
}));

vi.mock("./api", () => ({
  createReservation: mocks.createReservation,
}));

vi.mock("@/features/table-booking/api", () => ({
  getTableHold: mocks.getTableHold,
  refreshTableHold: mocks.refreshTableHold,
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

describe("ReservationCreatePage", () => {
  beforeEach(() => {
    mocks.createReservation.mockReset();
    mocks.getTableHold.mockReset();
    mocks.refreshTableHold.mockReset();
    mocks.getTableHold.mockResolvedValue({
      hold_id: "hold-123",
      expire_at: futureIso(2),
      hold_status: "Holding",
      row_version: 2,
      start_time: new Date(Date.now() + 8 * 60 * 60 * 1000).toISOString(),
      end_time: new Date(Date.now() + 9.5 * 60 * 60 * 1000).toISOString(),
      duration_minutes: 90,
      tables: [{ table_id: 7 }, { table_id: 8 }],
    });
    mocks.push.mockReset();
    mocks.searchParams = createHoldSearchParams();
  });

  it("locks held visit details when the page is opened from a table hold", async () => {
    const holdStartTime = mocks.searchParams.get("start_time") as string;

    renderPage();

    const holdNotice = await screen.findByRole("region", { name: "Thông tin bàn đang giữ" });
    expect(within(holdNotice).getByText("Bàn đang được giữ cho bạn")).toBeInTheDocument();
    expect(within(holdNotice).getByText("Bàn đã chọn")).toBeInTheDocument();
    expect(within(holdNotice).getByText("Bàn 7, Bàn 8")).toBeInTheDocument();
    expect(within(holdNotice).getByText("Số khách")).toBeInTheDocument();
    expect(within(holdNotice).getByText("4 khách")).toBeInTheDocument();
    expect(within(holdNotice).getByText("Thời lượng giữ bàn")).toBeInTheDocument();
    expect(within(holdNotice).getByText("90 phút")).toBeInTheDocument();
    expect(screen.queryByText(/Lượt ghé/i)).not.toBeInTheDocument();
    expect(screen.getByDisplayValue(holdStartTime)).toBeDisabled();
    expect(screen.getByDisplayValue("90")).toBeDisabled();
    expect(screen.getByDisplayValue("4")).toBeDisabled();
    expect(screen.getByRole("button", { name: "Gia hạn giữ bàn" })).toBeInTheDocument();
  });

  it("blocks reservation create when the linked hold is already expired", async () => {
    mocks.searchParams = createHoldSearchParams({
      hold_status: "Holding",
      hold_expires_at: futureIso(2),
    });
    mocks.getTableHold.mockResolvedValue({
      hold_id: "hold-123",
      expire_at: futureIso(-2),
      hold_status: "Expired",
      row_version: 3,
      start_time: new Date(Date.now() + 8 * 60 * 60 * 1000).toISOString(),
      end_time: new Date(Date.now() + 9.5 * 60 * 60 * 1000).toISOString(),
      duration_minutes: 90,
      tables: [{ table_id: 7 }, { table_id: 8 }],
    });

    renderPage();

    expect(await screen.findByText(/không còn hiệu lực/i)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Tạo lịch đặt" })).toBeDisabled();
    expect(screen.getByRole("link", { name: "Tìm bàn phù hợp lại" })).toHaveAttribute("href", "/booking");
  });

  it("blocks reservation create when the linked hold cannot be verified live", async () => {
    mocks.getTableHold.mockRejectedValue({
      kind: "not_found",
      status: 404,
      message: "Hold not found.",
      errorCode: "not_found",
      categoryCode: "not_found",
      requestId: "req-hold-missing",
      validationErrors: null,
    });

    renderPage();

    expect(await screen.findByText(/Chưa xác minh được bàn đã chọn/i)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Tạo lịch đặt" })).toBeDisabled();
    expect(screen.getByRole("link", { name: "Tìm bàn phù hợp lại" })).toHaveAttribute("href", "/booking");
  });

  it("creates a reservation with the verified hold context and redirects to detail", async () => {
    const user = userEvent.setup();

    mocks.createReservation.mockResolvedValue({
      reservation_id: 501,
      reservation_code: "RSV-501",
      status: "Confirmed",
      row_version: 1,
      guest_count: 4,
    });

    renderPage();

    await screen.findByRole("region", { name: "Thông tin bàn đang giữ" });
    await user.type(screen.getByLabelText("Tên khách"), "Demo Customer");
    await user.type(screen.getByLabelText("Số điện thoại"), "5550100");
    await user.type(screen.getByLabelText("Ghi chú"), "Window seat");
    await user.click(screen.getByRole("button", { name: "Tạo lịch đặt" }));

    expect(mocks.createReservation).toHaveBeenCalledWith(
      expect.objectContaining({
        guest_name: "Demo Customer",
        guest_phone: "5550100",
        notes: "Window seat",
        hold_id: "hold-123",
        table_ids: [7, 8],
      }),
    );
    expect(mocks.push).toHaveBeenCalledWith("/reservations/501");
  });
});
