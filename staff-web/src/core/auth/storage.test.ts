import { beforeEach, describe, expect, it } from 'vitest';
import { persistStaffSessionToken, readStoredStaffToken, STAFF_TOKEN_STORAGE_KEY, type StaffSession } from './storage';

function makeSession(overrides: Partial<StaffSession> = {}): StaffSession {
  return {
    auth_mode: 'staff_api_key',
    token_type: 'opaque',
    auth_header: 'X-Staff-Key',
    access_token: 'fresh-token',
    staff_api_key_id: 1,
    expires_at_utc: '2026-04-10T09:00:00Z',
    user: {
      user_id: 42,
      username: 'foh.staff',
      full_name: 'Front Desk',
      email: 'foh@example.test',
      phone: '0909000111',
      role_id: 5,
      role_name: 'Staff',
    },
    capabilities: ['table.board.view'],
    known_capabilities: ['table.board.view'],
    capability_source: 'role',
    startup: {
      default_branch: null,
      active_cashier_shift: null,
      readiness: {
        access: 'ready',
        branch: 'ready',
        cashier_shift: 'not_applicable',
        operator_ready: true,
        requires_cashier_shift: false,
        granted_capability_count: 1,
        known_capability_count: 1,
      },
    },
    ...overrides,
  };
}

describe('persistStaffSessionToken', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('keeps the stored opaque token when auth/me omits access_token', () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'persisted-token');

    persistStaffSessionToken(makeSession({ access_token: null }));

    expect(readStoredStaffToken()).toBe('persisted-token');
  });

  it('replaces the stored token when the backend issues a fresh opaque token', () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'old-token');

    persistStaffSessionToken(makeSession({ access_token: 'fresh-token' }));

    expect(readStoredStaffToken()).toBe('fresh-token');
  });

  it('clears the stored token when the backend explicitly returns an empty access token', () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'old-token');

    persistStaffSessionToken(makeSession({ access_token: '   ' }));

    expect(readStoredStaffToken()).toBeNull();
  });
});
