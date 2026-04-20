import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { unwrapData } from "@/lib/api/envelope";
import { apiCall, idempotentSessionOptions } from "@/lib/api/sdk-client";
import type {
  CustomerActiveOrderEnvelope,
  CustomerBillPaymentSessionEnvelope,
  CustomerBillPreviewEnvelope,
  CustomerReservationBillEnvelope,
} from "@/lib/contracts/generated/restaurantpos-sdk";

export type ActiveOrderResult = CustomerActiveOrderEnvelope["data"];
export type BillPreviewResult = CustomerBillPreviewEnvelope["data"];
export type BillResult = CustomerReservationBillEnvelope["data"];
export type BillPaymentSessionResult = CustomerBillPaymentSessionEnvelope["data"];

export function getActiveOrder(reservationId: number): Promise<ActiveOrderResult> {
  return apiCall((client) => client.getV1ReservationsReservationIdActiveOrder({ reservation_id: reservationId })).then(unwrapData);
}

export function getBillPreview(reservationId: number): Promise<BillPreviewResult> {
  return apiCall((client) => client.getV1ReservationsReservationIdBillPreview({ reservation_id: reservationId })).then(unwrapData);
}

export function getBill(reservationId: number): Promise<BillResult> {
  return apiCall((client) => client.getV1ReservationsReservationIdBill({ reservation_id: reservationId })).then(unwrapData);
}

export function createBillPaymentSession(
  reservationId: number,
  rowVersion: number,
): Promise<BillPaymentSessionResult> {
  return apiCall((client) =>
    client.postV1ReservationsReservationIdBillPaymentSessions(
      { reservation_id: reservationId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("bill-payment-session-create"),
    ),
  ).then(unwrapData);
}

export function refreshBillPaymentSession(
  reservationId: number,
  sessionId: number,
  rowVersion: number,
): Promise<BillPaymentSessionResult> {
  return apiCall((client) =>
    client.postV1ReservationsReservationIdBillPaymentSessionsSessionIdRefresh(
      { reservation_id: reservationId, session_id: sessionId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("bill-payment-session-refresh"),
    ),
  ).then(unwrapData);
}

export function confirmBillPaymentSession(
  reservationId: number,
  sessionId: number,
  rowVersion: number,
): Promise<BillPaymentSessionResult> {
  return apiCall((client) =>
    client.postV1ReservationsReservationIdBillPaymentSessionsSessionIdConfirm(
      { reservation_id: reservationId, session_id: sessionId },
      { row_version: rowVersion, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("bill-payment-session-confirm"),
    ),
  ).then(unwrapData);
}
