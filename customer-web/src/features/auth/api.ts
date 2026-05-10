import { normalizeApiError } from "@/lib/api/errors";
import { ensureCustomerSessionId, getCustomerToken, storeCustomerAuthSession } from "@/lib/auth/storage";
import type { CustomerAuthSessionEnvelope, RegisterRequest } from "@/lib/contracts/generated/restaurantpos-sdk";
import { apiCall } from "@/lib/api/sdk-client";
import type { LoginFormValues, RegisterFormValues } from "./schemas";

export async function loginCustomer(values: LoginFormValues): Promise<CustomerAuthSessionEnvelope> {
  const sessionId = ensureCustomerSessionId();
  const session = await apiCall((client) =>
    client.postV1AuthCustomerLogin({
      identifier: values.identifier,
      password: values.password,
      session_id: sessionId,
      session_label: "customer-web",
    }),
  );

  storeCustomerAuthSession(session);
  return session;
}

export async function registerCustomer(values: RegisterFormValues): Promise<CustomerAuthSessionEnvelope> {
  const sessionId = ensureCustomerSessionId();
  const body: RegisterRequest = {
    full_name: values.full_name,
    email: optionalText(values.email),
    phone: optionalText(values.phone),
    password: values.password,
    password_confirmation: values.password_confirmation,
    session_id: sessionId,
    session_label: "customer-web",
  };

  const session = await apiCall((client) => client.postV1AuthCustomerRegister(body));

  storeCustomerAuthSession(session);
  return session;
}

export async function fetchCurrentCustomer(): Promise<CustomerAuthSessionEnvelope> {
  return apiCall((client) => client.getV1AuthCustomerMe());
}

export async function bootstrapCustomerSession(): Promise<CustomerAuthSessionEnvelope> {
  try {
    return await fetchCurrentCustomer();
  } catch (error) {
    const normalized = normalizeApiError(error);

    if (normalized.kind !== "unauthorized" || !getCustomerToken()) {
      throw normalized;
    }
  }

  await refreshCustomerSession();
  return fetchCurrentCustomer();
}

export async function refreshCustomerSession(): Promise<CustomerAuthSessionEnvelope> {
  const session = await apiCall((client) => client.postV1AuthCustomerRefresh({}));
  storeCustomerAuthSession(session);
  return session;
}

export async function logoutCustomer(): Promise<void> {
  await apiCall((client) => client.postV1AuthCustomerLogout());
}

function optionalText(value: string | undefined): string | undefined {
  const normalized = value?.trim() ?? "";

  return normalized !== "" ? normalized : undefined;
}
