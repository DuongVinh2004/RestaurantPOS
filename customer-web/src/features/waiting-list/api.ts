import { unwrapData } from "@/lib/api/envelope";
import { apiCall, idempotentOptions } from "@/lib/api/sdk-client";
import type {
  CustomerWaitingListArrivalEnvelope,
  CustomerWaitingListCollectionEnvelope,
  CustomerWaitingListEntry,
  CustomerWaitingListEnvelope,
  CustomerRespondWaitlistInviteRequest,
} from "@/lib/contracts/generated/restaurantpos-sdk";
import type { WaitingListCreateValues } from "./schemas";

export type WaitingListEntries = CustomerWaitingListCollectionEnvelope["data"];
export type WaitingListEntry = CustomerWaitingListEnvelope["data"];
export type WaitingListArrivalMeta = CustomerWaitingListArrivalEnvelope["meta"];
export type WaitingListMutationResult = {
  entry: CustomerWaitingListEntry;
  meta: WaitingListArrivalMeta | null;
};

export function listWaitingList(): Promise<WaitingListEntries> {
  return apiCall((client) => client.getV1WaitingList({ active_only: false })).then(unwrapData);
}

export function getWaitingListEntry(id: number): Promise<WaitingListEntry> {
  return apiCall((client) => client.getV1WaitingListId({ id })).then(unwrapData);
}

export function createWaitingListEntry(values: WaitingListCreateValues): Promise<WaitingListMutationResult> {
  return apiCall((client) => client.postV1WaitingList(values, waitingListOwnerMutationOptions("waiting-list-create"))).then(
    waitingListMutationResult,
  );
}

export function acceptWaitingListEntry(id: number, body: CustomerRespondWaitlistInviteRequest): Promise<WaitingListMutationResult> {
  return apiCall((client) =>
    client.postV1WaitingListIdAccept({ id }, body, waitingListOwnerMutationOptions("waiting-list-accept")),
  ).then(waitingListMutationResult);
}

export function confirmWaitingListArrival(
  id: number,
  body: CustomerRespondWaitlistInviteRequest,
): Promise<WaitingListMutationResult> {
  return apiCall((client) =>
    client.postV1WaitingListIdConfirmArrival({ id }, body, waitingListOwnerMutationOptions("waiting-list-arrival")),
  ).then(waitingListMutationResult);
}

export function declineWaitingListEntry(id: number, body: CustomerRespondWaitlistInviteRequest): Promise<WaitingListMutationResult> {
  return apiCall((client) =>
    client.postV1WaitingListIdDecline({ id }, body, waitingListOwnerMutationOptions("waiting-list-decline")),
  ).then(waitingListMutationResult);
}

export function cancelWaitingListEntry(id: number, body: CustomerRespondWaitlistInviteRequest): Promise<WaitingListMutationResult> {
  return apiCall((client) =>
    client.postV1WaitingListIdCancel({ id }, body, waitingListOwnerMutationOptions("waiting-list-cancel")),
  ).then(waitingListMutationResult);
}

export function waitingListMutationEntry(result: WaitingListMutationResult): CustomerWaitingListEntry {
  return result.entry;
}

function waitingListMutationResult(result: CustomerWaitingListEnvelope | CustomerWaitingListArrivalEnvelope): WaitingListMutationResult {
  return {
    entry: result.data,
    meta: "meta" in result ? result.meta : null,
  };
}

function waitingListOwnerMutationOptions(scope: string) {
  // Waiting-list owner routes are customer-token scoped only. Do not attach X-Session-Id here.
  return idempotentOptions(scope);
}
