export type StaffWorkspacePageId =
  | 'admin-landing'
  | 'admin-catalog'
  | 'admin-settings'
  | 'admin-inventory'
  | 'admin-benefits'
  | 'admin-privacy'
  | 'dashboard'
  | 'tables'
  | 'reservations'
  | 'orders'
  | 'checkout'
  | 'refunds'
  | 'waiting-list'
  | 'cashier-shift'
  | 'finance-review'
  | 'conversations'
  | 'command-center'
  | 'kitchen-landing'
  | 'kitchen-board'
  | 'reporting'
  | 'audit-trail';

export type StaffWorkspaceRouteDefinition = {
  key: string;
  path: string;
  absolutePath: string;
  page: StaffWorkspacePageId;
  capability?: string;
};
