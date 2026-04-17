import { create } from 'zustand';
import type { StaffSession } from '../../shared/auth/storage';
import type { WorkspaceId } from '../../workspaces/workspaces';
import {
  resolveAvailableWorkspaces,
  resolvePrimaryWorkspace,
} from '../../workspaces/workspaces';

type WorkspaceState = {
  sessionOwnerKey: number | null;
  activeWorkspace: WorkspaceId | null;
  availableWorkspaces: Array<WorkspaceId>;
  primaryWorkspace: WorkspaceId | null;
  syncSession: (session: StaffSession | null) => void;
  setActiveWorkspace: (workspace: WorkspaceId) => void;
  switchWorkspace: (workspace: WorkspaceId) => boolean;
  reset: () => void;
};

const clearedWorkspaceState = {
  sessionOwnerKey: null,
  activeWorkspace: null,
  availableWorkspaces: [],
  primaryWorkspace: null,
} satisfies Pick<
  WorkspaceState,
  'sessionOwnerKey' | 'activeWorkspace' | 'availableWorkspaces' | 'primaryWorkspace'
>;

export const useWorkspaceStore = create<WorkspaceState>((set) => ({
  ...clearedWorkspaceState,
  syncSession: (session) => {
    if (!session) {
      set(clearedWorkspaceState);
      return;
    }

    const nextSessionOwnerKey = session.staff_api_key_id ?? null;
    const availableWorkspaces = resolveAvailableWorkspaces(session);
    const primaryWorkspace = resolvePrimaryWorkspace(session);

    set((state) => {
      const canKeepActiveWorkspace = state.sessionOwnerKey === nextSessionOwnerKey
        && state.activeWorkspace !== null
        && availableWorkspaces.includes(state.activeWorkspace);

      return {
        sessionOwnerKey: nextSessionOwnerKey,
        activeWorkspace: canKeepActiveWorkspace ? state.activeWorkspace : primaryWorkspace,
        availableWorkspaces,
        primaryWorkspace,
      };
    });
  },
  setActiveWorkspace: (workspace) => {
    set((state) => (
      state.availableWorkspaces.includes(workspace)
        ? { activeWorkspace: workspace }
        : {}
    ));
  },
  switchWorkspace: (workspace) => {
    let didSwitch = false;

    set((state) => {
      if (!state.availableWorkspaces.includes(workspace) || state.activeWorkspace === workspace) {
        return {};
      }

      didSwitch = true;
      return { activeWorkspace: workspace };
    });

    return didSwitch;
  },
  reset: () => set(clearedWorkspaceState),
}));
