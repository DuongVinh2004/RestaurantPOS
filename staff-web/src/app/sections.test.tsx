import { describe, expect, it } from 'vitest';
import { defaultStaffPath, visibleStaffSections } from './sections';
import { buildStaffSession } from '../test/fixtures';

describe('sections', () => {
  it('shows navigation only for granted capabilities', () => {
    const session = buildStaffSession({
      capabilities: ['table.board.view'],
      known_capabilities: ['table.board.view', 'conversation.manage', 'settlement.manage'],
    });

    expect(visibleStaffSections(session).map((section) => section.path)).toEqual(['/board']);
    expect(defaultStaffPath(session)).toBe('/board');
  });

  it('falls back to the access page when the session has no granted sections', () => {
    const session = buildStaffSession({
      capabilities: [],
      known_capabilities: ['conversation.manage'],
    });

    expect(visibleStaffSections(session)).toEqual([]);
    expect(defaultStaffPath(session)).toBe('/access');
  });

  it('falls back to the access page when backend startup says the operator shell is not ready', () => {
    const baseSession = buildStaffSession();
    const session = buildStaffSession({
      startup: {
        ...baseSession.startup,
        active_cashier_shift: null,
        readiness: {
          ...baseSession.startup.readiness,
          cashier_shift: 'action_required',
          operator_ready: false,
        },
      },
    });

    expect(visibleStaffSections(session)).toEqual([]);
    expect(defaultStaffPath(session)).toBe('/access');
  });

  it('keeps non-finance sections visible while cashier-dependent flows stay locked', () => {
    const baseSession = buildStaffSession();
    const session = buildStaffSession({
      startup: {
        ...baseSession.startup,
        active_cashier_shift: null,
        readiness: {
          ...baseSession.startup.readiness,
          cashier_shift: 'action_required',
          operator_ready: true,
        },
      },
    });

    expect(visibleStaffSections(session).map((section) => section.path)).toEqual([
      '/board',
      '/orders',
      '/kitchen',
      '/reporting',
      '/conversations',
    ]);
    expect(defaultStaffPath(session)).toBe('/board');
  });
});
