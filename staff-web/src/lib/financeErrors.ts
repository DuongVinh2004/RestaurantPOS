import {
  formatApiError as formatCoreApiError,
  normalizeApiError as normalizeCoreApiError,
  type NormalizedApiError,
} from '../core/api/errors';
import {
  formatApiError as formatLegacyApiError,
  normalizeApiError as normalizeLegacyApiError,
} from './api-errors';
import { humanizeCode } from './format';

const financeInvariantFields = [
  'cashier_shift',
  'refund_amount',
  'reservation',
  'payment_method',
  'payment_provider',
  'currency',
  'transaction_code',
  'actual_cash_amount',
  'paid_amount',
  'order',
] as const;

export function formatFinanceOperatorError(error: unknown, fallback: string): string {
  const normalized = normalizeFinanceApiError(error, fallback);

  if (normalized.status === 403) {
    return appendContext(
      normalized.requiredCapability
        ? `Mutation finance bi chan boi capability ${normalized.requiredCapability}.`
        : 'Mutation finance bi tu choi boi capability gate.',
      normalized.requestId,
    );
  }

  if (normalized.kind === 'validation') {
    const invariantField = financeInvariantFields.find((field) => (normalized.validation[field] ?? []).length > 0);

    if (invariantField) {
      return appendContext(
        `Finance invariant (${humanizeCode(invariantField)}) chan mutation nay: ${normalized.message}`,
        normalized.requestId,
      );
    }

    return appendContext(`Validation finance: ${normalized.message}`, normalized.requestId);
  }

  if (normalized.kind === 'conflict') {
    return appendContext(`Finance state conflict: ${normalized.message}`, normalized.requestId);
  }

  if (normalized.kind === 'server') {
    return appendContext(`Backend finance gap: ${normalized.message}`, normalized.requestId);
  }

  return formatFinanceFallback(error, fallback);
}

function appendContext(message: string, requestId: string | null): string {
  if (!requestId || message.includes(requestId)) {
    return message;
  }

  return `${message} Request ID: ${requestId}.`;
}

function normalizeFinanceApiError(error: unknown, fallback: string): NormalizedApiError {
  const normalized = normalizeCoreApiError(error, fallback);

  if (hasStructuredApiSignal(normalized)) {
    return normalized;
  }

  return normalizeLegacyApiError(error, fallback);
}

function formatFinanceFallback(error: unknown, fallback: string): string {
  const normalized = normalizeCoreApiError(error, fallback);

  if (hasStructuredApiSignal(normalized)) {
    return formatCoreApiError(error, fallback);
  }

  return formatLegacyApiError(error, fallback);
}

function hasStructuredApiSignal(error: NormalizedApiError): boolean {
  return (
    error.status !== null
    || error.code !== null
    || error.requiredCapability !== null
    || error.requestId !== null
    || Object.keys(error.validation).length > 0
  );
}
