import type { CustomerWaitingListCollectionEnvelope, CustomerWaitingListEntry } from "@/lib/contracts/generated/restaurantpos-sdk";

export type WaitingListOwnerAction = "accept" | "arrival" | "decline" | "cancel";

type WaitingListResponseState = "none" | "accepted" | "arrival_confirmed" | "declined";

export type WaitingListJourneyState = {
  state: "waiting" | "invite_pending" | "accepted" | "arrival_confirmed" | "declined" | "expired" | "seated" | "cancelled" | "unknown";
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
  state: "unavailable" | "waiting_for_staff" | "seated" | "cancelled";
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
  const responseState = responseStateFromPayload(entry);

  if (entry.status === "Cancelled") {
    return {
      state: "cancelled",
      title: "Entry cancelled",
      description: "This waiting-list entry is no longer active for this customer account.",
      nextStep: "Join again later if you still need a table.",
    };
  }

  if (responseState === "declined") {
    return {
      state: "declined",
      title: "Invite declined",
      description: "This invite has a recorded customer decline response.",
      nextStep: "Wait for another invite if the restaurant opens one.",
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

  if (responseState === "arrival_confirmed") {
    return {
      state: "arrival_confirmed",
      title: "Arrival confirmed",
      description: "Your arrival is confirmed. Final seating still happens from restaurant runtime.",
      nextStep: "Wait for staff to seat you, then refresh manually for the latest result.",
    };
  }

  if (!isNotifyWindowOpen(entry)) {
    return {
      state: "expired",
      title: "Invite window expired",
      description: "The current invite window is no longer active for this entry.",
      nextStep: "Wait for another invite or contact the restaurant directly if staff asked you to respond again.",
    };
  }

  if (responseState === "accepted") {
    return {
      state: "accepted",
      title: "Invite accepted",
      description: "You accepted the current invite. Staff still needs your arrival confirmation or final seating action from restaurant runtime.",
      nextStep: "Confirm arrival when you reach the restaurant.",
    };
  }

  return {
    state: "invite_pending",
    title: "Invite response available",
    description: "A live invite window is open for this entry and the backend returned owner actions for this customer.",
    nextStep: formatNextStep(entry.next_step) ?? "Use the available actions before the invite window closes.",
  };
}

export function getWaitingListOwnerActionPolicy(entry: WaitingListEntryLike): WaitingListOwnerActionPolicy {
  const availableActions = ownerActionsForResponseState(entry);

  if (entry.status === "Waiting") {
    return {
      availableActions,
      title: availableActions.includes("cancel") ? "Cancel is available" : "No online response available",
      description: availableActions.includes("cancel")
        ? "This entry is still waiting for an invite, so the only customer action available online is to cancel it."
        : "This waiting-list entry is read-only from customer-web.",
    };
  }

  if (entry.status !== "Notified") {
    return {
      availableActions,
      title: "No online response available",
      description: "This waiting-list status is read-only from customer-web.",
    };
  }

  if (responseStateFromPayload(entry) === "declined") {
    return {
      availableActions: [],
      title: "Invite already declined",
      description: "This invite was already declined, so no further owner response is available from customer-web.",
    };
  }

  if (!isNotifyWindowOpen(entry)) {
    return {
      availableActions: [],
      title: "Invite window closed",
      description: "The current invite window is no longer active, so customer response buttons stay hidden until the backend opens another state.",
    };
  }

  if (responseStateFromPayload(entry) === "accepted") {
    return {
      availableActions,
      title: "Arrival confirmation available",
      description: "The invite is already accepted. Confirm arrival when you reach the restaurant, or decline or cancel before staff seats you.",
    };
  }

  if (availableActions.length === 0 && entry.arrival_confirmation.staff_seat_required) {
    return {
      availableActions,
      title: "Waiting for staff seating",
      description: "Arrival is confirmed or staff owns the next step. Customer-web stays read-only until staff finishes the seating outcome.",
    };
  }

  return {
    availableActions,
    title: availableActions.length > 0 ? "Invite response available" : "No online response available",
    description:
      availableActions.length > 0
        ? "The invite window is active and backend returned the owner actions available for this entry."
        : "The invite window is active, but the backend did not return any customer actions for this entry.",
  };
}

export function getWaitingListSeatResultState(entry: WaitingListEntryLike): WaitingListSeatResultState {
  const responseState = responseStateFromPayload(entry);

  if (entry.status === "Cancelled") {
    return {
      state: "cancelled",
      title: "No seat result",
      description: "This entry was cancelled before the restaurant recorded a final seating result.",
      reservationId: null,
      tableLabel: null,
    };
  }

  if (entry.status === "Seated" || Boolean(entry.seated_at)) {
    return {
      state: "seated",
      title: "Seat result recorded",
      description: "The restaurant marked this entry as seated in backend runtime.",
      reservationId: null,
      tableLabel: null,
    };
  }

  if (entry.status === "Notified" && responseState === "arrival_confirmed" && entry.arrival_confirmation.staff_seat_required) {
    return {
      state: "waiting_for_staff",
      title: "Waiting for staff seat result",
      description: "Your arrival is confirmed, but final seating still happens in restaurant runtime. Refresh manually when staff asks you to check again.",
      reservationId: null,
      tableLabel: null,
    };
  }

  return {
    state: "unavailable",
    title: "Seat result not exposed yet",
    description:
      "This page does not fake notification or seating progress. Seat-result details only appear here after the backend exposes a stable owner-visible record.",
    reservationId: null,
    tableLabel: null,
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

function ownerActionsFromPayload(entry: WaitingListEntryLike): WaitingListOwnerAction[] {
  const actions: WaitingListOwnerAction[] = [];

  if (entry.available_actions.accept) actions.push("accept");
  if (entry.available_actions.confirm_arrival) actions.push("arrival");
  if (entry.available_actions.decline) actions.push("decline");
  if (entry.available_actions.cancel) actions.push("cancel");

  return actions;
}

function ownerActionsForResponseState(entry: WaitingListEntryLike): WaitingListOwnerAction[] {
  const actions = ownerActionsFromPayload(entry);
  const responseState = responseStateFromPayload(entry);

  if (responseState === "arrival_confirmed" || responseState === "declined") {
    return [];
  }

  if (responseState === "accepted") {
    return actions.filter((action) => action !== "accept");
  }

  return actions;
}

function responseStateFromPayload(entry: WaitingListEntryLike): WaitingListResponseState {
  const value = "response_state" in entry ? entry.response_state : null;

  switch (value) {
    case "accepted":
    case "arrival_confirmed":
    case "declined":
      return value;
    default:
      return "none";
  }
}

function isNotifyWindowOpen(entry: WaitingListEntryLike): boolean {
  return Boolean(entry.notify_window.is_open && entry.window.is_notified_window_open);
}

function entryTimestamp(value: string | null): number {
  const timestamp = value ? Date.parse(value) : Number.NaN;

  return Number.isFinite(timestamp) ? timestamp : 0;
}

function formatNextStep(value: string | null | undefined): string | null {
  if (!value) {
    return null;
  }

  return value
    .replace(/[_-]+/g, " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
