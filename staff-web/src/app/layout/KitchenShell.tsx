import { AppFrame } from './frame/AppFrame';
import { AppFrameNavigation } from './frame/AppFrameNavigation';
import { StaffShellControls } from './StaffShellControls';
import type { StaffShellProps } from './staff-shell-types';

export function KitchenShell({
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
      brandTitle={workspaceOption?.label ?? 'Kitchen'}
      brandCopy={workspaceOption?.description ?? 'Station-first ticket operations.'}
      navigationGroups={navigationGroups}
      selectedMenuKey={selectedMenuKey}
      onOpenPath={onOpenPath}
    />
  );

  const kitchenContext = contextDock
    .filter((entry) => entry.key === 'context' || entry.key === 'readiness' || entry.key === 'branch')
    .slice(0, 2);

  const header = (
    <header className="staff-shell-header staff-shell-header-kitchen">
      <div className="staff-shell-header-top">
        <div className="staff-shell-header-primary staff-shell-header-primary-kitchen">
          <div className="staff-shell-header-status" aria-label="Current workspace context">
            <div className="staff-shell-header-title-block">
              <span className="staff-eyebrow">Kitchen line</span>
              <div className="staff-shell-header-title-row">
                <h1 className="staff-shell-header-title">{routeDescriptor.label}</h1>
                <span className={`staff-shell-freshness-chip staff-shell-freshness-chip-${freshnessTone}`}>
                  {freshnessLabel}
                </span>
              </div>
            </div>
          </div>

          <p className="staff-shell-header-note staff-shell-header-note-kitchen">
            Station, queue, and live sync stay in one kitchen lane.
          </p>

          <div className="staff-shell-header-context staff-shell-header-context-kitchen">
            {kitchenContext.map((entry) => (
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
      contentClassName={['staff-shell-content-kitchen-focus', contentClassName].filter(Boolean).join(' ')}
    >
      {children}
    </AppFrame>
  );
}
