import { staffRoutePaths } from '../../app/router/workspace-paths';
import { can } from '../../shared/auth/capabilities';
import type { StaffSession } from '../../shared/auth/storage';

export type AdminWorkspaceGroupKey = 'control' | 'catalog' | 'supply' | 'governance';
export type AdminWorkspaceCardStatus = 'live' | 'contract-ready' | 'restricted';

export type AdminWorkspaceCardDefinition = {
  key: string;
  group: AdminWorkspaceGroupKey;
  title: string;
  description: string;
  capability?: string;
  route?: string | null;
  backendSurface: string;
  workflow: 'read-only' | 'import-export' | 'dry-run-commit' | 'read-write';
  branchSensitive: boolean;
};

export type AdminWorkspaceCard = AdminWorkspaceCardDefinition & {
  enabled: boolean;
  status: AdminWorkspaceCardStatus;
  statusLabel: string;
  workflowLabel: string;
  actionPath: string | null;
};

export type AdminWorkspaceGroup = {
  key: AdminWorkspaceGroupKey;
  label: string;
  cards: Array<AdminWorkspaceCard>;
};

const adminWorkspaceDefinitions: Array<AdminWorkspaceCardDefinition> = [
  {
    key: 'branches-settings',
    group: 'control',
    title: 'Branches and settings',
    description: 'Branch registry, finance settings, and import-export entry points for branch-owned configuration.',
    capability: 'settings.manage',
    route: staffRoutePaths.admin.settings,
    backendSurface: '/admin/settings/branches, /admin/settings/finance/tax-profile',
    workflow: 'import-export',
    branchSensitive: false,
  },
  {
    key: 'table-config',
    group: 'control',
    title: 'Table configuration',
    description: 'Dining-room tables, zones, and import flows stay under the settings ownership lane.',
    capability: 'settings.manage',
    route: staffRoutePaths.admin.settings,
    backendSurface: '/admin/restaurant/tables, /admin/restaurant/zones',
    workflow: 'import-export',
    branchSensitive: true,
  },
  {
    key: 'kitchen-routing-config',
    group: 'control',
    title: 'Kitchen routing config',
    description: 'Stations and category routes stay in back-office configuration instead of the live kitchen lane.',
    capability: 'settings.manage',
    route: staffRoutePaths.admin.settings,
    backendSurface: '/admin/kitchen/stations, /admin/kitchen/stations/{station_id}/category-routes',
    workflow: 'read-write',
    branchSensitive: true,
  },
  {
    key: 'menu-pricing',
    group: 'catalog',
    title: 'Menu and pricing',
    description: 'Menu categories, item pricing, and pricing imports are exposed by the backend but not wired into a dedicated admin page yet.',
    capability: 'menu.manage',
    route: null,
    backendSurface: '/admin/menu/categories, /admin/menu/items, /admin/menu/prices',
    workflow: 'import-export',
    branchSensitive: false,
  },
  {
    key: 'benefits',
    group: 'catalog',
    title: 'Voucher and loyalty settings',
    description: 'Voucher master data and loyalty tiers belong to admin ownership rather than the live ops workspace.',
    capability: 'voucher.master_data.manage',
    route: null,
    backendSurface: '/admin/benefits/vouchers, /admin/benefits/loyalty-tiers, /admin/settings/benefits',
    workflow: 'import-export',
    branchSensitive: false,
  },
  {
    key: 'privacy-review',
    group: 'catalog',
    title: 'Privacy review',
    description: 'Privacy request review and customer export flows stay inside the back-office governance surface.',
    capability: 'privacy.manage',
    route: null,
    backendSurface: '/admin/privacy/requests, /admin/privacy/customers/{user_id}/data-export',
    workflow: 'dry-run-commit',
    branchSensitive: false,
  },
  {
    key: 'inventory-purchasing',
    group: 'supply',
    title: 'Inventory and purchasing',
    description: 'Ingredient, supplier, purchase-order, and receiving reads are grouped into one supply-control lane.',
    capability: 'inventory.manage',
    route: staffRoutePaths.admin.inventory,
    backendSurface: '/admin/inventory/ingredients, /admin/inventory/suppliers, /admin/inventory/purchase-orders',
    workflow: 'read-write',
    branchSensitive: true,
  },
  {
    key: 'reporting-read-models',
    group: 'governance',
    title: 'Reporting read models',
    description: 'Daily sales, operations, and inventory snapshots stay in the admin workspace instead of the floor lane.',
    capability: 'reporting.view',
    route: staffRoutePaths.admin.reporting,
    backendSurface: '/staff/reporting/daily-sales, /staff/reporting/daily-operations, /staff/reporting/daily-inventory',
    workflow: 'read-only',
    branchSensitive: true,
  },
  {
    key: 'audit-trail',
    group: 'governance',
    title: 'Audit trail',
    description: 'Request-linked audit reads and investigation paths stay in back-office governance.',
    capability: 'audit.view',
    route: staffRoutePaths.admin.auditTrail,
    backendSurface: '/staff/audit-trail',
    workflow: 'read-only',
    branchSensitive: true,
  },
];

export function resolveAdminWorkspaceCards(session: StaffSession | null): Array<AdminWorkspaceCard> {
  return adminWorkspaceDefinitions.map((definition) => {
    const enabled = !definition.capability || can(session, definition.capability);
    const hasRoute = typeof definition.route === 'string' && definition.route !== '';
    const status: AdminWorkspaceCardStatus = !enabled
      ? 'restricted'
      : hasRoute
        ? 'live'
        : 'contract-ready';

    return {
      ...definition,
      enabled,
      status,
      statusLabel: statusLabel(status),
      workflowLabel: workflowLabel(definition.workflow),
      actionPath: enabled && hasRoute ? definition.route ?? null : null,
    };
  });
}

export function groupAdminWorkspaceCards(cards: Array<AdminWorkspaceCard>): Array<AdminWorkspaceGroup> {
  const groups: Array<AdminWorkspaceGroup> = [
    { key: 'control', label: 'Configuration ownership', cards: cards.filter((card) => card.group === 'control') },
    { key: 'catalog', label: 'Catalog and benefits', cards: cards.filter((card) => card.group === 'catalog') },
    { key: 'supply', label: 'Supply and receiving', cards: cards.filter((card) => card.group === 'supply') },
    { key: 'governance', label: 'Governance and read models', cards: cards.filter((card) => card.group === 'governance') },
  ];

  return groups.filter((group) => group.cards.length > 0);
}

export function summarizeAdminWorkspace(cards: Array<AdminWorkspaceCard>) {
  return {
    domainCount: cards.length,
    enabledCount: cards.filter((card) => card.enabled).length,
    liveCount: cards.filter((card) => card.status === 'live').length,
    importExportCount: cards.filter((card) => card.workflow === 'import-export').length,
    reviewCount: cards.filter((card) => card.workflow === 'dry-run-commit').length,
  };
}

export function resolveAdminQuickLinks(cards: Array<AdminWorkspaceCard>): Array<AdminWorkspaceCard> {
  const seenPaths = new Set<string>();

  return cards.filter((card) => {
    if (!card.enabled || !card.actionPath) {
      return false;
    }

    if (seenPaths.has(card.actionPath)) {
      return false;
    }

    seenPaths.add(card.actionPath);
    return true;
  });
}

function statusLabel(status: AdminWorkspaceCardStatus): string {
  switch (status) {
    case 'live':
      return 'Live page';
    case 'contract-ready':
      return 'API ready';
    default:
      return 'Capability required';
  }
}

function workflowLabel(workflow: AdminWorkspaceCardDefinition['workflow']): string {
  switch (workflow) {
    case 'import-export':
      return 'Import / export';
    case 'dry-run-commit':
      return 'Review / commit';
    case 'read-write':
      return 'Read / write';
    default:
      return 'Read only';
  }
}
