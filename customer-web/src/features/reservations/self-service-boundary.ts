import { normalizeApiError } from "@/lib/api/errors";

export type SelfServiceBoundaryEntity = "reservation" | "deposit" | "bill" | "preorder" | "benefits";

export type SelfServiceAccessState =
  | {
      kind: "owner_only";
      title: string;
      description: string;
    }
  | {
      kind: "session_linked";
      title: string;
      description: string;
    };

export type SelfServiceBlockedState =
  | {
      kind: "unavailable";
      title: string;
      description: string;
    }
  | {
      kind: "forbidden";
      title: string;
      description: string;
    }
  | {
      kind: "error";
      title: string;
      error: unknown;
    };

export function getSelfServiceAccessState(accessScope?: string | null): SelfServiceAccessState | null {
  if (accessScope === "owner") {
    return {
      kind: "owner_only",
      title: "Account owner access",
      description: "You are viewing this reservation as the signed-in customer account owner.",
    };
  }

  if (accessScope === "session") {
    return {
      kind: "session_linked",
      title: "Linked visit session",
      description: "You are viewing this reservation through the linked visit session for this browser.",
    };
  }

  return null;
}

export function getSelfServiceBlockedState(
  entity: SelfServiceBoundaryEntity,
  error: unknown,
  fallbackTitle: string,
): SelfServiceBlockedState {
  const normalized = normalizeApiError(error);

  if (normalized.kind === "not_found") {
    return {
      kind: "unavailable",
      title: getUnavailableTitle(entity),
      description: getUnavailableDescription(entity),
    };
  }

  if (normalized.kind === "forbidden") {
    return {
      kind: "forbidden",
      title: getForbiddenTitle(entity),
      description: getForbiddenDescription(entity),
    };
  }

  return {
    kind: "error",
    title: fallbackTitle,
    error,
  };
}

function getUnavailableTitle(entity: SelfServiceBoundaryEntity): string {
  switch (entity) {
    case "reservation":
      return "This reservation is unavailable";
    case "deposit":
      return "Deposit is unavailable";
    case "bill":
      return "Bill is unavailable";
    case "preorder":
      return "Preorder is unavailable";
    case "benefits":
      return "Benefits are unavailable";
  }
}

function getUnavailableDescription(entity: SelfServiceBoundaryEntity): string {
  switch (entity) {
    case "reservation":
      return "The reservation could not be found or is no longer available from customer self-service.";
    case "deposit":
      return "Deposit details are not available for this reservation right now.";
    case "bill":
      return "Bill details are not available for this reservation right now.";
    case "preorder":
      return "Preorder details are not available for this reservation right now.";
    case "benefits":
      return "Benefits preview is not available for this reservation right now.";
  }
}

function getForbiddenTitle(entity: SelfServiceBoundaryEntity): string {
  switch (entity) {
    case "reservation":
      return "Reservation access is blocked";
    case "deposit":
      return "Deposit access is blocked";
    case "bill":
      return "Bill access is blocked";
    case "preorder":
      return "Preorder access is blocked";
    case "benefits":
      return "Benefits access is blocked";
  }
}

function getForbiddenDescription(entity: SelfServiceBoundaryEntity): string {
  switch (entity) {
    case "reservation":
      return "This reservation cannot be opened from customer self-service with the current actor.";
    case "deposit":
      return "Deposit self-service is not available for the current actor on this reservation.";
    case "bill":
      return "Bill self-service is not available for the current actor on this reservation.";
    case "preorder":
      return "Preorder self-service is not available for the current actor on this reservation.";
    case "benefits":
      return "Benefits preview is not available for the current actor on this reservation.";
  }
}
