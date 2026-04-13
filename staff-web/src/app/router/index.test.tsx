import { describe, expect, it } from 'vitest';
import type { StaffSession } from '../../core/auth/storage';
import { resolveFallbackRedirectPath, resolveIndexRedirectPath } from './redirects';

function makeSession(overrides: Partial<StaffSession> = {}): StaffSession {
  return {
    auth_mode: 'staff_api_key',
    token_type: 'opaque',
    auth_header: 'X-Staff-Key',
    access_token: 'staff-token',
    staff_api_key_id: 1,
    capabilities: ['table.board.view'],
    known_capabilities: ['table.board.view'],
    capability_source: 'role_capabilities',
    startup: {
      default_branch: null,
      active_cashier_shift: null,
      readiness: {
        access: 'ready',
        branch: 'ready',
        cashier_shift: 'not_applicable',
        operator_ready: true,
        requires_cashier_shift: false,
        granted_capability_count: 1,
        known_capability_count: 1,
      },
    },
    ...overrides,
  };
}

describe('router redirects', () => {
  it('sends ready sessions to the dashboard from the index route', () => {
    expect(resolveIndexRedirectPath(makeSession())).toBe('/dashboard');
  });

  it('sends incomplete sessions back to the access hub', () => {
    expect(resolveFallbackRedirectPath(makeSession({
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
    expect(resolveFallbackRedirectPath(null)).toBe('/login');
  });
});
