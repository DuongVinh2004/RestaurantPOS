import type { ReactNode } from 'react';

export function SplitWorkspace({
  main,
  side,
  stickySide = true,
  variant = 'balanced',
  className,
}: {
  main: ReactNode;
  side: ReactNode;
  stickySide?: boolean;
  variant?: 'balanced' | 'detail-heavy' | 'board-heavy';
  className?: string;
}) {
  const classes = [
    'staff-split-workspace',
    `staff-split-workspace-${variant}`,
    stickySide ? 'staff-split-workspace-sticky-side' : '',
    className ?? '',
  ].filter(Boolean).join(' ');

  return (
    <div className={classes}>
      <div className="staff-split-main">{main}</div>
      <div className="staff-split-side">{side}</div>
    </div>
  );
}
