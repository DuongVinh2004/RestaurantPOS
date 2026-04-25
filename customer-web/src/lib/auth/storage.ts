import type { CustomerAuthSessionEnvelope } from "@/lib/contracts/generated/restaurantpos-sdk";

const tokenKey = "restaurantpos.customer.token.v1";
const tokenExpiresKey = "restaurantpos.customer.expires.v1";
const sessionIdKey = "restaurantpos.customer.session-id.v1";

export type StoredCustomerAuth = {
  customerToken: string | null;
  sessionId: string | null;
  expiresAtUtc: string | null;
};

function browserStorage(kind: "local" | "session"): Storage | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    return kind === "local" ? window.localStorage : window.sessionStorage;
  } catch {
    return null;
  }
}

function createSessionId(): string {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }

  return `cw-${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
}

export function ensureCustomerSessionId(): string {
  const storage = browserStorage("session");
  const existing = storage?.getItem(sessionIdKey);

  if (existing) {
    return existing;
  }

  const nextSessionId = createSessionId();
  storage?.setItem(sessionIdKey, nextSessionId);
  return nextSessionId;
}

export function getCustomerToken(): string | null {
  return browserStorage("local")?.getItem(tokenKey) ?? null;
}

export function getCustomerSessionId(): string | null {
  return browserStorage("session")?.getItem(sessionIdKey) ?? null;
}

export function getStoredCustomerAuth(): StoredCustomerAuth {
  return {
    customerToken: getCustomerToken(),
    sessionId: getCustomerSessionId(),
    expiresAtUtc: browserStorage("local")?.getItem(tokenExpiresKey) ?? null,
  };
}

export function storeCustomerAuthSession(envelope: CustomerAuthSessionEnvelope): StoredCustomerAuth {
  const token = envelope.data.access_token ?? null;
  const sessionId = envelope.data.session_id || ensureCustomerSessionId();
  const expiresAtUtc = envelope.data.expires_at_utc ?? null;

  const local = browserStorage("local");
  const session = browserStorage("session");

  if (token) {
    local?.setItem(tokenKey, token);
  } else {
    local?.removeItem(tokenKey);
  }

  if (expiresAtUtc) {
    local?.setItem(tokenExpiresKey, expiresAtUtc);
  } else {
    local?.removeItem(tokenExpiresKey);
  }

  session?.setItem(sessionIdKey, sessionId);

  return {
    customerToken: token,
    sessionId,
    expiresAtUtc,
  };
}

export function syncStoredCustomerAuthSession(envelope: CustomerAuthSessionEnvelope): StoredCustomerAuth {
  const local = browserStorage("local");
  const session = browserStorage("session");
  const currentToken = getCustomerToken();
  const nextToken = envelope.data.access_token ?? currentToken;
  const sessionId = envelope.data.session_id || getCustomerSessionId() || ensureCustomerSessionId();
  const expiresAtUtc = envelope.data.expires_at_utc ?? null;

  if (nextToken) {
    local?.setItem(tokenKey, nextToken);
  } else {
    local?.removeItem(tokenKey);
  }

  if (expiresAtUtc) {
    local?.setItem(tokenExpiresKey, expiresAtUtc);
  } else {
    local?.removeItem(tokenExpiresKey);
  }

  session?.setItem(sessionIdKey, sessionId);

  return {
    customerToken: nextToken,
    sessionId,
    expiresAtUtc,
  };
}

export function clearStoredCustomerAuth(): void {
  const local = browserStorage("local");
  const session = browserStorage("session");

  local?.removeItem(tokenKey);
  local?.removeItem(tokenExpiresKey);
  session?.removeItem(sessionIdKey);
}
