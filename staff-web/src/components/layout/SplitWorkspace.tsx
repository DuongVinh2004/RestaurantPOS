import type { ReactNode } from 'react';

export function SplitWorkspace({
  main,
  side,
}: {
  main: ReactNode;
  side: ReactNode;
}) {
  return (
    <div className="staff-split-workspace">
      <div className="staff-split-main">{main}</div>
      <div className="staff-split-side">{side}</div>
    </div>
  );
}
