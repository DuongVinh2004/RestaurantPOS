import { beforeEach, describe, expect, it } from "vitest";
import {
  clearStoredCustomerAuth,
  ensureCustomerSessionId,
  getCustomerSessionId,
  getCustomerToken,
  storeCustomerAuthSession,
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
        expires_at_utc: "2026-04-18T00:00:00Z",
        user: { user_id: 1 },
      },
    });

    expect(getCustomerToken()).toBe("token-123");
    expect(getCustomerSessionId()).toBe("session-123");

    clearStoredCustomerAuth();

    expect(getCustomerToken()).toBeNull();
    expect(getCustomerSessionId()).toBeNull();
  });
});
