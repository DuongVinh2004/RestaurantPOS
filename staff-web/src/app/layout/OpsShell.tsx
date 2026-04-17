import { AppFrame } from './frame/AppFrame';
import { AppFrameNavigation } from './frame/AppFrameNavigation';
import { StaffShellControls } from './StaffShellControls';
import type { StaffShellProps } from './staff-shell-types';

export function OpsShell({
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
      brandTitle={workspaceOption?.label ?? 'Ops'}
      brandCopy={workspaceOption?.description ?? 'Ops navigation stays focused on floor, reservation, and checkout work.'}
      navigationGroups={navigationGroups}
      selectedMenuKey={selectedMenuKey}
      onOpenPath={onOpenPath}
    />
  );

  const header = (
    <header className="staff-shell-header">
      <div className="staff-shell-header-top">
        <div className="staff-shell-header-primary">
          <div className="staff-shell-header-status" aria-label="Current workspace context">
            <div className="staff-shell-header-title-block">
              <span className="staff-eyebrow">Floor operations</span>
              <div className="staff-shell-header-title-row">
                <h1 className="staff-shell-header-title">{routeDescriptor.label}</h1>
                <span className={`staff-shell-freshness-chip staff-shell-freshness-chip-${freshnessTone}`}>
                  {freshnessLabel}
                </span>
              </div>
              <p className="staff-shell-header-note">
                Tables, reservations, orders, and checkout stay coordinated in one operator lane.
              </p>
            </div>
          </div>

          <div className="staff-shell-header-context">
            {contextDock.slice(0, 3).map((entry) => (
              <div
                key={entry.key}
                className={`staff-shell-context-card staff-shell-context-card-${entry.tone}`}
                aria-label={entry.label}
              >
                <span className="staff-shell-context-label">{entry.label}</span>
                <strong className="staff-shell-context-value">{entry.value}</strong>
                {entry.meta ? <span className="staff-shell-context-meta">{entry.meta}</span> : null}
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
