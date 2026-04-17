import { beforeEach, describe, expect, it } from 'vitest';
import { buildStaffSession } from '../../test/fixtures';
import { useWorkspaceStore } from './workspace-store';

const initialState = useWorkspaceStore.getState();

describe('workspace-store', () => {
  beforeEach(() => {
    useWorkspaceStore.setState(initialState, true);
  });

  it('syncs active, available, and primary workspaces from the current session', () => {
    useWorkspaceStore.getState().syncSession(buildStaffSession({
      capabilities: ['reservation.manage', 'audit.view'],
      known_capabilities: ['reservation.manage', 'audit.view'],
    }));

    expect(useWorkspaceStore.getState()).toMatchObject({
      activeWorkspace: 'ops',
      availableWorkspaces: ['ops', 'admin'],
      primaryWorkspace: 'ops',
    });
  });

  it('switches only to workspaces available in the current session', () => {
    useWorkspaceStore.getState().syncSession(buildStaffSession({
      capabilities: ['reservation.manage', 'kitchen.manage'],
      known_capabilities: ['reservation.manage', 'kitchen.manage'],
    }));

    expect(useWorkspaceStore.getState().switchWorkspace('kitchen')).toBe(true);
    expect(useWorkspaceStore.getState().activeWorkspace).toBe('kitchen');
    expect(useWorkspaceStore.getState().switchWorkspace('admin')).toBe(false);
    expect(useWorkspaceStore.getState().activeWorkspace).toBe('kitchen');
  });

  it('preserves the chosen workspace for the same operator but resets for a new one', () => {
    useWorkspaceStore.getState().syncSession(buildStaffSession({
      staff_api_key_id: 17,
      capabilities: ['reservation.manage', 'kitchen.manage'],
      known_capabilities: ['reservation.manage', 'kitchen.manage'],
    }));
    useWorkspaceStore.getState().setActiveWorkspace('kitchen');

    useWorkspaceStore.getState().syncSession(buildStaffSession({
      staff_api_key_id: 17,
      capabilities: ['reservation.manage', 'kitchen.manage'],
      known_capabilities: ['reservation.manage', 'kitchen.manage'],
    }));
    expect(useWorkspaceStore.getState().activeWorkspace).toBe('kitchen');

    useWorkspaceStore.getState().syncSession(buildStaffSession({
      staff_api_key_id: 18,
      capabilities: ['reporting.view'],
      known_capabilities: ['reporting.view'],
    }));
    expect(useWorkspaceStore.getState().activeWorkspace).toBe('admin');
  });

  it('clears workspace state when the session is removed', () => {
    useWorkspaceStore.getState().syncSession(buildStaffSession());
    useWorkspaceStore.getState().reset();

    expect(useWorkspaceStore.getState()).toMatchObject({
      sessionOwnerKey: null,
      activeWorkspace: null,
      availableWorkspaces: [],
      primaryWorkspace: null,
    });
  });
});
