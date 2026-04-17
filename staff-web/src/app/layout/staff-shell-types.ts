import type { ChangeEvent, ReactNode } from 'react';
import type { StaffNavGroup, StaffWorkspaceNavigationOption } from '../../workspaces/navigation/types';
import type { WorkspaceId } from '../../workspaces/workspaces';
import type { ContextDockEntry } from './useStaffShellContext';

export type StaffShellProps = {
  workspace: WorkspaceId;
  workspaceOption: StaffWorkspaceNavigationOption | null;
  compactNavigation: boolean;
  navigationGroups: Array<StaffNavGroup>;
  selectedMenuKey?: string;
  contextDock: Array<ContextDockEntry>;
  freshnessLabel: string;
  freshnessTone: string;
  branchId: number | null;
  branchOptions: Array<{ value: number; label: string }>;
  branchesLoading: boolean;
  routeDescriptor: {
    label: string;
    description: string;
  };
  children: ReactNode;
  alerts: ReactNode;
  onOpenPath: (path: string) => void;
  onOpenNavigation: () => void;
  onOpenCommandPalette: () => void;
  onRefresh: () => void;
  onLogout: () => void;
  onBranchChange: (event: ChangeEvent<HTMLSelectElement>) => void;
  onSwitchWorkspace: (workspace: WorkspaceId) => void;
  workspaceOptions: Array<StaffWorkspaceNavigationOption>;
  contentClassName?: string;
};
