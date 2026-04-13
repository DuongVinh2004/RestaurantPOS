import { describe, expect, it } from 'vitest';
import { findEncodingIssues } from '../../scripts/ui-text-encoding-guard.mjs';

describe('ui text encoding guard', () => {
  it('keeps browser-facing sources free of mojibake', () => {
    expect(findEncodingIssues()).toHaveLength(0);
  });
});
