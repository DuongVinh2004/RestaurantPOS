import { stripJourneySearch } from '../../core/utils/journey';

export type ReservationBucket = 'upcoming' | 'today' | 'all' | 'history';

export type ReservationsUrlState = {
  bucket: ReservationBucket;
  q: string;
  reservationId: number | null;
};

export function readReservationsUrlState(search: string | URLSearchParams): ReservationsUrlState {
  const params = toSearchParams(search);

  return {
    bucket: readBucket(params.get('bucket')),
    q: params.get('q')?.trim() ?? '',
    reservationId: readPositiveInteger(params.get('reservation')),
  };
}

export function buildReservationsSearch(
  currentSearch: string | URLSearchParams,
  patch: Partial<ReservationsUrlState>,
): string {
  const params = toSearchParams(stripJourneySearch(currentSearch));
  const merged = {
    ...readReservationsUrlState(params),
    ...patch,
  } satisfies ReservationsUrlState;

  setOrDelete(params, 'bucket', merged.bucket !== 'upcoming' ? merged.bucket : null);
  setOrDelete(params, 'q', merged.q !== '' ? merged.q : null);
  setOrDelete(params, 'reservation', merged.reservationId ? String(merged.reservationId) : null);

  return params.toString();
}

function readBucket(value: string | null): ReservationBucket {
  return value === 'today' || value === 'all' || value === 'history' ? value : 'upcoming';
}

function readPositiveInteger(value: string | null): number | null {
  if (!value) {
    return null;
  }

  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function toSearchParams(search: string | URLSearchParams): URLSearchParams {
  if (search instanceof URLSearchParams) {
    return new URLSearchParams(search);
  }

  return new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);
}

function setOrDelete(params: URLSearchParams, key: string, value: string | null): void {
  if (!value) {
    params.delete(key);
    return;
  }

  params.set(key, value);
}
