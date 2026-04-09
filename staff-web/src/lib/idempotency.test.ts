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

    expect(createIdempotencyKey('settlement')).toBe('staff-web:settlement:uuid-123');
  });

  it('falls back to a timestamp when crypto is unavailable', () => {
    vi.stubGlobal('crypto', undefined);
    vi.spyOn(Date, 'now').mockReturnValue(123456789);

    expect(createIdempotencyKey('refund')).toBe('staff-web:refund:123456789');
  });
});
