import { RestaurantPosClient, type RequestOptions } from "@/lib/contracts/generated/restaurantpos-sdk";
import { ensureCustomerSessionId, getCustomerSessionId, getCustomerToken } from "@/lib/auth/storage";
import { featureFlags } from "@/lib/config/feature-flags";
import { publicEnv } from "@/lib/config/env";
import { createIdempotencyKey } from "./idempotency";
import { createMockFetch } from "./mock-fetch";
import { normalizeApiError } from "./errors";

export type ApiClientOptions = {
  fetchImpl?: typeof fetch;
};

export type ApiRuntimeDiagnostics = {
  baseUrl: string;
  usingDevMocks: boolean;
  hasCustomerToken: boolean;
  sessionId: string | null;
};

export type BackendHealthResult = {
  ok: boolean;
  status: number;
  requestId: string | null;
  checkedUrl: string;
  baseUrl: string;
  usingDevMocks: boolean;
};

export function getApiRuntimeDiagnostics(): ApiRuntimeDiagnostics {
  return {
    baseUrl: publicEnv.apiBaseUrl,
    usingDevMocks: featureFlags.devMocks,
    hasCustomerToken: Boolean(getCustomerToken()),
    sessionId: getCustomerSessionId(),
  };
}

export function createApiClient(options: ApiClientOptions = {}): RestaurantPosClient {
  const baseFetchImpl = featureFlags.devMocks ? createMockFetch() : (options.fetchImpl ?? fetch);

  return new RestaurantPosClient({
    baseUrl: publicEnv.apiBaseUrl,
    fetchImpl: withRequestIdPreservation(baseFetchImpl),
    customerToken: () => getCustomerToken(),
    customerSessionId: () => getCustomerSessionId() ?? ensureCustomerSessionId(),
    defaultHeaders: {
      "X-Requested-With": "customer-web",
    },
  });
}

export function idempotentOptions(scope: string, options: RequestOptions = {}): RequestOptions {
  return {
    ...options,
    idempotencyKey: options.idempotencyKey ?? createIdempotencyKey(scope),
  };
}

export function withRequestIdPreservation(fetchImpl: typeof fetch): typeof fetch {
  return async (input, init) => {
    const response = await fetchImpl(input, init);

    return preserveErrorRequestId(response);
  };
}

async function preserveErrorRequestId(response: Response): Promise<Response> {
  if (response.ok) {
    return response;
  }

  const requestId = response.headers.get("X-Request-Id");

  if (!requestId) {
    return response;
  }

  const cloned = response.clone();
  const raw = await cloned.text();
  const payload = parseJsonRecord(raw);

  if (typeof payload.request_id === "string" && payload.request_id.trim() !== "") {
    return response;
  }

  const headers = new Headers(response.headers);
  headers.set("Content-Type", "application/json");

  return new Response(
    JSON.stringify({
      ...payload,
      request_id: requestId,
    }),
    {
      status: response.status,
      statusText: response.statusText,
      headers,
    },
  );
}

function parseJsonRecord(raw: string): Record<string, unknown> {
  if (!raw) {
    return {};
  }

  try {
    const payload: unknown = JSON.parse(raw);

    return payload && typeof payload === "object" && !Array.isArray(payload) ? (payload as Record<string, unknown>) : {};
  } catch {
    return {};
  }
}

export function sessionOptions(options: RequestOptions = {}): RequestOptions {
  const sessionId = ensureCustomerSessionId();

  return {
    ...options,
    headers: {
      ...options.headers,
      "X-Session-Id": sessionId,
    },
  };
}

export function idempotentSessionOptions(scope: string, options: RequestOptions = {}): RequestOptions {
  return sessionOptions(idempotentOptions(scope, options));
}

export async function apiCall<T>(operation: (client: RestaurantPosClient) => Promise<T>): Promise<T> {
  const client = createApiClient();

  try {
    return await operation(client);
  } catch (error) {
    throw normalizeApiError(error);
  }
}

export async function checkBackendHealth(fetchImpl: typeof fetch = featureFlags.devMocks ? createMockFetch() : fetch): Promise<BackendHealthResult> {
  const diagnostics = getApiRuntimeDiagnostics();
  const checkedUrl = `${diagnostics.baseUrl.replace(/\/+$/, "")}/api/v1/health`;

  try {
    const response = await fetchImpl(checkedUrl, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "customer-web",
      },
      cache: "no-store",
    });

    return {
      ok: response.ok,
      status: response.status,
      requestId: response.headers.get("X-Request-Id"),
      checkedUrl,
      baseUrl: diagnostics.baseUrl,
      usingDevMocks: diagnostics.usingDevMocks,
    };
  } catch (error) {
    throw normalizeApiError(error);
  }
}
