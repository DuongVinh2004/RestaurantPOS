import { describe, expect, it } from 'vitest';
import { can, hasAll, hasAny } from './capabilities';

describe('capabilities helpers', () => {
  it('grants explicit capability', () => {
    expect(can({ capabilities: ['table.board.view', 'order.manage'] }, 'order.manage')).toBe(true);
  });

  it('supports wildcard capability', () => {
    expect(hasAll(['*'], ['table.board.view', 'settlement.manage'])).toBe(true);
  });

  it('checks any capability correctly', () => {
    expect(hasAny({ capabilities: ['reservation.manage'] }, ['order.manage', 'reservation.manage'])).toBe(true);
    expect(hasAny({ capabilities: ['reservation.manage'] }, ['order.manage', 'settlement.manage'])).toBe(false);
  });
});
