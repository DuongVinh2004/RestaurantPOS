import { ensureCustomerSessionId, getCustomerToken } from "@/lib/auth/storage";
import { unwrapData } from "@/lib/api/envelope";
import { apiCall, idempotentSessionOptions } from "@/lib/api/sdk-client";
import { createStableIdempotencyKey } from "@/lib/api/idempotency";
import type {
  CustomerRescheduleReservationRequest,
  CreateReservationRequest,
  ReservationActionEnvelope,
  ReservationEnvelope,
  ReservationSelfServiceCollectionEnvelope,
  ReservationSummary,
} from "@/lib/contracts/generated/restaurantpos-sdk";
import { reservationTimes, type ReservationFormValues } from "./schemas";

export type ReservationList = ReservationSelfServiceCollectionEnvelope["data"];

function normalizeTableIds(tableIds?: number[] | null): number[] | undefined {
  if (!Array.isArray(tableIds)) {
    return undefined;
  }

  const normalized = [...new Set(tableIds.filter((tableId) => Number.isInteger(tableId) && tableId > 0))].sort((left, right) => left - right);

  return normalized.length > 0 ? normalized : undefined;
}

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
  const normalizedTableIds = normalizeTableIds(values.table_ids);
  const body: CreateReservationRequest = {
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
    table_ids: normalizedTableIds,
    notes: values.notes || undefined,
  };
  const idempotencyKey = createStableIdempotencyKey("reservation-create", {
    session_id: sessionId,
    hold_id: body.hold_id ?? null,
    table_ids: normalizedTableIds ?? [],
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
  const sessionId = ensureCustomerSessionId();
  const idempotencyKey = createStableIdempotencyKey("reservation-cancel", {
    cancel_reason: reason ?? null,
    reservation_id: id,
    row_version: rowVersion,
    session_id: sessionId,
  });

  return unwrapData(
    await apiCall((client) =>
      client.postV1ReservationsIdCancel(
        { id },
        { row_version: rowVersion, cancel_reason: reason || undefined, session_id: sessionId },
        idempotentSessionOptions("reservation-cancel", { idempotencyKey }),
      ),
    ),
  );
}

export function rescheduleReservation(
  id: number,
  body: Omit<CustomerRescheduleReservationRequest, "session_id">,
): Promise<ReservationActionEnvelope["data"]> {
  const sessionId = ensureCustomerSessionId();
  const normalizedTableIds = normalizeTableIds(body.table_ids);
  const idempotencyKey = createStableIdempotencyKey("reservation-reschedule", {
    end_time: body.end_time ?? null,
    guest_count: body.guest_count ?? null,
    notes: body.notes ?? null,
    reason: body.reason ?? null,
    reservation_id: id,
    row_version: body.row_version,
    session_id: sessionId,
    start_time: body.start_time ?? null,
    table_ids: normalizedTableIds ?? [],
  });

  return apiCall((client) =>
    client.postV1ReservationsIdReschedule(
      { id },
      { ...body, session_id: sessionId, table_ids: normalizedTableIds },
      idempotentSessionOptions("reservation-reschedule", { idempotencyKey }),
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
