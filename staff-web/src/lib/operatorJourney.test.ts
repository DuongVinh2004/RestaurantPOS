import { describe, expect, it } from 'vitest';
import { buildOperatorJourneySearch, readOperatorJourneyContext } from './operatorJourney';

describe('operatorJourney', () => {
  it('builds and parses board handoff context with positive integer fields only', () => {
    const search = buildOperatorJourneySearch({
      source: 'board',
      tableId: 10,
      reservationId: 77,
      reservationRowVersion: 9,
      orderId: 9001,
      orderRowVersion: 14,
    });

    expect(search).toContain('source=board');
    expect(search).toContain('table_id=10');
    expect(search).toContain('reservation_id=77');
    expect(search).toContain('reservation_row_version=9');
    expect(search).toContain('order_id=9001');
    expect(search).toContain('order_row_version=14');

    expect(readOperatorJourneyContext(search)).toEqual({
      source: 'board',
      tableId: 10,
      reservationId: 77,
      reservationRowVersion: 9,
      orderId: 9001,
      orderRowVersion: 14,
    });
  });

  it('drops invalid or non-positive query values', () => {
    expect(readOperatorJourneyContext('?source=unknown&table_id=abc&reservation_id=-1&order_id=0&order_row_version=-4')).toEqual({
      source: undefined,
      tableId: undefined,
      reservationId: undefined,
      reservationRowVersion: undefined,
      orderId: undefined,
      orderRowVersion: undefined,
    });
  });
});
