import { beforeEach, describe, expect, it, vi } from "vitest";
import { cancelReservation, createReservation, rescheduleReservation } from "./api";

const mocks = vi.hoisted(() => ({
  ensureCustomerSessionId: vi.fn(),
  getCustomerToken: vi.fn(),
  idempotentSessionOptions: vi.fn(),
  createStableIdempotencyKey: vi.fn(),
  apiCall: vi.fn(),
  postV1Reservations: vi.fn(),
  postV1ReservationsIdCancel: vi.fn(),
  postV1ReservationsIdReschedule: vi.fn(),
}));

vi.mock("@/lib/auth/storage", () => ({
  ensureCustomerSessionId: mocks.ensureCustomerSessionId,
  getCustomerToken: mocks.getCustomerToken,
}));

vi.mock("@/lib/api/sdk-client", () => ({
  apiCall: mocks.apiCall,
  idempotentSessionOptions: mocks.idempotentSessionOptions,
}));

vi.mock("@/lib/api/idempotency", () => ({
  createStableIdempotencyKey: mocks.createStableIdempotencyKey,
}));

describe("reservations api", () => {
  beforeEach(() => {
    mocks.ensureCustomerSessionId.mockReset();
    mocks.idempotentSessionOptions.mockReset();
    mocks.createStableIdempotencyKey.mockReset();
    mocks.apiCall.mockReset();
    mocks.postV1Reservations.mockReset();
    mocks.postV1ReservationsIdCancel.mockReset();
    mocks.postV1ReservationsIdReschedule.mockReset();
    mocks.getCustomerToken.mockReset();

    mocks.ensureCustomerSessionId.mockReturnValue("session-456");
    mocks.getCustomerToken.mockReturnValue(null);
    mocks.createStableIdempotencyKey.mockReturnValue("idem-stable-456");
    mocks.idempotentSessionOptions.mockReturnValue({ idempotencyKey: "idem-456" });
    mocks.apiCall.mockImplementation(async (operation: (client: unknown) => unknown) =>
      operation({
        postV1Reservations: mocks.postV1Reservations,
        postV1ReservationsIdCancel: mocks.postV1ReservationsIdCancel,
        postV1ReservationsIdReschedule: mocks.postV1ReservationsIdReschedule,
      }),
    );
  });

  it("creates reservations with the active session, hold id, and UTC visit range", async () => {
    mocks.postV1Reservations.mockResolvedValue({ data: { reservation_id: 501 } });

    await createReservation({
      guest_name: "Demo Customer",
      guest_phone: "5550100",
      guest_email: "demo@example.test",
      start_time: "2026-04-18T18:30",
      duration_minutes: 90,
      guest_count: 2,
      notes: "Window seat",
      hold_id: "hold-123",
      table_ids: [7],
    });

    expect(mocks.createStableIdempotencyKey).toHaveBeenCalledWith(
      "reservation-create",
      expect.objectContaining({
        session_id: "session-456",
        hold_id: "hold-123",
        table_ids: [7],
        authenticated_customer: false,
      }),
    );
    expect(mocks.idempotentSessionOptions).toHaveBeenCalledWith("reservation-create", { idempotencyKey: "idem-stable-456" });
    expect(mocks.postV1Reservations).toHaveBeenCalledWith(
      {
        guest_name: "Demo Customer",
        guest_phone: "5550100",
        guest_email: "demo@example.test",
        guest_count: 2,
        start_time: new Date(2026, 3, 18, 18, 30, 0, 0).toISOString(),
        end_time: new Date(2026, 3, 18, 20, 0, 0, 0).toISOString(),
        hold_id: "hold-123",
        session_id: "session-456",
        table_ids: [7],
        notes: "Window seat",
      },
      { idempotencyKey: "idem-456" },
    );
  });

  it("omits guest-only fields for customer-authenticated reservation creates", async () => {
    mocks.getCustomerToken.mockReturnValue("token-customer");
    mocks.postV1Reservations.mockResolvedValue({ data: { reservation_id: 502 } });

    await createReservation({
      guest_name: "Ignored Customer",
      guest_phone: "5550100",
      guest_email: "ignored@example.test",
      start_time: "2026-04-18T18:30",
      duration_minutes: 90,
      guest_count: 2,
      notes: "",
      hold_id: "hold-456",
      table_ids: [8],
    });

    expect(mocks.postV1Reservations).toHaveBeenCalledWith(
      {
        guest_count: 2,
        start_time: new Date(2026, 3, 18, 18, 30, 0, 0).toISOString(),
        end_time: new Date(2026, 3, 18, 20, 0, 0, 0).toISOString(),
        hold_id: "hold-456",
        session_id: "session-456",
        table_ids: [8],
        notes: undefined,
      },
      { idempotencyKey: "idem-456" },
    );
  });

  it("sends reservation cancel mutations with the linked session id and idempotency options", async () => {
    mocks.postV1ReservationsIdCancel.mockResolvedValue({ data: { reservation_id: 7 } });

    await cancelReservation(7, 9, "Change of plans");

    expect(mocks.createStableIdempotencyKey).toHaveBeenCalledWith(
      "reservation-cancel",
      {
        cancel_reason: "Change of plans",
        reservation_id: 7,
        row_version: 9,
        session_id: "session-456",
      },
    );
    expect(mocks.idempotentSessionOptions).toHaveBeenCalledWith("reservation-cancel", { idempotencyKey: "idem-stable-456" });
    expect(mocks.postV1ReservationsIdCancel).toHaveBeenCalledWith(
      { id: 7 },
      {
        row_version: 9,
        cancel_reason: "Change of plans",
        session_id: "session-456",
      },
      { idempotencyKey: "idem-456" },
    );
  });

  it("sends reservation reschedule mutations with the linked session id and idempotency options", async () => {
    mocks.postV1ReservationsIdReschedule.mockResolvedValue({ data: { reservation_id: 7 } });

    await rescheduleReservation(7, {
      row_version: 9,
      start_time: "2026-04-18T18:30:00Z",
      end_time: "2026-04-18T20:00:00Z",
      guest_count: 4,
      reason: "Running late",
    });

    expect(mocks.createStableIdempotencyKey).toHaveBeenCalledWith(
      "reservation-reschedule",
      {
        end_time: "2026-04-18T20:00:00Z",
        guest_count: 4,
        notes: null,
        reason: "Running late",
        reservation_id: 7,
        row_version: 9,
        session_id: "session-456",
        start_time: "2026-04-18T18:30:00Z",
        table_ids: [],
      },
    );
    expect(mocks.idempotentSessionOptions).toHaveBeenCalledWith("reservation-reschedule", { idempotencyKey: "idem-stable-456" });
    expect(mocks.postV1ReservationsIdReschedule).toHaveBeenCalledWith(
      { id: 7 },
      {
        row_version: 9,
        start_time: "2026-04-18T18:30:00Z",
        end_time: "2026-04-18T20:00:00Z",
        guest_count: 4,
        reason: "Running late",
        session_id: "session-456",
      },
      { idempotencyKey: "idem-456" },
    );
  });
});
