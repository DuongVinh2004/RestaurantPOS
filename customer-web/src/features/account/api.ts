import { unwrapData } from "@/lib/api/envelope";
import { apiCall } from "@/lib/api/sdk-client";
import type { CustomerLoyaltySummaryEnvelope } from "@/lib/contracts/generated/restaurantpos-sdk";

export type LoyaltySummary = CustomerLoyaltySummaryEnvelope["data"];

export function getLoyalty(): Promise<LoyaltySummary> {
  return apiCall((client) => client.getV1MeLoyalty({ limit: 10 })).then(unwrapData);
}
