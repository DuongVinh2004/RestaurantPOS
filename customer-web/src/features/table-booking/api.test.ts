import { describe, expect, it, vi, beforeEach } from "vitest";
import { cancelTableHold, createTableHold, refreshTableHold, searchAvailableTables } from "./api";

const mocks = vi.hoisted(() => ({
  ensureCustomerSessionId: vi.fn(),
  idempotentSessionOptions: vi.fn(),
  apiCall: vi.fn(),
  getV1TablesAvailable: vi.fn(),
  postV1TableHolds: vi.fn(),
  patchV1TableHoldsHoldIdRefresh: vi.fn(),
  deleteV1TableHoldsHoldId: vi.fn(),
}));

vi.mock("@/lib/auth/storage", () => ({
  ensureCustomerSessionId: mocks.ensureCustomerSessionId,
}));

vi.mock("@/lib/api/sdk-client", () => ({
  apiCall: mocks.apiCall,
  idempotentSessionOptions: mocks.idempotentSessionOptions,
}));

describe("table booking api", () => {
  beforeEach(() => {
    mocks.ensureCustomerSessionId.mockReset();
    mocks.idempotentSessionOptions.mockReset();
    mocks.apiCall.mockReset();
    mocks.getV1TablesAvailable.mockReset();
    mocks.postV1TableHolds.mockReset();
    mocks.patchV1TableHoldsHoldIdRefresh.mockReset();
    mocks.deleteV1TableHoldsHoldId.mockReset();

    mocks.ensureCustomerSessionId.mockReturnValue("session-123");
    mocks.idempotentSessionOptions.mockReturnValue({ idempotencyKey: "idem-123" });
    mocks.apiCall.mockImplementation(async (operation: (client: unknown) => unknown) =>
      operation({
        getV1TablesAvailable: mocks.getV1TablesAvailable,
        postV1TableHolds: mocks.postV1TableHolds,
        patchV1TableHoldsHoldIdRefresh: mocks.patchV1TableHoldsHoldIdRefresh,
        deleteV1TableHoldsHoldId: mocks.deleteV1TableHoldsHoldId,
      }),
    );
  });

  it("uses the current browser session and UTC range for availability search", async () => {
    mocks.getV1TablesAvailable.mockResolvedValue({ data: [] });

    await searchAvailableTables({
      start_time: "2026-04-18T18:30",
      duration_minutes: 90,
      guest_count: 4,
      branch_id: 2,
    });

    expect(mocks.getV1TablesAvailable).toHaveBeenCalledWith({
      from: new Date(2026, 3, 18, 18, 30, 0, 0).toISOString(),
      to: new Date(2026, 3, 18, 20, 0, 0, 0).toISOString(),
      guest_count: 4,
      branch_id: 2,
      session_id: "session-123",
      suggest: true,
    });
  });

  it("creates holds with session propagation and idempotent request options", async () => {
    mocks.postV1TableHolds.mockResolvedValue({ data: { hold_id: "hold-1" } });

    await createTableHold(
      {
        start_time: "2026-04-18T18:30",
        duration_minutes: 90,
        guest_count: 4,
        branch_id: 2,
      },
      [7, 8],
    );

    expect(mocks.idempotentSessionOptions).toHaveBeenCalledWith("table-hold-create");
    expect(mocks.postV1TableHolds).toHaveBeenCalledWith(
      {
        session_id: "session-123",
        start_time: new Date(2026, 3, 18, 18, 30, 0, 0).toISOString(),
        end_time: new Date(2026, 3, 18, 20, 0, 0, 0).toISOString(),
        table_ids: [7, 8],
        branch_id: 2,
      },
      { idempotencyKey: "idem-123" },
    );
  });

  it("refreshes holds with the current session, row version, and idempotency options", async () => {
    mocks.patchV1TableHoldsHoldIdRefresh.mockResolvedValue({ data: { hold_id: "hold-1" } });

    await refreshTableHold("hold-1", 3);

    expect(mocks.idempotentSessionOptions).toHaveBeenCalledWith("table-hold-refresh");
    expect(mocks.patchV1TableHoldsHoldIdRefresh).toHaveBeenCalledWith(
      { hold_id: "hold-1" },
      {
        session_id: "session-123",
        row_version: 3,
        extend_minutes: 10,
      },
      { idempotencyKey: "idem-123" },
    );
  });

  it("cancels holds with the current session, row version, and idempotency options", async () => {
    mocks.deleteV1TableHoldsHoldId.mockResolvedValue({ data: { hold_id: "hold-1" } });

    await cancelTableHold("hold-1", 4);

    expect(mocks.idempotentSessionOptions).toHaveBeenCalledWith("table-hold-cancel");
    expect(mocks.deleteV1TableHoldsHoldId).toHaveBeenCalledWith(
      { hold_id: "hold-1" },
      {
        session_id: "session-123",
        row_version: 4,
      },
      { idempotencyKey: "idem-123" },
    );
  });
});
