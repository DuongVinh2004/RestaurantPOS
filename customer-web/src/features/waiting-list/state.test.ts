import { describe, expect, it } from "vitest";
import type { CustomerWaitingListEntry } from "@/lib/contracts/generated/restaurantpos-sdk";
import { getWaitingListOwnerActionPolicy, getWaitingListSeatResultState } from "./state";

function createWaitingListEntry(overrides: Partial<CustomerWaitingListEntry> = {}): CustomerWaitingListEntry {
  return {
    waiting_id: 101,
    branch_id: 7,
    user_id: 20,
    guest_name: "Taylor",
    phone: "0900000000",
    guest_count: 2,
    requested_at: "2026-04-19T09:00:00Z",
    status: "Waiting",
    priority: 1,
    notified_at: null,
    notify_expires_at: null,
    notified_by: null,
    seated_at: null,
    cancelled_at: null,
    cancel_reason: null,
    notes: null,
    updated_by: null,
    row_version: 3,
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

describe("waiting-list state helpers", () => {
  it("keeps waiting entries cancel-only", () => {
    const policy = getWaitingListOwnerActionPolicy(createWaitingListEntry());

    expect(policy.availableActions).toEqual(["cancel"]);
    expect(policy.title).toBe("Cancel is available");
  });

  it("reduces active invite actions after acceptance", () => {
    const policy = getWaitingListOwnerActionPolicy(
      createWaitingListEntry({
        status: "Notified",
        current_response_state: "accepted",
        invite_window: {
          notified_at: "2026-04-19T09:30:00Z",
          expires_at: "2026-04-19T09:50:00Z",
          is_active: true,
          is_expired: false,
          seconds_remaining: 1200,
        },
      }),
    );

    expect(policy.availableActions).toEqual(["arrival", "decline", "cancel"]);
    expect(policy.title).toBe("Arrival confirmation available");
  });

  it("keeps arrival-confirmed entries read-only until staff seating result exists", () => {
    const entry = createWaitingListEntry({
      status: "Notified",
      current_response_state: "arrival_confirmed",
      response: {
        status: "accepted",
        responded_at: "2026-04-19T09:32:00Z",
        confirmed_arrival_at: "2026-04-19T09:35:00Z",
      },
      invite_window: {
        notified_at: "2026-04-19T09:30:00Z",
        expires_at: "2026-04-19T09:50:00Z",
        is_active: true,
        is_expired: false,
        seconds_remaining: 900,
      },
      invite_lifecycle: {
        requires_explicit_staff_seat: true,
        auto_convert_to_reservation: false,
        seat_readiness: "ready_to_seat",
        customer_next_step: "wait_for_staff_seat",
        staff_next_step: "seat_customer",
        can_staff_seat_now: true,
      },
    });

    const policy = getWaitingListOwnerActionPolicy(entry);
    const seatResult = getWaitingListSeatResultState(entry);

    expect(policy.availableActions).toEqual([]);
    expect(policy.title).toBe("Waiting for staff seating");
    expect(seatResult.state).toBe("waiting_for_staff");
  });

  it("surfaces linked reservation seat results only when backend exposes them", () => {
    const seatResult = getWaitingListSeatResultState(
      createWaitingListEntry({
        status: "Notified",
        current_response_state: "arrival_confirmed",
        invite_hold: {
          has_active_hold: true,
          active: {
            hold_id: "hold-1",
            status: "Active",
            session_id: "session-1",
            expires_at: "2026-04-19T09:50:00Z",
            confirmed_reservation_id: 808,
            table_ids: [12],
          },
          latest: null,
        },
      }),
    );

    expect(seatResult.state).toBe("reservation_linked");
    expect(seatResult.reservationId).toBe(808);
    expect(seatResult.description).toMatch(/reservation #808/i);
  });
});
