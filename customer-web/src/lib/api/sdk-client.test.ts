import { beforeEach, describe, expect, it } from "vitest";
import { storeCustomerAuthSession } from "@/lib/auth/storage";
import { createApiClient, idempotentSessionOptions } from "./sdk-client";

describe("RestaurantPOS SDK client wrapper", () => {
  beforeEach(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
  });

  it("injects session id and idempotency key for session-bound mutations", async () => {
    storeCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-abc",
        access_session_id: 10,
        session_id: "session-abc",
        expires_at_utc: null,
        user: { user_id: 10 },
      },
    });

    let capturedHeaders: Headers | undefined;
    const client = createApiClient({
      fetchImpl: async (_input, init) => {
        capturedHeaders = new Headers(init?.headers);

        return new Response(
          JSON.stringify({
            data: {
              hold_id: "hold-1",
              session_hash: null,
              start_time: "2026-04-18T10:00:00Z",
              end_time: "2026-04-18T11:30:00Z",
              duration_minutes: 90,
              hold_status: "Holding",
              confirmed_reservation_id: null,
              row_version: 1,
              tables: [],
            },
          }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        );
      },
    });

    await client.postV1TableHolds(
      {
        session_id: "session-abc",
        start_time: "2026-04-18T10:00:00Z",
        end_time: "2026-04-18T11:30:00Z",
        table_ids: [1],
      },
      idempotentSessionOptions("test", { idempotencyKey: "idem-1" }),
    );

    expect(capturedHeaders).toBeDefined();
    const headers = capturedHeaders as Headers;
    expect(headers.get("X-Session-Id")).toBe("session-abc");
    expect(headers.get("Idempotency-Key")).toBe("idem-1");
  });

  it("injects X-Customer-Token for customer-authenticated reads", async () => {
    storeCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-read",
        access_session_id: 11,
        session_id: "session-read",
        expires_at_utc: null,
        user: { user_id: 11 },
      },
    });

    let capturedHeaders: Headers | undefined;
    const client = createApiClient({
      fetchImpl: async (_input, init) => {
        capturedHeaders = new Headers(init?.headers);

        return new Response(
          JSON.stringify({
            data: {
              auth_mode: "customer_access_session",
              token_type: "opaque",
              auth_header: "X-Customer-Token",
              access_token: "token-read",
              access_session_id: 11,
              session_id: "session-read",
              user: { user_id: 11 },
            },
          }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        );
      },
    });

    await client.getV1AuthCustomerMe();

    expect(capturedHeaders).toBeDefined();
    expect((capturedHeaders as Headers).get("X-Customer-Token")).toBe("token-read");
  });
});
