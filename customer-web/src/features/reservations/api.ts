import { ensureCustomerSessionId, getCustomerToken } from "@/lib/auth/storage";
import { unwrapData } from "@/lib/api/envelope";
import { apiCall, idempotentSessionOptions } from "@/lib/api/sdk-client";
import { createStableIdempotencyKey } from "@/lib/api/idempotency";
import type {
  CustomerRescheduleReservationRequest,
  ReservationActionEnvelope,
  ReservationEnvelope,
  ReservationSelfServiceCollectionEnvelope,
  ReservationSummary,
  StoreReservationRequest,
} from "@/lib/contracts/generated/restaurantpos-sdk";
import { reservationTimes, type ReservationFormValues } from "./schemas";

export type ReservationList = ReservationSelfServiceCollectionEnvelope["data"];

export async function listReservations(bucket: "upcoming" | "history" | "all" = "upcoming"): Promise<ReservationList> {
  return unwrapData(await apiCall((client) => client.getV1Reservations({ bucket })));
}

export async function getReservation(id: number): Promise<ReservationEnvelope["data"]> {
  return unwrapData(await apiCall((client) => client.getV1ReservationsId({ id })));
}

export async function createReservation(
  values: ReservationFormValues & { hold_id?: string | null; table_ids?: number[] },
): Promise<ReservationEnvelope["data"]> {
  const times = reservationTimes(values);
  const usesCustomerAuth = Boolean(getCustomerToken());
  const sessionId = ensureCustomerSessionId();
  const body: StoreReservationRequest = {
    ...(usesCustomerAuth
      ? {}
      : {
          guest_name: values.guest_name,
          guest_phone: values.guest_phone,
          guest_email: values.guest_email || undefined,
        }),
    guest_count: values.guest_count,
    start_time: times.start_time,
    end_time: times.end_time,
    hold_id: values.hold_id ?? undefined,
    session_id: sessionId,
    table_ids: values.table_ids,
    notes: values.notes || undefined,
  };
  const idempotencyKey = createStableIdempotencyKey("reservation-create", {
    session_id: sessionId,
    hold_id: body.hold_id ?? null,
    table_ids: body.table_ids ?? [],
    start_time: body.start_time,
    end_time: body.end_time,
    guest_count: body.guest_count,
    guest_name: body.guest_name ?? null,
    guest_phone: body.guest_phone ?? null,
    guest_email: body.guest_email ?? null,
    notes: body.notes ?? null,
    authenticated_customer: usesCustomerAuth,
  });

  return unwrapData(
    await apiCall((client) =>
      client.postV1Reservations(body, idempotentSessionOptions("reservation-create", { idempotencyKey })),
    ),
  );
}

export async function cancelReservation(id: number, rowVersion: number, reason?: string): Promise<ReservationSummary> {
  return unwrapData(
    await apiCall((client) =>
      client.postV1ReservationsIdCancel(
        { id },
        { row_version: rowVersion, cancel_reason: reason || undefined, session_id: ensureCustomerSessionId() },
        idempotentSessionOptions("reservation-cancel"),
      ),
    ),
  );
}

export function rescheduleReservation(
  id: number,
  body: Omit<CustomerRescheduleReservationRequest, "session_id">,
): Promise<ReservationActionEnvelope["data"]> {
  return apiCall((client) =>
    client.postV1ReservationsIdReschedule(
      { id },
      { ...body, session_id: ensureCustomerSessionId() },
      idempotentSessionOptions("reservation-reschedule"),
    ),
  ).then(unwrapData);
}

export function mergeReservationInList(reservations: ReservationList | undefined, reservation: ReservationSummary): ReservationList | undefined {
  if (!reservations) {
    return reservations;
  }

  let replaced = false;
  const next = reservations.map((entry) => {
    if (entry.reservation_id !== reservation.reservation_id) {
      return entry;
    }

    replaced = true;
    return {
      ...entry,
      ...reservation,
    };
  });

  return replaced ? next : reservations;
}
