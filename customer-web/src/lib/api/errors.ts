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
  conflictType: string | null;
  replayState: string | null;
  stateReason: string | null;
  nextActions: string[];
  raw: unknown;
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

export const ACTIVE_TABLE_HOLD_SESSION_MESSAGE =
  "Phiên này đang có một lượt giữ bàn khác. Vui lòng tải lại phiên đặt bàn để đồng bộ lượt giữ hiện tại.";

type ErrorPayload = {
  message?: unknown;
  error_code?: unknown;
  category_code?: unknown;
  request_id?: unknown;
  errors?: unknown;
  details?: unknown;
  conflict_type?: unknown;
  replay_state?: unknown;
  state_reason?: unknown;
  next_actions?: unknown;
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

function stringField(value: unknown): string | null {
  return typeof value === "string" && value.trim() !== "" ? value : null;
}

function stringListField(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value.filter((entry): entry is string => typeof entry === "string" && entry.trim() !== "");
  }

  if (typeof value === "string" && value.trim() !== "") {
    return [value];
  }

  return [];
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
      errorCode: stringField(payload.error_code),
      categoryCode: stringField(payload.category_code),
      requestId: stringField(payload.request_id),
      validationErrors,
      conflictType: stringField(payload.conflict_type),
      replayState: stringField(payload.replay_state),
      stateReason: stringField(payload.state_reason),
      nextActions: stringListField(payload.next_actions),
      raw: error.payload,
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
      conflictType: null,
      replayState: null,
      stateReason: null,
      nextActions: [],
      raw: null,
      cause: error,
    };
  }

  if (error && typeof error === "object" && "kind" in error && "message" in error) {
    const candidate = error as Partial<NormalizedApiError>;
    const { nextActions, raw, ...rest } = candidate;

    return {
      conflictType: null,
      replayState: null,
      stateReason: null,
      ...rest,
      nextActions: nextActions ?? [],
      raw: raw ?? error,
    } as NormalizedApiError;
  }

  return {
    kind: "unknown",
    status: null,
    message: "Đã có lỗi xảy ra.",
    errorCode: null,
    categoryCode: null,
    requestId: null,
    validationErrors: null,
    conflictType: null,
    replayState: null,
    stateReason: null,
    nextActions: [],
    raw: null,
    cause: error,
  };
}

export function userFacingApiMessage(error: unknown): string {
  return getApiErrorDisplay(error).message;
}

export function customerFriendlyHoldMessage(
  error: unknown,
  options: { recoveryFailed?: boolean } = {},
): string {
  if (isExpiredHoldApiError(error)) {
    return options.recoveryFailed
      ? "Bàn đã hết thời gian giữ. Bạn có thể chọn lại bàn mà không cần nhập lại thông tin."
      : "Bàn vừa hết thời gian giữ. Mình sẽ thử giữ lại bàn này cho bạn.";
  }

  if (isHoldConflictApiError(error)) {
    return "Bàn này vừa có khách khác chọn. Hãy chọn một bàn còn trống khác.";
  }

  if (isHoldSessionMismatchApiError(error)) {
    return "Phiên giữ bàn không còn hợp lệ. Vui lòng chọn lại bàn để tiếp tục.";
  }

  if (isHoldScopeMismatchApiError(error)) {
    return "Thông tin giữ bàn không khớp với thời gian đặt. Vui lòng chọn lại bàn.";
  }

  return "Mộc Sen chưa thể kiểm tra trạng thái lúc này. Vui lòng thử lại sau ít phút.";
}

export function customerFriendlyDepositMessage(error: unknown): string {
  if (isStaleRowVersionApiError(error)) {
    return "Thông tin đặt bàn vừa được cập nhật. Vui lòng tải lại trạng thái mới nhất trước khi tiếp tục.";
  }

  if (isPaymentSessionExpiredApiError(error)) {
    return "Phiên thanh toán đã hết hạn. Bạn có thể tạo lại phiên thanh toán mới.";
  }

  if (isConflictLikeApiError(error)) {
    return "Thông tin đặt bàn vừa được cập nhật. Vui lòng tải lại trạng thái mới nhất trước khi tiếp tục.";
  }

  return "Mộc Sen chưa thể kiểm tra trạng thái lúc này. Vui lòng thử lại sau ít phút.";
}

export function getApiErrorDisplay(error: unknown): ApiErrorDisplay {
  const normalized = normalizeApiError(error);
  const requestIdLabel = normalized.requestId ? `Mã hỗ trợ: ${normalized.requestId}` : null;
  const errorCodeLabel = normalized.errorCode ? `Mã lỗi ${normalized.errorCode}` : null;
  const statusLabel = normalized.status ? `Trạng thái ${normalized.status}` : null;

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
      message: "Phiên đăng nhập đã hết hạn.",
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

  if (isActiveTableHoldSessionError(normalized)) {
    return {
      message: ACTIVE_TABLE_HOLD_SESSION_MESSAGE,
      retryHint: null,
      statusLabel,
      requestIdLabel,
      errorCodeLabel,
    };
  }

  if (isRecoverableMutationApiError(normalized)) {
    return {
      message: isConflictLikeApiError(normalized)
        ? "Thông tin đã thay đổi trong lúc bạn thao tác."
        : normalized.message,
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

  const requestIdLabel = normalized.requestId ? `Mã hỗ trợ: ${normalized.requestId}` : null;
  const errorCodeLabel = normalized.errorCode ? `Mã lỗi ${normalized.errorCode}` : null;
  const statusLabel = normalized.status ? `Trạng thái ${normalized.status}` : null;

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
      title: "Phiên không còn hợp lệ",
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
      title: "Không thể mở lượt đặt này",
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

  if (
    normalized.categoryCode === "resource_conflict" ||
    normalized.categoryCode === "stale_write" ||
    normalized.categoryCode === "idempotency_conflict"
  ) {
    return true;
  }

  if (
    normalized.errorCode === "stale_row_version" ||
    normalized.errorCode === "idempotency_conflict" ||
    normalized.conflictType === "stale_write" ||
    normalized.conflictType === "state_conflict" ||
    normalized.conflictType === "idempotency_payload_mismatch" ||
    normalized.replayState === "payload_mismatch" ||
    normalized.nextActions?.includes("reload_resource") ||
    normalized.nextActions?.includes("retry_with_latest_row_version")
  ) {
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

export function isStaleRowVersionApiError(error: unknown): boolean {
  const normalized = normalizeApiError(error);

  return (
    normalized.errorCode === "stale_row_version" ||
    normalized.categoryCode === "stale_write" ||
    normalized.conflictType === "stale_write" ||
    normalized.nextActions?.includes("retry_with_latest_row_version") ||
    hasValidationField(normalized, ["row_version", "pre_order_row_version"]) ||
    /row[_\s-]?version|stale|updated elsewhere/.test(searchableErrorText(normalized))
  );
}

export function isExpiredHoldApiError(error: unknown): boolean {
  const normalized = normalizeApiError(error);
  const haystack = searchableErrorText(normalized);

  return (
    normalized.errorCode === "hold_expired" ||
    normalized.errorCode === "table_hold_expired" ||
    normalized.categoryCode === "hold_expired" ||
    normalized.stateReason === "expired" ||
    /(expired hold|hold.*expired|expired|expire_at|hết hạn|maximum ttl|can no longer|not in a state)/.test(haystack) &&
      hasValidationField(normalized, ["hold_id", "expire_at", "hold_status"])
  );
}

export function isHoldConflictApiError(error: unknown): boolean {
  const normalized = normalizeApiError(error);
  const haystack = searchableErrorText(normalized);

  return (
    normalized.kind === "conflict" ||
    normalized.errorCode === "table_hold_conflict" ||
    normalized.errorCode === "reservation_conflict" ||
    normalized.categoryCode === "resource_conflict" ||
    hasValidationField(normalized, ["table_ids"]) &&
      /(overlap|conflict|another session|already|held|reservation|trùng|khác chọn)/.test(haystack)
  );
}

export function isHoldSessionMismatchApiError(error: unknown): boolean {
  const normalized = normalizeApiError(error);
  const haystack = searchableErrorText(normalized);

  return (
    normalized.errorCode === "session_mismatch" ||
    normalized.errorCode === "hold_session_mismatch" ||
    normalized.categoryCode === "session_mismatch" ||
    hasValidationField(normalized, ["session_id", "hold_id"]) && /(session|không thuộc session|not authorized|mismatch|không còn hợp lệ)/.test(haystack)
  );
}

export function isHoldScopeMismatchApiError(error: unknown): boolean {
  const normalized = normalizeApiError(error);
  const haystack = searchableErrorText(normalized);

  return (
    normalized.errorCode === "hold_scope_mismatch" ||
    normalized.errorCode === "reservation_hold_mismatch" ||
    normalized.categoryCode === "scope_mismatch" ||
    hasValidationField(normalized, ["branch_id", "table_ids", "hold_id", "start_time", "end_time"]) &&
    /(branch|time window|bao phủ|does not match|không khớp|requested branch|selected table)/.test(haystack)
  );
}

export function isPaymentSessionExpiredApiError(error: unknown): boolean {
  const normalized = normalizeApiError(error);
  const haystack = searchableErrorText(normalized);

  return (
    normalized.errorCode === "payment_session_expired" ||
    normalized.errorCode === "deposit_payment_session_expired" ||
    normalized.categoryCode === "payment_session_expired" ||
    hasValidationField(normalized, ["payment_session", "session_id", "provider_expires_at"]) &&
    /(expired|expire|hết hạn|session)/.test(haystack)
  );
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

export function isActiveTableHoldSessionError(error: unknown): boolean {
  const normalized = normalizeApiError(error);

  if (normalized.kind !== "validation" || !hasValidationField(normalized, ["session_id"])) {
    return false;
  }

  const haystack = searchableErrorText(normalized);

  return /active hold|another active hold|holding|pending/.test(haystack);
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
    error.conflictType,
    error.replayState,
    error.stateReason,
    ...(error.nextActions ?? []),
    ...Object.values(error.validationErrors ?? {}).flat(),
  ]
    .filter((value): value is string => typeof value === "string" && value.trim() !== "")
    .join(" ")
    .toLowerCase();
}
