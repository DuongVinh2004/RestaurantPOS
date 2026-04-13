export type StaffNavIconKey =
  | 'dashboard'
  | 'tables'
  | 'reservations'
  | 'waiting'
  | 'orders'
  | 'kitchen'
  | 'checkout'
  | 'cashier'
  | 'finance'
  | 'conversations'
  | 'audit'
  | 'reporting';

export type StaffNavItem = {
  key: string;
  label: string;
  path: string;
  iconKey: StaffNavIconKey;
  capability?: string | null;
  description: string;
  badgeCount?: number | null;
};

export type StaffNavGroup = {
  key: string;
  label: string;
  items: Array<StaffNavItem>;
};
