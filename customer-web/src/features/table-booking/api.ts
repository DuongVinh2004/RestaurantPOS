import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { unwrapData } from "@/lib/api/envelope";
import { apiCall, idempotentSessionOptions } from "@/lib/api/sdk-client";
import { createStableIdempotencyKey } from "@/lib/api/idempotency";
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

function normalizeTableIds(tableIds: number[]): number[] {
  return [...new Set(tableIds.filter((tableId) => Number.isInteger(tableId) && tableId > 0))].sort((left, right) => left - right);
}

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

export async function getTableHold(holdId: string): Promise<TableHold> {
  return unwrapData(await apiCall((client) => client.getV1TableHoldsHoldId({ hold_id: holdId })));
}

export function createTableHold(
  values: AvailabilitySearchValues,
  tableIds: number[],
): Promise<TableHold> {
  const times = availabilityTimes(values);
  const sessionId = ensureCustomerSessionId();
  const normalizedTableIds = normalizeTableIds(tableIds);
  const idempotencyKey = createStableIdempotencyKey("table-hold-create", {
    branch_id: values.branch_id ?? null,
    end_time: times.end_time,
    session_id: sessionId,
    start_time: times.start_time,
    table_ids: normalizedTableIds,
  });

  return apiCall((client) =>
    client.postV1TableHolds(
      {
        session_id: sessionId,
        start_time: times.start_time,
        end_time: times.end_time,
        table_ids: normalizedTableIds,
        branch_id: values.branch_id,
      },
      idempotentSessionOptions("table-hold-create", { idempotencyKey }),
    ),
  ).then(unwrapData);
}

export function refreshTableHold(holdId: string, rowVersion?: number): Promise<TableHold> {
  const sessionId = ensureCustomerSessionId();
  const extendMinutes = 10;
  const idempotencyKey = createStableIdempotencyKey("table-hold-refresh", {
    extend_minutes: extendMinutes,
    hold_id: holdId,
    row_version: rowVersion ?? null,
    session_id: sessionId,
  });

  return apiCall((client) =>
    client.patchV1TableHoldsHoldIdRefresh(
      { hold_id: holdId },
      { session_id: sessionId, row_version: rowVersion ?? undefined, extend_minutes: extendMinutes },
      idempotentSessionOptions("table-hold-refresh", { idempotencyKey }),
    ),
  ).then(unwrapData);
}

export function cancelTableHold(holdId: string, rowVersion?: number): Promise<TableHold> {
  const sessionId = ensureCustomerSessionId();
  const idempotencyKey = createStableIdempotencyKey("table-hold-cancel", {
    hold_id: holdId,
    row_version: rowVersion ?? null,
    session_id: sessionId,
  });

  return apiCall((client) =>
    client.deleteV1TableHoldsHoldId(
      { hold_id: holdId },
      { session_id: sessionId, row_version: rowVersion ?? undefined },
      idempotentSessionOptions("table-hold-cancel", { idempotencyKey }),
    ),
  ).then(unwrapData);
}
