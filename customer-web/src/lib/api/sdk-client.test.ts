import { beforeEach, describe, expect, it } from "vitest";
import { storeCustomerAuthSession } from "@/lib/auth/storage";
import { normalizeApiError } from "./errors";
import { checkBackendHealth, createApiClient, idempotentOptions, idempotentSessionOptions } from "./sdk-client";

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

  it("uses X-Session-Id without customer token when no token is stored", async () => {
    let capturedHeaders: Headers | undefined;
    let capturedInit: RequestInit | undefined;
    const client = createApiClient({
      fetchImpl: async (_input, init) => {
        capturedHeaders = new Headers(init?.headers);
        capturedInit = init;

        return new Response(
          JSON.stringify({
            data: {
              hold_id: "hold-session-only",
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
    const options = idempotentSessionOptions("test", { idempotencyKey: "idem-session-only" });
    const sessionId = options.headers?.["X-Session-Id"] ?? "";
    expect(sessionId).not.toBe("");

    await client.postV1TableHolds(
      {
        session_id: sessionId,
        start_time: "2026-04-18T10:00:00Z",
        end_time: "2026-04-18T11:30:00Z",
        table_ids: [1],
      },
      options,
    );

    expect(capturedHeaders).toBeDefined();
    const headers = capturedHeaders as Headers;
    expect(headers.get("X-Customer-Token")).toBeNull();
    expect(headers.get("X-Session-Id")).toBe(sessionId);
    expect(headers.get("Idempotency-Key")).toBe("idem-session-only");
    expect(capturedInit?.credentials).toBeUndefined();
  });

  it("generates an idempotency key when callers do not provide one", () => {
    const generated = idempotentOptions("reservation-create");
    const callerProvided = idempotentOptions("reservation-create", { idempotencyKey: "idem-explicit" });

    expect(generated.idempotencyKey).toMatch(/^reservation-create:/);
    expect(callerProvided.idempotencyKey).toBe("idem-explicit");
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

  it("serializes boolean query params using Laravel validator-safe values", async () => {
    let capturedUrl: string | undefined;
    const client = createApiClient({
      fetchImpl: async (input) => {
        capturedUrl = String(input);

        return new Response(
          JSON.stringify({
            data: [],
            meta: {},
          }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        );
      },
    });

    await client.getV1TablesAvailable({
      from: "2026-04-18T11:30:00.000Z",
      to: "2026-04-18T13:00:00.000Z",
      guest_count: 2,
      session_id: "session-query",
      suggest: true,
    });

    expect(capturedUrl).toBeDefined();
    const url = new URL(capturedUrl as string);
    expect(url.searchParams.get("suggest")).toBe("1");
  });

  it("returns checked-url diagnostics for backend health probes", async () => {
    const result = await checkBackendHealth(async () => {
      return new Response(null, {
        status: 503,
        headers: {
          "X-Request-Id": "req-health-1",
        },
      });
    });

    expect(result).toMatchObject({
      ok: false,
      status: 503,
      requestId: "req-health-1",
      checkedUrl: "http://127.0.0.1:8000/api/v1/health",
      baseUrl: "http://127.0.0.1:8000",
      usingDevMocks: false,
    });
  });

  it("preserves X-Request-Id from error response headers when the payload omits request_id", async () => {
    const client = createApiClient({
      fetchImpl: async () =>
        new Response(
          JSON.stringify({
            message: "Validation error.",
            error_code: "validation_error",
            errors: {
              guest_count: ["The guest count field is required."],
            },
          }),
          {
            status: 422,
            headers: {
              "Content-Type": "application/json",
              "X-Request-Id": "req-header-only",
            },
          },
        ),
    });

    await expect(client.getV1Reservations({ bucket: "upcoming" })).rejects.toMatchObject({
      status: 422,
      payload: expect.objectContaining({
        request_id: "req-header-only",
      }),
    });

    try {
      await client.getV1Reservations({ bucket: "upcoming" });
    } catch (error) {
      expect(normalizeApiError(error)).toMatchObject({
        requestId: "req-header-only",
        validationErrors: {
          guest_count: ["The guest count field is required."],
        },
      });
    }
  });
});
