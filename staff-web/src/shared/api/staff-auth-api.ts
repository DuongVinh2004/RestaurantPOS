import type { StaffLoginRequest as LoginStaffAuthRequest, StaffAuthSessionEnvelope } from './sdk';
import { persistStaffSessionToken, readStoredStaffToken, writeStoredStaffToken, type StaffSession } from '../auth/storage';
import { apiRequest } from './http';

export async function loginStaff(
  payload: Pick<LoginStaffAuthRequest, 'identifier' | 'password' | 'device_name'>,
): Promise<StaffSession> {
  const envelope = await apiRequest<StaffAuthSessionEnvelope>('/auth/staff/login', {
    method: 'POST',
    body: payload,
    token: null,
  });

  persistStaffSessionToken(envelope.data);
  return envelope.data;
}

export async function getCurrentStaffSession(): Promise<StaffSession> {
  const envelope = await apiRequest<StaffAuthSessionEnvelope>('/auth/staff/me');
  persistStaffSessionToken(envelope.data);
  return envelope.data;
}

export async function refreshStaffSession(): Promise<StaffSession> {
  const envelope = await apiRequest<StaffAuthSessionEnvelope>('/auth/staff/refresh', { method: 'POST' });
  persistStaffSessionToken(envelope.data);
  return envelope.data;
}

export async function logoutStaff(): Promise<void> {
  try {
    if (readStoredStaffToken()) {
      await apiRequest('/auth/staff/logout', { method: 'POST' });
    }
  } finally {
    writeStoredStaffToken(null);
  }
}
