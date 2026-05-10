import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { readStoredPendingReservationPreorderDraft } from "@/features/preorder/reservation-draft-storage";
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

function createReservationResult(overrides: Record<string, unknown> = {}) {
  return {
    reservation: {
      reservation_id: 501,
      reservation_code: "RSV-501",
      status: "Confirmed",
      row_version: 1,
      guest_count: 4,
      ...overrides,
    },
    preorder: null,
  };
}

const mocks = vi.hoisted(() => {
  class MockReservationPreorderPersistenceError extends Error {
    readonly reservation: Record<string, unknown>;
    readonly stage: "snapshot" | "preview" | "replace";
    override readonly cause: unknown;

    constructor(
      reservation: Record<string, unknown>,
      stage: "snapshot" | "preview" | "replace",
      cause: unknown,
    ) {
      super("reservation preorder persistence failed");
      this.name = "MockReservationPreorderPersistenceError";
      this.reservation = reservation;
      this.stage = stage;
      this.cause = cause;
    }
  }

  return {
    createReservationWithPreorderDraft: vi.fn(),
    getTableHold: vi.fn(),
    isReservationPreorderPersistenceError: (
      error: unknown,
    ): error is InstanceType<typeof MockReservationPreorderPersistenceError> =>
      error instanceof MockReservationPreorderPersistenceError,
    listMenuItems: vi.fn(),
    previewMenuPreorder: vi.fn(),
    push: vi.fn(),
    refreshTableHold: vi.fn(),
    ReservationPreorderPersistenceError: MockReservationPreorderPersistenceError,
    searchParams: createHoldSearchParams(),
    authState: {
      isAuthenticated: false,
      profile: null as null | { name: string; email: string | null; phone: string | null },
    },
    toastError: vi.fn(),
    toastSuccess: vi.fn(),
    customerWebRollout: {
      preorder: {
        enabled: true,
        disabledTitle: "Món đặt trước chưa được bật",
        disabledDescription: "Nhà hàng chưa bật món đặt trước cho khách hàng.",
      },
    },
  };
});

vi.mock("next/navigation", () => ({
  useRouter: () => ({
    push: mocks.push,
  }),
  useSearchParams: () => mocks.searchParams,
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
  getTableHold: mocks.getTableHold,
  refreshTableHold: mocks.refreshTableHold,
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

describe("ReservationCreatePage", () => {
  beforeEach(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
    ensureCustomerSessionId();
    mocks.createReservationWithPreorderDraft.mockReset();
    mocks.getTableHold.mockReset();
    mocks.listMenuItems.mockReset();
    mocks.previewMenuPreorder.mockReset();
    mocks.push.mockReset();
    mocks.refreshTableHold.mockReset();
    mocks.searchParams = createHoldSearchParams();
    mocks.authState.isAuthenticated = false;
    mocks.authState.profile = null;
    mocks.toastError.mockReset();
    mocks.toastSuccess.mockReset();
    mocks.customerWebRollout.preorder.enabled = true;
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
    mocks.createReservationWithPreorderDraft.mockResolvedValue(
      createReservationResult(),
    );
  });

  it("locks held visit details when the page is opened from a table hold", async () => {
    const holdStartTime = mocks.searchParams.get("start_time") as string;

    renderPage();

    const holdNotice = await screen.findByRole("region", {
      name: "Thông tin bàn đang giữ",
    });
    expect(
      within(holdNotice).getByText("Bàn đang được giữ cho bạn"),
    ).toBeInTheDocument();
    expect(within(holdNotice).getByText("Bàn đã chọn")).toBeInTheDocument();
    expect(within(holdNotice).getByText("Bàn 7, Bàn 8")).toBeInTheDocument();
    expect(within(holdNotice).getByText("Số khách")).toBeInTheDocument();
    expect(within(holdNotice).getByText("4 khách")).toBeInTheDocument();
    expect(within(holdNotice).getByText("Thời lượng giữ bàn")).toBeInTheDocument();
    expect(within(holdNotice).getByText("90 phút")).toBeInTheDocument();
    expect(screen.getByDisplayValue(holdStartTime)).toBeDisabled();
    expect(screen.getByDisplayValue("90")).toBeDisabled();
    expect(screen.getByDisplayValue("4")).toBeDisabled();
    expect(screen.queryByRole("button", { name: "Gia hạn giữ bàn" })).not.toBeInTheDocument();
  });

  it("uses the authenticated customer profile without requiring contact re-entry", async () => {
    const user = userEvent.setup();
    mocks.authState.isAuthenticated = true;
    mocks.authState.profile = {
      name: "Casey Nguyen",
      email: "casey@example.test",
      phone: null,
    };

    renderPage();

    await screen.findByRole("region", { name: "Thông tin bàn đang giữ" });
    expect(screen.getByText(/Đang dùng thông tin từ tài khoản Casey Nguyen/i)).toBeInTheDocument();
    expect(screen.getByDisplayValue("Casey Nguyen")).toBeInTheDocument();
    expect(screen.getByDisplayValue("casey@example.test")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Tạo lịch đặt" }));

    await waitFor(() => {
      expect(mocks.createReservationWithPreorderDraft).toHaveBeenCalledWith({
        reservationInput: expect.objectContaining({
          guest_name: "Casey Nguyen",
          guest_phone: "",
          guest_email: "casey@example.test",
          hold_id: "hold-123",
          table_ids: [7, 8],
        }),
        preorderItems: [],
      });
    });
    expect(mocks.push).toHaveBeenCalledWith("/reservations/501");
  });

  it("blocks reservation create when the linked hold is already expired", async () => {
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
    expect(
      screen.getByRole("button", { name: "Tạo lịch đặt" }),
    ).toBeDisabled();
    expect(
      screen.getByRole("link", { name: "Tìm bàn phù hợp lại" }),
    ).toHaveAttribute("href", "/booking");
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

    expect(
      await screen.findByText(/Chưa xác minh được bàn đã chọn/i),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Tạo lịch đặt" }),
    ).toBeDisabled();
    expect(
      screen.getByRole("link", { name: "Tìm bàn phù hợp lại" }),
    ).toHaveAttribute("href", "/booking");
  });

  it("previews preorder items with the selected booking time", async () => {
    const user = userEvent.setup();
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

    renderPage();

    const quantityInput = await screen.findByLabelText("Số lượng Gỏi cuốn");
    await user.clear(quantityInput);
    await user.type(quantityInput, "2");
    await user.click(screen.getByRole("button", { name: "Xem trước món" }));

    await waitFor(() => {
      expect(mocks.previewMenuPreorder).toHaveBeenCalledWith({
        start_time: new Date(
          mocks.searchParams.get("start_time") as string,
        ).toISOString(),
        pre_order_items: [{ item_id: 101, quantity: 2 }],
      });
    });
    expect(
      await screen.findByText("Bản xem trước món đặt trước"),
    ).toBeInTheDocument();
    expect(
      screen.getByText(/Nhà hàng sẽ xác nhận lại số lượng/i),
    ).toBeInTheDocument();
  });

  it("keeps reservation create disabled until a preorder draft has been previewed", async () => {
    const user = userEvent.setup();

    renderPage();

    await screen.findByRole("region", { name: "Thông tin bàn đang giữ" });
    await user.clear(await screen.findByLabelText("Số lượng Gỏi cuốn"));
    await user.type(screen.getByLabelText("Số lượng Gỏi cuốn"), "2");

    expect(screen.getByRole("button", { name: "Tạo lịch đặt" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "Xác nhận đặt bàn" })).toBeDisabled();
    expect(mocks.createReservationWithPreorderDraft).not.toHaveBeenCalled();
  });

  it("does not submit by form event while a preorder draft still needs preview", async () => {
    const user = userEvent.setup();

    renderPage();

    await screen.findByRole("region", { name: "Thông tin bàn đang giữ" });
    await user.type(screen.getByLabelText("Tên khách"), "Demo Customer");
    await user.type(screen.getByLabelText("Số điện thoại"), "5550100");
    await user.clear(await screen.findByLabelText("Số lượng Gỏi cuốn"));
    await user.type(screen.getByLabelText("Số lượng Gỏi cuốn"), "2");

    const submitButton = screen.getByRole("button", { name: "Tạo lịch đặt" });
    expect(submitButton).toBeDisabled();

    const form = submitButton.closest("form");
    expect(form).not.toBeNull();
    fireEvent.submit(form as HTMLFormElement);

    expect(mocks.createReservationWithPreorderDraft).not.toHaveBeenCalled();
    await waitFor(() => {
      expect(mocks.toastError).toHaveBeenCalledWith(
        "Vui lòng xem trước món đặt trước trước khi tạo lịch đặt.",
      );
    });
  });

  it("creates a reservation with the verified hold context and preorder draft", async () => {
    const user = userEvent.setup();

    renderPage();

    await screen.findByRole("region", { name: "Thông tin bàn đang giữ" });
    await user.type(screen.getByLabelText("Tên khách"), "Demo Customer");
    await user.type(screen.getByLabelText("Số điện thoại"), "5550100");
    await user.type(screen.getByLabelText("Ghi chú"), "Window seat");
    await user.clear(await screen.findByLabelText("Số lượng Gỏi cuốn"));
    await user.type(screen.getByLabelText("Số lượng Gỏi cuốn"), "2");
    await user.click(screen.getByRole("button", { name: "Xem trước món" }));
    await screen.findByText("Bản xem trước món đặt trước");
    await user.click(screen.getByRole("button", { name: "Tạo lịch đặt" }));

    await waitFor(() => {
      expect(mocks.createReservationWithPreorderDraft).toHaveBeenCalledWith({
        reservationInput: expect.objectContaining({
          guest_name: "Demo Customer",
          guest_phone: "5550100",
          notes: "Window seat",
          hold_id: "hold-123",
          table_ids: [7, 8],
        }),
        preorderItems: [{ item_id: 101, quantity: 2 }],
      });
    });
    expect(mocks.push).toHaveBeenCalledWith("/reservations/501");
    expect(mocks.toastSuccess).toHaveBeenCalledWith("Đã tạo lịch đặt.");
  }, 10_000);

  it("stores the preorder draft and redirects to detail when reservation create partially succeeds", async () => {
    const user = userEvent.setup();
    mocks.createReservationWithPreorderDraft.mockRejectedValue(
      new mocks.ReservationPreorderPersistenceError(
        {
          reservation_id: 901,
          reservation_code: "RSV-901",
          row_version: 1,
          status: "Confirmed",
        },
        "replace",
        new Error("replace failed"),
      ),
    );

    renderPage();

    await screen.findByRole("region", { name: "Thông tin bàn đang giữ" });
    await user.type(screen.getByLabelText("Tên khách"), "Demo Customer");
    await user.type(screen.getByLabelText("Số điện thoại"), "5550100");
    await user.clear(await screen.findByLabelText("Số lượng Gỏi cuốn"));
    await user.type(screen.getByLabelText("Số lượng Gỏi cuốn"), "2");
    await user.click(screen.getByRole("button", { name: "Xem trước món" }));
    await screen.findByText("Bản xem trước món đặt trước");
    await user.click(screen.getByRole("button", { name: "Tạo lịch đặt" }));

    await waitFor(() => {
      expect(mocks.push).toHaveBeenCalledWith("/reservations/901");
    });
    expect(readStoredPendingReservationPreorderDraft(901)).toMatchObject({
      reservation_id: 901,
      failure_stage: "replace",
      items: [{ item_id: 101, quantity: 2 }],
    });
    expect(mocks.toastError).toHaveBeenCalledWith(
      "Đã tạo lịch đặt nhưng món đặt trước chưa lưu. Mở chi tiết để tiếp tục cập nhật.",
    );
  }, 10_000);
});
