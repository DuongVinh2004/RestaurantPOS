const IDEMPOTENCY_KEY_MAX_LENGTH = 64;
const SCOPE_SLUG_MAX_LENGTH = 20;
const SCOPE_HASH_LENGTH = 7;
const NONCE_LENGTH = 16;

export function createIdempotencyKey(scope: string): string {
  const normalizedScope = normalizeScope(scope);
  const scopeHash = hashScope(scope);
  const nonce = createNonce();
  const key = `sw:${normalizedScope}:${scopeHash}:${nonce}`;

  return key.slice(0, IDEMPOTENCY_KEY_MAX_LENGTH);
}

function normalizeScope(scope: string): string {
  const normalized = scope
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  return (normalized || 'req').slice(0, SCOPE_SLUG_MAX_LENGTH);
}

function hashScope(scope: string): string {
  let hash = 2166136261;

  for (const char of scope.trim().toLowerCase()) {
    hash ^= char.charCodeAt(0);
    hash = Math.imul(hash, 16777619);
  }

  return (hash >>> 0).toString(36).padStart(SCOPE_HASH_LENGTH, '0').slice(0, SCOPE_HASH_LENGTH);
}

function createNonce(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID().replace(/-/g, '').slice(0, NONCE_LENGTH);
  }

  return Date.now().toString(36);
}
