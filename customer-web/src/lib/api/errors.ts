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
  requestId: string | null;
  validationErrors: Record<string, string[]> | null;
  cause?: unknown;
};

type ErrorPayload = {
  message?: unknown;
  error_code?: unknown;
  request_id?: unknown;
  errors?: unknown;
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
    const message = typeof payload.message === "string" ? payload.message : "The request could not be completed.";

    return {
      kind: kindForStatus(error.status),
      status: error.status,
      message,
      errorCode: typeof payload.error_code === "string" ? payload.error_code : null,
      requestId: typeof payload.request_id === "string" ? payload.request_id : null,
      validationErrors: normalizeValidationErrors(payload.errors),
      cause: error,
    };
  }

  if (error instanceof TypeError) {
    return {
      kind: "backend_unavailable",
      status: null,
      message: "The restaurant service is not reachable right now.",
      errorCode: "backend_unavailable",
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
    requestId: null,
    validationErrors: null,
    cause: error,
  };
}

export function userFacingApiMessage(error: unknown): string {
  const normalized = normalizeApiError(error);

  if (normalized.kind === "backend_unavailable") {
    return "We cannot reach the restaurant service. Check that the backend is running, then try again.";
  }

  if (normalized.kind === "unauthorized") {
    return "Please sign in again to continue.";
  }

  if (normalized.kind === "forbidden") {
    return "This item is not available for this customer session.";
  }

  if (normalized.kind === "conflict") {
    return "This changed while you were working. Refresh and try again.";
  }

  return normalized.message;
}
