import { AppFrame } from './frame/AppFrame';
import { AppFrameNavigation } from './frame/AppFrameNavigation';
import { StaffShellControls } from './StaffShellControls';
import type { StaffShellProps } from './staff-shell-types';

export function AdminShell({
  workspace,
  workspaceOption,
  compactNavigation,
  navigationGroups,
  selectedMenuKey,
  contextDock,
  freshnessLabel,
  freshnessTone,
  branchId,
  branchOptions,
  branchesLoading,
  routeDescriptor,
  children,
  alerts,
  onOpenPath,
  onOpenNavigation,
  onOpenCommandPalette,
  onRefresh,
  onLogout,
  onBranchChange,
  onSwitchWorkspace,
  workspaceOptions,
  contentClassName,
}: StaffShellProps) {
  const navigation = (
    <AppFrameNavigation
      brandEyebrow="RestaurantPOS"
      brandTitle={workspaceOption?.label ?? 'Admin'}
      brandCopy={workspaceOption?.description ?? 'Back-office configuration and read models stay separate from live floor work.'}
      navigationGroups={navigationGroups}
      selectedMenuKey={selectedMenuKey}
      onOpenPath={onOpenPath}
    />
  );

  const adminContext = contextDock
    .filter((entry) => entry.key === 'readiness' || entry.key === 'branch' || entry.key === 'context')
    .slice(0, 3);

  const header = (
    <header className="staff-shell-header staff-shell-header-admin">
      <div className="staff-shell-header-top">
        <div className="staff-shell-header-primary staff-shell-header-primary-admin">
          <div className="staff-shell-header-status" aria-label="Current workspace context">
            <div className="staff-shell-header-title-block">
              <span className="staff-eyebrow">Back office</span>
              <div className="staff-shell-header-title-row">
                <h1 className="staff-shell-header-title">{routeDescriptor.label}</h1>
                <span className={`staff-shell-freshness-chip staff-shell-freshness-chip-${freshnessTone}`}>
                  {freshnessLabel}
                </span>
              </div>
              <p className="staff-shell-header-note staff-shell-header-note-admin">
                Settings, governance, and read models stay inside one admin lane.
              </p>
            </div>
          </div>

          <div className="staff-shell-header-context staff-shell-header-context-admin">
            {adminContext.map((entry) => (
              <div
                key={entry.key}
                className={`staff-shell-context-card staff-shell-context-card-${entry.tone}`}
                aria-label={entry.label}
              >
                <span className="staff-shell-context-label">{entry.label}</span>
                <strong className="staff-shell-context-value">{entry.value}</strong>
              </div>
            ))}
          </div>
        </div>

        <StaffShellControls
          variant={workspace}
          activeWorkspace={workspace}
          workspaceOptions={workspaceOptions}
          onSwitchWorkspace={onSwitchWorkspace}
          branchId={branchId}
          branchOptions={branchOptions}
          branchesLoading={branchesLoading}
          onBranchChange={onBranchChange}
          compactNavigation={compactNavigation}
          onLogout={onLogout}
          onOpenCommandPalette={onOpenCommandPalette}
          onOpenNavigation={onOpenNavigation}
          onRefresh={onRefresh}
        />
      </div>
    </header>
  );

  return (
    <AppFrame
      workspace={workspace}
      navigation={navigation}
      header={header}
      alerts={alerts}
      contentClassName={contentClassName}
    >
      {children}
    </AppFrame>
  );
}
