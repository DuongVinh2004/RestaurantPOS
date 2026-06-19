import type { RestaurantProfileEnvelope } from "@/lib/contracts/generated/restaurantpos-sdk";
import { unwrapData } from "@/lib/api/envelope";
import { apiCall } from "@/lib/api/sdk-client";

export type RestaurantProfile = RestaurantProfileEnvelope["data"];

export function getRestaurantProfile(): Promise<RestaurantProfile> {
  return apiCall((client) => client.getV1RestaurantProfile()).then(unwrapData);
}

export function getRestaurantBranches(): Promise<RestaurantProfile[]> {
  return apiCall((client) => client.getV1restaurantbranches()).then(unwrapData);
}
