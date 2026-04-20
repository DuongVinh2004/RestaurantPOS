import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { unwrapData } from "@/lib/api/envelope";
import { apiCall, idempotentSessionOptions } from "@/lib/api/sdk-client";
import type {
  CustomerDepositPaymentSessionEnvelope,
  CustomerReservationDepositPreviewEnvelope,
} from "@/lib/contracts/generated/restaurantpos-sdk";

export type DepositPreview = CustomerReservationDepositPreviewEnvelope["data"];
export type DepositPaymentSessionResult = CustomerDepositPaymentSessionEnvelope["data"];

export function getDepositPreview(reservationId: number): Promise<DepositPreview> {
  return apiCall((client) => client.getV1ReservationsIdDepositPreview({ id: reservationId })).then(unwrapData);
}

export function acknowledgeDeposit(reservationId: number, rowVersion: number): Promise<DepositPreview> {
  return apiCall((client) =>
    client.postV1ReservationsIdDepositAcknowledge(
      { id: reservationId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("deposit-acknowledge"),
    ),
  ).then(unwrapData);
}

export function submitDepositIntent(reservationId: number, rowVersion: number): Promise<DepositPreview> {
  return apiCall((client) =>
    client.postV1ReservationsIdDepositIntent(
      { id: reservationId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("deposit-intent"),
    ),
  ).then(unwrapData);
}

export function revokeDepositIntent(reservationId: number, rowVersion: number): Promise<DepositPreview> {
  return apiCall((client) =>
    client.postV1ReservationsIdDepositIntentRevoke(
      { id: reservationId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("deposit-intent-revoke"),
    ),
  ).then(unwrapData);
}

export function createDepositPaymentSession(
  reservationId: number,
  rowVersion: number,
): Promise<DepositPaymentSessionResult> {
  return apiCall((client) =>
    client.postV1ReservationsReservationIdDepositPaymentSessions(
      { reservation_id: reservationId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("deposit-payment-session-create"),
    ),
  ).then(unwrapData);
}

export function refreshDepositPaymentSession(
  reservationId: number,
  sessionId: number,
  rowVersion: number,
): Promise<DepositPaymentSessionResult> {
  return apiCall((client) =>
    client.postV1ReservationsReservationIdDepositPaymentSessionsSessionIdRefresh(
      { reservation_id: reservationId, session_id: sessionId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("deposit-payment-session-refresh"),
    ),
  ).then(unwrapData);
}

export function confirmDepositPaymentSession(
  reservationId: number,
  sessionId: number,
  rowVersion: number,
): Promise<DepositPaymentSessionResult> {
  return apiCall((client) =>
    client.postV1ReservationsReservationIdDepositPaymentSessionsSessionIdConfirm(
      { reservation_id: reservationId, session_id: sessionId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("deposit-payment-session-confirm"),
    ),
  ).then(unwrapData);
}
