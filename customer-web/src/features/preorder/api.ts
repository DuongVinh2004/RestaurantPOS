import { unwrapData } from "@/lib/api/envelope";
import { apiCall, idempotentSessionOptions } from "@/lib/api/sdk-client";
import type {
  CustomerReservationPreorderEnvelope,
  PreviewCustomerReservationPreorderRequest,
  ReplaceCustomerReservationPreorderRequest,
} from "@/lib/contracts/generated/restaurantpos-sdk";

export type ReservationPreorderResult = CustomerReservationPreorderEnvelope["data"];

export function getReservationPreorder(reservationId: number): Promise<ReservationPreorderResult> {
  return apiCall((client) => client.getV1ReservationsIdPreorder({ id: reservationId })).then(unwrapData);
}

export function previewReservationPreorder(
  reservationId: number,
  body: PreviewCustomerReservationPreorderRequest,
): Promise<ReservationPreorderResult> {
  return apiCall((client) =>
    client.postV1ReservationsIdPreorderPreview({ id: reservationId }, body, idempotentSessionOptions("reservation-preorder-preview")),
  ).then(unwrapData);
}

export function replaceReservationPreorder(
  reservationId: number,
  body: ReplaceCustomerReservationPreorderRequest,
): Promise<ReservationPreorderResult> {
  return apiCall((client) =>
    client.putV1ReservationsIdPreorder({ id: reservationId }, body, idempotentSessionOptions("reservation-preorder-replace")),
  ).then(unwrapData);
}

export function submitReservationPreorder(
  reservationId: number,
  rowVersion: number,
  preOrderRowVersion?: number | null,
): Promise<ReservationPreorderResult> {
  return apiCall((client) =>
    client.postV1reservationsidpreordersubmit(
      { id: reservationId },
      {
        row_version: rowVersion,
        pre_order_row_version: preOrderRowVersion ?? undefined,
      },
      idempotentSessionOptions("reservation-preorder-submit")
    ),
  ).then(unwrapData) as Promise<ReservationPreorderResult>;
}

export function clearReservationPreorder(
  reservationId: number,
  rowVersion: number,
  preOrderRowVersion?: number | null,
): Promise<ReservationPreorderResult> {
  return apiCall((client) =>
    client.deleteV1ReservationsIdPreorder(
      { id: reservationId },
      {
        row_version: rowVersion,
        pre_order_row_version: preOrderRowVersion ?? undefined,
      },
      idempotentSessionOptions("reservation-preorder-clear"),
    ),
  ).then(unwrapData);
}
