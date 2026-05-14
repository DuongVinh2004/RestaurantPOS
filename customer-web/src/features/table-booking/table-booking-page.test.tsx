import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { TableBookingPage } from "./table-booking-page";
import { storeActiveTableHoldSnapshot } from "./state";

const mocks = vi.hoisted(() => ({
  searchAvailableTables: vi.fn(),
  createTableHold: vi.fn(),
  refreshTableHold: vi.fn(),
  cancelTableHold: vi.fn(),
  toastSuccess: vi.fn(),
  toastError: vi.fn(),
}));

vi.mock("next/link", () => ({
  default: ({ children, href, ...props }: { children: ReactNode; href: string }) => <a href={href} {...props}>{children}</a>,
}));

vi.mock("sonner", () => ({
  toast: {
    success: mocks.toastSuccess,
    error: mocks.toastError,
  },
}));

vi.mock("./api", () => ({
  searchAvailableTables: mocks.searchAvailableTables,
  createTableHold: mocks.createTableHold,
  refreshTableHold: mocks.refreshTableHold,
  cancelTableHold: mocks.cancelTableHold,
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
      <TableBookingPage />
    </QueryClientProvider>,
  );
}

function futureIso(minutesFromNow: number): string {
  return new Date(Date.now() + minutesFromNow * 60_000).toISOString();
}

async function clickSearchButton(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole("button", { name: "Tìm bàn" }));
}

describe("TableBookingPage", () => {
  beforeEach(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
    mocks.searchAvailableTables.mockReset();
    mocks.createTableHold.mockReset();
    mocks.refreshTableHold.mockReset();
    mocks.cancelTableHold.mockReset();
    mocks.toastSuccess.mockReset();
    mocks.toastError.mockReset();
  });

  it("searches live availability, auto-creates a hold on table selection, and links into reservation create", async () => {
    const user = userEvent.setup();
    const activeHoldExpiresAt = futureIso(90);

    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "T7",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
      ],
      meta: null,
    });
    mocks.createTableHold.mockResolvedValue({
      hold_id: "hold-123",
      expire_at: activeHoldExpiresAt,
      hold_status: "Holding",
      row_version: 2,
      tables: [{ table_id: 7, table_code: "T7", zone: "A" }],
    });

    renderPage();

    await clickSearchButton(user);

    await waitFor(() => {
      expect(mocks.searchAvailableTables).toHaveBeenCalledWith(
        expect.objectContaining({
          guest_count: 2,
          duration_minutes: 90,
        }),
      );
    });

    const tableOption = await screen.findByRole("button", { name: "Chọn Bàn 7" });
    expect(tableOption).toHaveAttribute("aria-pressed", "false");
    expect(screen.getByText("Sẵn bàn")).toBeInTheDocument();

    await user.click(tableOption);

    await waitFor(() => {
      expect(mocks.createTableHold).toHaveBeenCalledWith(
        expect.objectContaining({
          guest_count: 2,
          duration_minutes: 90,
        }),
        [7],
      );
    });

    expect(tableOption).toHaveAttribute("aria-pressed", "true");
    const continueLink = await screen.findByRole("link", { name: "Xác nhận thông tin đặt bàn" });
    expect(continueLink).toHaveAttribute("href", expect.stringContaining("hold_id=hold-123"));
    expect(continueLink).toHaveAttribute("href", expect.stringContaining("hold_status=Holding"));
    expect(continueLink).toHaveAttribute("href", expect.stringContaining("hold_expires_at="));
    expect(continueLink).toHaveAttribute("href", expect.stringContaining("tables=7"));
    expect(screen.queryByRole("button", { name: "Gia hạn giữ bàn" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Hủy giữ bàn" })).not.toBeInTheDocument();
  });

  it("holds the searched visit snapshot even if form values change after results render", async () => {
    const user = userEvent.setup();

    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "T7",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
      ],
      meta: null,
    });
    mocks.createTableHold.mockResolvedValue({
      hold_id: "hold-123",
      expire_at: futureIso(90),
      hold_status: "Holding",
      row_version: 2,
      tables: [{ table_id: 7, table_code: "T7", zone: "A" }],
    });

    renderPage();

    await clickSearchButton(user);
    const tableOption = await screen.findByRole("button", { name: "Chọn Bàn 7" });

    await user.clear(screen.getByLabelText("Số khách"));
    await user.type(screen.getByLabelText("Số khách"), "6");
    await user.click(tableOption);

    await waitFor(() => {
      expect(mocks.createTableHold).toHaveBeenCalledWith(
        expect.objectContaining({
          guest_count: 2,
          duration_minutes: 90,
        }),
        [7],
      );
    });
  });

  it("marks the selected quick date option", async () => {
    const user = userEvent.setup();

    renderPage();

    const tomorrowButton = await screen.findByRole("button", { name: "Ngày mai" });
    await user.click(tomorrowButton);

    expect(tomorrowButton).toHaveAttribute("aria-pressed", "true");
    expect(screen.getByRole("button", { name: "Hôm nay" })).toHaveAttribute("aria-pressed", "false");
  });

  it("automatically replaces the current hold when the customer chooses another table", async () => {
    const user = userEvent.setup();

    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "T7",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
        {
          table_id: 8,
          branch_id: 1,
          table_code: "T8",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
      ],
      meta: null,
    });
    mocks.createTableHold
      .mockResolvedValueOnce({
        hold_id: "hold-123",
        expire_at: futureIso(90),
        hold_status: "Holding",
        row_version: 2,
        tables: [{ table_id: 7, table_code: "T7", zone: "A" }],
      })
      .mockResolvedValueOnce({
        hold_id: "hold-456",
        expire_at: futureIso(90),
        hold_status: "Holding",
        row_version: 1,
        tables: [{ table_id: 8, table_code: "T8", zone: "A" }],
      });
    mocks.cancelTableHold.mockResolvedValue({
      hold_id: "hold-123",
      hold_status: "Cancelled",
      row_version: 3,
      tables: [{ table_id: 7 }],
    });

    renderPage();

    await clickSearchButton(user);
    await user.click(await screen.findByRole("button", { name: "Chọn Bàn 7" }));
    await screen.findByRole("link", { name: "Xác nhận thông tin đặt bàn" });

    await user.click(screen.getByRole("button", { name: "Chọn Bàn 8" }));

    await waitFor(() => {
      expect(mocks.cancelTableHold).toHaveBeenCalledWith("hold-123", 2);
    });

    await waitFor(() => {
      expect(mocks.createTableHold).toHaveBeenCalledTimes(2);
      expect(mocks.createTableHold).toHaveBeenLastCalledWith(
        expect.objectContaining({ guest_count: 2, duration_minutes: 90 }),
        [8],
      );
    });

    expect(await screen.findByRole("link", { name: "Xác nhận thông tin đặt bàn" })).toHaveAttribute("href", expect.stringContaining("hold_id=hold-456"));
    expect(screen.queryByText("Đang có lượt giữ bàn")).not.toBeInTheDocument();
  });

  it("hides the stale reservation link while replacing a hold", async () => {
    const user = userEvent.setup();
    let resolveCancel: (value: unknown) => void = () => {};
    let resolveSecondHold: (value: unknown) => void = () => {};

    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "T7",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
        {
          table_id: 8,
          branch_id: 1,
          table_code: "T8",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
      ],
      meta: null,
    });
    mocks.cancelTableHold.mockReturnValue(
      new Promise((resolve) => {
        resolveCancel = resolve;
      }),
    );
    mocks.createTableHold
      .mockResolvedValueOnce({
        hold_id: "hold-123",
        expire_at: futureIso(90),
        hold_status: "Holding",
        row_version: 2,
        tables: [{ table_id: 7, table_code: "T7", zone: "A" }],
      })
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveSecondHold = resolve;
        }),
      );

    renderPage();

    await clickSearchButton(user);
    await user.click(await screen.findByRole("button", { name: "Chọn Bàn 7" }));
    expect(await screen.findByRole("link")).toHaveAttribute("href", expect.stringContaining("hold_id=hold-123"));

    await user.click(screen.getByRole("button", { name: "Chọn Bàn 8" }));

    await waitFor(() => {
      expect(mocks.cancelTableHold).toHaveBeenCalledWith("hold-123", 2);
    });
    expect(screen.queryByRole("link")).not.toBeInTheDocument();

    resolveCancel({
      hold_id: "hold-123",
      hold_status: "Cancelled",
      row_version: 3,
      tables: [{ table_id: 7 }],
    });

    await waitFor(() => {
      expect(mocks.createTableHold).toHaveBeenCalledTimes(2);
    });
    expect(screen.queryByRole("link")).not.toBeInTheDocument();

    resolveSecondHold({
      hold_id: "hold-456",
      expire_at: futureIso(90),
      hold_status: "Holding",
      row_version: 1,
      tables: [{ table_id: 8, table_code: "T8", zone: "A" }],
    });

    expect(await screen.findByRole("link")).toHaveAttribute("href", expect.stringContaining("hold_id=hold-456"));
  });

  it("ignores availability form submits while a hold replacement is pending", async () => {
    const user = userEvent.setup();

    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "T7",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
        {
          table_id: 8,
          branch_id: 1,
          table_code: "T8",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
      ],
      meta: null,
    });
    mocks.cancelTableHold.mockReturnValue(new Promise(() => {}));
    mocks.createTableHold.mockResolvedValueOnce({
      hold_id: "hold-123",
      expire_at: futureIso(90),
      hold_status: "Holding",
      row_version: 2,
      tables: [{ table_id: 7, table_code: "T7", zone: "A" }],
    });

    renderPage();

    const searchButton = await screen.findByRole("button", { name: "Tìm bàn" });
    await user.click(searchButton);
    await user.click(await screen.findByRole("button", { name: "Chọn Bàn 7" }));
    await screen.findByRole("link");
    await user.click(screen.getByRole("button", { name: "Chọn Bàn 8" }));

    await waitFor(() => {
      expect(mocks.cancelTableHold).toHaveBeenCalledWith("hold-123", 2);
    });

    const form = searchButton.closest("form");
    expect(form).not.toBeNull();
    fireEvent.submit(form as HTMLFormElement);

    expect(mocks.searchAvailableTables).toHaveBeenCalledTimes(1);
  });

  it("duplicate table clicks send only one create hold request", async () => {
    const user = userEvent.setup();
    let resolveHold: (value: unknown) => void = () => {};

    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "T7",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
      ],
      meta: null,
    });
    mocks.createTableHold.mockReturnValue(
      new Promise((resolve) => {
        resolveHold = resolve;
      }),
    );

    renderPage();

    await clickSearchButton(user);
    await user.dblClick(await screen.findByRole("button", { name: "Chọn Bàn 7" }));

    expect(mocks.createTableHold).toHaveBeenCalledTimes(1);

    resolveHold({
      hold_id: "hold-123",
      expire_at: futureIso(90),
      hold_status: "Holding",
      row_version: 2,
      tables: [{ table_id: 7, table_code: "T7", zone: "A" }],
    });

    expect(await screen.findByRole("link", { name: "Xác nhận thông tin đặt bàn" })).toHaveAttribute("href", expect.stringContaining("hold_id=hold-123"));
  });

  it("restores an active local hold and automatically replaces it when choosing a new table", async () => {
    const user = userEvent.setup();
    const sessionId = ensureCustomerSessionId();

    storeActiveTableHoldSnapshot({
      hold_id: "hold-local-1",
      row_version: 7,
      session_id: sessionId,
      table_ids: [9],
      start_time: futureIso(60),
      end_time: futureIso(150),
      expire_at: futureIso(30),
      hold_status: "Holding",
      duration_minutes: 90,
      guest_count: 3,
      branch_id: 1,
    });
    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "T7",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
      ],
      meta: null,
    });
    mocks.cancelTableHold.mockResolvedValue({
      hold_id: "hold-local-1",
      hold_status: "Cancelled",
      row_version: 8,
      tables: [{ table_id: 9 }],
    });
    mocks.createTableHold.mockResolvedValue({
      hold_id: "hold-new-7",
      expire_at: futureIso(90),
      hold_status: "Holding",
      row_version: 1,
      tables: [{ table_id: 7, table_code: "T7", zone: "A" }],
    });

    renderPage();

    expect(await screen.findByText("Mã giữ bàn")).toBeInTheDocument();
    expect(screen.getByText("hold-local-1")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Xác nhận thông tin đặt bàn" })).toHaveAttribute("href", expect.stringContaining("hold_id=hold-local-1"));

    await clickSearchButton(user);
    await user.click(await screen.findByRole("button", { name: "Chọn Bàn 7" }));

    await waitFor(() => {
      expect(mocks.cancelTableHold).toHaveBeenCalledWith("hold-local-1", 7);
      expect(mocks.createTableHold).toHaveBeenCalledWith(
        expect.objectContaining({ guest_count: 3, duration_minutes: 90 }),
        [7],
      );
    });
    expect(await screen.findByRole("link", { name: "Xác nhận thông tin đặt bàn" })).toHaveAttribute("href", expect.stringContaining("hold_id=hold-new-7"));
  });

  it("shows a friendly Vietnamese message when Laravel rejects another active session hold", async () => {
    const user = userEvent.setup();

    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "T7",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
      ],
      meta: null,
    });
    mocks.createTableHold.mockRejectedValue({
      kind: "validation",
      status: 422,
      message: "Validation error.",
      errorCode: "validation_error",
      categoryCode: null,
      requestId: "req-active-hold",
      validationErrors: {
        session_id: [
          "This session already has another active hold. Refresh or cancel the existing hold, or replay the original request with the same Idempotency-Key.",
        ],
      },
    });

    renderPage();

    await clickSearchButton(user);
    await user.click(await screen.findByRole("button", { name: "Chọn Bàn 7" }));

    expect(await screen.findByText("Phiên đặt bàn đang có lượt giữ khác")).toBeInTheDocument();
    expect(screen.getByText("Phiên này đang có một lượt giữ bàn khác. Vui lòng tải lại phiên đặt bàn để đồng bộ lượt giữ hiện tại.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Tải lại phiên đặt bàn" })).toBeInTheDocument();
  });

  it("hides manual hold refresh and cancel actions because the hold lifecycle is automatic", async () => {
    const user = userEvent.setup();

    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "T7",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
      ],
      meta: null,
    });
    mocks.createTableHold.mockResolvedValue({
      hold_id: "hold-123",
      expire_at: futureIso(90),
      hold_status: "Holding",
      row_version: 2,
      tables: [{ table_id: 7, table_code: "T7", zone: "A" }],
    });
    renderPage();

    await clickSearchButton(user);
    await user.click(await screen.findByRole("button", { name: "Chọn Bàn 7" }));
    await screen.findByRole("link", { name: "Xác nhận thông tin đặt bàn" });

    expect(screen.queryByRole("button", { name: "Gia hạn giữ bàn" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Hủy giữ bàn" })).not.toBeInTheDocument();
  });

  it("uses backend suggestions for larger parties and holds the suggested table set", async () => {
    const user = userEvent.setup();

    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "A07",
          seats: 2,
          zone: "A",
          status: "Available",
          description: null,
        },
        {
          table_id: 8,
          branch_id: 1,
          table_code: "A08",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
      ],
      meta: {
        count: 2,
        suggestions: [{ table_ids: [7, 8], total_seats: 6, over: 0 }],
      },
    });
    mocks.createTableHold.mockResolvedValue({
      hold_id: "hold-combo",
      expire_at: futureIso(90),
      hold_status: "Holding",
      row_version: 1,
      tables: [{ table_id: 7 }, { table_id: 8 }],
    });

    renderPage();

    const guestCountInput = await screen.findByLabelText("Số khách");
    await user.clear(guestCountInput);
    await user.type(guestCountInput, "6");
    await clickSearchButton(user);
    await user.click(await screen.findByRole("button", { name: "Chọn Ghép Bàn 7 + Bàn 8" }));

    await waitFor(() => {
      expect(mocks.createTableHold).toHaveBeenCalledWith(
        expect.objectContaining({ guest_count: 6 }),
        [7, 8],
      );
    });
  });

  it("does not continue with an expired hold", async () => {
    const user = userEvent.setup();
    const expiredHoldAt = futureIso(-90);

    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "T7",
          seats: 4,
          zone: "A",
          status: "Available",
          description: null,
        },
      ],
      meta: null,
    });
    mocks.createTableHold.mockResolvedValue({
      hold_id: "hold-expired",
      expire_at: expiredHoldAt,
      hold_status: "Expired",
      row_version: 1,
      tables: [{ table_id: 7 }],
    });

    renderPage();

    await clickSearchButton(user);
    await user.click(await screen.findByRole("button", { name: "Chọn Bàn 7" }));

    expect(await screen.findByText("Bàn đã chọn không còn hiệu lực")).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Xác nhận thông tin đặt bàn" })).not.toBeInTheDocument();
  });

  it("shows loading, empty, and retry states for availability search", async () => {
    const user = userEvent.setup();
    let resolveSearch: (value: { tables: []; meta: { count: number } }) => void = () => {};

    mocks.searchAvailableTables.mockReturnValue(
      new Promise((resolve) => {
        resolveSearch = resolve;
      }),
    );

    renderPage();

    await clickSearchButton(user);

    expect(screen.getByLabelText("Đang tìm bàn trống")).toBeInTheDocument();

    resolveSearch({ tables: [], meta: { count: 0 } });

    expect(await screen.findByText("Chưa có bàn trống")).toBeInTheDocument();

    mocks.searchAvailableTables.mockRejectedValueOnce({
      kind: "server",
      status: 503,
      message: "Availability is offline.",
      errorCode: "availability_offline",
      categoryCode: "server_error",
      requestId: "req-availability",
      validationErrors: null,
    });

    await clickSearchButton(user);

    expect(await screen.findByText("Chưa tìm được bàn trống")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Thử lại" }));

    await waitFor(() => {
      expect(mocks.searchAvailableTables).toHaveBeenCalledTimes(3);
    });
  });
});
