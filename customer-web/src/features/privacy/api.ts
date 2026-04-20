import { unwrapData } from "@/lib/api/envelope";
import { apiCall, idempotentOptions } from "@/lib/api/sdk-client";
import type {
  CustomerDataExportEnvelope,
  CustomerPrivacyRequestCollectionEnvelope,
  CustomerPrivacyRequestEnvelope,
} from "@/lib/contracts/generated/restaurantpos-sdk";

export type DataExportResult = CustomerDataExportEnvelope["data"];
export type PrivacyRequests = CustomerPrivacyRequestCollectionEnvelope["data"];
export type PrivacyRequestResult = CustomerPrivacyRequestEnvelope["data"];

export function getDataExport(): Promise<DataExportResult> {
  return apiCall((client) => client.getV1MeDataExport()).then(unwrapData);
}

export function listPrivacyRequests(): Promise<PrivacyRequests> {
  return apiCall((client) => client.getV1MePrivacyRequests({ per_page: 20 })).then(unwrapData);
}

export function createPrivacyRequest(reason?: string): Promise<PrivacyRequestResult> {
  return apiCall((client) =>
    client.postV1MePrivacyRequests(
      { request_type: "anonymize", reason: reason || undefined },
      idempotentOptions("privacy-request-create"),
    ),
  ).then(unwrapData);
}
