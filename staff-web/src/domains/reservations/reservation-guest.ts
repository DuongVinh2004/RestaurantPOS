type ReservationGuestIdentity = {
  guest?: {
    full_name?: string | null;
    phone?: string | null;
    is_snapshot_only?: boolean | null;
  } | null;
  user?: {
    full_name?: string | null;
    phone?: string | null;
  } | null;
} | null | undefined;

export const RESERVATION_SNAPSHOT_GUEST_LABEL = 'Khách snapshot';

export function getReservationGuestLabel(
  reservation: ReservationGuestIdentity,
  fallback = 'Khách vãng lai',
): string {
  return reservation?.user?.full_name
    ?? reservation?.user?.phone
    ?? reservation?.guest?.full_name
    ?? reservation?.guest?.phone
    ?? fallback;
}

export function isReservationSnapshotOnlyGuest(reservation: ReservationGuestIdentity): boolean {
  return Boolean(reservation?.guest?.is_snapshot_only);
}
