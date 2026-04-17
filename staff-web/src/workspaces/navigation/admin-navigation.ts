import type { StaffWorkspaceNavigationDefinition } from './types';
import { staffRoutePaths } from '../../app/router/workspace-paths';

export const adminNavigation: StaffWorkspaceNavigationDefinition = {
  workspace: 'admin',
  label: 'Admin',
  description: 'Back-office configuration, catalog ownership, and read models.',
  landingPath: staffRoutePaths.admin.landing,
  groups: [
    {
      key: 'admin-overview',
      label: 'Control center',
      items: [
        {
          key: 'admin-landing',
          label: 'Admin home',
          path: staffRoutePaths.admin.landing,
          iconKey: 'dashboard',
          workspace: 'admin',
          description: 'Start in the admin workspace and choose the next back-office domain.',
          exact: true,
        },
      ],
    },
    {
      key: 'admin-configuration',
      label: 'Configuration',
      items: [
        {
          key: 'admin-settings',
          label: 'Settings',
          path: staffRoutePaths.admin.settings,
          iconKey: 'settings',
          workspace: 'admin',
          capability: 'settings.manage',
          description: 'Branches, table config, kitchen routing, and settings-side control surfaces.',
        },
        {
          key: 'admin-inventory',
          label: 'Inventory',
          path: staffRoutePaths.admin.inventory,
          iconKey: 'inventory',
          workspace: 'admin',
          capability: 'inventory.manage',
          description: 'Ingredients, suppliers, purchase orders, and receiving oversight.',
        },
      ],
    },
    {
      key: 'admin-governance',
      label: 'Governance',
      items: [
        {
          key: 'reporting',
          label: 'Reporting',
          path: staffRoutePaths.admin.reporting,
          iconKey: 'reporting',
          workspace: 'admin',
          capability: 'reporting.view',
          description: 'Operational read models, freshness checks, and branch-scoped reporting.',
        },
        {
          key: 'audit-trail',
          label: 'Audit trail',
          path: staffRoutePaths.admin.auditTrail,
          iconKey: 'audit',
          workspace: 'admin',
          capability: 'audit.view',
          description: 'Investigation reads and request-linked audit history.',
        },
      ],
    },
  ],
};
