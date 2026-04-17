import { normalizeApiError } from './errors';

export function isRowVersionConflict(error: unknown): boolean {
  const normalized = normalizeApiError(error, '');
  const rowVersionMessages = normalized.validation.row_version ?? [];
  const signals = [normalized.message, ...rowVersionMessages];

  if (normalized.code === 'stale_row_version') {
    return true;
  }

  if (normalized.status === 409) {
    return rowVersionMessages.length > 0 || signals.some(hasStaleSignal);
  }

  if (normalized.status === 422) {
    return signals.some(hasStaleSignal);
  }

  return false;
}

export function rowVersionConflictMessage(resourceLabel: string): string {
  return `${resourceLabel} vừa thay đổi. Hãy tải lại dữ liệu rồi thử lại.`;
}

function hasStaleSignal(value: string): boolean {
  const normalized = value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

  return (
    normalized.includes('stale row_version') ||
    normalized.includes('stale row version') ||
    normalized.includes('row_version mismatch') ||
    normalized.includes('row version mismatch') ||
    normalized.includes('modified by another writer') ||
    normalized.includes('du lieu da thay doi')
  );
}
