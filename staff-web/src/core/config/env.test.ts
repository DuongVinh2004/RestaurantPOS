import { describe, expect, it } from 'vitest';
import { resolveApiBaseUrl } from './env';

describe('resolveApiBaseUrl', () => {
  it('keeps an explicit API URL and trims trailing slashes', () => {
    expect(resolveApiBaseUrl('http://api.example.test/api/v1///', '127.0.0.1')).toBe('http://api.example.test/api/v1');
  });

  it('falls back to the current browser hostname for local preview parity', () => {
    expect(resolveApiBaseUrl(undefined, '127.0.0.1')).toBe('http://127.0.0.1:8000/api/v1');
  });

  it('falls back to localhost when no browser hostname is available', () => {
    expect(resolveApiBaseUrl(undefined, 'localhost')).toBe('http://localhost:8000/api/v1');
  });
});
