import { beforeEach, describe, expect, it, vi } from "vitest";
import { createBillPaymentSession, getBillPaymentSession, refreshBillPaymentSession } from "./api";

const mocks = vi.hoisted(() => ({
  ensureCustomerSessionId: vi.fn(),
  idempotentSessionOptions: vi.fn(),
  apiCall: vi.fn(),
  postV1ReservationsReservationIdBillPaymentSessions: vi.fn(),
  getV1ReservationsReservationIdBillPaymentSessionsSessionId: vi.fn(),
  postV1ReservationsReservationIdBillPaymentSessionsSessionIdRefresh: vi.fn(),
}));

vi.mock("@/lib/auth/storage", () => ({
  ensureCustomerSessionId: mocks.ensureCustomerSessionId,
}));

vi.mock("@/lib/api/sdk-client", () => ({
  apiCall: mocks.apiCall,
  idempotentSessionOptions: mocks.idempotentSessionOptions,
}));

describe("billing api adapter", () => {
  beforeEach(() => {
    mocks.ensureCustomerSessionId.mockReset();
    mocks.idempotentSessionOptions.mockReset();
    mocks.apiCall.mockReset();
    mocks.postV1ReservationsReservationIdBillPaymentSessions.mockReset();
    mocks.getV1ReservationsReservationIdBillPaymentSessionsSessionId.mockReset();
    mocks.postV1ReservationsReservationIdBillPaymentSessionsSessionIdRefresh.mockReset();

    mocks.ensureCustomerSessionId.mockReturnValue("session-bill");
    mocks.idempotentSessionOptions.mockImplementation((scope: string) => ({ idempotencyKey: `idem:${scope}` }));
    mocks.apiCall.mockImplementation(async (operation: (client: unknown) => unknown) =>
      operation({
        postV1ReservationsReservationIdBillPaymentSessions: mocks.postV1ReservationsReservationIdBillPaymentSessions,
        getV1ReservationsReservationIdBillPaymentSessionsSessionId:
          mocks.getV1ReservationsReservationIdBillPaymentSessionsSessionId,
        postV1ReservationsReservationIdBillPaymentSessionsSessionIdRefresh:
          mocks.postV1ReservationsReservationIdBillPaymentSessionsSessionIdRefresh,
      }),
    );
  });

  it("sends row_version, session_id, and idempotency options for bill payment session create and refresh", async () => {
    mocks.postV1ReservationsReservationIdBillPaymentSessions.mockResolvedValue({ data: { payment_session: { row_version: 31 } } });
    mocks.getV1ReservationsReservationIdBillPaymentSessionsSessionId.mockResolvedValue({
      data: { payment_session: { row_version: 31 } },
    });
    mocks.postV1ReservationsReservationIdBillPaymentSessionsSessionIdRefresh.mockResolvedValue({
      data: { payment_session: { row_version: 32 } },
    });

    await createBillPaymentSession(7, 31);
    await getBillPaymentSession(7, 401);
    await refreshBillPaymentSession(7, 401, 32);

    expect(mocks.postV1ReservationsReservationIdBillPaymentSessions).toHaveBeenCalledWith(
      { reservation_id: 7 },
      { row_version: 31, session_id: "session-bill" },
      { idempotencyKey: "idem:bill-payment-session-create" },
    );
    expect(mocks.getV1ReservationsReservationIdBillPaymentSessionsSessionId).toHaveBeenCalledWith({
      reservation_id: 7,
      session_id: 401,
    });
    expect(mocks.postV1ReservationsReservationIdBillPaymentSessionsSessionIdRefresh).toHaveBeenCalledWith(
      { reservation_id: 7, session_id: 401 },
      { row_version: 32, session_id: "session-bill" },
      { idempotencyKey: "idem:bill-payment-session-refresh" },
    );
  });
});
