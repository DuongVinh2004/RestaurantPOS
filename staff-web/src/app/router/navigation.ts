import type { StaffSession } from '../../core/auth/storage';
import { can } from '../../core/permissions/capabilities';
import type { StaffNavGroup, StaffNavItem } from '../../core/types/navigation';

export const staffNavigationGroups: Array<StaffNavGroup> = [
  {
    key: 'floor-operations',
    label: 'Điều phối sàn',
    items: [
      {
        key: 'dashboard',
        label: 'Tổng quan',
        path: '/dashboard',
        iconKey: 'dashboard',
        description: 'Nhìn nhanh việc nóng, KPI ca và nơi cần vào xử lý ngay.',
      },
      {
        key: 'tables',
        label: 'Sơ đồ bàn',
        path: '/tables',
        iconKey: 'tables',
        capability: 'table.board.view',
        description: 'Theo dõi bàn đang phục vụ, bàn trống và nhịp điều phối mặt sàn.',
      },
      {
        key: 'reservations',
        label: 'Đặt bàn',
        path: '/reservations',
        iconKey: 'reservations',
        capability: 'reservation.manage',
        description: 'Xử lý lịch đến, gán bàn và các lượt nhận bàn trong ngày.',
      },
      {
        key: 'waiting-list',
        label: 'Chờ bàn',
        path: '/waiting-list',
        iconKey: 'waiting',
        capability: 'waiting_list.manage',
        description: 'Điều phối khách chờ, gọi lại và đưa khách vào bàn đúng lúc.',
      },
      {
        key: 'orders',
        label: 'Đơn hàng',
        path: '/orders',
        iconKey: 'orders',
        capability: 'order.manage',
        description: 'Mở đơn, thêm món và đẩy bếp theo đúng ngữ cảnh bàn.',
      },
    ],
  },
  {
    key: 'kitchen-payment',
    label: 'Bếp & Thanh toán',
    items: [
      {
        key: 'kitchen',
        label: 'Bếp',
        path: '/kitchen',
        iconKey: 'kitchen',
        capability: 'kitchen.manage',
        description: 'Theo dõi hàng chờ, trạm nghẽn và tiến độ ra món.',
      },
      {
        key: 'checkout',
        label: 'Thanh toán',
        path: '/checkout',
        iconKey: 'checkout',
        capability: 'settlement.manage',
        description: 'Chốt bill và hoàn tất thanh toán theo đúng phiên bản đơn.',
      },
      {
        key: 'cashier-shift',
        label: 'Ca thu ngân',
        path: '/cashier-shift',
        iconKey: 'cashier',
        capability: 'cashier.shift.manage',
        description: 'Mở ca, theo dõi giao dịch và kiểm tra trước khi bàn giao.',
      },
      {
        key: 'finance-review',
        label: 'Đối soát',
        path: '/finance-review',
        iconKey: 'finance',
        capability: 'settlement.manage',
        description: 'Rà soát chênh lệch, hoàn tiền và xử lý các dòng cần kiểm tra.',
      },
    ],
  },
  {
    key: 'support-control',
    label: 'Hỗ trợ khách & kiểm soát',
    items: [
      {
        key: 'conversations',
        label: 'Hội thoại',
        path: '/conversations',
        iconKey: 'conversations',
        capability: 'conversation.manage',
        description: 'Nhận xử lý, phân công và phản hồi các cuộc hội thoại mở.',
      },
      {
        key: 'audit-trail',
        label: 'Nhật ký',
        path: '/audit-trail',
        iconKey: 'audit',
        capability: 'audit.view',
        description: 'Lần vết thao tác khi cần kiểm tra hoặc đối chiếu sự cố.',
      },
    ],
  },
  {
    key: 'oversight',
    label: 'Giám sát',
    items: [
      {
        key: 'reporting',
        label: 'Báo cáo',
        path: '/reporting',
        iconKey: 'reporting',
        capability: 'reporting.view',
        description: 'Theo dõi snapshot bán hàng, vận hành và tồn kho trong ngày.',
      },
    ],
  },
];

function itemIsVisible(session: StaffSession, item: StaffNavItem): boolean {
  return !item.capability || can(session, item.capability);
}

function withCanonicalNavigationCopy(item: StaffNavItem): StaffNavItem {
  if (item.key !== 'checkout') {
    return item;
  }

  return {
    ...item,
    label: 'Thanh toán & hoàn tiền',
    description: 'Chốt bill, hoàn tiền theo reservation và hoàn tất thanh toán theo đúng phiên bản đơn.',
  };
}

export function visibleNavigation(session: StaffSession | null): Array<StaffNavItem> {
  if (!session) {
    return [];
  }

  return staffNavigationGroups.flatMap((group) => (
    group.items
      .filter((item) => itemIsVisible(session, item))
      .map((item) => withCanonicalNavigationCopy(item))
  ));
}

export function visibleNavigationGroups(
  session: StaffSession | null,
  badgeCounts: Partial<Record<string, number>> = {},
): Array<StaffNavGroup> {
  if (!session) {
    return [];
  }

  return staffNavigationGroups
    .map((group) => ({
      ...group,
      items: group.items
        .filter((item) => itemIsVisible(session, item))
        .map((item) => ({
          ...withCanonicalNavigationCopy(item),
          badgeCount: badgeCounts[item.key] ?? null,
        })),
    }))
    .filter((group) => group.items.length > 0);
}
