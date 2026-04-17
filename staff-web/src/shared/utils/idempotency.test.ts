import { afterEach, describe, expect, it, vi } from 'vitest';
import { createIdempotencyKey } from './idempotency';

describe('idempotency', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('uses crypto.randomUUID when available', () => {
    vi.stubGlobal('crypto', {
      randomUUID: () => 'uuid-123',
    });

    const key = createIdempotencyKey('settlement');

    expect(key).toMatch(/^sw:settlement:[a-z0-9]{7}:uuid123$/);
    expect(key.length).toBeLessThanOrEqual(64);
  });

  it('falls back to a timestamp when crypto is unavailable', () => {
    vi.stubGlobal('crypto', undefined);
    vi.spyOn(Date, 'now').mockReturnValue(123456789);

    const key = createIdempotencyKey('refund');

    expect(key).toMatch(/^sw:refund:[a-z0-9]{7}:21i3v9$/);
    expect(key.length).toBeLessThanOrEqual(64);
  });

  it('keeps long scopes within the payment column limit', () => {
    vi.stubGlobal('crypto', {
      randomUUID: () => '12345678-1234-1234-1234-1234567890ab',
    });

    const key = createIdempotencyKey('reservation-assign-best-fit-1234567890-extra-context');

    expect(key.length).toBeLessThanOrEqual(64);
    expect(key).toMatch(/^sw:reservation-assign-b:[a-z0-9]{7}:[a-z0-9]{16}$/);
  });
});
