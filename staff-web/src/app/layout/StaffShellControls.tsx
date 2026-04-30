import { Command, LogOut, PanelLeftOpen, RefreshCcw } from 'lucide-react';
import type { ChangeEvent } from 'react';
import type { StaffWorkspaceNavigationOption } from '../../workspaces/navigation/types';
import type { WorkspaceId } from '../../workspaces/workspaces';
import { StaffWorkspaceSwitcher } from './StaffWorkspaceSwitcher';

function BranchControl({
  branchId,
  branchOptions,
  branchesLoading,
  onBranchChange,
}: {
  branchId: number | null;
  branchOptions: Array<{ value: number; label: string }>;
  branchesLoading: boolean;
  onBranchChange: (event: ChangeEvent<HTMLSelectElement>) => void;
}) {
  const branchSelectValue = branchId === null ? '' : String(branchId);

  return (
    <div className="staff-shell-header-select">
      <label className="staff-shell-branch-label" htmlFor="staff-shell-branch-select">
        Chi nhánh thao tác
      </label>
      <div className="staff-shell-select-wrap">
        <select
          id="staff-shell-branch-select"
          aria-label="Chọn chi nhánh hoạt động"
          className="staff-shell-branch-select"
          value={branchSelectValue}
          disabled={branchesLoading || branchOptions.length === 0}
          onChange={onBranchChange}
        >
          {branchSelectValue === '' ? (
            <option value="">
              {branchesLoading ? 'Đang tải chi nhánh...' : 'Chọn chi nhánh'}
            </option>
          ) : null}
          {branchOptions.map((option) => (
            <option key={option.value} value={String(option.value)}>
              {option.label}
            </option>
          ))}
        </select>
      </div>
    </div>
  );
}

function ActionRow({
  compactNavigation,
  showCommandPalette = true,
  onLogout,
  onOpenCommandPalette,
  onOpenNavigation,
  onRefresh,
}: {
  compactNavigation: boolean;
  showCommandPalette?: boolean;
  onLogout: () => void;
  onOpenCommandPalette: () => void;
  onOpenNavigation: () => void;
  onRefresh: () => void;
}) {
  return (
    <div className="staff-shell-action-row">
      {compactNavigation ? (
        <button
          type="button"
          className="staff-shell-control-button staff-shell-button-ghost staff-shell-button-icon staff-shell-nav-toggle"
          onClick={onOpenNavigation}
          aria-label="Mở điều hướng"
          title="Mở điều hướng"
        >
          <PanelLeftOpen size={18} />
        </button>
      ) : null}

      <button
        type="button"
        className="staff-shell-control-button staff-shell-button-subtle staff-shell-button-icon"
        onClick={onRefresh}
        aria-label="Làm mới"
        title="Làm mới"
      >
        <RefreshCcw size={16} />
      </button>

      {showCommandPalette ? (
        <button
          type="button"
          className="staff-shell-control-button staff-shell-button-quiet staff-shell-button-icon"
          onClick={onOpenCommandPalette}
          aria-label="Tìm nhanh"
          title="Tìm nhanh"
        >
          <Command size={16} />
        </button>
      ) : null}

      <button
        type="button"
        className="staff-shell-control-button staff-shell-button-ghost staff-shell-button-icon"
        onClick={onLogout}
        aria-label="Đăng xuất"
        title="Đăng xuất"
      >
        <LogOut size={16} />
      </button>
    </div>
  );
}

export function StaffShellControls({
  variant,
  activeWorkspace,
  workspaceOptions,
  onSwitchWorkspace,
  branchId,
  branchOptions,
  branchesLoading,
  onBranchChange,
  compactNavigation,
  onLogout,
  onOpenCommandPalette,
  onOpenNavigation,
  onRefresh,
}: {
  variant: WorkspaceId;
  activeWorkspace: WorkspaceId;
  workspaceOptions: Array<StaffWorkspaceNavigationOption>;
  onSwitchWorkspace: (workspace: WorkspaceId) => void;
  branchId: number | null;
  branchOptions: Array<{ value: number; label: string }>;
  branchesLoading: boolean;
  onBranchChange: (event: ChangeEvent<HTMLSelectElement>) => void;
  compactNavigation: boolean;
  onLogout: () => void;
  onOpenCommandPalette: () => void;
  onOpenNavigation: () => void;
  onRefresh: () => void;
}) {
  const workspaceSwitch = (
    <StaffWorkspaceSwitcher
      activeWorkspace={activeWorkspace}
      options={workspaceOptions}
      onSwitchWorkspace={onSwitchWorkspace}
    />
  );

  const branchControl = (
    <BranchControl
      branchId={branchId}
      branchOptions={branchOptions}
      branchesLoading={branchesLoading}
      onBranchChange={onBranchChange}
    />
  );

  const actionRow = (
    <ActionRow
      compactNavigation={compactNavigation}
      showCommandPalette={variant !== 'kitchen'}
      onLogout={onLogout}
      onOpenCommandPalette={onOpenCommandPalette}
      onOpenNavigation={onOpenNavigation}
      onRefresh={onRefresh}
    />
  );

  if (variant === 'kitchen') {
    return (
      <div className="staff-shell-header-controls staff-shell-header-controls-kitchen">
        <div className="staff-shell-header-controls-row">
          {workspaceSwitch}
          {actionRow}
        </div>
        {branchControl}
      </div>
    );
  }

  if (variant === 'admin') {
    return (
      <div className="staff-shell-header-controls staff-shell-header-controls-admin">
        <div className="staff-shell-header-controls-row">
          {workspaceSwitch}
          {actionRow}
        </div>
        {branchControl}
      </div>
    );
  }

  return (
    <div className="staff-shell-header-controls staff-shell-header-controls-ops">
      {workspaceSwitch}
      {branchControl}
      {actionRow}
    </div>
  );
}
