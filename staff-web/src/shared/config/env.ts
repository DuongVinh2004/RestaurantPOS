const rawApiUrl = import.meta.env.VITE_API_URL as string | undefined;
const rawAppTitle = (import.meta.env.VITE_APP_TITLE as string | undefined) ?? 'RestaurantPOS Staff Web';

export const apiBaseUrl = resolveApiBaseUrl(rawApiUrl);
export const appTitle = rawAppTitle.trim() === '' ? 'RestaurantPOS Staff Web' : rawAppTitle.trim();

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
