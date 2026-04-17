import { ensureCustomerSessionId, storeCustomerAuthSession } from "@/lib/auth/storage";
import type { CustomerAuthSessionEnvelope } from "@/lib/contracts/generated/restaurantpos-sdk";
import { apiCall } from "@/lib/api/sdk-client";
import type { LoginFormValues } from "./schemas";

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

export async function fetchCurrentCustomer(): Promise<CustomerAuthSessionEnvelope> {
  return apiCall((client) => client.getV1AuthCustomerMe());
}

export async function refreshCustomerSession(): Promise<CustomerAuthSessionEnvelope> {
  const session = await apiCall((client) => client.postV1AuthCustomerRefresh());
  storeCustomerAuthSession(session);
  return session;
}

export async function logoutCustomer(): Promise<void> {
  await apiCall((client) => client.postV1AuthCustomerLogout());
}
