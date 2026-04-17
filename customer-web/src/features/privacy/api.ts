import { apiCall, idempotentOptions } from "@/lib/api/sdk-client";
import type {
  CustomerDataExportEnvelope,
  CustomerPrivacyRequestCollectionEnvelope,
  CustomerPrivacyRequestEnvelope,
} from "@/lib/contracts/generated/restaurantpos-sdk";

export function getDataExport(): Promise<CustomerDataExportEnvelope> {
  return apiCall((client) => client.getV1MeDataExport());
}

export function listPrivacyRequests(): Promise<CustomerPrivacyRequestCollectionEnvelope> {
  return apiCall((client) => client.getV1MePrivacyRequests({ per_page: 20 }));
}

export function createPrivacyRequest(reason?: string): Promise<CustomerPrivacyRequestEnvelope> {
  return apiCall((client) =>
    client.postV1MePrivacyRequests(
      { request_type: "anonymize", reason: reason || undefined },
      idempotentOptions("privacy-request-create"),
    ),
  );
}
