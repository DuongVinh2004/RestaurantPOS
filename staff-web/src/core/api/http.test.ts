import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { STAFF_TOKEN_STORAGE_KEY } from '../auth/storage';
import { registerStaffAuthFailureHandler } from '../auth/session-events';
import { apiRequest, StaffApiError } from './http';

describe('apiRequest', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    registerStaffAuthFailureHandler(null);
    vi.restoreAllMocks();
  });

  it('notifies the auth failure handler when a protected request returns 401', async () => {
    const handler = vi.fn();
    registerStaffAuthFailureHandler(handler);
    localStorage.setItem(STAFF_TOKEN_STORAGE_KEY, 'staff-key');

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ message: 'Unauthorized' }), {
        status: 401,
        statusText: 'Unauthorized',
        headers: {
          'content-type': 'application/json',
        },
      }),
    ));

    await expect(apiRequest('/staff/tables/board')).rejects.toBeInstanceOf(StaffApiError);

    expect(handler).toHaveBeenCalledWith({
      status: 401,
      path: '/staff/tables/board',
    });
  });

  it('does not notify the auth failure handler for unauthenticated login failures', async () => {
    const handler = vi.fn();
    registerStaffAuthFailureHandler(handler);

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ message: 'Unauthorized' }), {
        status: 401,
        statusText: 'Unauthorized',
        headers: {
          'content-type': 'application/json',
        },
      }),
    ));

    await expect(apiRequest('/auth/staff/login', {
      method: 'POST',
      token: null,
      body: {
        identifier: 'staff@example.test',
        password: 'secret',
      },
    })).rejects.toBeInstanceOf(StaffApiError);

    expect(handler).not.toHaveBeenCalled();
  });
});
