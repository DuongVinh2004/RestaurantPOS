import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { unwrapData } from "@/lib/api/envelope";
import { apiCall, idempotentSessionOptions } from "@/lib/api/sdk-client";
import type {
  AvailableTablesCollectionEnvelope,
  RestaurantTable,
  TableHold,
} from "@/lib/contracts/generated/restaurantpos-sdk";
import { availabilityTimes, type AvailabilitySearchValues } from "./schemas";

export type AvailableTablesResult = {
  tables: RestaurantTable[];
  meta?: AvailableTablesCollectionEnvelope["meta"];
};

export async function searchAvailableTables(values: AvailabilitySearchValues): Promise<AvailableTablesResult> {
  const times = availabilityTimes(values);
  const envelope = await apiCall((client) =>
    client.getV1TablesAvailable({
      from: times.start_time,
      to: times.end_time,
      guest_count: values.guest_count,
      branch_id: values.branch_id,
      session_id: ensureCustomerSessionId(),
      suggest: true,
    }),
  );

  return {
    tables: envelope.data,
    meta: envelope.meta,
  };
}

export function createTableHold(
  values: AvailabilitySearchValues,
  tableIds: number[],
): Promise<TableHold> {
  const times = availabilityTimes(values);

  return apiCall((client) =>
    client.postV1TableHolds(
      {
        session_id: ensureCustomerSessionId(),
        start_time: times.start_time,
        end_time: times.end_time,
        table_ids: tableIds,
        branch_id: values.branch_id,
      },
      idempotentSessionOptions("table-hold-create"),
    ),
  ).then(unwrapData);
}

export function refreshTableHold(holdId: string, rowVersion?: number): Promise<TableHold> {
  return apiCall((client) =>
    client.patchV1TableHoldsHoldIdRefresh(
      { hold_id: holdId },
      { session_id: ensureCustomerSessionId(), row_version: rowVersion ?? undefined, extend_minutes: 10 },
      idempotentSessionOptions("table-hold-refresh"),
    ),
  ).then(unwrapData);
}

export function cancelTableHold(holdId: string, rowVersion?: number): Promise<TableHold> {
  return apiCall((client) =>
    client.deleteV1TableHoldsHoldId(
      { hold_id: holdId },
      { session_id: ensureCustomerSessionId(), row_version: rowVersion ?? undefined },
      idempotentSessionOptions("table-hold-cancel"),
    ),
  ).then(unwrapData);
}
