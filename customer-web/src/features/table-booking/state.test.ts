import { beforeEach, describe, expect, it } from "vitest";
import {
  clearStoredActiveTableHoldSnapshot,
  createActiveTableHoldSnapshot,
  readStoredActiveTableHoldSnapshot,
  storeActiveTableHoldSnapshot,
  tableHoldFromSnapshot,
  parseAvailabilityMeta,
  parseTableHoldState,
} from "./state";

function futureIso(minutesFromNow: number): string {
  return new Date(Date.now() + minutesFromNow * 60_000).toISOString();
}

describe("table booking state helpers", () => {
  beforeEach(() => {
    window.sessionStorage.clear();
  });

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
        suggestions: [
          {
            table_ids: [8, 7],
            total_seats: "6",
            over: 2,
            tables: [{ table_id: 7, table_code: "A07", seats: 2 }],
          },
        ],
      },
      1,
    );

    expect(state).toMatchObject({
      count: 2,
      timezone: "UTC",
      branchTimezone: "Asia/Bangkok",
      suggestionCount: 1,
      suggestions: [
        {
          tableIds: [7, 8],
          totalSeats: 6,
          over: 2,
          tables: [{ tableId: 7, tableCode: "A07", seats: 2 }],
        },
      ],
      policy: {
        requiresHold: true,
        holdMinutes: 10,
        maxGuestCount: 8,
      },
    });
  });

  it("narrows table hold expiry, translated status, and row-version state", () => {
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
      statusLabel: "Đang giữ bàn",
      expiresAt: expireAt,
      rowVersion: 3,
      tableCount: 0,
      isExpired: false,
      isActive: true,
    });
  });

  it("persists and restores an active hold snapshot for the current session only", () => {
    const expireAt = futureIso(20);
    const snapshot = createActiveTableHoldSnapshot(
      {
        hold_id: "hold-local",
        session_hash: null,
        start_time: futureIso(60),
        end_time: futureIso(150),
        duration_minutes: 90,
        hold_status: "Holding",
        confirmed_reservation_id: null,
        row_version: 5,
        expire_at: expireAt,
        tables: [{ table_id: 8 } as never, { table_id: 7 } as never],
      },
      {
        sessionId: "session-a",
        tableIds: [7, 8],
        durationMinutes: 90,
        guestCount: 4,
        branchId: 2,
      },
    );

    expect(snapshot).toMatchObject({
      hold_id: "hold-local",
      row_version: 5,
      session_id: "session-a",
      table_ids: [7, 8],
      expire_at: expireAt,
      guest_count: 4,
      branch_id: 2,
    });

    storeActiveTableHoldSnapshot(snapshot as NonNullable<typeof snapshot>);

    expect(readStoredActiveTableHoldSnapshot("session-a")).toMatchObject({
      hold_id: "hold-local",
      table_ids: [7, 8],
    });
    expect(readStoredActiveTableHoldSnapshot("session-b")).toBeNull();
  });

  it("drops expired stored holds and can rebuild the table hold object for UI state", () => {
    storeActiveTableHoldSnapshot({
      hold_id: "hold-expired",
      row_version: 2,
      session_id: "session-a",
      table_ids: [9],
      start_time: futureIso(-60),
      end_time: futureIso(30),
      expire_at: futureIso(-1),
      hold_status: "Holding",
    });

    expect(readStoredActiveTableHoldSnapshot("session-a")).toBeNull();

    const activeSnapshot = {
      hold_id: "hold-active",
      row_version: 3,
      session_id: "session-a",
      table_ids: [9],
      start_time: futureIso(60),
      end_time: futureIso(150),
      expire_at: futureIso(20),
      hold_status: "Pending",
    };

    storeActiveTableHoldSnapshot(activeSnapshot);

    expect(parseTableHoldState(tableHoldFromSnapshot(activeSnapshot))).toMatchObject({
      holdId: "hold-active",
      rowVersion: 3,
      isActive: true,
      tableCount: 1,
    });

    clearStoredActiveTableHoldSnapshot("session-a");
    expect(readStoredActiveTableHoldSnapshot("session-a")).toBeNull();
  });
});
