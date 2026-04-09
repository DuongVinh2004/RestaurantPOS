export function createIdempotencyKey(scope: string): string {
  const prefix = `staff-web:${scope}`;

  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return `${prefix}:${crypto.randomUUID()}`;
  }

  return `${prefix}:${Date.now()}`;
}
