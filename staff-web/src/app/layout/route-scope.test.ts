import { describe, expect, it } from 'vitest';
import { resolveRouteDataScope } from './route-scope';

describe('route data scope', () => {
  it('keeps audit trail as the remaining mixed-scope investigative workspace', () => {
    expect(resolveRouteDataScope('/audit-trail')).toMatchObject({
      kind: 'mixed',
      tone: 'warning',
    });
  });

  it('returns null for branch-scoped workspaces that are already operational-safe', () => {
    expect(resolveRouteDataScope('/dashboard')).toBeNull();
    expect(resolveRouteDataScope('/cashier-shift')).toBeNull();
    expect(resolveRouteDataScope('/finance-review')).toBeNull();
    expect(resolveRouteDataScope('/kitchen')).toBeNull();
    expect(resolveRouteDataScope('/tables')).toBeNull();
    expect(resolveRouteDataScope('/reservations')).toBeNull();
    expect(resolveRouteDataScope('/waiting-list')).toBeNull();
  });
});
