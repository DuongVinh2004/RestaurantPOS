import { describe, expect, it } from 'vitest';
import { buildStaffSession } from '../../test/fixtures';
import {
  canManageStaffCashierShift,
  hasStaffStartupBranch,
  isStaffCashierShiftActionRequired,
  requiresStaffAccessGate,
  shouldRedirectToStaffCashierShift,
} from './startup';

describe('staff startup helpers', () => {
  it('requires the access gate when backend startup branch readiness is missing', () => {
    const session = buildStaffSession({
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
    });

    expect(hasStaffStartupBranch(session)).toBe(false);
    expect(requiresStaffAccessGate(session)).toBe(true);
  });

  it('redirects to cashier shift only when startup requires it and the session can manage shifts', () => {
    const session = buildStaffSession({
      capabilities: ['settlement.manage', 'cashier.shift.manage'],
      known_capabilities: ['settlement.manage', 'cashier.shift.manage'],
      startup: {
        default_branch: {
          branch_id: 1,
          branch_code: 'MAIN',
          branch_name: 'Chi nhanh chinh',
          timezone: 'Asia/Ho_Chi_Minh',
          currency: 'VND',
          is_default: true,
          is_active: true,
        },
        active_cashier_shift: null,
        readiness: {
          access: 'ready',
          branch: 'ready',
          cashier_shift: 'action_required',
          operator_ready: true,
          requires_cashier_shift: true,
          granted_capability_count: 2,
          known_capability_count: 2,
        },
      },
    });

    expect(isStaffCashierShiftActionRequired(session)).toBe(true);
    expect(canManageStaffCashierShift(session)).toBe(true);
    expect(shouldRedirectToStaffCashierShift(session)).toBe(true);
  });

  it('keeps finance blocked state without redirecting when the session cannot manage cashier shift', () => {
    const session = buildStaffSession({
      capabilities: ['settlement.manage'],
      known_capabilities: ['settlement.manage', 'cashier.shift.manage'],
      startup: {
        default_branch: {
          branch_id: 1,
          branch_code: 'MAIN',
          branch_name: 'Chi nhanh chinh',
          timezone: 'Asia/Ho_Chi_Minh',
          currency: 'VND',
          is_default: true,
          is_active: true,
        },
        active_cashier_shift: null,
        readiness: {
          access: 'ready',
          branch: 'ready',
          cashier_shift: 'action_required',
          operator_ready: true,
          requires_cashier_shift: true,
          granted_capability_count: 1,
          known_capability_count: 2,
        },
      },
    });

    expect(isStaffCashierShiftActionRequired(session)).toBe(true);
    expect(canManageStaffCashierShift(session)).toBe(false);
    expect(shouldRedirectToStaffCashierShift(session)).toBe(false);
  });
});
