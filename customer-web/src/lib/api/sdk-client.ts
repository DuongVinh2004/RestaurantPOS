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

export function createApiClient(options: ApiClientOptions = {}): RestaurantPosClient {
  const fetchImpl = featureFlags.devMocks ? createMockFetch() : options.fetchImpl;

  return new RestaurantPosClient({
    baseUrl: publicEnv.apiBaseUrl,
    fetchImpl,
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

export async function checkBackendHealth(fetchImpl: typeof fetch = featureFlags.devMocks ? createMockFetch() : fetch) {
  try {
    const response = await fetchImpl(`${publicEnv.apiBaseUrl.replace(/\/+$/, "")}/api/v1/health`, {
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
    };
  } catch (error) {
    throw normalizeApiError(error);
  }
}
