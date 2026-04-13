import { can } from '../permissions/capabilities';
import type { StaffSession } from './storage';

export type StaffStartupReadiness = StaffSession['startup']['readiness'];

export function staffStartupReadiness(session: StaffSession): StaffStartupReadiness {
  return session.startup.readiness;
}

export function hasStaffStartupAccess(session: StaffSession): boolean {
  return staffStartupReadiness(session).access === 'ready';
}

export function hasStaffStartupBranch(session: StaffSession): boolean {
  return staffStartupReadiness(session).branch === 'ready';
}

export function isStaffSessionOperatorReady(session: StaffSession): boolean {
  return staffStartupReadiness(session).operator_ready;
}

export function requiresStaffCashierShift(session: StaffSession): boolean {
  return staffStartupReadiness(session).requires_cashier_shift;
}

export function isStaffCashierShiftActionRequired(session: StaffSession): boolean {
  const readiness = staffStartupReadiness(session);

  return readiness.requires_cashier_shift && readiness.cashier_shift === 'action_required';
}

export function canManageStaffCashierShift(session: StaffSession): boolean {
  return can(session, 'cashier.shift.manage');
}

export function requiresStaffAccessGate(session: StaffSession): boolean {
  return !hasStaffStartupAccess(session)
    || !hasStaffStartupBranch(session)
    || !isStaffSessionOperatorReady(session);
}

export function shouldRedirectToStaffCashierShift(session: StaffSession): boolean {
  return !requiresStaffAccessGate(session)
    && isStaffCashierShiftActionRequired(session)
    && canManageStaffCashierShift(session);
}
