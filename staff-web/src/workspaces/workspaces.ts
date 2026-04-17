import type { StaffSession } from '../shared/auth/storage';
import { hasAny } from '../shared/auth/capabilities';

export const workspaceIds = ['ops', 'kitchen', 'admin'] as const;

export type WorkspaceId = (typeof workspaceIds)[number];

export const DEFAULT_WORKSPACE_ID: WorkspaceId = 'ops';
const workspaceIdSet = new Set<string>(workspaceIds);

const workspaceCapabilityMap: Record<WorkspaceId, Array<string>> = {
  ops: [
    'table.board.view',
    'table.release',
    'reservation.manage',
    'waiting_list.manage',
    'order.manage',
    'settlement.manage',
    'payment.refund',
    'cashier.shift.manage',
    'conversation.manage',
    'voucher.manage',
    'loyalty.view',
    'loyalty.redeem',
    'loyalty.adjust',
  ],
  kitchen: [
    'kitchen.manage',
  ],
  admin: [
    'audit.view',
    'reporting.view',
    'inventory.manage',
    'menu.manage',
    'settings.manage',
    'privacy.manage',
    'ops.view',
    'ops.health.view',
    'ops.metrics.view',
    'ops.release.view',
    'voucher.master_data.manage',
  ],
};

function hasWorkspaceCapabilities(session: StaffSession, workspace: WorkspaceId): boolean {
  return hasAny(session, workspaceCapabilityMap[workspace]);
}

function isWorkspaceId(value: unknown): value is WorkspaceId {
  return typeof value === 'string' && workspaceIdSet.has(value);
}

function normalizedWorkspaceIds(value: unknown): Array<WorkspaceId> | null {
  if (!Array.isArray(value)) {
    return null;
  }

  const normalized = value.filter(isWorkspaceId);

  return workspaceIds.filter((workspace) => normalized.includes(workspace));
}

function startupAvailableWorkspaces(session: StaffSession): Array<WorkspaceId> | null {
  return normalizedWorkspaceIds((session.startup as { available_workspaces?: unknown }).available_workspaces);
}

function startupPrimaryWorkspace(session: StaffSession): WorkspaceId | null {
  const primaryWorkspace = (session.startup as { primary_workspace?: unknown }).primary_workspace;

  return isWorkspaceId(primaryWorkspace) ? primaryWorkspace : null;
}

export function resolveAvailableWorkspaces(session: StaffSession): Array<WorkspaceId> {
  const contractWorkspaces = startupAvailableWorkspaces(session);
  if (contractWorkspaces !== null) {
    return contractWorkspaces;
  }

  const available = workspaceIds.filter((workspace) => hasWorkspaceCapabilities(session, workspace));

  if (available.length > 0) {
    return available;
  }

  // Keep staff-web on a stable operator workspace while legacy capability catalogs catch up.
  return [DEFAULT_WORKSPACE_ID];
}

export function resolvePrimaryWorkspace(session: StaffSession): WorkspaceId {
  const availableWorkspaces = resolveAvailableWorkspaces(session);
  const contractPrimaryWorkspace = startupPrimaryWorkspace(session);

  if (contractPrimaryWorkspace && (availableWorkspaces.length === 0 || availableWorkspaces.includes(contractPrimaryWorkspace))) {
    return contractPrimaryWorkspace;
  }

  return availableWorkspaces[0] ?? DEFAULT_WORKSPACE_ID;
}

export function isWorkspaceAvailable(session: StaffSession, workspace: WorkspaceId): boolean {
  return resolveAvailableWorkspaces(session).includes(workspace);
}
