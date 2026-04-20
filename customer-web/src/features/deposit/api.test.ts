import { beforeEach, describe, expect, it, vi } from "vitest";
import { acknowledgeDeposit, createDepositPaymentSession, refreshDepositPaymentSession } from "./api";

const mocks = vi.hoisted(() => ({
  ensureCustomerSessionId: vi.fn(),
  idempotentSessionOptions: vi.fn(),
  apiCall: vi.fn(),
  postV1ReservationsIdDepositAcknowledge: vi.fn(),
  postV1ReservationsReservationIdDepositPaymentSessions: vi.fn(),
  postV1ReservationsReservationIdDepositPaymentSessionsSessionIdRefresh: vi.fn(),
}));

vi.mock("@/lib/auth/storage", () => ({
  ensureCustomerSessionId: mocks.ensureCustomerSessionId,
}));

vi.mock("@/lib/api/sdk-client", () => ({
  apiCall: mocks.apiCall,
  idempotentSessionOptions: mocks.idempotentSessionOptions,
}));

describe("deposit api adapter", () => {
  beforeEach(() => {
    mocks.ensureCustomerSessionId.mockReset();
    mocks.idempotentSessionOptions.mockReset();
    mocks.apiCall.mockReset();
    mocks.postV1ReservationsIdDepositAcknowledge.mockReset();
    mocks.postV1ReservationsReservationIdDepositPaymentSessions.mockReset();
    mocks.postV1ReservationsReservationIdDepositPaymentSessionsSessionIdRefresh.mockReset();

    mocks.ensureCustomerSessionId.mockReturnValue("session-deposit");
    mocks.idempotentSessionOptions.mockImplementation((scope: string) => ({ idempotencyKey: `idem:${scope}` }));
    mocks.apiCall.mockImplementation(async (operation: (client: unknown) => unknown) =>
      operation({
        postV1ReservationsIdDepositAcknowledge: mocks.postV1ReservationsIdDepositAcknowledge,
        postV1ReservationsReservationIdDepositPaymentSessions: mocks.postV1ReservationsReservationIdDepositPaymentSessions,
        postV1ReservationsReservationIdDepositPaymentSessionsSessionIdRefresh:
          mocks.postV1ReservationsReservationIdDepositPaymentSessionsSessionIdRefresh,
      }),
    );
  });

  it("sends row_version, session_id, and idempotency options for deposit acknowledge", async () => {
    mocks.postV1ReservationsIdDepositAcknowledge.mockResolvedValue({ data: { reservation: { reservation_id: 7 }, deposit: {} } });

    await acknowledgeDeposit(7, 14);

    expect(mocks.idempotentSessionOptions).toHaveBeenCalledWith("deposit-acknowledge");
    expect(mocks.postV1ReservationsIdDepositAcknowledge).toHaveBeenCalledWith(
      { id: 7 },
      { row_version: 14, session_id: "session-deposit" },
      { idempotencyKey: "idem:deposit-acknowledge" },
    );
  });

  it("sends row_version, session_id, and idempotency options for payment session create and refresh", async () => {
    mocks.postV1ReservationsReservationIdDepositPaymentSessions.mockResolvedValue({ data: { payment_session: { row_version: 21 } } });
    mocks.postV1ReservationsReservationIdDepositPaymentSessionsSessionIdRefresh.mockResolvedValue({
      data: { payment_session: { row_version: 22 } },
    });

    await createDepositPaymentSession(7, 21);
    await refreshDepositPaymentSession(7, 301, 22);

    expect(mocks.postV1ReservationsReservationIdDepositPaymentSessions).toHaveBeenCalledWith(
      { reservation_id: 7 },
      { row_version: 21, session_id: "session-deposit" },
      { idempotencyKey: "idem:deposit-payment-session-create" },
    );
    expect(mocks.postV1ReservationsReservationIdDepositPaymentSessionsSessionIdRefresh).toHaveBeenCalledWith(
      { reservation_id: 7, session_id: 301 },
      { row_version: 22, session_id: "session-deposit" },
      { idempotencyKey: "idem:deposit-payment-session-refresh" },
    );
  });
});
