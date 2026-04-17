import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { SplitWorkspace } from './SplitWorkspace';

describe('SplitWorkspace', () => {
  it('applies the requested layout variant and sticky-side class', () => {
    const { container } = render(
      <SplitWorkspace
        main={<div>Main</div>}
        side={<div>Side</div>}
        variant="detail-heavy"
        stickySide
        className="workspace-shell"
      />,
    );

    const workspace = container.querySelector('.staff-split-workspace');
    expect(workspace).toHaveClass('staff-split-workspace-detail-heavy');
    expect(workspace).toHaveClass('staff-split-workspace-sticky-side');
    expect(workspace).toHaveClass('workspace-shell');
  });
});
