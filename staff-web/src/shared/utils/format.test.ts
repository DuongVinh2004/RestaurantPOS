import { describe, expect, it } from 'vitest';
import { formatDateTime } from './format';

describe('formatDateTime', () => {
  it('formats zoned timestamps with vi-VN 24-hour output', () => {
    expect(formatDateTime('2026-12-31T11:00:00Z', 'Asia/Ho_Chi_Minh')).toBe('18:00 31/12/2026');
  });

  it('formats branch-local date time strings without shifting the clock time', () => {
    expect(formatDateTime('2026-12-31 18:00:00', 'Asia/Ho_Chi_Minh')).toBe('18:00 31/12/2026');
  });

  it('returns the shared empty label for empty values', () => {
    expect(formatDateTime(null, 'Asia/Ho_Chi_Minh')).toBe('Không có');
  });
});
