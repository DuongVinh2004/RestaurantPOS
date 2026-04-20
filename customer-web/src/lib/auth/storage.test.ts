import { beforeEach, describe, expect, it } from "vitest";
import {
  clearStoredCustomerAuth,
  ensureCustomerSessionId,
  getCustomerSessionId,
  getCustomerToken,
  getStoredCustomerAuth,
  storeCustomerAuthSession,
  syncStoredCustomerAuthSession,
} from "./storage";

describe("customer auth storage", () => {
  beforeEach(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
  });

  it("creates and reuses a browser session id", () => {
    const first = ensureCustomerSessionId();
    const second = ensureCustomerSessionId();

    expect(first).toBeTruthy();
    expect(second).toBe(first);
    expect(getCustomerSessionId()).toBe(first);
  });

  it("stores customer token separately from browser session id", () => {
    storeCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-123",
        access_session_id: 1,
        session_id: "session-123",
        expires_at_utc: "2030-04-18T00:00:00Z",
        user: { user_id: 1 },
      },
    });

    expect(getCustomerToken()).toBe("token-123");
    expect(getCustomerSessionId()).toBe("session-123");

    clearStoredCustomerAuth();

    expect(getCustomerToken()).toBeNull();
    expect(getCustomerSessionId()).toBeNull();
  });

  it("removes a stale stored token when a later auth envelope omits access_token", () => {
    storeCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-123",
        access_session_id: 1,
        session_id: "session-123",
        expires_at_utc: "2030-04-18T00:00:00Z",
        user: { user_id: 1 },
      },
    });

    const stored = storeCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: null,
        access_session_id: 1,
        session_id: "session-456",
        expires_at_utc: null,
        user: { user_id: 1 },
      },
    });

    expect(getCustomerToken()).toBeNull();
    expect(getCustomerSessionId()).toBe("session-456");
    expect(stored).toEqual(getStoredCustomerAuth());
  });

  it("keeps the current token while syncing session metadata from bootstrap responses", () => {
    storeCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-bootstrap",
        access_session_id: 1,
        session_id: "session-bootstrap",
        expires_at_utc: "2030-04-18T00:00:00Z",
        user: { user_id: 1 },
      },
    });

    const stored = syncStoredCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: null,
        access_session_id: 2,
        session_id: "session-bootstrapped",
        expires_at_utc: "2031-04-18T00:00:00Z",
        user: { user_id: 1 },
      },
    });

    expect(getCustomerToken()).toBe("token-bootstrap");
    expect(getCustomerSessionId()).toBe("session-bootstrapped");
    expect(stored).toEqual({
      customerToken: "token-bootstrap",
      sessionId: "session-bootstrapped",
      expiresAtUtc: "2031-04-18T00:00:00Z",
    });
  });
});
