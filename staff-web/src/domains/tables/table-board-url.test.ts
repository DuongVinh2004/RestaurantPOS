import { describe, expect, it } from 'vitest';
import { buildTableBoardSearch, readTableBoardUrlState } from './table-board-url';

describe('table board url helpers', () => {
  it('reads the active zone from the url', () => {
    expect(readTableBoardUrlState('?zone=Garden')).toEqual({
      zone: 'Garden',
      status: '',
    });
  });

  it('writes zone state while preserving journey params', () => {
    expect(buildTableBoardSearch(
      '?source=board&table_id=18',
      { zone: 'Garden' },
    )).toBe('source=board&table_id=18&zone=Garden');
  });
});
