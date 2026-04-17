import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { apiCall, idempotentSessionOptions } from "@/lib/api/sdk-client";
import type {
  CustomerRescheduleReservationRequest,
  ReservationActionEnvelope,
  ReservationEnvelope,
  ReservationSelfServiceCollectionEnvelope,
  StoreReservationRequest,
} from "@/lib/contracts/generated/restaurantpos-sdk";
import { reservationTimes, type ReservationFormValues } from "./schemas";

export function listReservations(bucket: "upcoming" | "history" | "all" = "upcoming"): Promise<ReservationSelfServiceCollectionEnvelope> {
  return apiCall((client) => client.getV1Reservations({ bucket }));
}

export function getReservation(id: number): Promise<ReservationEnvelope> {
  return apiCall((client) => client.getV1ReservationsId({ id }));
}

export function createReservation(values: ReservationFormValues & { hold_id?: string | null; table_ids?: number[] }): Promise<ReservationEnvelope> {
  const times = reservationTimes(values);
  const body: StoreReservationRequest = {
    guest_name: values.guest_name,
    guest_phone: values.guest_phone,
    guest_email: values.guest_email || undefined,
    guest_count: values.guest_count,
    start_time: times.start_time,
    end_time: times.end_time,
    hold_id: values.hold_id ?? undefined,
    session_id: ensureCustomerSessionId(),
    table_ids: values.table_ids,
    notes: values.notes || undefined,
  };

  return apiCall((client) => client.postV1Reservations(body, idempotentSessionOptions("reservation-create")));
}

export function cancelReservation(id: number, rowVersion: number, reason?: string): Promise<ReservationActionEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsIdCancel(
      { id },
      { row_version: rowVersion, cancel_reason: reason || undefined, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("reservation-cancel"),
    ),
  );
}

export function rescheduleReservation(
  id: number,
  body: Omit<CustomerRescheduleReservationRequest, "session_id">,
): Promise<ReservationActionEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsIdReschedule(
      { id },
      { ...body, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("reservation-reschedule"),
    ),
  );
}
