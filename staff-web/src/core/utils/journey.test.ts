import { describe, expect, it } from 'vitest';
import {
  buildJourneyResumeTarget,
  buildJourneySearch,
  mergeJourneyContext,
  mergeJourneySearch,
  readJourneyContext,
  stripJourneySearch,
} from './journey';

describe('journey helpers', () => {
  it('builds and reads journey context consistently', () => {
    const search = buildJourneySearch({
      source: 'board',
      tableId: 18,
      tableIds: [18, 21],
      reservationId: 24,
      reservationRowVersion: 7,
      orderId: 33,
      orderRowVersion: 5,
      stationId: 2,
    });

    expect(readJourneyContext(search)).toEqual({
      source: 'board',
      tableId: 18,
      tableIds: [18, 21],
      reservationId: 24,
      reservationRowVersion: 7,
      orderId: 33,
      orderRowVersion: 5,
      stationId: 2,
    });
  });

  it('drops invalid values', () => {
    expect(readJourneyContext('?table_id=abc&order_id=-1')).toEqual({
      source: undefined,
      tableId: undefined,
      tableIds: undefined,
      reservationId: undefined,
      reservationRowVersion: undefined,
      orderId: undefined,
      orderRowVersion: undefined,
      stationId: undefined,
    });
  });

  it('merges explicit route journey over stored fallback context', () => {
    expect(mergeJourneyContext(
      {
        source: 'checkout',
        orderId: 19,
      },
      {
        source: 'board',
        tableId: 8,
        tableIds: [8, 12],
        reservationId: 11,
        reservationRowVersion: 3,
        orderId: 7,
        orderRowVersion: 4,
        stationId: 2,
      },
    )).toEqual({
      source: 'checkout',
      tableId: 8,
      tableIds: [8, 12],
      reservationId: 11,
      reservationRowVersion: 3,
      orderId: 19,
      orderRowVersion: 4,
      stationId: 2,
    });
  });

  it('builds a resume target that preserves the current operational context', () => {
    expect(buildJourneyResumeTarget({
      source: 'checkout',
      tableId: 18,
      tableIds: [18, 21],
      reservationId: 24,
      reservationRowVersion: 7,
      orderId: 33,
      orderRowVersion: 5,
    })).toEqual({
      path: '/checkout?source=checkout&table_id=18&table_ids=18%2C21&reservation_id=24&reservation_row_version=7&order_id=33&order_row_version=5',
      label: 'Tiếp tục thanh toán',
    });
  });

  it('builds a refund resume target when the route is already focused on refund work', () => {
    expect(buildJourneyResumeTarget({
      source: 'refund',
      reservationId: 24,
      reservationRowVersion: 7,
      orderId: 33,
      orderRowVersion: 5,
    })).toEqual({
      path: '/refunds?source=refund&reservation_id=24&reservation_row_version=7&order_id=33&order_row_version=5',
      label: 'Tiếp tục hoàn tiền',
    });
  });

  it('falls back to a safe route when the source is absent but context remains', () => {
    expect(buildJourneyResumeTarget({
      reservationId: 24,
      reservationRowVersion: 7,
    })).toEqual({
      path: '/reservations?reservation_id=24&reservation_row_version=7',
      label: 'Tiếp tục đặt bàn',
    });
  });

  it('keeps audit as a trace source while resuming the concrete operational route', () => {
    expect(buildJourneyResumeTarget({
      source: 'audit',
      reservationId: 24,
    })).toEqual({
      path: '/reservations?source=audit&reservation_id=24',
      label: 'Tiếp tục đặt bàn',
    });
  });

  it('recovers the primary table from multi-table metadata when only table_ids is present', () => {
    expect(readJourneyContext('?source=reservation&table_ids=12,14&reservation_id=24')).toEqual({
      source: 'reservation',
      tableId: 12,
      tableIds: [12, 14],
      reservationId: 24,
      reservationRowVersion: undefined,
      orderId: undefined,
      orderRowVersion: undefined,
      stationId: undefined,
    });
  });

  it('removes route-owned operational params while preserving screen-local query state', () => {
    expect(stripJourneySearch('?source=checkout&table_ids=18,21&order_id=33&reservation_id=24&page=2&status=Open')).toBe('page=2&status=Open');
  });

  it('replaces journey params while preserving screen-local query state', () => {
    expect(mergeJourneySearch(
      '?page=2&status=Open&source=checkout&order_id=33&reservation_id=24',
      {
        source: 'board',
        tableId: 18,
        tableIds: [18, 21],
      },
    )).toBe('page=2&status=Open&source=board&table_id=18&table_ids=18%2C21');
  });
});
