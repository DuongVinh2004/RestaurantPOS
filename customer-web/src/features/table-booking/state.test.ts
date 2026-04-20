import { describe, expect, it } from "vitest";
import { parseAvailabilityMeta, parseTableHoldState } from "./state";

function futureIso(minutesFromNow: number): string {
  return new Date(Date.now() + minutesFromNow * 60_000).toISOString();
}

describe("table booking state helpers", () => {
  it("narrows loose availability metadata into a stable UI contract", () => {
    const state = parseAvailabilityMeta(
      {
        count: 2,
        timezone: "UTC",
        branch_timezone: "Asia/Bangkok",
        from_utc: "2026-04-18T11:30:00.000Z",
        to_utc: "2026-04-18T13:00:00.000Z",
        availability_policy: {
          requires_hold: true,
          hold_minutes: "10",
          max_guest_count: 8,
        },
        suggestions: [{ reason: "adjacent tables" }],
      },
      1,
    );

    expect(state).toMatchObject({
      count: 2,
      timezone: "UTC",
      branchTimezone: "Asia/Bangkok",
      suggestionCount: 1,
      policy: {
        requiresHold: true,
        holdMinutes: 10,
        maxGuestCount: 8,
      },
    });
  });

  it("narrows table hold expiry and row-version state", () => {
    const startTime = futureIso(30);
    const endTime = futureIso(120);
    const expireAt = futureIso(40);

    const state = parseTableHoldState({
      hold_id: "hold-1",
      session_hash: null,
      start_time: startTime,
      end_time: endTime,
      duration_minutes: 90,
      hold_status: "Holding",
      confirmed_reservation_id: null,
      row_version: 3,
      expire_at: expireAt,
      tables: [],
    });

    expect(state).toEqual({
      holdId: "hold-1",
      status: "Holding",
      expiresAt: expireAt,
      rowVersion: 3,
      tableCount: 0,
      isExpired: false,
      isActive: true,
    });
  });
});
