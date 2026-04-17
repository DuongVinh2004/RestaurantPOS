import { describe, expect, it } from 'vitest';
import type { StaffSession } from '../../shared/auth/storage';
import { buildStaffSession, type StaffStartupOverrides } from '../../test/fixtures';
import { resolveRecommendedStaffPath } from './session-paths';
import { staffRoutePaths } from './workspace-paths';

type StaffSessionOverrides = Omit<Partial<StaffSession>, 'startup'> & {
  startup?: StaffStartupOverrides;
};

function makeSession(overrides: StaffSessionOverrides = {}): StaffSession {
  const { startup: startupOverrides, ...sessionOverrides } = overrides;
  const capabilities = sessionOverrides.capabilities ?? ['table.board.view'];
  const knownCapabilities = sessionOverrides.known_capabilities ?? capabilities;

  return buildStaffSession({
    ...sessionOverrides,
    staff_api_key_id: 1,
    capabilities,
    known_capabilities: knownCapabilities,
    startup: {
      default_branch: null,
      active_cashier_shift: null,
      ...startupOverrides,
      readiness: {
        access: 'ready',
        branch: 'ready',
        cashier_shift: 'not_applicable',
        operator_ready: true,
        requires_cashier_shift: false,
        granted_capability_count: capabilities.length,
        known_capability_count: knownCapabilities.length,
        ...startupOverrides?.readiness,
      },
    },
  });
}

describe('router redirects', () => {
  it('sends ready ops sessions to the canonical ops landing route from the index route', () => {
    expect(resolveRecommendedStaffPath(makeSession())).toBe(staffRoutePaths.ops.dashboard);
  });

  it('sends incomplete sessions back to the access hub', () => {
    expect(resolveRecommendedStaffPath(makeSession({
      startup: {
        default_branch: null,
        active_cashier_shift: null,
        readiness: {
          access: 'ready',
          branch: 'missing',
          cashier_shift: 'not_applicable',
          operator_ready: false,
          requires_cashier_shift: false,
          granted_capability_count: 1,
          known_capability_count: 1,
        },
      },
    }))).toBe('/access');
  });

  it('keeps anonymous fallback on the login route', () => {
    expect(resolveRecommendedStaffPath(null)).toBe(staffRoutePaths.login);
  });

  it('lands multi-workspace sessions on the primary ops workspace by default', () => {
    expect(resolveRecommendedStaffPath(makeSession({
      capabilities: ['reservation.manage', 'kitchen.manage'],
      known_capabilities: ['reservation.manage', 'kitchen.manage'],
    }))).toBe(staffRoutePaths.ops.dashboard);
  });

  it('lands admin-only sessions on the admin workspace', () => {
    expect(resolveRecommendedStaffPath(makeSession({
      capabilities: ['reporting.view'],
      known_capabilities: ['reporting.view'],
    }))).toBe(staffRoutePaths.admin.landing);
  });

  it('lands kitchen-only sessions on the kitchen workspace landing', () => {
    expect(resolveRecommendedStaffPath(makeSession({
      capabilities: ['kitchen.manage'],
      known_capabilities: ['kitchen.manage'],
      startup: {
        assigned_station_ids: [33],
      },
    }))).toBe(staffRoutePaths.kitchen.landing);
  });
});
