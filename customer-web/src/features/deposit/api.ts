import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { apiCall, idempotentSessionOptions } from "@/lib/api/sdk-client";
import type {
  CustomerDepositPaymentSessionEnvelope,
  CustomerReservationDepositPreviewEnvelope,
} from "@/lib/contracts/generated/restaurantpos-sdk";

export function getDepositPreview(reservationId: number): Promise<CustomerReservationDepositPreviewEnvelope> {
  return apiCall((client) => client.getV1ReservationsIdDepositPreview({ id: reservationId }));
}

export function acknowledgeDeposit(reservationId: number, rowVersion: number): Promise<CustomerReservationDepositPreviewEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsIdDepositAcknowledge(
      { id: reservationId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("deposit-acknowledge"),
    ),
  );
}

export function submitDepositIntent(reservationId: number, rowVersion: number): Promise<CustomerReservationDepositPreviewEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsIdDepositIntent(
      { id: reservationId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("deposit-intent"),
    ),
  );
}

export function revokeDepositIntent(reservationId: number, rowVersion: number): Promise<CustomerReservationDepositPreviewEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsIdDepositIntentRevoke(
      { id: reservationId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("deposit-intent-revoke"),
    ),
  );
}

export function createDepositPaymentSession(
  reservationId: number,
  rowVersion: number,
): Promise<CustomerDepositPaymentSessionEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsReservationIdDepositPaymentSessions(
      { reservation_id: reservationId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("deposit-payment-session-create"),
    ),
  );
}

export function refreshDepositPaymentSession(
  reservationId: number,
  sessionId: number,
  rowVersion: number,
): Promise<CustomerDepositPaymentSessionEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsReservationIdDepositPaymentSessionsSessionIdRefresh(
      { reservation_id: reservationId, session_id: sessionId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("deposit-payment-session-refresh"),
    ),
  );
}

export function confirmDepositPaymentSession(
  reservationId: number,
  sessionId: number,
  rowVersion: number,
): Promise<CustomerDepositPaymentSessionEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsReservationIdDepositPaymentSessionsSessionIdConfirm(
      { reservation_id: reservationId, session_id: sessionId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("deposit-payment-session-confirm"),
    ),
  );
}
