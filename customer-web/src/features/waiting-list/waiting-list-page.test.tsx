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
    user_id: 22,
    guest_name: "Alex",
    phone: "0900000000",
    guest_count: 4,
    requested_at: "2026-04-19T10:00:00Z",
    status: "Waiting",
    priority: 1,
    notified_at: null,
    notify_expires_at: null,
    notified_by: null,
    seated_at: null,
    cancelled_at: null,
    cancel_reason: null,
    notes: "Window seat",
    updated_by: null,
    row_version: 7,
    current_response_state: "none",
    response: {
      status: null,
      responded_at: null,
      confirmed_arrival_at: null,
    },
    invite_window: {
      notified_at: null,
      expires_at: null,
      is_active: false,
      is_expired: false,
      seconds_remaining: 0,
    },
    invite_lifecycle: {
      requires_explicit_staff_seat: true,
      auto_convert_to_reservation: false,
      seat_readiness: "not_notified",
      customer_next_step: "wait_to_be_called",
      staff_next_step: "notify_customer",
      can_staff_seat_now: false,
    },
    invite_hold: {
      has_active_hold: false,
      active: null,
      latest: null,
    },
    orchestration: {
      mode: "semi_automated_waiting_list_orchestration",
      actionable_state: "waiting",
      recommended_action: "notify_customer",
      released_table: null,
      advance_queue: {
        supported: false,
        can_apply_now: false,
        resulting_action: "none",
        released_table_available: false,
        next_candidate: null,
        disabled_reason: null,
      },
      actions: [],
    },
    user: null,
    ...overrides,
  };
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
    expect(screen.getByRole("link", { name: "Find a table" })).toHaveAttribute("href", "/booking");
    expect(screen.getByRole("link", { name: "View reservations" })).toHaveAttribute("href", "/reservations");
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

    expect(await screen.findByText("Cancel is available")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Cancel entry" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Accept invite" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Confirm arrival" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Decline invite" })).not.toBeInTheDocument();
  });

  it("shows the full owner response set for an active invite window and keeps refresh manual", async () => {
    const notifiedEntry = createWaitingListEntryRecord({
      status: "Notified",
      current_response_state: "pending",
      notified_at: "2026-04-19T10:10:00Z",
      notify_expires_at: "2026-04-19T10:40:00Z",
      invite_window: {
        notified_at: "2026-04-19T10:10:00Z",
        expires_at: "2026-04-19T10:40:00Z",
        is_active: true,
        is_expired: false,
        seconds_remaining: 1800,
      },
      invite_lifecycle: {
        requires_explicit_staff_seat: true,
        auto_convert_to_reservation: false,
        seat_readiness: "awaiting_customer_response",
        customer_next_step: "respond_to_invite",
        staff_next_step: "wait_for_customer_response",
        can_staff_seat_now: false,
      },
      orchestration: {
        mode: "semi_automated_waiting_list_orchestration",
        actionable_state: "invite_open",
        recommended_action: "wait_for_customer_response",
        released_table: null,
        advance_queue: {
          supported: false,
          can_apply_now: false,
          resulting_action: "none",
          released_table_available: false,
          next_candidate: null,
          disabled_reason: null,
        },
        actions: [],
      },
    });

    mocks.customerWebRollout.waitingList.enabled = true;
    mocks.listWaitingList.mockResolvedValue([notifiedEntry]);
    mocks.getWaitingListEntry.mockResolvedValue(notifiedEntry);

    renderPage();

    expect(await screen.findByText("Invite response available")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Accept invite" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Confirm arrival" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Decline invite" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Cancel entry" })).toBeInTheDocument();
    expect(screen.getAllByText("Refresh manually")[0]).toBeInTheDocument();
    expect(screen.getByText("Seat result not exposed yet")).toBeInTheDocument();
    expect(screen.getByText(/does not fake notification or seating progress/i)).toBeInTheDocument();
  });

  it("refetches the list and detail when an owner action hits a stale row version", async () => {
    const user = userEvent.setup();
    const notifiedEntry = createWaitingListEntryRecord({
      status: "Notified",
      current_response_state: "pending",
      invite_window: {
        notified_at: "2026-04-19T10:10:00Z",
        expires_at: "2026-04-19T10:40:00Z",
        is_active: true,
        is_expired: false,
        seconds_remaining: 1800,
      },
      invite_lifecycle: {
        requires_explicit_staff_seat: true,
        auto_convert_to_reservation: false,
        seat_readiness: "awaiting_customer_response",
        customer_next_step: "respond_to_invite",
        staff_next_step: "wait_for_customer_response",
        can_staff_seat_now: false,
      },
    });

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

    await user.click(await screen.findByRole("button", { name: "Accept invite" }));

    await waitFor(() => {
      expect(mocks.acceptWaitingListEntry).toHaveBeenCalledWith(91, { row_version: 7 });
    });

    await waitFor(() => {
      expect(mocks.listWaitingList.mock.calls.length).toBeGreaterThanOrEqual(2);
      expect(mocks.getWaitingListEntry.mock.calls.length).toBeGreaterThanOrEqual(2);
    });

    expect(await screen.findByText("Waiting-list details changed")).toBeInTheDocument();
    expect(screen.getByText("This changed while you were working.")).toBeInTheDocument();
  });
});
