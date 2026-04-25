import { beforeEach, describe, expect, it, vi } from 'vitest';
import { buildStaffSession } from '../../test/fixtures';
import { STAFF_TOKEN_STORAGE_KEY, readStoredStaffToken, writeStoredStaffToken } from '../auth/storage';
import { canAttemptStaffBrowserSessionRefresh, loginStaff, logoutStaff, refreshStaffSession } from './staff-auth-api';

describe('staff auth api token transport', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllEnvs();
    localStorage.clear();
    document.cookie = 'staff_web_csrf=; Max-Age=0; path=/';
    writeStoredStaffToken(null);
  });

  it('keeps the login staff key in memory without writing a reusable key to localStorage', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      jsonResponse({
        data: buildStaffSession({
          access_token: 'login-memory-token',
        }),
      }),
    ));

    const session = await loginStaff({
      identifier: 'cashier-a',
      password: 'secret-123',
      device_name: 'staff-web',
    });

    expect(session.access_token).toBe('login-memory-token');
    expect(readStoredStaffToken()).toBe('login-memory-token');
    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
  });

  it('rotates the in-memory key on refresh and clears it on logout', async () => {
    writeStoredStaffToken('old-memory-token');

    const fetchSpy = vi.fn()
      .mockResolvedValueOnce(jsonResponse({
        data: buildStaffSession({
          access_token: 'refreshed-memory-token',
        }),
      }))
      .mockResolvedValueOnce(jsonResponse({
        data: {
          auth_mode: 'staff_api_key',
          staff_api_key_id: 17,
          revoked_at_utc: '2026-04-07T10:30:00Z',
        },
      }));

    vi.stubGlobal('fetch', fetchSpy);

    await refreshStaffSession();

    expect(new Headers(fetchSpy.mock.calls[0]?.[1]?.headers).get('X-Staff-Key')).toBe('old-memory-token');
    expect(readStoredStaffToken()).toBe('refreshed-memory-token');
    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();

    await logoutStaff();

    expect(new Headers(fetchSpy.mock.calls[1]?.[1]?.headers).get('X-Staff-Key')).toBe('refreshed-memory-token');
    expect(readStoredStaffToken()).toBeNull();
    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
  });

  it('clears the in-memory key when logout fails server-side', async () => {
    writeStoredStaffToken('logout-token');
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      jsonResponse({ message: 'Server error.' }, 500),
    ));

    await expect(logoutStaff()).rejects.toThrow();

    expect(readStoredStaffToken()).toBeNull();
    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
  });

  it('opts staff auth endpoints into refresh-cookie credentials only when the rollout flag is enabled', async () => {
    vi.stubEnv('VITE_STAFF_REFRESH_COOKIE_ENABLED', 'true');
    document.cookie = 'staff_web_csrf=csrf-cookie-token; path=/';

    const fetchSpy = vi.fn()
      .mockResolvedValueOnce(jsonResponse({
        data: buildStaffSession({
          access_token: 'login-access-token',
        }),
      }))
      .mockResolvedValueOnce(jsonResponse({
        data: buildStaffSession({
          access_token: 'refresh-access-token',
        }),
      }))
      .mockResolvedValueOnce(jsonResponse({
        data: {
          auth_mode: 'staff_browser_session',
          session_transport: 'refresh_cookie',
          staff_api_key_id: 17,
          revoked_at_utc: '2026-04-07T10:30:00Z',
        },
      }));

    vi.stubGlobal('fetch', fetchSpy);

    await loginStaff({
      identifier: 'cashier-a',
      password: 'secret-123',
      device_name: 'staff-web',
    });

    const loginInit = fetchSpy.mock.calls[0]?.[1] as RequestInit;
    expect(loginInit.credentials).toBe('include');
    expect(JSON.parse(String(loginInit.body))).toMatchObject({
      identifier: 'cashier-a',
      session_transport: 'refresh_cookie',
    });

    writeStoredStaffToken(null);
    expect(canAttemptStaffBrowserSessionRefresh()).toBe(true);

    await refreshStaffSession();

    const refreshInit = fetchSpy.mock.calls[1]?.[1] as RequestInit;
    expect(refreshInit.credentials).toBe('include');
    expect(new Headers(refreshInit.headers).get('X-Staff-CSRF')).toBe('csrf-cookie-token');
    expect(readStoredStaffToken()).toBe('refresh-access-token');

    await logoutStaff();

    const logoutInit = fetchSpy.mock.calls[2]?.[1] as RequestInit;
    expect(logoutInit.credentials).toBe('include');
    expect(new Headers(logoutInit.headers).get('X-Staff-CSRF')).toBe('csrf-cookie-token');
    expect(readStoredStaffToken()).toBeNull();
  });
});

function jsonResponse(payload: unknown, status = 200): Response {
  return new Response(JSON.stringify(payload), {
    status,
    headers: {
      'content-type': 'application/json',
    },
  });
}
