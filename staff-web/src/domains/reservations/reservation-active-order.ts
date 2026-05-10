import type { StaffReservationLookupEntry } from '../../shared/api/sdk';

export function shouldLookupActiveOrder(reservation: StaffReservationLookupEntry | null): boolean {
  if (!reservation) {
    return false;
  }

  if (reservation.checked_in_at) {
    return true;
  }

  const normalizedStatus = reservation.status.trim().toLowerCase();
  return normalizedStatus === 'checkedin' || normalizedStatus === 'checked_in' || normalizedStatus === 'reserved';
}
