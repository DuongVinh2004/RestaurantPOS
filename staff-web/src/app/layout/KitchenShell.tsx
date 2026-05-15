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
      brandEyebrow="Mộc Sen Bistro"
      brandTitle={workspaceOption?.label ?? 'Bếp'}
      brandCopy={workspaceOption?.description ?? 'Điều phối phiếu bếp theo từng trạm.'}
      navigationGroups={navigationGroups}
      selectedMenuKey={selectedMenuKey}
      onOpenPath={onOpenPath}
    />
  );

  const header = (
    <header className="staff-shell-header staff-shell-header-kitchen">
      <div className="staff-shell-header-top">
        <div className="staff-shell-header-primary staff-shell-header-primary-kitchen">
          <div className="staff-shell-header-status" aria-label="Current workspace context">
            <div className="staff-shell-header-title-block">
              <div className="staff-shell-header-title-row">
                <span className="staff-shell-workspace-kicker">Bếp</span>
                <h1 className="staff-shell-header-title">{routeDescriptor.label}</h1>
                <span className={`staff-shell-freshness-chip staff-shell-freshness-chip-${freshnessTone}`}>
                  {freshnessLabel}
                </span>
              </div>
            </div>
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
