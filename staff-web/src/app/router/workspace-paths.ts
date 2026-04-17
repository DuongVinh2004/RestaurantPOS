import type { WorkspaceId } from '../../workspaces/workspaces';

export const staffRoutePaths = {
  login: '/login',
  access: '/access',
  ops: {
    root: '/ops',
    dashboard: '/ops/dashboard',
    tables: '/ops/tables',
    reservations: '/ops/reservations',
    orders: '/ops/orders',
    checkout: '/ops/checkout',
    refunds: '/ops/refunds',
    waitingList: '/ops/waiting-list',
    cashierShift: '/ops/cashier-shift',
    financeReview: '/ops/finance-review',
    conversations: '/ops/conversations',
  },
  kitchen: {
    root: '/kitchen',
    landing: '/kitchen',
    board: '/kitchen/board',
  },
  admin: {
    root: '/admin',
    landing: '/admin',
    settings: '/admin/settings',
    inventory: '/admin/inventory',
    reporting: '/admin/reporting',
    auditTrail: '/admin/audit-trail',
  },
} as const;

export function resolveWorkspaceRootPath(workspace: WorkspaceId): string {
  if (workspace === 'ops') {
    return staffRoutePaths.ops.root;
  }

  if (workspace === 'kitchen') {
    return staffRoutePaths.kitchen.root;
  }

  return staffRoutePaths.admin.root;
}
