import { booleanValue, numberValue, recordValue } from "@/lib/contracts/loose";
import type { AvailableTablesCollectionEnvelope, TableHold } from "@/lib/contracts/generated/restaurantpos-sdk";

type AvailabilityMeta = NonNullable<AvailableTablesCollectionEnvelope["meta"]>;

export type AvailabilityPolicyState = {
  requiresHold: boolean | null;
  holdMinutes: number | null;
  maxGuestCount: number | null;
};

export type AvailabilityMetaState = {
  count: number;
  timezone: string | null;
  branchTimezone: string | null;
  fromUtc: string | null;
  toUtc: string | null;
  suggestionCount: number;
  policy: AvailabilityPolicyState;
};

export type TableHoldState = {
  holdId: string;
  status: string;
  expiresAt: string | null;
  rowVersion: number;
  tableCount: number;
  isExpired: boolean;
  isActive: boolean;
};

export function parseAvailabilityMeta(meta: AvailabilityMeta | undefined, tableCount = 0): AvailabilityMetaState {
  const policy = recordValue(meta, ["availability_policy"]);

  return {
    count: meta?.count ?? tableCount,
    timezone: meta?.timezone ?? null,
    branchTimezone: meta?.branch_timezone ?? null,
    fromUtc: meta?.from_utc ?? null,
    toUtc: meta?.to_utc ?? null,
    suggestionCount: meta?.suggestions?.length ?? 0,
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
  const isExpired = hold.hold_status === "Expired" || (Number.isFinite(expiresAtTimestamp) && expiresAtTimestamp <= Date.now());

  return {
    holdId: hold.hold_id,
    status: hold.hold_status,
    expiresAt,
    rowVersion: hold.row_version,
    tableCount: Array.isArray(hold.tables) ? hold.tables.length : 0,
    isExpired,
    isActive: hold.hold_status === "Holding" && !isExpired,
  };
}
