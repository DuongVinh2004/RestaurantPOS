import type { WorkspaceId } from '../workspaces';

export type StaffNavIconKey =
  | 'dashboard'
  | 'settings'
  | 'inventory'
  | 'menu'
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
  workspace: WorkspaceId;
  capability?: string | null;
  description: string;
  badgeCount?: number | null;
  exact?: boolean;
  matchPaths?: Array<string>;
};

export type StaffNavGroup = {
  key: string;
  label: string;
  items: Array<StaffNavItem>;
};

export type StaffWorkspaceNavigationDefinition = {
  workspace: WorkspaceId;
  label: string;
  description: string;
  landingPath: string;
  groups: Array<StaffNavGroup>;
};

export type StaffWorkspaceNavigationOption = {
  workspace: WorkspaceId;
  label: string;
  description: string;
  landingPath: string;
};
