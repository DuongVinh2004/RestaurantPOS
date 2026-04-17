import { apiCall, idempotentSessionOptions } from "@/lib/api/sdk-client";
import type {
  CustomerReservationPreorderEnvelope,
  PreviewCustomerReservationPreorderRequest,
  ReplaceCustomerReservationPreorderRequest,
} from "@/lib/contracts/generated/restaurantpos-sdk";

export function getReservationPreorder(reservationId: number): Promise<CustomerReservationPreorderEnvelope> {
  return apiCall((client) => client.getV1ReservationsIdPreorder({ id: reservationId }));
}

export function previewReservationPreorder(
  reservationId: number,
  body: PreviewCustomerReservationPreorderRequest,
): Promise<CustomerReservationPreorderEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsIdPreorderPreview({ id: reservationId }, body, idempotentSessionOptions("reservation-preorder-preview")),
  );
}

export function replaceReservationPreorder(
  reservationId: number,
  body: ReplaceCustomerReservationPreorderRequest,
): Promise<CustomerReservationPreorderEnvelope> {
  return apiCall((client) =>
    client.putV1ReservationsIdPreorder({ id: reservationId }, body, idempotentSessionOptions("reservation-preorder-replace")),
  );
}

export function clearReservationPreorder(
  reservationId: number,
  rowVersion: number,
  preOrderRowVersion?: number | null,
): Promise<CustomerReservationPreorderEnvelope> {
  return apiCall((client) =>
    client.deleteV1ReservationsIdPreorder(
      { id: reservationId },
      {
        row_version: rowVersion,
        pre_order_row_version: preOrderRowVersion ?? undefined,
      },
      idempotentSessionOptions("reservation-preorder-clear"),
    ),
  );
}
