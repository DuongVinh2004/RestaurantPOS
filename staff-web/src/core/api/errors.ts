import { RestaurantPosApiError } from './sdk';
import { StaffApiError } from './http';

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

export function isKnownApiError(error: unknown): error is RestaurantPosApiError<unknown> | StaffApiError<unknown> {
  return error instanceof RestaurantPosApiError || error instanceof StaffApiError;
}

export function normalizeApiError(error: unknown, fallback: string): NormalizedApiError {
  if (isKnownApiError(error)) {
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
  const normalized = normalizeApiError(error, fallback);
  const parts = [normalized.message.trim() !== '' ? normalized.message.trim() : fallback];

  if (normalized.requiredCapability) {
    parts.push(`Required capability: ${normalized.requiredCapability}.`);
  }

  if (normalized.requestId) {
    parts.push(`Request: ${normalized.requestId}.`);
  }

  return parts.join(' ').trim();
}

export function isApiStatus(error: unknown, status: number): boolean {
  return normalizeApiError(error, '').status === status;
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
  const output: ApiValidationErrors = {};
  const record = asRecord(source);

  mergeValidationErrors(output, asRecord(record?.errors));
  mergeValidationErrors(output, asRecord(asRecord(record?.details)?.errors));

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

function selectMessage(source: unknown, validation: ApiValidationErrors, fallback: string): string {
  const record = asRecord(source);
  const topLevelMessage = readString(record, 'message');

  if (topLevelMessage && !isGenericApiMessage(topLevelMessage)) {
    return topLevelMessage;
  }

  const validationMessage = Object.values(validation).flat().find((message) => message.trim() !== '');
  if (validationMessage) {
    return validationMessage;
  }

  if (topLevelMessage) {
    return topLevelMessage;
  }

  return firstMessage(source) ?? fallback;
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

  const record = asRecord(source);
  if (!record) {
    return null;
  }

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

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value) ? (value as Record<string, unknown>) : null;
}

function readString(source: Record<string, unknown> | null | undefined, key: string): string | null {
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
