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
    title: 'Chi nhánh và thiết lập',
    description: 'Quản lý chi nhánh, cấu hình tài chính và nhập/xuất dữ liệu cấu hình.',
    capability: 'settings.manage',
    route: staffRoutePaths.admin.settings,
    backendSurface: '/admin/settings/branches, /admin/settings/finance/tax-profile',
    workflow: 'import-export',
    branchSensitive: false,
  },
  {
    key: 'table-config',
    group: 'control',
    title: 'Bàn và khu vực',
    description: 'Thiết lập bàn ăn, khu vực phục vụ và luồng nhập danh mục bàn.',
    capability: 'settings.manage',
    route: staffRoutePaths.admin.settings,
    backendSurface: '/admin/restaurant/tables, /admin/restaurant/zones',
    workflow: 'import-export',
    branchSensitive: true,
  },
  {
    key: 'kitchen-routing-config',
    group: 'control',
    title: 'Tuyến bếp',
    description: 'Cấu hình trạm bếp và tuyến món theo danh mục, tách khỏi màn vận hành bếp trực tiếp.',
    capability: 'settings.manage',
    route: staffRoutePaths.admin.settings,
    backendSurface: '/admin/kitchen/stations, /admin/kitchen/stations/{station_id}/category-routes',
    workflow: 'read-write',
    branchSensitive: true,
  },
  {
    key: 'menu-pricing',
    group: 'catalog',
    title: 'Thực đơn và giá bán',
    description: 'Quản lý loại món, món ăn, giá bán và nhập danh mục thực đơn.',
    capability: 'menu.manage',
    route: staffRoutePaths.admin.catalog,
    backendSurface: '/admin/menu/categories, /admin/menu/items, /admin/menu/prices',
    workflow: 'import-export',
    branchSensitive: false,
  },
  {
    key: 'benefits',
    group: 'catalog',
    title: 'Voucher và khách thân thiết',
    description: 'Quản lý voucher, hạng thành viên và các cấu hình ưu đãi.',
    capability: 'voucher.master_data.manage',
    route: null,
    backendSurface: '/admin/benefits/vouchers, /admin/benefits/loyalty-tiers, /admin/settings/benefits',
    workflow: 'import-export',
    branchSensitive: false,
  },
  {
    key: 'privacy-review',
    group: 'catalog',
    title: 'Rà soát dữ liệu khách',
    description: 'Xử lý yêu cầu dữ liệu cá nhân và xuất dữ liệu khách hàng trong khu vực quản trị.',
    capability: 'privacy.manage',
    route: null,
    backendSurface: '/admin/privacy/requests, /admin/privacy/customers/{user_id}/data-export',
    workflow: 'dry-run-commit',
    branchSensitive: false,
  },
  {
    key: 'inventory-purchasing',
    group: 'supply',
    title: 'Kho và mua hàng',
    description: 'Theo dõi nguyên liệu, nhà cung cấp, đơn mua và nhận hàng.',
    capability: 'inventory.manage',
    route: staffRoutePaths.admin.inventory,
    backendSurface: '/admin/inventory/ingredients, /admin/inventory/suppliers, /admin/inventory/purchase-orders',
    workflow: 'read-write',
    branchSensitive: true,
  },
  {
    key: 'reporting-read-models',
    group: 'governance',
    title: 'Báo cáo vận hành',
    description: 'Xem snapshot bán hàng, vận hành và kho theo ngày trong khu vực quản trị.',
    capability: 'reporting.view',
    route: staffRoutePaths.admin.reporting,
    backendSurface: '/staff/reporting/daily-sales, /staff/reporting/daily-operations, /staff/reporting/daily-inventory',
    workflow: 'read-only',
    branchSensitive: true,
  },
  {
    key: 'audit-trail',
    group: 'governance',
    title: 'Nhật ký thao tác',
    description: 'Tra cứu lịch sử thao tác, request và dữ liệu truy vết phục vụ kiểm soát.',
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
    { key: 'control', label: 'Cấu hình vận hành', cards: cards.filter((card) => card.group === 'control') },
    { key: 'catalog', label: 'Danh mục và ưu đãi', cards: cards.filter((card) => card.group === 'catalog') },
    { key: 'supply', label: 'Kho và nhận hàng', cards: cards.filter((card) => card.group === 'supply') },
    { key: 'governance', label: 'Kiểm soát và báo cáo', cards: cards.filter((card) => card.group === 'governance') },
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
      return 'Đã có màn';
    case 'contract-ready':
      return 'API đã sẵn sàng';
    default:
      return 'Cần quyền';
  }
}

function workflowLabel(workflow: AdminWorkspaceCardDefinition['workflow']): string {
  switch (workflow) {
    case 'import-export':
      return 'Nhập / xuất';
    case 'dry-run-commit':
      return 'Rà soát / ghi nhận';
    case 'read-write':
      return 'Xem / chỉnh sửa';
    default:
      return 'Chỉ xem';
  }
}
