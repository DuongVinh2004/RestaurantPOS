import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { CustomerWaitingListEntry } from "@/lib/contracts/generated/restaurantpos-sdk";
import { WaitingListPage } from "./waiting-list-page";

const mocks = vi.hoisted(() => ({
  customerWebRollout: {
    waitingList: {
      enabled: false,
      disabledTitle: "Waiting list is not in this rollout",
      disabledDescription:
        "This build keeps customer waiting-list access off by default. Enable the dedicated waiting-list rollout flag only for a focused QA, UAT, or Wave 2 pass.",
    },
  },
  toastSuccess: vi.fn(),
  listWaitingList: vi.fn(),
  getWaitingListEntry: vi.fn(),
  createWaitingListEntry: vi.fn(),
  acceptWaitingListEntry: vi.fn(),
  confirmWaitingListArrival: vi.fn(),
  declineWaitingListEntry: vi.fn(),
  cancelWaitingListEntry: vi.fn(),
}));

vi.mock("next/link", () => ({
  default: ({ children, href, ...props }: { children: ReactNode; href: string }) => <a href={href} {...props}>{children}</a>,
}));

vi.mock("sonner", () => ({
  toast: {
    success: mocks.toastSuccess,
  },
}));

vi.mock("@/lib/config/feature-flags", () => ({
  customerWebRollout: mocks.customerWebRollout,
}));

vi.mock("./api", () => ({
  listWaitingList: mocks.listWaitingList,
  getWaitingListEntry: mocks.getWaitingListEntry,
  createWaitingListEntry: mocks.createWaitingListEntry,
  acceptWaitingListEntry: mocks.acceptWaitingListEntry,
  confirmWaitingListArrival: mocks.confirmWaitingListArrival,
  declineWaitingListEntry: mocks.declineWaitingListEntry,
  cancelWaitingListEntry: mocks.cancelWaitingListEntry,
  waitingListMutationEntry: (result: { entry: CustomerWaitingListEntry }) => result.entry,
}));

function createWaitingListEntryRecord(overrides: Partial<CustomerWaitingListEntry> = {}): CustomerWaitingListEntry {
  return {
    waiting_id: 91,
    branch_id: 7,
    guest_name: "Alex",
    phone: "0900000000",
    guest_count: 4,
    requested_at: "2026-04-19T10:00:00Z",
    status: "Waiting",
    priority: 1,
    notified_at: null,
    notify_expires_at: null,
    seated_at: null,
    cancelled_at: null,
    cancel_reason: null,
    notes: "Window seat",
    row_version: 7,
    response_state: "none",
    can_accept: false,
    can_decline: false,
    can_confirm_arrival: false,
    can_cancel: true,
    notify_window: {
      is_open: false,
      expires_at: null,
    },
    window: {
      is_notified_window_open: false,
    },
    available_actions: {
      accept: false,
      decline: false,
      confirm_arrival: false,
      cancel: true,
    },
    staff_seat_required: false,
    next_step: "await_notification",
    arrival_confirmation: {
      supported: true,
      staff_seat_required: false,
      message: null,
    },
    ...overrides,
  };
}

function activeInvite(overrides: Partial<CustomerWaitingListEntry> = {}): CustomerWaitingListEntry {
  return createWaitingListEntryRecord({
    status: "Notified",
    notified_at: "2026-04-19T10:10:00Z",
    notify_expires_at: "2026-04-19T10:40:00Z",
    can_accept: true,
    can_decline: true,
    can_confirm_arrival: true,
    can_cancel: true,
    notify_window: {
      is_open: true,
      expires_at: "2026-04-19T10:40:00Z",
    },
    window: {
      is_notified_window_open: true,
    },
    available_actions: {
      accept: true,
      decline: true,
      confirm_arrival: true,
      cancel: true,
    },
    staff_seat_required: true,
    next_step: "await_staff_seating",
    arrival_confirmation: {
      supported: true,
      staff_seat_required: true,
      message: "Customers only confirm arrival. Staff still completes seating.",
    },
    ...overrides,
  });
}

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
      <WaitingListPage />
    </QueryClientProvider>,
  );
}

describe("WaitingListPage", () => {
  beforeEach(() => {
    mocks.customerWebRollout.waitingList.enabled = false;
    mocks.listWaitingList.mockReset();
    mocks.getWaitingListEntry.mockReset();
    mocks.createWaitingListEntry.mockReset();
    mocks.acceptWaitingListEntry.mockReset();
    mocks.confirmWaitingListArrival.mockReset();
    mocks.declineWaitingListEntry.mockReset();
    mocks.cancelWaitingListEntry.mockReset();
    mocks.toastSuccess.mockReset();
  });

  it("renders a rollout-disabled state without calling live waiting-list queries", () => {
    renderPage();

    expect(screen.getByText("Waiting list is not in this rollout")).toBeInTheDocument();
    expect(screen.getByText(/dedicated waiting-list rollout flag/i)).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Tìm bàn" })).toHaveAttribute("href", "/booking");
    expect(screen.getByRole("link", { name: "Xem lịch đặt" })).toHaveAttribute("href", "/reservations");
    expect(mocks.listWaitingList).not.toHaveBeenCalled();
    expect(mocks.getWaitingListEntry).not.toHaveBeenCalled();
  });

  it("shows only cancel for entries that are still waiting for an invite", async () => {
    const waitingEntry = createWaitingListEntryRecord();

    mocks.customerWebRollout.waitingList.enabled = true;
    mocks.listWaitingList.mockResolvedValue([waitingEntry]);
    mocks.getWaitingListEntry.mockResolvedValue(waitingEntry);

    renderPage();

    await waitFor(() => {
      expect(mocks.listWaitingList).toHaveBeenCalledTimes(1);
      expect(mocks.getWaitingListEntry).toHaveBeenCalledWith(91);
    });

    expect(await screen.findByText("Có thể hủy đăng ký chờ")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Hủy đăng ký chờ" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Nhận lời mời" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Xác nhận đã đến" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Từ chối lời mời" })).not.toBeInTheDocument();
  });

  it("shows the backend owner response set for an active invite window and keeps refresh manual", async () => {
    const notifiedEntry = activeInvite();

    mocks.customerWebRollout.waitingList.enabled = true;
    mocks.listWaitingList.mockResolvedValue([notifiedEntry]);
    mocks.getWaitingListEntry.mockResolvedValue(notifiedEntry);

    renderPage();

    expect((await screen.findAllByText("Có lời mời cần phản hồi")).length).toBeGreaterThan(0);
    expect(screen.getByRole("button", { name: "Nhận lời mời" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Xác nhận đã đến" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Từ chối lời mời" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Hủy đăng ký chờ" })).toBeInTheDocument();
    expect(screen.getAllByText("Cập nhật thủ công")[0]).toBeInTheDocument();
    expect(screen.getByText("Chưa có kết quả xếp bàn")).toBeInTheDocument();
    expect(screen.queryByText("Đang chờ kết quả xếp bàn")).not.toBeInTheDocument();
  });

  it("uses response_state to distinguish accepted from arrival-confirmed entries", async () => {
    const acceptedEntry = activeInvite({
      waiting_id: 93,
      row_version: 9,
      response_state: "accepted",
      arrival_confirmation: {
        supported: true,
        staff_seat_required: true,
        message: "Localized message is not used as state.",
      },
    });
    const arrivalConfirmedEntry = activeInvite({
      waiting_id: 92,
      row_version: 8,
      response_state: "arrival_confirmed",
    });

    mocks.customerWebRollout.waitingList.enabled = true;
    mocks.listWaitingList.mockResolvedValue([acceptedEntry, arrivalConfirmedEntry]);
    mocks.getWaitingListEntry.mockResolvedValueOnce(acceptedEntry).mockResolvedValueOnce(arrivalConfirmedEntry);

    renderPage();

    expect((await screen.findAllByText("Đã nhận lời mời")).length).toBeGreaterThan(0);
    expect(screen.queryByRole("button", { name: "Nhận lời mời" })).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Xác nhận đã đến" })).toBeInTheDocument();

    await userEvent.click(screen.getByText("Mã chờ #92"));

    expect((await screen.findAllByText("Đã xác nhận đến nơi")).length).toBeGreaterThan(0);
    expect(screen.getAllByText("Đang chờ nhân viên xếp bàn").length).toBeGreaterThan(0);
    expect(screen.getByText("Đang chờ kết quả xếp bàn")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Xác nhận đã đến" })).not.toBeInTheDocument();
  });

  it("refetches the list and detail when an owner action hits a stale row version", async () => {
    const user = userEvent.setup();
    const notifiedEntry = activeInvite();

    mocks.customerWebRollout.waitingList.enabled = true;
    mocks.listWaitingList.mockResolvedValue([notifiedEntry]);
    mocks.getWaitingListEntry.mockResolvedValue(notifiedEntry);
    mocks.acceptWaitingListEntry.mockRejectedValue({
      kind: "validation",
      status: 422,
      message: "Validation error.",
      errorCode: "stale_row_version",
      categoryCode: "stale_write",
      requestId: "req-waiting-stale",
      validationErrors: {
        row_version: ["Changed."],
      },
    });

    renderPage();

    await waitFor(() => {
      expect(mocks.getWaitingListEntry).toHaveBeenCalledWith(91);
    });

    await user.click(await screen.findByRole("button", { name: "Nhận lời mời" }));

    await waitFor(() => {
      expect(mocks.acceptWaitingListEntry).toHaveBeenCalledWith(91, { row_version: 7 });
    });

    await waitFor(() => {
      expect(mocks.listWaitingList.mock.calls.length).toBeGreaterThanOrEqual(2);
      expect(mocks.getWaitingListEntry.mock.calls.length).toBeGreaterThanOrEqual(2);
    });

    expect(await screen.findByText("Thông tin danh sách chờ đã thay đổi")).toBeInTheDocument();
    expect(screen.getByText("Thông tin đã thay đổi trong lúc bạn thao tác.")).toBeInTheDocument();
  });
});
