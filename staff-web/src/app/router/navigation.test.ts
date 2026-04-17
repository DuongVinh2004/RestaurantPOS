import { describe, expect, it } from 'vitest';
import { buildStaffSession } from '../../test/fixtures';
import { staffRoutePaths } from './workspace-paths';
import {
  resolveWorkspaceForPath,
  resolveWorkspaceLandingPath,
  resolveWorkspaceNavigationSections,
  resolveWorkspaceNavigationOptions,
  visibleWorkspaceNavigation,
  visibleWorkspaceNavigationGroups,
} from './navigation';

describe('workspace navigation registry', () => {
  it('builds ops navigation for ops-only sessions without leaking kitchen or admin entries', () => {
    const session = buildStaffSession({
      capabilities: ['table.board.view', 'reservation.manage', 'settlement.manage'],
      known_capabilities: ['table.board.view', 'reservation.manage', 'settlement.manage'],
    });

    expect(visibleWorkspaceNavigation(session, 'ops').map((item) => item.path)).toEqual([
      staffRoutePaths.ops.dashboard,
      staffRoutePaths.ops.tables,
      staffRoutePaths.ops.reservations,
      staffRoutePaths.ops.checkout,
      staffRoutePaths.ops.financeReview,
    ]);
    expect(visibleWorkspaceNavigation(session, 'ops').some((item) => item.path === staffRoutePaths.kitchen.board)).toBe(false);
    expect(visibleWorkspaceNavigation(session, 'ops').some((item) => item.path === staffRoutePaths.admin.reporting)).toBe(false);
  });

  it('builds kitchen navigation for kitchen-only sessions', () => {
    const session = buildStaffSession({
      capabilities: ['kitchen.manage'],
      known_capabilities: ['kitchen.manage'],
    });

    expect(visibleWorkspaceNavigation(session, 'kitchen').map((item) => item.path)).toEqual([
      staffRoutePaths.kitchen.landing,
      staffRoutePaths.kitchen.board,
    ]);
    expect(resolveWorkspaceLandingPath(session, 'kitchen')).toBe(staffRoutePaths.kitchen.landing);
    expect(resolveWorkspaceForPath(session, staffRoutePaths.kitchen.root)).toBe('kitchen');
    expect(resolveWorkspaceForPath(session, staffRoutePaths.kitchen.board)).toBe('kitchen');
  });

  it('builds admin navigation for admin-only sessions', () => {
    const session = buildStaffSession({
      capabilities: ['reporting.view', 'audit.view'],
      known_capabilities: ['reporting.view', 'audit.view'],
    });

    expect(visibleWorkspaceNavigation(session, 'admin').map((item) => item.path)).toEqual([
      staffRoutePaths.admin.landing,
      staffRoutePaths.admin.reporting,
      staffRoutePaths.admin.auditTrail,
    ]);
    expect(resolveWorkspaceLandingPath(session, 'admin')).toBe(staffRoutePaths.admin.landing);
    expect(resolveWorkspaceForPath(session, staffRoutePaths.admin.root)).toBe('admin');
    expect(resolveWorkspaceForPath(session, staffRoutePaths.admin.auditTrail)).toBe('admin');
  });

  it('returns workspace-specific navigation options for multi-workspace sessions', () => {
    const session = buildStaffSession({
      capabilities: ['reservation.manage', 'kitchen.manage', 'audit.view'],
      known_capabilities: ['reservation.manage', 'kitchen.manage', 'audit.view'],
    });

    expect(resolveWorkspaceNavigationOptions(session, ['ops', 'kitchen', 'admin']).map((option) => option.workspace)).toEqual([
      'ops',
      'kitchen',
      'admin',
    ]);
    expect(visibleWorkspaceNavigationGroups(session, 'ops').map((group) => group.key)).toEqual(['ops-floor']);
    expect(visibleWorkspaceNavigationGroups(session, 'kitchen').map((group) => group.key)).toEqual(['kitchen-line']);
    expect(visibleWorkspaceNavigationGroups(session, 'admin').map((group) => group.key)).toEqual(['admin-overview', 'admin-governance']);
  });

  it('treats refund work as part of the owning checkout navigation entry', () => {
    const session = buildStaffSession({
      capabilities: ['settlement.manage', 'payment.refund'],
      known_capabilities: ['settlement.manage', 'payment.refund'],
    });

    const opsItems = visibleWorkspaceNavigation(session, 'ops');

    expect(opsItems.find((item) => item.key === 'checkout')?.matchPaths).toEqual([
      staffRoutePaths.ops.refunds,
    ]);
    expect(opsItems.some((item) => item.path === staffRoutePaths.ops.refunds)).toBe(false);
    expect(resolveWorkspaceForPath(session, staffRoutePaths.ops.refunds)).toBe('ops');
  });

  it('builds workspace navigation sections without a mixed compatibility export', () => {
    const session = buildStaffSession({
      capabilities: ['reservation.manage', 'kitchen.manage', 'reporting.view'],
      known_capabilities: ['reservation.manage', 'kitchen.manage', 'reporting.view'],
    });

    expect(resolveWorkspaceNavigationSections(session).map((section) => [
      section.workspace,
      section.items.map((item) => item.path),
    ])).toEqual([
      ['ops', [staffRoutePaths.ops.dashboard, staffRoutePaths.ops.reservations]],
      ['kitchen', [staffRoutePaths.kitchen.landing, staffRoutePaths.kitchen.board]],
      ['admin', [staffRoutePaths.admin.landing, staffRoutePaths.admin.reporting]],
    ]);
  });
});
