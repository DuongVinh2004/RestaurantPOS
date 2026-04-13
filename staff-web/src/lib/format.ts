const localDateTimePattern = /^(\d{4})-(\d{2})-(\d{2})(?:[ T])(\d{2}):(\d{2})(?::(\d{2}))?$/;

function buildDateTimeFormatter(timeZone?: string) {
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    ...(timeZone ? { timeZone } : {}),
  });
}

export function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value) ? (value as Record<string, unknown>) : null;
}

export function readString(source: unknown, key: string): string | null {
  const value = asRecord(source)?.[key];
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

export function readNumber(source: unknown, key: string): number | null {
  const value = asRecord(source)?.[key];

  if (typeof value === 'number') {
    return value;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isNaN(parsed) ? null : parsed;
  }

  return null;
}

export function readBoolean(source: unknown, key: string): boolean | null {
  const value = asRecord(source)?.[key];
  return typeof value === 'boolean' ? value : null;
}

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
    return 'N/A';
  }

  const localMatch = value.match(localDateTimePattern);
  if (localMatch && !/[zZ]|[+-]\d{2}:\d{2}$/.test(value)) {
    const [, year, month, day, hour, minute] = localMatch;
    return `${hour}:${minute} ${day}/${month}/${year}`;
  }

  const parsed = new Date(value);

  return Number.isNaN(parsed.getTime()) ? value : buildDateTimeFormatter(timeZone).format(parsed);
}

export function humanizeCode(value: string | null | undefined): string {
  if (!value) {
    return 'N/A';
  }

  return value
    .replace(/_/g, ' ')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/\b\w/g, (char) => char.toUpperCase());
}
