import { beforeEach, describe, expect, it, vi } from 'vitest';
import { registerStaffAuthFailureHandler } from '../auth/session-events';
import { STAFF_TOKEN_STORAGE_KEY, writeStoredStaffToken } from '../auth/storage';
import { buildStaffSession } from '../../test/fixtures';
import { getCurrentStaffSession, getStaffToken, loginStaff, staffClient } from './client';
import { RestaurantPosApiError } from './sdk';

describe('staff api client session persistence', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    localStorage.clear();
    writeStoredStaffToken(null);
    registerStaffAuthFailureHandler(null);
  });

  it('keeps the in-memory staff token when auth/me omits access_token', async () => {
    writeStoredStaffToken('memory-token');
    vi.spyOn(staffClient, 'getV1AuthStaffMe').mockResolvedValue({
      data: buildStaffSession({
        access_token: null,
      }),
    });

    await getCurrentStaffSession();

    expect(getStaffToken()).toBe('memory-token');
    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
  });

  it('replaces the in-memory token when login returns a fresh opaque key', async () => {
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'old-token');
    writeStoredStaffToken('old-token');
    vi.spyOn(staffClient, 'postV1AuthStaffLogin').mockResolvedValue({
      data: buildStaffSession({
        access_token: 'fresh-token',
      }),
    });

    await loginStaff('cashier-a', 'secret-123', 'staff-web');

    expect(getStaffToken()).toBe('fresh-token');
    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
  });

  it('notifies auth failure handler when generated client requests return 401 with a staff key', async () => {
    const handler = vi.fn();
    registerStaffAuthFailureHandler(handler);
    writeStoredStaffToken('memory-staff-key');

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ message: 'Unauthorized.' }), {
        status: 401,
        headers: {
          'content-type': 'application/json',
        },
      }),
    ));

    await expect(staffClient.getV1StaffTablesBoardChanges({ limit: 20 }))
      .rejects
      .toBeInstanceOf(RestaurantPosApiError);

    expect(handler).toHaveBeenCalledWith({
      status: 401,
      path: '/api/v1/staff/tables/board/changes?limit=20',
    });
    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
  });
});
