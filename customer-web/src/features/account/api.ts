import { apiCall } from "@/lib/api/sdk-client";
import type { CustomerLoyaltySummaryEnvelope } from "@/lib/contracts/generated/restaurantpos-sdk";

export function getLoyalty(): Promise<CustomerLoyaltySummaryEnvelope> {
  return apiCall((client) => client.getV1MeLoyalty({ limit: 10 }));
}
