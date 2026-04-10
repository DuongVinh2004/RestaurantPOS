import { describe, expect, it } from 'vitest';
import { buildKitchenBoardSearch, readKitchenBoardUrlState } from './kitchen-board-url';

describe('kitchen board url helpers', () => {
  it('reads status and selected ticket from the url', () => {
    expect(readKitchenBoardUrlState('?status=Ready&ticket=91')).toEqual({
      status: 'Ready',
      ticketId: 91,
    });
  });

  it('writes local kitchen state while preserving journey params', () => {
    expect(buildKitchenBoardSearch(
      '?source=kitchen&station_id=3',
      { status: 'Fired', ticketId: 44 },
    )).toBe('source=kitchen&station_id=3&status=Fired&ticket=44');
  });
});
