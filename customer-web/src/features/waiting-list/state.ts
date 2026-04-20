import type { CustomerWaitingListCollectionEnvelope, CustomerWaitingListEntry } from "@/lib/contracts/generated/restaurantpos-sdk";

export type WaitingListOwnerAction = "accept" | "arrival" | "decline" | "cancel";

export type WaitingListJourneyState = {
  state:
    | "waiting"
    | "invite_pending"
    | "accepted"
    | "arrival_confirmed"
    | "declined"
    | "expired"
    | "seated"
    | "cancelled"
    | "unknown";
  title: string;
  description: string;
  nextStep: string;
};

export type WaitingListOwnerActionPolicy = {
  availableActions: WaitingListOwnerAction[];
  title: string;
  description: string;
};

export type WaitingListSeatResultState = {
  state: "unavailable" | "waiting_for_staff" | "table_ready" | "reservation_linked" | "seated" | "cancelled";
  title: string;
  description: string;
  reservationId: number | null;
  tableLabel: string | null;
};

export type WaitingListRefreshPolicy = {
  mode: "manual";
  title: string;
  description: string;
};

type WaitingListEntryLike = CustomerWaitingListEntry | CustomerWaitingListCollectionEnvelope["data"][number];

export const waitingListActionLabels: Record<WaitingListOwnerAction, string> = {
  accept: "Accept invite",
  arrival: "Confirm arrival",
  decline: "Decline invite",
  cancel: "Cancel entry",
};

export function sortWaitingListEntries<T extends WaitingListEntryLike>(entries: T[]): T[] {
  return [...entries].sort((left, right) => {
    const requestedDelta = entryTimestamp(right.requested_at) - entryTimestamp(left.requested_at);

    return requestedDelta !== 0 ? requestedDelta : right.waiting_id - left.waiting_id;
  });
}

export function getWaitingListJourneyState(entry: WaitingListEntryLike): WaitingListJourneyState {
  if (entry.status === "Cancelled") {
    return {
      state: "cancelled",
      title: "Entry cancelled",
      description: "This waiting-list entry is no longer active for this customer account.",
      nextStep: "Join again later if you still need a table.",
    };
  }

  if (entry.status === "Seated" || Boolean(entry.seated_at)) {
    return {
      state: "seated",
      title: "Seated",
      description: "The restaurant marked this waiting-list entry as seated in the backend runtime.",
      nextStep: "No further waiting-list action is needed from customer-web.",
    };
  }

  if (entry.status === "Waiting") {
    return {
      state: "waiting",
      title: "Waiting for an invite",
      description: "The restaurant has your request, but no invite window is open for this entry yet.",
      nextStep: "Wait for restaurant outreach, then refresh this page manually when staff asks you to check again.",
    };
  }

  if (entry.status !== "Notified") {
    return {
      state: "unknown",
      title: "Waiting-list state changed",
      description: "This entry moved into a state that this browser workspace does not manage automatically.",
      nextStep: "Refresh the entry details before taking another action.",
    };
  }

  if (entry.invite_window.is_expired || !entry.invite_window.is_active || entry.current_response_state === "invite_expired") {
    return {
      state: "expired",
      title: "Invite window expired",
      description: "The current invite window is no longer active for this entry.",
      nextStep: "Wait for another invite or contact the restaurant directly if staff asked you to respond again.",
    };
  }

  switch (entry.current_response_state) {
    case "accepted":
      return {
        state: "accepted",
        title: "Invite accepted",
        description: "You accepted the current invite. Staff still needs your arrival confirmation or final seating action from restaurant runtime.",
        nextStep: "Confirm arrival when you reach the restaurant.",
      };
    case "arrival_confirmed":
      return {
        state: "arrival_confirmed",
        title: "Arrival confirmed",
        description: "Your arrival is confirmed. Final seating still happens from the restaurant runtime, not from browser realtime.",
        nextStep: "Wait for staff to seat you, then refresh manually for the latest result.",
      };
    case "declined":
      return {
        state: "declined",
        title: "Invite declined",
        description: "You already declined this invite window from the signed-in customer account.",
        nextStep: "Wait for another invite if the restaurant opens one.",
      };
    case "pending":
      return {
        state: "invite_pending",
        title: "Invite response needed",
        description: "A live invite window is open for this entry and still needs a customer response.",
        nextStep: "Accept, confirm arrival, decline, or cancel before the invite window closes.",
      };
    default:
      return {
        state: "unknown",
        title: "Waiting-list state changed",
        description: "This invite is active, but the current response state is not stable enough for browser-side assumptions.",
        nextStep: "Refresh the entry details before taking another action.",
      };
  }
}

export function getWaitingListOwnerActionPolicy(entry: WaitingListEntryLike): WaitingListOwnerActionPolicy {
  if (entry.status === "Waiting") {
    return {
      availableActions: ["cancel"],
      title: "Cancel is available",
      description: "This entry is still waiting for an invite, so the only customer action available online is to cancel it.",
    };
  }

  if (entry.status !== "Notified") {
    return {
      availableActions: [],
      title: "No online response available",
      description: "This waiting-list status is read-only from customer-web.",
    };
  }

  if (entry.invite_window.is_expired || !entry.invite_window.is_active) {
    return {
      availableActions: [],
      title: "Invite window closed",
      description: "The current invite window is no longer active, so customer response buttons stay hidden until the backend opens another state.",
    };
  }

  switch (entry.current_response_state) {
    case "pending":
      return {
        availableActions: ["accept", "arrival", "decline", "cancel"],
        title: "Invite response available",
        description: "The invite window is active and still waiting for an owner response.",
      };
    case "accepted":
      return {
        availableActions: ["arrival", "decline", "cancel"],
        title: "Arrival confirmation available",
        description: "The invite is already accepted. Confirm arrival when you reach the restaurant, or decline or cancel before staff seats you.",
      };
    case "arrival_confirmed":
      return {
        availableActions: [],
        title: "Waiting for staff seating",
        description: "Arrival is already confirmed. Customer-web stays read-only until staff finishes the seating outcome in restaurant runtime.",
      };
    case "declined":
      return {
        availableActions: [],
        title: "Invite already declined",
        description: "This invite was already declined, so no further owner response is available from customer-web.",
      };
    case "invite_expired":
      return {
        availableActions: [],
        title: "Invite expired",
        description: "This invite window expired before another owner response was recorded.",
      };
    default:
      return {
        availableActions: [],
        title: "Refresh before responding",
        description: "The current owner response state is not stable enough for another browser action without a refresh.",
      };
  }
}

export function getWaitingListSeatResultState(entry: WaitingListEntryLike): WaitingListSeatResultState {
  const releasedTable = entry.orchestration.released_table;
  const hold = entry.invite_hold.active ?? entry.invite_hold.latest;
  const reservationId = hold?.confirmed_reservation_id ?? null;
  const tableLabel = formatTableLabel(releasedTable?.table_code ?? null, releasedTable?.table_ids ?? hold?.table_ids ?? []);

  if (entry.status === "Cancelled") {
    return {
      state: "cancelled",
      title: "No seat result",
      description: "This entry was cancelled before the restaurant recorded a final seating result.",
      reservationId,
      tableLabel,
    };
  }

  if (entry.status === "Seated" || Boolean(entry.seated_at)) {
    return {
      state: "seated",
      title: "Seat result recorded",
      description: tableLabel
        ? `The restaurant marked this entry as seated. Backend runtime last exposed ${tableLabel}.`
        : "The restaurant marked this entry as seated in backend runtime.",
      reservationId,
      tableLabel,
    };
  }

  if (reservationId !== null) {
    return {
      state: "reservation_linked",
      title: "Seat result linked",
      description: tableLabel
        ? `Backend runtime linked this invite to reservation #${reservationId} and last exposed ${tableLabel}. Final seating still depends on staff completion.`
        : `Backend runtime linked this invite to reservation #${reservationId}. Final seating still depends on staff completion.`,
      reservationId,
      tableLabel,
    };
  }

  if (releasedTable) {
    return {
      state: "table_ready",
      title: "Table released for staff seating",
      description: tableLabel
        ? `${tableLabel} is exposed by backend orchestration, but customer-web does not simulate the final staff seating step.`
        : "A released table is exposed by backend orchestration, but customer-web does not simulate the final staff seating step.",
      reservationId,
      tableLabel,
    };
  }

  if (
    entry.current_response_state === "arrival_confirmed" ||
    entry.invite_lifecycle.seat_readiness === "ready_to_seat" ||
    entry.invite_lifecycle.customer_next_step === "wait_for_staff_seat"
  ) {
    return {
      state: "waiting_for_staff",
      title: "Waiting for staff seat result",
      description: "Your arrival is confirmed, but final seating still happens in restaurant runtime. Refresh manually when staff asks you to check again.",
      reservationId,
      tableLabel,
    };
  }

  return {
    state: "unavailable",
    title: "Seat result not exposed yet",
    description:
      "This page does not fake notification or seating progress. Seat-result details only appear here after the backend exposes a stable owner-visible record.",
    reservationId,
    tableLabel,
  };
}

export function getWaitingListRefreshPolicy(): WaitingListRefreshPolicy {
  return {
    mode: "manual",
    title: "Refresh manually",
    description:
      "Waiting-list updates are not pushed to this browser. Use Refresh list or Refresh details when staff asks you to check again or after you submit an owner response.",
  };
}

function entryTimestamp(value: string | null): number {
  const timestamp = value ? Date.parse(value) : Number.NaN;

  return Number.isFinite(timestamp) ? timestamp : 0;
}

function formatTableLabel(tableCode: string | null, tableIds: number[]): string | null {
  if (tableCode) {
    return `table ${tableCode}`;
  }

  if (tableIds.length === 1) {
    return `table #${tableIds[0]}`;
  }

  if (tableIds.length > 1) {
    return `tables #${tableIds.join(", #")}`;
  }

  return null;
}
