import type { StaffAuthSessionEnvelope } from '../api/sdk';

export const STAFF_TOKEN_STORAGE_KEY = 'restaurantpos.staff_web.staff_api_key';

export type StaffSession = StaffAuthSessionEnvelope['data'];

let inMemoryStaffToken: string | null = null;

export function readStoredStaffToken(): string | null {
  clearLegacyPersistentStaffToken();
  return inMemoryStaffToken;
}

export function writeStoredStaffToken(token: string | null): void {
  clearLegacyPersistentStaffToken();

  if (!token || token.trim() === '') {
    inMemoryStaffToken = null;
    return;
  }

  inMemoryStaffToken = token.trim();
}

export function persistStaffSessionToken(session: StaffSession): void {
  clearLegacyPersistentStaffToken();

  if (typeof session.access_token === 'string') {
    const normalizedToken = session.access_token.trim();

    writeStoredStaffToken(normalizedToken === '' ? null : normalizedToken);
    return;
  }

  if (!readStoredStaffToken()) {
    writeStoredStaffToken(null);
  }
}

function clearLegacyPersistentStaffToken(): void {
  if (typeof window === 'undefined') {
    return;
  }

  try {
    window.localStorage.removeItem(STAFF_TOKEN_STORAGE_KEY);
  } catch {
    // Ignore blocked storage access; the active staff token is kept in memory only.
  }

  try {
    window.sessionStorage.removeItem(STAFF_TOKEN_STORAGE_KEY);
  } catch {
    // Ignore blocked storage access; the active staff token is kept in memory only.
  }
}
