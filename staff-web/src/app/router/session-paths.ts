import type { StaffSession } from '../../shared/auth/storage';
import { resolvePrimaryWorkspace } from '../../workspaces/workspaces';
import { requiresStaffAccessGate, shouldRedirectToStaffCashierShift } from '../auth/startup';
import { resolveWorkspaceLandingPath } from './navigation';
import { staffRoutePaths } from './workspace-paths';

export function resolveDefaultStaffPath(session: StaffSession | null): string {
  if (!session) {
    return staffRoutePaths.login;
  }

  return staffRoutePaths.access;
}

export function resolveRecommendedStaffPath(session: StaffSession | null): string {
  if (!session) {
    return staffRoutePaths.login;
  }

  if (requiresStaffAccessGate(session)) {
    return staffRoutePaths.access;
  }

  if (shouldRedirectToStaffCashierShift(session)) {
    return staffRoutePaths.ops.cashierShift;
  }

  return resolveWorkspaceLandingPath(session, resolvePrimaryWorkspace(session));
}
