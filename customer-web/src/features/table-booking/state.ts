import { asRecord, booleanValue, numberValue, recordValue } from "@/lib/contracts/loose";
import type { AvailableTablesCollectionEnvelope, RestaurantTable, TableHold } from "@/lib/contracts/generated/restaurantpos-sdk";
import { translateCustomerStatus } from "@/lib/i18n/customer-display";

type AvailabilityMeta = NonNullable<AvailableTablesCollectionEnvelope["meta"]>;
const activeTableHoldStorageKey = "restaurantpos.customer.active-table-hold.v1";

export type AvailabilityPolicyState = {
  requiresHold: boolean | null;
  holdMinutes: number | null;
  maxGuestCount: number | null;
};

export type AvailableTableSuggestionState = {
  tableIds: number[];
  totalSeats: number | null;
  over: number | null;
  tables: Array<{
    tableId: number;
    tableCode: string | null;
    seats: number | null;
  }>;
};

export type AvailabilityMetaState = {
  count: number;
  timezone: string | null;
  branchTimezone: string | null;
  fromUtc: string | null;
  toUtc: string | null;
  suggestionCount: number;
  suggestions: AvailableTableSuggestionState[];
  policy: AvailabilityPolicyState;
};

export type TableHoldState = {
  holdId: string;
  status: string;
  statusLabel: string;
  expiresAt: string | null;
  rowVersion: number;
  tableCount: number;
  isExpired: boolean;
  isActive: boolean;
};

export type ActiveTableHoldSnapshot = {
  hold_id: string;
  row_version: number;
  session_id: string;
  table_ids: number[];
  start_time: string;
  end_time: string;
  expire_at: string | null;
  hold_status?: string;
  duration_minutes?: number | null;
  guest_count?: number | null;
  branch_id?: number | null;
};

type ActiveTableHoldSnapshotInput = {
  sessionId: string;
  tableIds: number[];
  startTime?: string | null;
  endTime?: string | null;
  durationMinutes?: number | null;
  guestCount?: number | null;
  branchId?: number | null;
};

function browserSessionStorage(): Storage | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    return window.sessionStorage;
  } catch {
    return null;
  }
}

function parsePositiveIntegerArray(value: unknown): number[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return [...new Set(value.map((item) => Number(item)).filter((item) => Number.isInteger(item) && item > 0))]
    .sort((left, right) => left - right);
}

function normalizeTableIds(tableIds: number[]): number[] {
  return [...new Set(tableIds.filter((tableId) => Number.isInteger(tableId) && tableId > 0))]
    .sort((left, right) => left - right);
}

function parseSuggestionTables(value: unknown): AvailableTableSuggestionState["tables"] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .map((entry) => {
      const record = asRecord(entry);
      const tableId = numberValue(record, ["table_id", "tableId"]);

      if (!tableId || tableId <= 0) {
        return null;
      }

      const tableCode = record?.table_code;

      return {
        tableId,
        tableCode: typeof tableCode === "string" && tableCode.trim() !== "" ? tableCode : null,
        seats: numberValue(record, ["seats"]),
      };
    })
    .filter((entry): entry is AvailableTableSuggestionState["tables"][number] => entry !== null);
}

function parseAvailabilitySuggestions(meta: AvailabilityMeta | undefined): AvailableTableSuggestionState[] {
  if (!Array.isArray(meta?.suggestions)) {
    return [];
  }

  return meta.suggestions
    .map((entry) => {
      const record = asRecord(entry);
      const tableIds = parsePositiveIntegerArray(record?.table_ids ?? record?.tableIds);

      if (tableIds.length === 0) {
        return null;
      }

      return {
        tableIds,
        totalSeats: numberValue(record, ["total_seats", "totalSeats"]),
        over: numberValue(record, ["over"]),
        tables: parseSuggestionTables(record?.tables),
      };
    })
    .filter((entry): entry is AvailableTableSuggestionState => entry !== null);
}

export function parseAvailabilityMeta(meta: AvailabilityMeta | undefined, tableCount = 0): AvailabilityMetaState {
  const policy = recordValue(meta, ["availability_policy"]);
  const suggestions = parseAvailabilitySuggestions(meta);

  return {
    count: meta?.count ?? tableCount,
    timezone: meta?.timezone ?? null,
    branchTimezone: meta?.branch_timezone ?? null,
    fromUtc: meta?.from_utc ?? null,
    toUtc: meta?.to_utc ?? null,
    suggestionCount: suggestions.length,
    suggestions,
    policy: {
      requiresHold: booleanValue(policy, ["requires_hold", "hold_required"]),
      holdMinutes: numberValue(policy, ["hold_minutes", "default_hold_minutes"]),
      maxGuestCount: numberValue(policy, ["max_guest_count", "max_party_size"]),
    },
  };
}

export function parseTableHoldState(hold: TableHold): TableHoldState {
  const expiresAt = hold.expire_at ?? null;
  const expiresAtTimestamp = expiresAt ? Date.parse(expiresAt) : Number.NaN;
  const normalizedStatus = (hold.hold_status ?? "").trim().toLowerCase();
  const isExpired = normalizedStatus === "expired" || (Number.isFinite(expiresAtTimestamp) && expiresAtTimestamp <= Date.now());

  const activeStatus = normalizedStatus === "holding" || normalizedStatus === "pending";

  return {
    holdId: hold.hold_id,
    status: hold.hold_status,
    statusLabel: translateCustomerStatus(hold.hold_status),
    expiresAt,
    rowVersion: hold.row_version ?? 1,
    tableCount: Array.isArray(hold.tables) ? hold.tables.length : 0,
    isExpired,
    isActive: activeStatus && !isExpired,
  };
}

export function createActiveTableHoldSnapshot(
  hold: TableHold,
  input: ActiveTableHoldSnapshotInput,
): ActiveTableHoldSnapshot | null {
  const startTime = hold.start_time ?? input.startTime ?? null;
  const endTime = hold.end_time ?? input.endTime ?? null;
  const tableIds = normalizeTableIds(
    Array.isArray(hold.tables) && hold.tables.length > 0
      ? hold.tables.map((table) => table.table_id)
      : input.tableIds,
  );

  if (!hold.hold_id || !startTime || !endTime || tableIds.length === 0) {
    return null;
  }

  return {
    hold_id: hold.hold_id,
    row_version: hold.row_version ?? 1,
    session_id: input.sessionId,
    table_ids: tableIds,
    start_time: startTime,
    end_time: endTime,
    expire_at: hold.expire_at ?? null,
    hold_status: hold.hold_status,
    duration_minutes: hold.duration_minutes ?? input.durationMinutes ?? null,
    guest_count: input.guestCount ?? null,
    branch_id: input.branchId ?? null,
  };
}

export function isActiveTableHoldSnapshot(
  snapshot: ActiveTableHoldSnapshot | null,
  sessionId: string,
  nowMs = Date.now(),
): snapshot is ActiveTableHoldSnapshot {
  if (!snapshot || snapshot.session_id !== sessionId || snapshot.hold_id.trim() === "") {
    return false;
  }

  const normalizedStatus = (snapshot.hold_status ?? "Holding").trim().toLowerCase();
  const expiresAtMs = snapshot.expire_at ? Date.parse(snapshot.expire_at) : Number.NaN;

  if (normalizedStatus !== "holding" && normalizedStatus !== "pending") {
    return false;
  }

  return !Number.isFinite(expiresAtMs) || expiresAtMs > nowMs;
}

export function readStoredActiveTableHoldSnapshot(sessionId: string): ActiveTableHoldSnapshot | null {
  const storage = browserSessionStorage();
  const raw = storage?.getItem(activeTableHoldStorageKey);

  if (!raw) {
    return null;
  }

  try {
    const parsed = JSON.parse(raw) as ActiveTableHoldSnapshot;

    if (isActiveTableHoldSnapshot(parsed, sessionId)) {
      return {
        ...parsed,
        table_ids: normalizeTableIds(parsed.table_ids),
      };
    }
  } catch {
    // Broken storage should not block a fresh booking flow.
  }

  storage?.removeItem(activeTableHoldStorageKey);
  return null;
}

export function storeActiveTableHoldSnapshot(snapshot: ActiveTableHoldSnapshot): void {
  browserSessionStorage()?.setItem(activeTableHoldStorageKey, JSON.stringify(snapshot));
}

export function clearStoredActiveTableHoldSnapshot(sessionId?: string): void {
  const storage = browserSessionStorage();

  if (!storage) {
    return;
  }

  if (!sessionId) {
    storage.removeItem(activeTableHoldStorageKey);
    return;
  }

  const current = readStoredActiveTableHoldSnapshot(sessionId);

  if (current) {
    storage.removeItem(activeTableHoldStorageKey);
  }
}

export function tableHoldFromSnapshot(snapshot: ActiveTableHoldSnapshot): TableHold {
  return {
    hold_id: snapshot.hold_id,
    session_hash: null,
    start_time: snapshot.start_time,
    end_time: snapshot.end_time,
    duration_minutes: snapshot.duration_minutes ?? null,
    hold_status: snapshot.hold_status ?? "Holding",
    confirmed_reservation_id: null,
    row_version: snapshot.row_version,
    expire_at: snapshot.expire_at,
    tables: snapshot.table_ids.map((tableId): RestaurantTable => ({
      table_id: tableId,
      branch_id: snapshot.branch_id ?? null,
      table_code: null,
      template_id: null,
      seats: null,
      zone: null,
      pos_x: null,
      pos_y: null,
      status: "Available",
      description: null,
      price: null,
      row_version: null,
      pivot: null,
      created_at: null,
      updated_at: null,
    })),
  };
}
