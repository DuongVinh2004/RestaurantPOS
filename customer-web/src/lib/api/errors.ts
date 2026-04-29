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
        : validationMessage ?? payloadMessage ?? "Yêu cầu chưa được xử lý.";

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
      message: "Hiện chưa kết nối được với hệ thống nhà hàng.",
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
    message: "Đã có lỗi xảy ra.",
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
  const statusLabel = normalized.status === null ? null : `Trạng thái ${normalized.status}`;
  const requestIdLabel = normalized.requestId ? `Mã hỗ trợ: ${normalized.requestId}` : null;
  const errorCodeLabel = normalized.errorCode ? `Mã lỗi ${normalized.errorCode}` : null;

  if (normalized.errorCode === "api_base_url_misconfigured") {
    return {
      message: normalized.message,
      retryHint: "Cấu hình lại địa chỉ API phù hợp cho môi trường này, rồi tải lại trang.",
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
    };
  }

  if (normalized.kind === "backend_unavailable") {
    return {
      message: "Hiện chưa kết nối được với hệ thống nhà hàng.",
      retryHint: "Kiểm tra hệ thống nhà hàng rồi thử lại.",
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
    };
  }

  if (normalized.kind === "unauthorized") {
    return {
      message: "Vui lòng đăng nhập lại để tiếp tục.",
      retryHint: "Đăng nhập lại, sau đó thử lại thao tác.",
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
    };
  }

  if (normalized.kind === "forbidden") {
    if (normalized.categoryCode === "owner_scope_denied") {
      return {
        message: "Mục này không khả dụng với tài khoản hiện tại.",
        retryHint: null,
        statusLabel,
        requestIdLabel,
        errorCodeLabel,
      };
    }

    if (normalized.categoryCode === "policy_denied") {
      return {
        message: "Trang này chưa khả dụng cho khách hàng tự thao tác.",
        retryHint: null,
        statusLabel,
        requestIdLabel,
        errorCodeLabel,
      };
    }

    return {
      message: "Mục này không khả dụng với phiên khách hàng hiện tại.",
      retryHint: null,
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
    };
  }

  if (isRecoverableMutationApiError(normalized)) {
    return {
      message: "Thông tin đã thay đổi trong lúc bạn thao tác.",
      retryHint: "Tải lại trang để lấy thông tin mới nhất rồi thử lại.",
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
    };
  }

  if (normalized.kind === "server" || normalized.kind === "unknown") {
    return {
      message: normalized.message,
      retryHint: "Thử lại. Nếu vẫn lỗi, gửi mã hỗ trợ cho nhà hàng.",
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

  const statusLabel = normalized.status === null ? null : `Trạng thái ${normalized.status}`;
  const requestIdLabel = normalized.requestId ? `Mã hỗ trợ: ${normalized.requestId}` : null;
  const errorCodeLabel = normalized.errorCode ? `Mã lỗi ${normalized.errorCode}` : null;

  if (normalized.errorCode === "api_base_url_misconfigured") {
    return {
      title: "Chưa thể đăng nhập",
      message: normalized.message,
      retryHint: "Cấu hình lại địa chỉ API phù hợp cho môi trường này, rồi tải lại trang.",
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
      primaryAction: "sign_in",
    };
  }

  if (normalized.restoreKind === "token_expired") {
    return {
      title: "Phiên đăng nhập đã hết hạn",
      message: "Vui lòng đăng nhập lại để tiếp tục.",
      retryHint: null,
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
      primaryAction: "sign_in",
    };
  }

  if (normalized.restoreKind === "invalid_session") {
    return {
      title: "Phiên đăng nhập không còn hợp lệ",
      message: "Phiên trên trình duyệt này không còn hợp lệ. Vui lòng đăng nhập lại.",
      retryHint: null,
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
      primaryAction: "sign_in",
    };
  }

  if (normalized.restoreKind === "unauthorized_owner_access") {
    return {
      title: "Trang này không thuộc tài khoản hiện tại",
      message: "Tài khoản đang đăng nhập không khớp với lượt đặt hoặc phiên ghé nhà hàng của trang này.",
      retryHint: null,
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
      primaryAction: "sign_in",
    };
  }

  if (normalized.restoreKind === "backend_unavailable") {
    return {
      title: "Chưa kết nối được với nhà hàng",
      message: "Hiện chưa kết nối được với hệ thống nhà hàng.",
      retryHint: "Kiểm tra hệ thống nhà hàng rồi thử lại.",
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
      primaryAction: "retry",
    };
  }

  return {
    title: "Chưa khôi phục được phiên đăng nhập",
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
