import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { apiCall, idempotentSessionOptions } from "@/lib/api/sdk-client";
import type {
  CustomerActiveOrderEnvelope,
  CustomerBillPaymentSessionEnvelope,
  CustomerBillPreviewEnvelope,
  CustomerReservationBillEnvelope,
} from "@/lib/contracts/generated/restaurantpos-sdk";

export function getActiveOrder(reservationId: number): Promise<CustomerActiveOrderEnvelope> {
  return apiCall((client) => client.getV1ReservationsReservationIdActiveOrder({ reservation_id: reservationId }));
}

export function getBillPreview(reservationId: number): Promise<CustomerBillPreviewEnvelope> {
  return apiCall((client) => client.getV1ReservationsReservationIdBillPreview({ reservation_id: reservationId }));
}

export function getBill(reservationId: number): Promise<CustomerReservationBillEnvelope> {
  return apiCall((client) => client.getV1ReservationsReservationIdBill({ reservation_id: reservationId }));
}

export function createBillPaymentSession(
  reservationId: number,
  rowVersion: number,
): Promise<CustomerBillPaymentSessionEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsReservationIdBillPaymentSessions(
      { reservation_id: reservationId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("bill-payment-session-create"),
    ),
  );
}

export function refreshBillPaymentSession(
  reservationId: number,
  sessionId: number,
  rowVersion: number,
): Promise<CustomerBillPaymentSessionEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsReservationIdBillPaymentSessionsSessionIdRefresh(
      { reservation_id: reservationId, session_id: sessionId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("bill-payment-session-refresh"),
    ),
  );
}

export function confirmBillPaymentSession(
  reservationId: number,
  sessionId: number,
  rowVersion: number,
): Promise<CustomerBillPaymentSessionEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsReservationIdBillPaymentSessionsSessionIdConfirm(
      { reservation_id: reservationId, session_id: sessionId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("bill-payment-session-confirm"),
    ),
  );
}
