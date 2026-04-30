import type { StaffWorkspaceNavigationDefinition } from './types';
import { staffRoutePaths } from '../../app/router/workspace-paths';

export const adminNavigation: StaffWorkspaceNavigationDefinition = {
  workspace: 'admin',
  label: 'Quản trị',
  description: 'Cấu hình nhà hàng, danh mục, báo cáo và dữ liệu kiểm soát.',
  landingPath: staffRoutePaths.admin.landing,
  groups: [
    {
      key: 'admin-overview',
      label: 'Trung tâm',
      items: [
        {
          key: 'admin-landing',
          label: 'Trang quản trị',
          path: staffRoutePaths.admin.landing,
          iconKey: 'dashboard',
          workspace: 'admin',
          description: 'Chọn nhanh khu vực cấu hình hoặc báo cáo cần xử lý.',
          exact: true,
        },
      ],
    },
    {
      key: 'admin-configuration',
      label: 'Cấu hình',
      items: [
        {
          key: 'admin-settings',
          label: 'Thiết lập',
          path: staffRoutePaths.admin.settings,
          iconKey: 'settings',
          workspace: 'admin',
          capability: 'settings.manage',
          description: 'Chi nhánh, bàn, khu vực, tuyến bếp và các cấu hình vận hành.',
        },
        {
          key: 'admin-catalog',
          label: 'Thực đơn',
          path: staffRoutePaths.admin.catalog,
          iconKey: 'menu',
          workspace: 'admin',
          capability: 'menu.manage',
          description: 'Loại món, món ăn, giá bán và nhập danh mục thực đơn.',
        },
        {
          key: 'admin-inventory',
          label: 'Kho',
          path: staffRoutePaths.admin.inventory,
          iconKey: 'inventory',
          workspace: 'admin',
          capability: 'inventory.manage',
          description: 'Nguyên liệu, nhà cung cấp, đơn mua và phiếu nhận hàng.',
        },
      ],
    },
    {
      key: 'admin-governance',
      label: 'Kiểm soát',
      items: [
        {
          key: 'reporting',
          label: 'Báo cáo',
          path: staffRoutePaths.admin.reporting,
          iconKey: 'reporting',
          workspace: 'admin',
          capability: 'reporting.view',
          description: 'Báo cáo bán hàng, vận hành, tồn kho và độ mới dữ liệu theo chi nhánh.',
        },
        {
          key: 'audit-trail',
          label: 'Nhật ký thao tác',
          path: staffRoutePaths.admin.auditTrail,
          iconKey: 'audit',
          workspace: 'admin',
          capability: 'audit.view',
          description: 'Tra cứu lịch sử thao tác và dữ liệu truy vết theo request.',
        },
      ],
    },
  ],
};
