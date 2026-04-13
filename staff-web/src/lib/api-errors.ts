import type { RestaurantPosApiError } from '../api/sdk';
import { StaffApiError } from '../core/api/http';

type ApiErrorWithPayload<TPayload = unknown> = {
  status: number;
  payload: TPayload;
};

export type ApiErrorKind =
  | 'auth'
  | 'forbidden'
  | 'not-found'
  | 'conflict'
  | 'validation'
  | 'rate-limit'
  | 'server'
  | 'unknown';

export type ApiValidationErrors = Record<string, Array<string>>;

export type NormalizedApiError = {
  status: number | null;
  kind: ApiErrorKind;
  code: string | null;
  message: string;
  validation: ApiValidationErrors;
  requiredCapability: string | null;
  requestId: string | null;
  payload: unknown;
};

export function isRestaurantPosApiError(error: unknown): error is RestaurantPosApiError<unknown> | StaffApiError<unknown> {
  return error instanceof StaffApiError || hasApiErrorPayload(error);
}

export function normalizeApiError(error: unknown, fallback: string): NormalizedApiError {
  if (isRestaurantPosApiError(error)) {
    const validation = collectValidationErrors(error.payload);
    const payloadRecord = asRecord(error.payload);

    return {
      status: error.status,
      kind: statusToKind(error.status),
      code: readString(payloadRecord, 'error_code'),
      message: selectMessage(error.payload, validation, fallback),
      validation,
      requiredCapability: readString(payloadRecord, 'required_capability'),
      requestId: readString(payloadRecord, 'request_id'),
      payload: error.payload,
    };
  }

  if (error instanceof Error) {
    return {
      status: null,
      kind: 'unknown',
      code: null,
      message: error.message.trim() !== '' ? error.message : fallback,
      validation: {},
      requiredCapability: null,
      requestId: null,
      payload: error,
    };
  }

  return {
    status: null,
    kind: 'unknown',
    code: null,
    message: fallback,
    validation: {},
    requiredCapability: null,
    requestId: null,
    payload: error,
  };
}

export function formatApiError(error: unknown, fallback: string): string {
  return operatorMessage(normalizeApiError(error, fallback));
}

export function isApiStatus(error: unknown, status: number): boolean {
  return normalizeApiError(error, '').status === status;
}

export function hasApiErrorCode(error: unknown, code: string): boolean {
  return normalizeApiError(error, '').code === code;
}

function statusToKind(status: number): ApiErrorKind {
  switch (status) {
    case 401:
      return 'auth';
    case 403:
      return 'forbidden';
    case 404:
      return 'not-found';
    case 409:
      return 'conflict';
    case 422:
      return 'validation';
    case 429:
      return 'rate-limit';
    default:
      return status >= 500 ? 'server' : 'unknown';
  }
}

function collectValidationErrors(source: unknown): ApiValidationErrors {
  const record = asRecord(source);

  if (!record) {
    return {};
  }

  const output: ApiValidationErrors = {};

  mergeValidationErrors(output, asRecord(record.errors));
  mergeValidationErrors(output, asRecord(asRecord(record.details)?.errors));

  return output;
}

function mergeValidationErrors(target: ApiValidationErrors, source: Record<string, unknown> | null): void {
  if (!source) {
    return;
  }

  for (const [field, value] of Object.entries(source)) {
    if (Array.isArray(value)) {
      const nextValues = value.filter((item): item is string => typeof item === 'string' && item.trim() !== '');
      if (nextValues.length > 0) {
        target[field] = Array.from(new Set([...(target[field] ?? []), ...nextValues]));
      }
      continue;
    }

    if (typeof value === 'string' && value.trim() !== '') {
      target[field] = Array.from(new Set([...(target[field] ?? []), value]));
    }
  }
}

function firstMessage(source: unknown): string | null {
  if (typeof source === 'string' && source.trim() !== '') {
    return source;
  }

  if (Array.isArray(source)) {
    for (const item of source) {
      const message = firstMessage(item);
      if (message) {
        return message;
      }
    }

    return null;
  }

  if (!source || typeof source !== 'object') {
    return null;
  }

  const record = source as Record<string, unknown>;

  if (typeof record.message === 'string' && record.message.trim() !== '') {
    return record.message;
  }

  for (const value of Object.values(record)) {
    const message = firstMessage(value);
    if (message) {
      return message;
    }
  }

  return null;
}

function selectMessage(source: unknown, validation: ApiValidationErrors, fallback: string): string {
  const record = asRecord(source);
  const topLevelMessage = readString(record, 'message');

  if (topLevelMessage && !isGenericApiMessage(topLevelMessage)) {
    return topLevelMessage;
  }

  const validationMessage = firstValidationMessage(validation);
  if (validationMessage) {
    return validationMessage;
  }

  if (topLevelMessage) {
    return topLevelMessage;
  }

  return firstMessage(source) ?? fallback;
}

function firstValidationMessage(validation: ApiValidationErrors): string | null {
  for (const messages of Object.values(validation)) {
    const message = messages.find((value) => value.trim() !== '');
    if (message) {
      return message;
    }
  }

  return null;
}

function operatorMessage(error: NormalizedApiError): string {
  const parts = [error.message.trim() !== '' ? error.message.trim() : 'Yêu cầu không thành công.'];

  if (error.code === 'idempotency_key_required' && !parts[0].toLowerCase().includes('idempotency')) {
    parts.push('Thiếu khóa chống gửi lặp.');
  }

  if (error.requiredCapability && !parts.join(' ').includes(error.requiredCapability)) {
    parts.push(`Thiếu quyền: ${error.requiredCapability}.`);
  }

  if (error.requestId && !parts.join(' ').includes(error.requestId)) {
    parts.push(`Mã truy vết: ${error.requestId}.`);
  }

  return parts.join(' ').trim();
}

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value) ? (value as Record<string, unknown>) : null;
}

function readString(source: Record<string, unknown> | null, key: string): string | null {
  const value = source?.[key];
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function isGenericApiMessage(message: string): boolean {
  return [
    'validation error.',
    'unauthorized.',
    'forbidden.',
    'not found.',
    'conflict.',
    'state conflict detected.',
    'too many requests.',
    'server error.',
  ].includes(message.trim().toLowerCase());
}

function hasApiErrorPayload(error: unknown): error is ApiErrorWithPayload {
  if (!error || typeof error !== 'object') {
    return false;
  }

  const candidate = error as Partial<ApiErrorWithPayload>;
  return typeof candidate.status === 'number' && 'payload' in candidate;
}
