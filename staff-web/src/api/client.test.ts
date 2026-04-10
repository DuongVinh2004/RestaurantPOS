import { beforeEach, describe, expect, it, vi } from 'vitest';
import { STAFF_TOKEN_STORAGE_KEY } from '../core/auth/storage';
import { buildStaffSession } from '../test/fixtures';
import { getCurrentStaffSession, getStaffToken, loginStaff, staffClient } from './client';

describe('staff api client session persistence', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    localStorage.clear();
  });

  it('keeps the stored staff token when auth/me omits access_token', async () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'persisted-token');
    vi.spyOn(staffClient, 'getV1AuthStaffMe').mockResolvedValue({
      data: buildStaffSession({
        access_token: null,
      }),
    });

    await getCurrentStaffSession();

    expect(getStaffToken()).toBe('persisted-token');
  });

  it('replaces the stored token when login returns a fresh opaque key', async () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'old-token');
    vi.spyOn(staffClient, 'postV1AuthStaffLogin').mockResolvedValue({
      data: buildStaffSession({
        access_token: 'fresh-token',
      }),
    });

    await loginStaff('cashier-a', 'secret-123', 'staff-web');

    expect(getStaffToken()).toBe('fresh-token');
  });
});
