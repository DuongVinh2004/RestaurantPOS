import { translateUiCode, translateUiLabel } from './translation';

const localDateTimePattern = /^(\d{4})-(\d{2})-(\d{2})(?:[ T])(\d{2}):(\d{2})(?::(\d{2}))?$/;

export function formatMoney(value: string | number | null | undefined, currency = 'VND'): string {
  if (value === null || value === undefined || value === '') {
    return `0 ${currency}`;
  }

  const numeric = typeof value === 'number' ? value : Number(value);
  if (Number.isNaN(numeric)) {
    return `${value} ${currency}`;
  }

  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency,
    maximumFractionDigits: currency === 'VND' ? 0 : 2,
  }).format(numeric);
}

export function formatDateTime(value: string | null | undefined, timeZone?: string): string {
  if (!value) {
    return translateUiLabel('n/a');
  }

  const localMatch = value.match(localDateTimePattern);
  if (localMatch && !/[zZ]|[+-]\d{2}:\d{2}$/.test(value)) {
    const [, year, month, day, hour, minute] = localMatch;
    return `${hour}:${minute} ${day}/${month}/${year}`;
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    ...(timeZone ? { timeZone } : {}),
  }).format(parsed);
}

export function humanizeCode(value: string | null | undefined): string {
  if (!value) {
    return translateUiLabel('n/a');
  }

  return translateUiCode(value);
}

export function readNumber(value: string | number | null | undefined): number | null {
  if (typeof value === 'number') {
    return value;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isNaN(parsed) ? null : parsed;
  }

  return null;
}

export function formatRelativeAge(
  value: string | number | Date | null | undefined,
  options: {
    now?: number;
    short?: boolean;
    emptyLabel?: string;
  } = {},
): string {
  const timestamp = normalizeTimestamp(value);
  if (timestamp === null) {
    return options.emptyLabel ?? 'Chưa có mốc thời gian';
  }

  const ageSeconds = Math.max(0, Math.floor(((options.now ?? Date.now()) - timestamp) / 1000));
  if (ageSeconds < 60) {
    return options.short ? '<1 phút' : 'Mới cập nhật';
  }

  const minutes = Math.floor(ageSeconds / 60);
  if (minutes < 60) {
    return options.short ? `${minutes} phút` : `${minutes} phút trước`;
  }

  const hours = Math.floor(minutes / 60);
  if (hours < 24) {
    return options.short ? `${hours} giờ` : `${hours} giờ trước`;
  }

  const days = Math.floor(hours / 24);
  return options.short ? `${days} ngày` : `${days} ngày trước`;
}

export function formatFreshnessLabel(
  value: string | number | Date | null | undefined,
  options: {
    now?: number;
    emptyLabel?: string;
  } = {},
): string {
  const timestamp = normalizeTimestamp(value);
  if (timestamp === null) {
    return options.emptyLabel ?? 'Chưa đồng bộ';
  }

  return `Cập nhật ${formatRelativeAge(timestamp, { now: options.now })}`;
}

function normalizeTimestamp(value: string | number | Date | null | undefined): number | null {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value.getTime();
  }

  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : null;
  }

  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : parsed.getTime();
}
