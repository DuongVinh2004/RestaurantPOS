import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { STAFF_TOKEN_STORAGE_KEY, writeStoredStaffToken } from '../auth/storage';
import { registerStaffAuthFailureHandler } from '../auth/session-events';
import { resetRepairedPayloadWarnings } from '../utils/text-encoding';
import { apiRequest, StaffApiError } from './http';

describe('apiRequest', () => {
  beforeEach(() => {
    localStorage.clear();
    writeStoredStaffToken(null);
  });

  afterEach(() => {
    registerStaffAuthFailureHandler(null);
    resetRepairedPayloadWarnings();
    vi.restoreAllMocks();
  });

  it('notifies the auth failure handler when a protected request returns 401', async () => {
    const handler = vi.fn();
    registerStaffAuthFailureHandler(handler);
    writeStoredStaffToken('staff-key');

    const fetchSpy = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ message: 'Unauthorized' }), {
        status: 401,
        statusText: 'Unauthorized',
        headers: {
          'content-type': 'application/json',
        },
      }),
    );

    vi.stubGlobal('fetch', fetchSpy);

    await expect(apiRequest('/staff/tables/board')).rejects.toBeInstanceOf(StaffApiError);

    expect(fetchSpy).toHaveBeenCalledTimes(1);
    expect(handler).toHaveBeenCalledWith({
      status: 401,
      path: '/staff/tables/board',
    });
    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
  });

  it('does not notify the auth failure handler for unauthenticated login failures', async () => {
    const handler = vi.fn();
    registerStaffAuthFailureHandler(handler);
    writeStoredStaffToken('stale-staff-key');

    const fetchSpy = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ message: 'Unauthorized' }), {
        status: 401,
        statusText: 'Unauthorized',
        headers: {
          'content-type': 'application/json',
        },
      }),
    );

    vi.stubGlobal('fetch', fetchSpy);

    await expect(apiRequest('/auth/staff/login', {
      method: 'POST',
      token: null,
      body: {
        identifier: 'staff@example.test',
        password: 'secret',
      },
    })).rejects.toBeInstanceOf(StaffApiError);

    expect(handler).not.toHaveBeenCalled();
    expect(new Headers(fetchSpy.mock.calls[0]?.[1]?.headers).get('X-Staff-Key')).toBeNull();
  });

  it('does not notify the auth failure handler when a protected request returns 403', async () => {
    const handler = vi.fn();
    registerStaffAuthFailureHandler(handler);
    writeStoredStaffToken('staff-key');

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ message: 'Forbidden' }), {
        status: 403,
        statusText: 'Forbidden',
        headers: {
          'content-type': 'application/json',
        },
      }),
    ));

    await expect(apiRequest('/staff/checkout')).rejects.toBeInstanceOf(StaffApiError);

    expect(handler).not.toHaveBeenCalled();
  });

  it('sends the staff key from memory without persisting it in localStorage', async () => {
    writeStoredStaffToken('memory-staff-key');
    const fetchSpy = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: [] }), {
        status: 200,
        headers: {
          'content-type': 'application/json',
        },
      }),
    );

    vi.stubGlobal('fetch', fetchSpy);

    await apiRequest('/staff/tables/board');

    expect(new Headers(fetchSpy.mock.calls[0]?.[1]?.headers).get('X-Staff-Key')).toBe('memory-staff-key');
    expect(localStorage.getItem(STAFF_TOKEN_STORAGE_KEY)).toBeNull();
  });

  it('repairs mojibake string values from json payloads and warns once in test mode', async () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      new Response(JSON.stringify({
        data: {
          title: encodeMojibake('Không có'),
          description: encodeMojibake('Sơ đồ bàn'),
          loading: encodeMojibake('Đang tải…'),
        },
      }), {
        status: 200,
        headers: {
          'content-type': 'application/json',
        },
      }),
    ));

    const payload = await apiRequest<{ data: { title: string; description: string; loading: string } }>('/staff/tables/board');

    expect(payload).toEqual({
      data: {
        title: 'Không có',
        description: 'Sơ đồ bàn',
        loading: 'Đang tải…',
      },
    });
    expect(warn).toHaveBeenCalledTimes(1);
    expect(warn).toHaveBeenCalledWith(
      expect.stringContaining('in response payload for /staff/tables/board'),
    );
  });
});

const WINDOWS_1252_CODEPOINTS = new Map<number, number>([
  [0x80, 0x20AC],
  [0x82, 0x201A],
  [0x83, 0x0192],
  [0x84, 0x201E],
  [0x85, 0x2026],
  [0x86, 0x2020],
  [0x87, 0x2021],
  [0x88, 0x02C6],
  [0x89, 0x2030],
  [0x8A, 0x0160],
  [0x8B, 0x2039],
  [0x8C, 0x0152],
  [0x8E, 0x017D],
  [0x91, 0x2018],
  [0x92, 0x2019],
  [0x93, 0x201C],
  [0x94, 0x201D],
  [0x95, 0x2022],
  [0x96, 0x2013],
  [0x97, 0x2014],
  [0x98, 0x02DC],
  [0x99, 0x2122],
  [0x9A, 0x0161],
  [0x9B, 0x203A],
  [0x9C, 0x0153],
  [0x9E, 0x017E],
  [0x9F, 0x0178],
]);

function encodeMojibake(value: string): string {
  return Array.from(new TextEncoder().encode(value), (byte) =>
    String.fromCodePoint(WINDOWS_1252_CODEPOINTS.get(byte) ?? byte),
  ).join('');
}
