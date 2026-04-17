import { describe, expect, it } from 'vitest';
import { resolveRouteDataScope } from './route-scope';
import { staffRoutePaths } from '../router/workspace-paths';

describe('route data scope', () => {
  it('keeps audit trail as the remaining cross-scope investigative workspace', () => {
    expect(resolveRouteDataScope(staffRoutePaths.admin.auditTrail)).toMatchObject({
      kind: 'cross-scope',
      tone: 'warning',
    });
  });

  it('returns null for branch-scoped workspaces that are already operational-safe', () => {
    expect(resolveRouteDataScope(staffRoutePaths.ops.dashboard)).toBeNull();
    expect(resolveRouteDataScope(staffRoutePaths.ops.cashierShift)).toBeNull();
    expect(resolveRouteDataScope(staffRoutePaths.ops.financeReview)).toBeNull();
    expect(resolveRouteDataScope(staffRoutePaths.kitchen.landing)).toBeNull();
    expect(resolveRouteDataScope(staffRoutePaths.kitchen.board)).toBeNull();
    expect(resolveRouteDataScope(staffRoutePaths.ops.tables)).toBeNull();
    expect(resolveRouteDataScope(staffRoutePaths.ops.reservations)).toBeNull();
    expect(resolveRouteDataScope(staffRoutePaths.ops.waitingList)).toBeNull();
  });
});
