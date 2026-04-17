import type { ReactNode } from 'react';
import type { WorkspaceId } from '../../../workspaces/workspaces';

function joinClasses(...classes: Array<string | false | null | undefined>) {
  return classes.filter(Boolean).join(' ');
}

export function AppFrame({
  workspace,
  navigation,
  header,
  alerts,
  children,
  contentClassName,
}: {
  workspace: WorkspaceId;
  navigation: ReactNode;
  header: ReactNode;
  alerts?: ReactNode;
  children: ReactNode;
  contentClassName?: string;
}) {
  return (
    <div className={joinClasses('staff-shell-layout', `staff-shell-layout-${workspace}`)}>
      <aside className={joinClasses('staff-shell-sider', `staff-shell-sider-${workspace}`)}>
        {navigation}
      </aside>

      <div className={joinClasses('staff-shell-main', `staff-shell-main-${workspace}`)}>
        {header}

        <main className={joinClasses('staff-shell-content', `staff-shell-content-${workspace}`, contentClassName)}>
          {alerts}
          {children}
        </main>
      </div>
    </div>
  );
}
