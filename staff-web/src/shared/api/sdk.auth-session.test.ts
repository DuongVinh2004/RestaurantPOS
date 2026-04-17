import { afterEach, describe, expect, it, vi } from 'vitest';
import { RestaurantPosClient } from './sdk';

describe('RestaurantPosClient customer session propagation', () => {
  const originalFetch = globalThis.fetch;

  afterEach(() => {
    vi.restoreAllMocks();
    Object.defineProperty(globalThis, 'fetch', {
      value: originalFetch,
      configurable: true,
      writable: true,
    });
  });

  it('sends both customer token and session id on customer-or-session routes', async () => {
    const fetchSpy = vi.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
      const headers = new Headers(init?.headers);

      expect(headers.get('X-Customer-Token')).toBe('cust-token');
      expect(headers.get('X-Session-Id')).toBe('sess-123');
      expect(headers.get('X-Staff-Key')).toBeNull();

      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify({ data: { reservation_id: 1 } }),
      } as Response;
    });

    Object.defineProperty(globalThis, 'fetch', {
      value: fetchSpy,
      configurable: true,
      writable: true,
    });

    const client = new RestaurantPosClient({
      baseUrl: 'http://example.test',
      customerToken: 'cust-token',
      customerSessionId: 'sess-123',
      staffApiKey: 'staff-token',
    });

    await client.getV1ReservationsIdPreorder({ id: 1 });

    expect(fetchSpy).toHaveBeenCalledOnce();
  });

  it('keeps session correlation on customer-or-staff routes when customer auth wins', async () => {
    const fetchSpy = vi.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
      const headers = new Headers(init?.headers);

      expect(headers.get('X-Customer-Token')).toBe('cust-token');
      expect(headers.get('X-Session-Id')).toBe('sess-456');
      expect(headers.get('X-Staff-Key')).toBeNull();

      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify({ data: { reservation_id: 2 } }),
      } as Response;
    });

    Object.defineProperty(globalThis, 'fetch', {
      value: fetchSpy,
      configurable: true,
      writable: true,
    });

    const client = new RestaurantPosClient({
      baseUrl: 'http://example.test',
      customerToken: 'cust-token',
      customerSessionId: 'sess-456',
      staffApiKey: 'staff-token',
    });

    await client.getV1ReservationsId({ id: 2 });

    expect(fetchSpy).toHaveBeenCalledOnce();
  });

  it('does not leak session id onto customer-token-only routes', async () => {
    const fetchSpy = vi.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
      const headers = new Headers(init?.headers);

      expect(headers.get('X-Customer-Token')).toBe('cust-token');
      expect(headers.get('X-Session-Id')).toBeNull();

      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify({ data: { user: null, transactions: [] } }),
      } as Response;
    });

    Object.defineProperty(globalThis, 'fetch', {
      value: fetchSpy,
      configurable: true,
      writable: true,
    });

    const client = new RestaurantPosClient({
      baseUrl: 'http://example.test',
      customerToken: 'cust-token',
      customerSessionId: 'sess-789',
    });

    await client.getV1MeLoyalty({ limit: 1 });

    expect(fetchSpy).toHaveBeenCalledOnce();
  });
});
