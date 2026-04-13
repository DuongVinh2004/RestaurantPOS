import { describe, expect, it } from 'vitest';
import { buildReservationsSearch, readReservationsUrlState } from './reservations-url';

describe('reservations url helpers', () => {
  it('reads bucket, query, and selected reservation from the url', () => {
    expect(readReservationsUrlState('?bucket=today&q=walk-in&reservation=77')).toEqual({
      bucket: 'today',
      q: 'walk-in',
      reservationId: 77,
    });
  });

  it('strips journey params while preserving reservations list state', () => {
    expect(buildReservationsSearch(
      '?source=board&table_id=8&reservation_id=14&bucket=history&q=late&reservation=22',
      {
        reservationId: 99,
      },
    )).toBe('bucket=history&q=late&reservation=99');
  });
});
