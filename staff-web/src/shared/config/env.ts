const rawApiUrl = import.meta.env.VITE_API_URL as string | undefined;
const rawAppTitle = (import.meta.env.VITE_APP_TITLE as string | undefined) ?? 'Mộc Sen Staff Web';

export const apiBaseUrl = resolveApiBaseUrl(rawApiUrl);
export const appTitle = rawAppTitle.trim() === '' ? 'Mộc Sen Staff Web' : rawAppTitle.trim();

export function staffRefreshCookieEnabled(): boolean {
  return parseBooleanFlag(import.meta.env.VITE_STAFF_REFRESH_COOKIE_ENABLED as string | undefined);
}

export function staffRefreshCsrfCookieName(): string {
  const value = (import.meta.env.VITE_STAFF_REFRESH_CSRF_COOKIE as string | undefined)?.trim();

  return value && value !== '' ? value : 'staff_web_csrf';
}

export function resolveApiBaseUrl(value?: string, browserHostname = resolveBrowserHostname()): string {
  const candidate = value?.trim();
  const resolved = candidate && candidate !== ''
    ? candidate
    : `http://${browserHostname}:8000/api/v1`;

  return normalizeApiUrl(resolved);
}

function resolveBrowserHostname(): string {
  if (typeof window !== 'undefined' && window.location?.hostname) {
    return window.location.hostname;
  }

  return 'localhost';
}

function normalizeApiUrl(value: string): string {
  return value.trim().replace(/\/+$/, '');
}

function parseBooleanFlag(value?: string): boolean {
  return ['1', 'true', 'yes', 'on'].includes((value ?? '').trim().toLowerCase());
}
