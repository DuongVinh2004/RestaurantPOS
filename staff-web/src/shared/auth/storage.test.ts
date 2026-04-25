import { beforeEach, describe, expect, it } from 'vitest';
import { buildStaffStartupContext, type StaffStartupOverrides } from '../../test/fixtures';
import {
  persistStaffSessionToken,
  readStoredStaffToken,
  STAFF_TOKEN_STORAGE_KEY,
  writeStoredStaffToken,
  type StaffSession,
} from './storage';

type StaffSessionOverrides = Omit<Partial<StaffSession>, 'startup'> & {
  startup?: StaffStartupOverrides;
};

function makeSession(overrides: StaffSessionOverrides = {}): StaffSession {
  const { startup: startupOverrides, ...sessionOverrides } = overrides;

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
    capability_source: 'role_capabilities',
    startup: buildStaffStartupContext({
      default_branch: null,
      active_cashier_shift: null,
      ...startupOverrides,
      readiness: {
        access: 'ready',
        branch: 'ready',
        cashier_shift: 'not_applicable',
        operator_ready: true,
        requires_cashier_shift: false,
        granted_capability_count: 1,
        known_capability_count: 1,
        ...startupOverrides?.readiness,
      },
    }),
    ...sessionOverrides,
  };
}

describe('persistStaffSessionToken', () => {
  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    writeStoredStaffToken(null);
  });

  it('keeps the in-memory opaque token when auth/me omits access_token', () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'persisted-token');
    writeStoredStaffToken('memory-token');

    persistStaffSessionToken(makeSession({ access_token: null }));

    expect(readStoredStaffToken()).toBe('memory-token');
    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
  });

  it('keeps fresh opaque tokens in memory without persisting them to localStorage', () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'old-token');
    sessionStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'old-session-token');
    writeStoredStaffToken('old-token');

    persistStaffSessionToken(makeSession({ access_token: 'fresh-token' }));

    expect(readStoredStaffToken()).toBe('fresh-token');
    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
    expect(sessionStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
  });

  it('clears the in-memory token and legacy localStorage when the backend explicitly returns an empty access token', () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'old-token');
    writeStoredStaffToken('old-token');

    persistStaffSessionToken(makeSession({ access_token: '   ' }));

    expect(readStoredStaffToken()).toBeNull();
    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
  });

  it('does not restore legacy persistent staff keys after reload', () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'legacy-token');
    sessionStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'legacy-session-token');

    expect(readStoredStaffToken()).toBeNull();
    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
    expect(sessionStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
  });
});
