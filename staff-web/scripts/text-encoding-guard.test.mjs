import { describe, expect, it } from 'vitest';
import { findEncodingIssues, repairText } from '../../scripts/ui-text-encoding-guard.mjs';

describe('ui text encoding guard', () => {
  it('keeps browser-facing sources free of mojibake', () => {
    expect(findEncodingIssues()).toHaveLength(0);
  });

  it('repairs mojibake ellipsis in browser-facing copy', () => {
    const mojibakeEllipsis = String.fromCodePoint(0x00e2, 0x20ac, 0x00a6);

    expect(repairText(`Đang tải${mojibakeEllipsis}`)).toEqual({
      repaired: 'Đang tải…',
      replacementCount: 1,
    });
  });
});
