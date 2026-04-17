import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { apiCall, idempotentSessionOptions } from "@/lib/api/sdk-client";
import type {
  AvailableTablesCollectionEnvelope,
  TableHoldEnvelope,
} from "@/lib/contracts/generated/restaurantpos-sdk";
import { availabilityTimes, type AvailabilitySearchValues } from "./schemas";

export function searchAvailableTables(values: AvailabilitySearchValues): Promise<AvailableTablesCollectionEnvelope> {
  const times = availabilityTimes(values);

  return apiCall((client) =>
    client.getV1TablesAvailable({
      from: times.start_time,
      to: times.end_time,
      guest_count: values.guest_count,
      branch_id: values.branch_id,
      session_id: ensureCustomerSessionId(),
      suggest: true,
    }),
  );
}

export function createTableHold(
  values: AvailabilitySearchValues,
  tableIds: number[],
): Promise<TableHoldEnvelope> {
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
  );
}

export function refreshTableHold(holdId: string, rowVersion?: number): Promise<TableHoldEnvelope> {
  return apiCall((client) =>
    client.patchV1TableHoldsHoldIdRefresh(
      { hold_id: holdId },
      { session_id: ensureCustomerSessionId(), row_version: rowVersion ?? undefined, extend_minutes: 10 },
      idempotentSessionOptions("table-hold-refresh"),
    ),
  );
}

export function cancelTableHold(holdId: string, rowVersion?: number): Promise<TableHoldEnvelope> {
  return apiCall((client) =>
    client.deleteV1TableHoldsHoldId(
      { hold_id: holdId },
      { session_id: ensureCustomerSessionId(), row_version: rowVersion ?? undefined },
      idempotentSessionOptions("table-hold-cancel"),
    ),
  );
}
