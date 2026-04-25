import { describe, expect, it } from "vitest";
import type { CustomerWaitingListEntry } from "@/lib/contracts/generated/restaurantpos-sdk";
import { getWaitingListJourneyState, getWaitingListOwnerActionPolicy, getWaitingListSeatResultState } from "./state";

function createWaitingListEntry(overrides: Partial<CustomerWaitingListEntry> = {}): CustomerWaitingListEntry {
  return {
    waiting_id: 101,
    branch_id: 7,
    guest_name: "Taylor",
    phone: "0900000000",
    guest_count: 2,
    requested_at: "2026-04-19T09:00:00Z",
    status: "Waiting",
    priority: 1,
    notified_at: null,
    notify_expires_at: null,
    seated_at: null,
    cancelled_at: null,
    cancel_reason: null,
    notes: null,
    row_version: 3,
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

describe("waiting-list state helpers", () => {
  it("uses the backend lean waiting-list payload shape", () => {
    const entry = createWaitingListEntry();

    expect(entry).toHaveProperty("notify_window");
    expect(entry).toHaveProperty("available_actions");
    expect(entry).toHaveProperty("arrival_confirmation");
    expect(entry).toHaveProperty("response_state", "none");
    expect(entry).not.toHaveProperty("current_response_state");
    expect(entry).not.toHaveProperty("invite_window");
    expect(entry).not.toHaveProperty("invite_hold");
    expect(entry).not.toHaveProperty("orchestration");
  });

  it("keeps waiting entries cancel-only", () => {
    const policy = getWaitingListOwnerActionPolicy(createWaitingListEntry());

    expect(policy.availableActions).toEqual(["cancel"]);
    expect(policy.title).toBe("Cancel is available");
  });

  it("derives active invite actions from backend available_actions", () => {
    const policy = getWaitingListOwnerActionPolicy(
      createWaitingListEntry({
        status: "Notified",
        notified_at: "2026-04-19T09:30:00Z",
        notify_expires_at: "2026-04-19T09:50:00Z",
        can_accept: true,
        can_decline: true,
        can_confirm_arrival: true,
        can_cancel: true,
        notify_window: {
          is_open: true,
          expires_at: "2026-04-19T09:50:00Z",
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
        response_state: "none",
        arrival_confirmation: {
          supported: true,
          staff_seat_required: true,
          message: "Customers only confirm arrival. Staff still completes seating.",
        },
      }),
    );

    expect(policy.availableActions).toEqual(["accept", "arrival", "decline", "cancel"]);
    expect(policy.title).toBe("Invite response available");
  });

  it("uses response_state to reduce actions after acceptance without localized text inference", () => {
    const entry = createWaitingListEntry({
      status: "Notified",
      response_state: "accepted",
      notified_at: "2026-04-19T09:30:00Z",
      notify_expires_at: "2026-04-19T09:50:00Z",
      notify_window: {
        is_open: true,
        expires_at: "2026-04-19T09:50:00Z",
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
        message: "Localized copy can change without changing machine state.",
      },
    });

    const journey = getWaitingListJourneyState(entry);
    const policy = getWaitingListOwnerActionPolicy(entry);

    expect(journey.state).toBe("accepted");
    expect(policy.availableActions).toEqual(["arrival", "decline", "cancel"]);
    expect(policy.title).toBe("Arrival confirmation available");
  });

  it("uses response_state to keep arrival-confirmed entries read-only until staff seating", () => {
    const entry = createWaitingListEntry({
      status: "Notified",
      response_state: "arrival_confirmed",
      notified_at: "2026-04-19T09:30:00Z",
      notify_expires_at: "2026-04-19T09:50:00Z",
      notify_window: {
        is_open: true,
        expires_at: "2026-04-19T09:50:00Z",
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
    });

    const policy = getWaitingListOwnerActionPolicy(entry);
    const seatResult = getWaitingListSeatResultState(entry);
    const journey = getWaitingListJourneyState(entry);

    expect(policy.availableActions).toEqual([]);
    expect(policy.title).toBe("Waiting for staff seating");
    expect(journey.state).toBe("arrival_confirmed");
    expect(seatResult.state).toBe("waiting_for_staff");
    expect(seatResult.reservationId).toBeNull();
    expect(seatResult.tableLabel).toBeNull();
  });
});
