import type { ChangeEvent } from 'react';
import type { StaffWorkspaceNavigationOption } from '../../workspaces/navigation/types';
import type { WorkspaceId } from '../../workspaces/workspaces';

export function StaffWorkspaceSwitcher({
  activeWorkspace,
  options,
  onSwitchWorkspace,
}: {
  activeWorkspace: WorkspaceId | null;
  options: Array<StaffWorkspaceNavigationOption>;
  onSwitchWorkspace: (workspace: WorkspaceId) => void;
}) {
  if (activeWorkspace === null || options.length < 2) {
    return null;
  }

  function handleChange(event: ChangeEvent<HTMLSelectElement>) {
    const nextWorkspace = event.target.value as WorkspaceId;

    if (nextWorkspace === activeWorkspace) {
      return;
    }

    onSwitchWorkspace(nextWorkspace);
  }

  return (
    <div className="staff-shell-header-select">
      <label className="staff-shell-branch-label" htmlFor="staff-shell-workspace-select">
        Khu vực
      </label>

      <div className="staff-shell-select-wrap">
        <select
          id="staff-shell-workspace-select"
          aria-label="Chuyển khu vực làm việc"
          className="staff-shell-branch-select"
          value={activeWorkspace}
          onChange={handleChange}
        >
          {options.map((option) => (
            <option key={option.workspace} value={option.workspace}>
              {option.label}
            </option>
          ))}
        </select>
      </div>
    </div>
  );
}
