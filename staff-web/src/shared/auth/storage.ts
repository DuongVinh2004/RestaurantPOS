import type { StaffAuthSessionEnvelope } from '../api/sdk';

export const STAFF_TOKEN_STORAGE_KEY = 'restaurantpos.staff_web.staff_api_key';

export type StaffSession = StaffAuthSessionEnvelope['data'];

export function readStoredStaffToken(): string | null {
  if (typeof window === 'undefined') {
    return null;
  }

  return window.localStorage.getItem(STAFF_TOKEN_STORAGE_KEY);
}

export function writeStoredStaffToken(token: string | null): void {
  if (typeof window === 'undefined') {
    return;
  }

  if (!token || token.trim() === '') {
    window.localStorage.removeItem(STAFF_TOKEN_STORAGE_KEY);
    return;
  }

  window.localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, token);
}

export function persistStaffSessionToken(session: StaffSession): void {
  if (typeof session.access_token === 'string') {
    const normalizedToken = session.access_token.trim();

    writeStoredStaffToken(normalizedToken === '' ? null : normalizedToken);
    return;
  }

  if (!readStoredStaffToken()) {
    writeStoredStaffToken(null);
  }
}
