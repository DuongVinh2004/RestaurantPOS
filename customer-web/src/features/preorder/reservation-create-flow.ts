import { createReservation } from "@/features/reservations/api";
import type { ReservationFormValues } from "@/features/reservations/schemas";
import {
  getReservationPreorder,
  previewReservationPreorder,
  replaceReservationPreorder,
  type ReservationPreorderResult,
} from "./api";
import {
  normalizePreorderCart,
  type PreorderCartItem,
} from "./cart";
import type { ReservationPreorderFailureStage } from "./reservation-draft-storage";

export type ReservationCreateInput = ReservationFormValues & {
  hold_id?: string | null;
  table_ids?: number[];
};

export type CreatedReservationResult = Awaited<ReturnType<typeof createReservation>>;

export type CreateReservationWithPreorderResult = {
  reservation: CreatedReservationResult;
  preorder: ReservationPreorderResult | null;
};

type ReservationCreateFlowDependencies = {
  createReservation: typeof createReservation;
  getReservationPreorder: typeof getReservationPreorder;
  previewReservationPreorder: typeof previewReservationPreorder;
  replaceReservationPreorder: typeof replaceReservationPreorder;
};

const defaultDependencies: ReservationCreateFlowDependencies = {
  createReservation,
  getReservationPreorder,
  previewReservationPreorder,
  replaceReservationPreorder,
};

export class ReservationPreorderPersistenceError extends Error {
  readonly reservation: CreatedReservationResult;
  readonly stage: ReservationPreorderFailureStage;
  override readonly cause: unknown;

  constructor(
    reservation: CreatedReservationResult,
    stage: ReservationPreorderFailureStage,
    cause: unknown,
  ) {
    super(`Reservation ${reservation.reservation_id} created but preorder ${stage} step failed.`);
    this.name = "ReservationPreorderPersistenceError";
    this.reservation = reservation;
    this.stage = stage;
    this.cause = cause;
  }
}

export function isReservationPreorderPersistenceError(
  error: unknown,
): error is ReservationPreorderPersistenceError {
  return error instanceof ReservationPreorderPersistenceError;
}

export async function createReservationWithPreorderDraft(
  input: {
    reservationInput: ReservationCreateInput;
    preorderItems: PreorderCartItem[];
  },
  dependencies: ReservationCreateFlowDependencies = defaultDependencies,
): Promise<CreateReservationWithPreorderResult> {
  const reservation = await dependencies.createReservation(input.reservationInput);
  const preorderItems = normalizePreorderCart(input.preorderItems);

  if (preorderItems.length === 0) {
    return {
      reservation,
      preorder: null,
    };
  }

  let snapshot: ReservationPreorderResult;

  try {
    snapshot = await dependencies.getReservationPreorder(reservation.reservation_id);
  } catch (error) {
    throw new ReservationPreorderPersistenceError(reservation, "snapshot", error);
  }

  try {
    await dependencies.previewReservationPreorder(reservation.reservation_id, {
      pre_order_items: preorderItems,
    });
  } catch (error) {
    throw new ReservationPreorderPersistenceError(reservation, "preview", error);
  }

  try {
    const preorder = await dependencies.replaceReservationPreorder(
      reservation.reservation_id,
      {
        pre_order_items: preorderItems,
        row_version: snapshot.reservation_row_version,
        pre_order_row_version: snapshot.pre_order.order_row_version,
      },
    );

    return {
      reservation,
      preorder,
    };
  } catch (error) {
    throw new ReservationPreorderPersistenceError(reservation, "replace", error);
  }
}
