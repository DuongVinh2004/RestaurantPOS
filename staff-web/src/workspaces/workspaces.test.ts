import { describe, expect, it } from 'vitest';
import { buildStaffSession } from '../test/fixtures';
import {
  isWorkspaceAvailable,
  resolveAvailableWorkspaces,
  resolvePrimaryWorkspace,
} from './workspaces';

describe('workspace resolution', () => {
  it('prefers the backend startup workspace contract over local capability inference', () => {
    const session = buildStaffSession({
      capabilities: ['reservation.manage'],
      known_capabilities: ['reservation.manage', 'kitchen.manage'],
      startup: {
        primary_workspace: 'kitchen',
        available_workspaces: ['kitchen'],
      },
    });

    expect(resolveAvailableWorkspaces(session)).toEqual(['kitchen']);
    expect(resolvePrimaryWorkspace(session)).toBe('kitchen');
    expect(isWorkspaceAvailable(session, 'ops')).toBe(false);
    expect(isWorkspaceAvailable(session, 'kitchen')).toBe(true);
  });

  it('resolves ops-only staff into the ops workspace', () => {
    const session = buildStaffSession({
      capabilities: ['table.board.view', 'order.manage'],
      known_capabilities: ['table.board.view', 'order.manage'],
    });

    expect(resolveAvailableWorkspaces(session)).toEqual(['ops']);
    expect(resolvePrimaryWorkspace(session)).toBe('ops');
    expect(isWorkspaceAvailable(session, 'ops')).toBe(true);
    expect(isWorkspaceAvailable(session, 'kitchen')).toBe(false);
    expect(isWorkspaceAvailable(session, 'admin')).toBe(false);
  });

  it('resolves kitchen-only staff into the kitchen workspace', () => {
    const session = buildStaffSession({
      capabilities: ['kitchen.manage'],
      known_capabilities: ['kitchen.manage'],
    });

    expect(resolveAvailableWorkspaces(session)).toEqual(['kitchen']);
    expect(resolvePrimaryWorkspace(session)).toBe('kitchen');
  });

  it('resolves admin-only staff into the admin workspace', () => {
    const session = buildStaffSession({
      capabilities: ['reporting.view'],
      known_capabilities: ['reporting.view'],
    });

    expect(resolveAvailableWorkspaces(session)).toEqual(['admin']);
    expect(resolvePrimaryWorkspace(session)).toBe('admin');
  });

  it('keeps canonical workspace ordering for multi-workspace staff', () => {
    const session = buildStaffSession({
      capabilities: ['reservation.manage', 'kitchen.manage', 'audit.view'],
      known_capabilities: ['reservation.manage', 'kitchen.manage', 'audit.view'],
    });

    expect(resolveAvailableWorkspaces(session)).toEqual(['ops', 'kitchen', 'admin']);
    expect(resolvePrimaryWorkspace(session)).toBe('ops');
  });

  it('falls back to ops when the session has no mapped workspace capability', () => {
    const session = buildStaffSession({
      capabilities: ['unknown.capability'],
      known_capabilities: ['unknown.capability'],
    });
    delete (session.startup as Partial<typeof session.startup>).primary_workspace;
    delete (session.startup as Partial<typeof session.startup>).available_workspaces;

    expect(resolveAvailableWorkspaces(session)).toEqual(['ops']);
    expect(resolvePrimaryWorkspace(session)).toBe('ops');
    expect(isWorkspaceAvailable(session, 'ops')).toBe(true);
  });

  it('keeps an explicit empty backend workspace list empty for access-gate sessions', () => {
    const session = buildStaffSession({
      capabilities: [],
      known_capabilities: ['reservation.manage'],
      startup: {
        primary_workspace: 'ops',
        available_workspaces: [],
        readiness: {
          access: 'capability_missing',
          operator_ready: false,
        },
      },
    });

    expect(resolveAvailableWorkspaces(session)).toEqual([]);
    expect(resolvePrimaryWorkspace(session)).toBe('ops');
    expect(isWorkspaceAvailable(session, 'ops')).toBe(false);
  });
});
