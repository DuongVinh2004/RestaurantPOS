import type { StaffWorkspaceRouteDefinition } from '../routes';
import { staffRoutePaths } from '../../app/router/workspace-paths';

export const opsWorkspaceRoutes: Array<StaffWorkspaceRouteDefinition> = [
  {
    key: 'dashboard',
    path: 'dashboard',
    absolutePath: staffRoutePaths.ops.dashboard,
    page: 'dashboard',
  },
  {
    key: 'tables',
    path: 'tables',
    absolutePath: staffRoutePaths.ops.tables,
    page: 'tables',
    capability: 'table.board.view',
  },
  {
    key: 'reservations',
    path: 'reservations',
    absolutePath: staffRoutePaths.ops.reservations,
    page: 'reservations',
    capability: 'reservation.manage',
  },
  {
    key: 'orders',
    path: 'orders',
    absolutePath: staffRoutePaths.ops.orders,
    page: 'orders',
    capability: 'order.manage',
  },
  {
    key: 'checkout',
    path: 'checkout',
    absolutePath: staffRoutePaths.ops.checkout,
    page: 'checkout',
    capability: 'settlement.manage',
  },
  {
    key: 'refunds',
    path: 'refunds',
    absolutePath: staffRoutePaths.ops.refunds,
    page: 'refunds',
    capability: 'payment.refund',
  },
  {
    key: 'waiting-list',
    path: 'waiting-list',
    absolutePath: staffRoutePaths.ops.waitingList,
    page: 'waiting-list',
    capability: 'waiting_list.manage',
  },
  {
    key: 'cashier-shift',
    path: 'cashier-shift',
    absolutePath: staffRoutePaths.ops.cashierShift,
    page: 'cashier-shift',
    capability: 'cashier.shift.manage',
  },
  {
    key: 'finance-review',
    path: 'finance-review',
    absolutePath: staffRoutePaths.ops.financeReview,
    page: 'finance-review',
    capability: 'settlement.manage',
  },
  {
    key: 'conversations',
    path: 'conversations',
    absolutePath: staffRoutePaths.ops.conversations,
    page: 'conversations',
    capability: 'conversation.manage',
  },
];
