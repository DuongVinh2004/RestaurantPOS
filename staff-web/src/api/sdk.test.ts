import { afterEach, describe, expect, it, vi } from 'vitest';
import { RestaurantPosClient } from './sdk';

describe('RestaurantPosClient fetch binding', () => {
  const originalFetch = globalThis.fetch;

  afterEach(() => {
    vi.restoreAllMocks();
    Object.defineProperty(globalThis, 'fetch', {
      value: originalFetch,
      configurable: true,
      writable: true,
    });
  });

  it('binds the default global fetch to globalThis before making requests', async () => {
    const fetchSpy = vi.fn(async function (this: typeof globalThis, input: RequestInfo | URL, init?: RequestInit) {
      expect(this).toBe(globalThis);
      expect(String(input)).toBe('http://example.test/api/v1/health');
      expect(init?.method).toBe('GET');

      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify({ status: 'ok' }),
      } as Response;
    });

    Object.defineProperty(globalThis, 'fetch', {
      value: fetchSpy,
      configurable: true,
      writable: true,
    });

    const client = new RestaurantPosClient({
      baseUrl: 'http://example.test',
    });

    await expect(client.health()).resolves.toEqual({ status: 'ok' });
    expect(fetchSpy).toHaveBeenCalledOnce();
  });
});
