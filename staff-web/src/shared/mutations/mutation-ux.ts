import { normalizeApiError, type NormalizedApiError } from '../api/errors';

export type StaffMutationPhase =
  | 'idle'
  | 'submitting'
  | 'succeeded'
  | 'validation_failed'
  | 'conflict'
  | 'denied'
  | 'retriable_failure';

export type StaffMutationFeedback = {
  phase: StaffMutationPhase;
  title: string;
  description: string;
  requestId: string | null;
  errorCode: string | null;
  categoryCode: string | null;
  retryable: boolean;
};

export type StaffMutationErrorContext = {
  actionLabel: string;
  fallbackMessage: string;
};

type FailurePhase = Exclude<StaffMutationPhase, 'idle' | 'submitting' | 'succeeded'>;

const ERROR_CODE_PHASE_MAP: Record<string, FailurePhase> = {
  stale_row_version: 'conflict',
  conflict: 'conflict',
  idempotency_in_progress: 'conflict',
  idempotency_conflict: 'conflict',
  forbidden: 'denied',
  unauthorized: 'denied',
  policy_denied: 'denied',
  validation_error: 'validation_failed',
  idempotency_key_required: 'validation_failed',
};

const CATEGORY_PHASE_MAP: Record<string, FailurePhase> = {
  stale_write: 'conflict',
  resource_conflict: 'conflict',
  idempotency_conflict: 'conflict',
  forbidden_capability: 'denied',
  policy_denied: 'denied',
  validation_error: 'validation_failed',
  domain_invariant_violation: 'validation_failed',
  rate_limited: 'retriable_failure',
};

export function createIdleMutationFeedback(): StaffMutationFeedback {
  return {
    phase: 'idle',
    title: '',
    description: '',
    requestId: null,
    errorCode: null,
    categoryCode: null,
    retryable: false,
  };
}

export function createSubmittingMutationFeedback(actionLabel: string, description: string): StaffMutationFeedback {
  return {
    phase: 'submitting',
    title: `${actionLabel} đang được gửi`,
    description,
    requestId: null,
    errorCode: null,
    categoryCode: null,
    retryable: false,
  };
}

export function createSuccessMutationFeedback(actionLabel: string, description: string): StaffMutationFeedback {
  return {
    phase: 'succeeded',
    title: `${actionLabel} đã hoàn tất`,
    description,
    requestId: null,
    errorCode: null,
    categoryCode: null,
    retryable: false,
  };
}

export function mapMutationErrorToFeedback(
  error: unknown,
  context: StaffMutationErrorContext,
): StaffMutationFeedback {
  const normalized = normalizeApiError(error, context.fallbackMessage);
  const phase = resolveFailurePhase(normalized);

  return {
    phase,
    title: titleForPhase(phase, normalized, context.actionLabel),
    description: descriptionForPhase(phase, normalized, context),
    requestId: normalized.requestId,
    errorCode: normalized.code,
    categoryCode: normalized.categoryCode,
    retryable: phase === 'conflict' || phase === 'retriable_failure',
  };
}

function resolveFailurePhase(error: NormalizedApiError): FailurePhase {
  const phaseFromCode = error.code ? ERROR_CODE_PHASE_MAP[error.code] : undefined;
  if (phaseFromCode) {
    return phaseFromCode;
  }

  const phaseFromCategory = error.categoryCode ? CATEGORY_PHASE_MAP[error.categoryCode] : undefined;
  if (phaseFromCategory) {
    return phaseFromCategory;
  }

  if (error.kind === 'auth' || error.kind === 'forbidden') {
    return 'denied';
  }

  if (error.kind === 'validation') {
    return 'validation_failed';
  }

  if (error.kind === 'conflict' || error.kind === 'not-found') {
    return 'conflict';
  }

  return 'retriable_failure';
}

function titleForPhase(
  phase: FailurePhase,
  error: NormalizedApiError,
  actionLabel: string,
): string {
  switch (phase) {
    case 'validation_failed':
      return error.code === 'idempotency_key_required'
        ? `${actionLabel} thiếu khóa chống gửi lặp`
        : `${actionLabel} bị chặn bởi dữ liệu hoặc rule nghiệp vụ`;
    case 'conflict':
      if (isStaleWrite(error)) {
        return `${actionLabel} gặp xung đột phiên bản`;
      }

      if (isIdempotencyConflict(error)) {
        return `${actionLabel} đang bị giữ bởi một yêu cầu khác`;
      }

      if (error.kind === 'not-found') {
        return `${actionLabel} không còn khớp với bản ghi đang xem`;
      }

      return `${actionLabel} gặp xung đột trạng thái`;
    case 'denied':
      if (error.kind === 'auth') {
        return 'Phiên làm việc hiện tại không còn hợp lệ';
      }

      if (error.requiredCapability) {
        return `${actionLabel} bị chặn bởi capability`;
      }

      if (error.code === 'policy_denied' || error.categoryCode === 'policy_denied') {
        return `${actionLabel} bị chặn bởi policy hoặc chi nhánh`;
      }

      return `${actionLabel} bị từ chối`;
    case 'retriable_failure':
      if (error.kind === 'rate-limit') {
        return `${actionLabel} đang bị hệ thống làm chậm`;
      }

      return `${actionLabel} chưa thể hoàn tất lúc này`;
  }
}

function descriptionForPhase(
  phase: FailurePhase,
  error: NormalizedApiError,
  context: StaffMutationErrorContext,
): string {
  const message = preferredMessage(error, context.fallbackMessage);

  switch (phase) {
    case 'validation_failed':
      if (error.code === 'idempotency_key_required') {
        return 'Frontend chưa gửi được khóa chống gửi lặp cho mutation này. Hãy tải lại màn hình rồi thử lại.';
      }

      return message;
    case 'conflict':
      if (isStaleWrite(error)) {
        return `${message} Hãy tải lại dữ liệu hoặc bảng điều phối trước khi thao tác lại.`;
      }

      if (isIdempotencyConflict(error)) {
        return error.replayState === 'payload_mismatch'
          ? 'Một Idempotency-Key cũ đã được dùng cho payload khác. Hãy làm mới màn hình rồi gửi lại thao tác mới.'
          : 'Yêu cầu trước đó vẫn đang xử lý hoặc vừa giữ khóa thao tác này. Chờ xong rồi tải lại trạng thái trước khi bấm lại.';
      }

      if (error.kind === 'not-found') {
        return 'Bản ghi vừa rời khỏi phạm vi hiện tại hoặc đã đổi trạng thái. Hãy làm mới danh sách rồi thao tác lại trên dòng mới nhất.';
      }

      return `${message} Hãy làm mới dữ liệu hiện tại trước khi thao tác lại.`;
    case 'denied':
      if (error.kind === 'auth') {
        return 'Hãy làm mới phiên đăng nhập hoặc đăng nhập lại trước khi tiếp tục thao tác này.';
      }

      if (error.requiredCapability) {
        return `Phiên hiện tại thiếu quyền ${error.requiredCapability}. Hãy chuyển sang đúng vai trò hoặc nhờ cấp quyền rồi thử lại.`;
      }

      return message;
    case 'retriable_failure':
      if (error.kind === 'rate-limit') {
        return 'Hệ thống đang nhận quá nhiều yêu cầu cùng lúc. Hãy chờ một lát rồi thử lại.';
      }

      return message;
  }
}

function preferredMessage(error: NormalizedApiError, fallback: string): string {
  const validationMessage = Object.values(error.validation).flat().find((value) => value.trim() !== '');

  if (validationMessage) {
    return validationMessage;
  }

  const normalizedMessage = error.message.trim();
  return normalizedMessage !== '' ? normalizedMessage : fallback;
}

function isStaleWrite(error: NormalizedApiError): boolean {
  return (
    error.code === 'stale_row_version'
    || error.categoryCode === 'stale_write'
    || error.conflictType === 'stale_write'
  );
}

function isIdempotencyConflict(error: NormalizedApiError): boolean {
  return (
    error.code === 'idempotency_in_progress'
    || error.code === 'idempotency_conflict'
    || error.categoryCode === 'idempotency_conflict'
  );
}
