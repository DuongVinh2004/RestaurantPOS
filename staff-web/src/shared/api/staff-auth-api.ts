import type { RequestOptions, StaffLoginRequest as LoginStaffAuthRequest } from './sdk';
import { persistStaffSessionToken, readStoredStaffToken, writeStoredStaffToken, type StaffSession } from '../auth/storage';
import { staffRefreshCookieEnabled, staffRefreshCsrfCookieName } from '../config/env';
import { staffClient } from './client';

export async function loginStaff(
  payload: Pick<LoginStaffAuthRequest, 'identifier' | 'password' | 'device_name'>,
): Promise<StaffSession> {
  const refreshCookieEnabled = staffRefreshCookieEnabled();
  const envelope = await staffClient.postV1AuthStaffLogin(
    refreshCookieEnabled ? { ...payload, session_transport: 'refresh_cookie' } : payload,
    refreshCookieEnabled ? { credentials: 'include' } : {},
  );

  persistStaffSessionToken(envelope.data);
  return envelope.data;
}

export async function getCurrentStaffSession(): Promise<StaffSession> {
  const envelope = await staffClient.getV1AuthStaffMe();
  persistStaffSessionToken(envelope.data);
  return envelope.data;
}

export async function refreshStaffSession(): Promise<StaffSession> {
  const envelope = await staffClient.postV1AuthStaffRefresh(staffRefreshCookieRequestOptions());
  persistStaffSessionToken(envelope.data);
  return envelope.data;
}

export async function logoutStaff(): Promise<void> {
  try {
    if (readStoredStaffToken() || canAttemptStaffBrowserSessionRefresh()) {
      await staffClient.postV1AuthStaffLogout(staffRefreshCookieRequestOptions());
    }
  } finally {
    writeStoredStaffToken(null);
  }
}

export function canAttemptStaffBrowserSessionRefresh(): boolean {
  return staffRefreshCookieEnabled() && readStaffCsrfToken() !== null;
}

function staffRefreshCookieRequestOptions(): RequestOptions {
  if (!staffRefreshCookieEnabled()) {
    return {};
  }

  const csrfToken = readStaffCsrfToken();

  return {
    credentials: 'include',
    staffCsrfToken: csrfToken ?? undefined,
  };
}

function readStaffCsrfToken(): string | null {
  if (typeof document === 'undefined') {
    return null;
  }

  const cookieName = `${staffRefreshCsrfCookieName()}=`;
  const match = document.cookie
    .split(';')
    .map((part) => part.trim())
    .find((part) => part.startsWith(cookieName));

  if (!match) {
    return null;
  }

  const value = match.slice(cookieName.length).trim();

  return value === '' ? null : decodeURIComponent(value);
}
