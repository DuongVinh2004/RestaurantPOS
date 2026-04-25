import { RestaurantPosApiError } from "@/lib/contracts/generated/restaurantpos-sdk";

export type NormalizedApiError = {
  kind:
    | "backend_unavailable"
    | "unauthorized"
    | "forbidden"
    | "conflict"
    | "validation"
    | "not_found"
    | "server"
    | "unknown";
  status: number | null;
  message: string;
  errorCode: string | null;
  categoryCode: string | null;
  requestId: string | null;
  validationErrors: Record<string, string[]> | null;
  cause?: unknown;
};

export type ApiErrorDisplay = {
  message: string;
  retryHint: string | null;
  statusLabel: string | null;
  requestIdLabel: string | null;
  errorCodeLabel: string | null;
};

export type SessionRestoreKind =
  | "token_expired"
  | "backend_unavailable"
  | "invalid_session"
  | "unauthorized_owner_access"
  | "unknown";

export type SessionRestoreError = NormalizedApiError & {
  restoreKind: SessionRestoreKind;
};

export type SessionRestoreDisplay = ApiErrorDisplay & {
  title: string;
  primaryAction: "retry" | "sign_in";
};

type ErrorPayload = {
  message?: unknown;
  error_code?: unknown;
  category_code?: unknown;
  request_id?: unknown;
  errors?: unknown;
  details?: unknown;
};

function payloadRecord(payload: unknown): ErrorPayload {
  return payload && typeof payload === "object" ? (payload as ErrorPayload) : {};
}

function normalizeValidationErrors(value: unknown): Record<string, string[]> | null {
  if (!value || typeof value !== "object") {
    return null;
  }

  const normalized: Record<string, string[]> = {};

  for (const [field, messages] of Object.entries(value)) {
    if (Array.isArray(messages)) {
      normalized[field] = messages.map((message) => String(message));
    } else if (typeof messages === "string") {
      normalized[field] = [messages];
    }
  }

  return Object.keys(normalized).length > 0 ? normalized : null;
}

function collectValidationErrors(payload: ErrorPayload): Record<string, string[]> | null {
  const direct = normalizeValidationErrors(payload.errors);
  const details = payload.details && typeof payload.details === "object" ? (payload.details as ErrorPayload) : {};
  const nested = normalizeValidationErrors(details.errors);

  if (!direct && !nested) {
    return null;
  }

  return {
    ...(nested ?? {}),
    ...(direct ?? {}),
  };
}

function firstValidationMessage(errors: Record<string, string[]> | null): string | null {
  if (!errors) {
    return null;
  }

  for (const messages of Object.values(errors)) {
    const message = messages.find((entry) => entry.trim() !== "");
    if (message) {
      return message;
    }
  }

  return null;
}

function kindForStatus(status: number): NormalizedApiError["kind"] {
  if (status === 401) return "unauthorized";
  if (status === 403) return "forbidden";
  if (status === 404) return "not_found";
  if (status === 409) return "conflict";
  if (status === 422) return "validation";
  if (status >= 500) return "server";
  return "unknown";
}

export function normalizeApiError(error: unknown): NormalizedApiError {
  if (error instanceof RestaurantPosApiError) {
    const payload = payloadRecord(error.payload);
    const validationErrors = collectValidationErrors(payload);
    const payloadMessage = typeof payload.message === "string" ? payload.message : null;
    const validationMessage = firstValidationMessage(validationErrors);
    const message =
      payloadMessage && (!isGenericApiMessage(payloadMessage) || !validationMessage)
        ? payloadMessage
        : validationMessage ?? payloadMessage ?? "The request could not be completed.";

    return {
      kind: kindForStatus(error.status),
      status: error.status,
      message,
      errorCode: typeof payload.error_code === "string" ? payload.error_code : null,
      categoryCode: typeof payload.category_code === "string" ? payload.category_code : null,
      requestId: typeof payload.request_id === "string" ? payload.request_id : null,
      validationErrors,
      cause: error,
    };
  }

  if (error instanceof TypeError) {
    return {
      kind: "backend_unavailable",
      status: null,
      message: "The restaurant service is not reachable right now.",
      errorCode: "backend_unavailable",
      categoryCode: "backend_unavailable",
      requestId: null,
      validationErrors: null,
      cause: error,
    };
  }

  if (error && typeof error === "object" && "kind" in error && "message" in error) {
    return error as NormalizedApiError;
  }

  return {
    kind: "unknown",
    status: null,
    message: "Something went wrong.",
    errorCode: null,
    categoryCode: null,
    requestId: null,
    validationErrors: null,
    cause: error,
  };
}

export function userFacingApiMessage(error: unknown): string {
  return getApiErrorDisplay(error).message;
}

export function getApiErrorDisplay(error: unknown): ApiErrorDisplay {
  const normalized = normalizeApiError(error);
  const statusLabel = normalized.status === null ? null : `Status ${normalized.status}`;
  const requestIdLabel = normalized.requestId ? `Request ID: ${normalized.requestId}` : null;
  const errorCodeLabel = normalized.errorCode ? `Code ${normalized.errorCode}` : null;

  if (normalized.errorCode === "api_base_url_misconfigured") {
    return {
      message: normalized.message,
      retryHint: "Deploy the correct NEXT_PUBLIC_API_BASE_URL for this environment, then reload the page.",
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
    };
  }

  if (normalized.kind === "backend_unavailable") {
    return {
      message: "We cannot reach the restaurant service right now.",
      retryHint: "Check that the backend is running, then try again.",
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
    };
  }

  if (normalized.kind === "unauthorized") {
    return {
      message: "Please sign in again to continue.",
      retryHint: "Sign in again, then retry the action.",
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
    };
  }

  if (normalized.kind === "forbidden") {
    if (normalized.categoryCode === "owner_scope_denied") {
      return {
        message: "This item is not available for this account.",
        retryHint: null,
        statusLabel,
        requestIdLabel,
        errorCodeLabel,
      };
    }

    if (normalized.categoryCode === "policy_denied") {
      return {
        message: "This page is not available from customer self-service.",
        retryHint: null,
        statusLabel,
        requestIdLabel,
        errorCodeLabel,
      };
    }

    return {
      message: "This item is not available for this customer session.",
      retryHint: null,
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
    };
  }

  if (isRecoverableMutationApiError(normalized)) {
    return {
      message: "This changed while you were working.",
      retryHint: "Refresh the page to load the latest reservation or linked session, then retry.",
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
    };
  }

  if (normalized.kind === "server" || normalized.kind === "unknown") {
    return {
      message: normalized.message,
      retryHint: "Try again. If it keeps failing, contact support with the request ID.",
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
    };
  }

  return {
    message: normalized.message,
    retryHint: null,
    statusLabel,
    requestIdLabel,
    errorCodeLabel,
  };
}

export function hasExpiredSessionTimestamp(expiresAtUtc: string | null | undefined, now = new Date()): boolean {
  if (!expiresAtUtc) {
    return false;
  }

  const timestamp = Date.parse(expiresAtUtc);

  return Number.isFinite(timestamp) && timestamp <= now.getTime();
}

export function classifySessionRestoreError(
  error: unknown,
  options: { expiresAtUtc?: string | null } = {},
): SessionRestoreError {
  const normalized = normalizeApiError(error);

  if (normalized.kind === "backend_unavailable") {
    return {
      ...normalized,
      restoreKind: "backend_unavailable",
    };
  }

  if (hasExpiredSessionTimestamp(options.expiresAtUtc)) {
    return {
      ...normalized,
      restoreKind: "token_expired",
    };
  }

  if (normalized.kind === "forbidden") {
    return {
      ...normalized,
      restoreKind: "unauthorized_owner_access",
    };
  }

  if (normalized.kind === "unauthorized") {
    return {
      ...normalized,
      restoreKind: sessionRestoreHint(normalized),
    };
  }

  if (normalized.kind === "not_found" && isUnauthorizedOwnerAccessError(normalized)) {
    return {
      ...normalized,
      restoreKind: "unauthorized_owner_access",
    };
  }

  return {
    ...normalized,
    restoreKind: "unknown",
  };
}

export function getSessionRestoreDisplay(error: unknown): SessionRestoreDisplay {
  const normalized =
    error && typeof error === "object" && "restoreKind" in error
      ? (error as SessionRestoreError)
      : classifySessionRestoreError(error);

  const statusLabel = normalized.status === null ? null : `Status ${normalized.status}`;
  const requestIdLabel = normalized.requestId ? `Request ID: ${normalized.requestId}` : null;
  const errorCodeLabel = normalized.errorCode ? `Code ${normalized.errorCode}` : null;

  if (normalized.errorCode === "api_base_url_misconfigured") {
    return {
      title: "Sign-in is blocked by runtime configuration",
      message: normalized.message,
      retryHint: "Deploy the correct NEXT_PUBLIC_API_BASE_URL for this environment, then reload the page.",
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
      primaryAction: "sign_in",
    };
  }

  if (normalized.restoreKind === "token_expired") {
    return {
      title: "Your session expired",
      message: "Your saved sign-in has expired. Sign in again to continue.",
      retryHint: null,
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
      primaryAction: "sign_in",
    };
  }

  if (normalized.restoreKind === "invalid_session") {
    return {
      title: "Your session is no longer valid",
      message: "This saved browser session is no longer valid. Sign in again to continue.",
      retryHint: null,
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
      primaryAction: "sign_in",
    };
  }

  if (normalized.restoreKind === "unauthorized_owner_access") {
    return {
      title: "This page is not available for this account",
      message: "This saved sign-in does not match the customer account or linked visit session for this page.",
      retryHint: null,
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
      primaryAction: "sign_in",
    };
  }

  if (normalized.restoreKind === "backend_unavailable") {
    return {
      title: "We could not reach the restaurant service",
      message: "We cannot reach the restaurant service right now.",
      retryHint: "Check that the backend is running, then try again.",
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
      primaryAction: "retry",
    };
  }

  return {
    title: "We could not restore your session",
    ...getApiErrorDisplay(normalized),
    primaryAction: normalized.kind === "backend_unavailable" ? "retry" : "sign_in",
  };
}

export function isConflictLikeApiError(error: unknown): boolean {
  const normalized = normalizeApiError(error);

  if (normalized.kind === "conflict") {
    return true;
  }

  if (normalized.kind !== "validation") {
    return false;
  }

  if (normalized.errorCode === "stale_row_version" || normalized.categoryCode === "stale_write") {
    return true;
  }

  if (hasValidationField(normalized, ["row_version"])) {
    return true;
  }

  const haystack = searchableErrorText(normalized);

  return /conflict|row[_\s-]?version|stale|updated elsewhere/.test(haystack);
}

export function isSessionDriftLikeApiError(error: unknown): boolean {
  const normalized = normalizeApiError(error);

  if (normalized.kind !== "validation") {
    return false;
  }

  if (hasValidationField(normalized, ["session_id", "access_session"])) {
    return true;
  }

  const haystack = searchableErrorText(normalized);

  return (
    /(access session|linked visit session|browser session|session[_\s-]?id)/.test(haystack) &&
    /(invalid|inactive|expired|mismatch|drift|reload|try again|active)/.test(haystack)
  );
}

export function isUnauthorizedOwnerAccessError(error: unknown): boolean {
  const normalized = normalizeApiError(error);

  if (normalized.kind === "forbidden") {
    return true;
  }

  if (normalized.kind !== "not_found") {
    return false;
  }

  const haystack = searchableErrorText(normalized);

  return /reservation(?: data)? (?:was )?not found/.test(haystack);
}

function isGenericApiMessage(message: string): boolean {
  return [
    "validation error.",
    "unauthorized.",
    "forbidden.",
    "not found.",
    "conflict.",
    "state conflict detected.",
    "too many requests.",
    "server error.",
  ].includes(message.trim().toLowerCase());
}

function sessionRestoreHint(error: NormalizedApiError): SessionRestoreKind {
  const haystack = searchableErrorText(error);

  if (haystack.includes("expired")) {
    return "token_expired";
  }

  if (haystack.includes("owner") || haystack.includes("scope")) {
    return "unauthorized_owner_access";
  }

  return "invalid_session";
}

function isRecoverableMutationApiError(error: NormalizedApiError): boolean {
  return error.kind === "conflict" || isConflictLikeApiError(error) || isSessionDriftLikeApiError(error);
}

function hasValidationField(error: NormalizedApiError, fields: string[]): boolean {
  if (!error.validationErrors) {
    return false;
  }

  return fields.some((field) => Object.prototype.hasOwnProperty.call(error.validationErrors, field));
}

function searchableErrorText(error: NormalizedApiError): string {
  return [
    error.message,
    error.errorCode,
    error.categoryCode,
    ...Object.values(error.validationErrors ?? {}).flat(),
  ]
    .filter((value): value is string => typeof value === "string" && value.trim() !== "")
    .join(" ")
    .toLowerCase();
}
