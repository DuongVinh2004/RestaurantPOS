import { apiCall, idempotentOptions } from "@/lib/api/sdk-client";
import type {
  CustomerWaitingListArrivalEnvelope,
  CustomerWaitingListCollectionEnvelope,
  CustomerWaitingListEnvelope,
  RespondOwnerWaitingListRequest,
} from "@/lib/contracts/generated/restaurantpos-sdk";
import type { WaitingListCreateValues } from "./schemas";

export function listWaitingList(): Promise<CustomerWaitingListCollectionEnvelope> {
  return apiCall((client) => client.getV1WaitingList({ active_only: true }));
}

export function createWaitingListEntry(values: WaitingListCreateValues): Promise<CustomerWaitingListEnvelope> {
  return apiCall((client) => client.postV1WaitingList(values, idempotentOptions("waiting-list-create")));
}

export function acceptWaitingListEntry(id: number, body: RespondOwnerWaitingListRequest): Promise<CustomerWaitingListEnvelope> {
  return apiCall((client) => client.postV1WaitingListIdAccept({ id }, body, idempotentOptions("waiting-list-accept")));
}

export function confirmWaitingListArrival(
  id: number,
  body: RespondOwnerWaitingListRequest,
): Promise<CustomerWaitingListArrivalEnvelope> {
  return apiCall((client) =>
    client.postV1WaitingListIdConfirmArrival({ id }, body, idempotentOptions("waiting-list-arrival")),
  );
}

export function declineWaitingListEntry(id: number, body: RespondOwnerWaitingListRequest): Promise<CustomerWaitingListEnvelope> {
  return apiCall((client) => client.postV1WaitingListIdDecline({ id }, body, idempotentOptions("waiting-list-decline")));
}

export function cancelWaitingListEntry(id: number, body: RespondOwnerWaitingListRequest): Promise<CustomerWaitingListEnvelope> {
  return apiCall((client) => client.postV1WaitingListIdCancel({ id }, body, idempotentOptions("waiting-list-cancel")));
}
