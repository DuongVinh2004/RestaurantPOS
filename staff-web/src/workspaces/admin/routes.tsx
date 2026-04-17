import type { StaffWorkspaceRouteDefinition } from '../routes';
import { staffRoutePaths } from '../../app/router/workspace-paths';

export const adminWorkspaceRoutes: Array<StaffWorkspaceRouteDefinition> = [
  {
    key: 'landing',
    path: '',
    absolutePath: staffRoutePaths.admin.landing,
    page: 'admin-landing',
  },
  {
    key: 'settings',
    path: 'settings',
    absolutePath: staffRoutePaths.admin.settings,
    page: 'admin-settings',
    capability: 'settings.manage',
  },
  {
    key: 'inventory',
    path: 'inventory',
    absolutePath: staffRoutePaths.admin.inventory,
    page: 'admin-inventory',
    capability: 'inventory.manage',
  },
  {
    key: 'reporting',
    path: 'reporting',
    absolutePath: staffRoutePaths.admin.reporting,
    page: 'reporting',
    capability: 'reporting.view',
  },
  {
    key: 'audit-trail',
    path: 'audit-trail',
    absolutePath: staffRoutePaths.admin.auditTrail,
    page: 'audit-trail',
    capability: 'audit.view',
  },
];
