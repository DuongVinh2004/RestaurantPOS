import type { StaffNavItem } from '../../core/types/navigation';
import { can } from '../../core/permissions/capabilities';
import type { StaffSession } from '../../core/auth/storage';

export const staffNavigation: Array<StaffNavItem> = [
  {
    key: 'tables',
    label: 'Sơ đồ bàn',
    path: '/tables',
    capability: 'table.board.view',
    description: 'Theo dõi bàn, khách vãng lai và ngữ cảnh phục vụ nhanh.',
  },
  {
    key: 'reservations',
    label: 'Đặt bàn',
    path: '/reservations',
    capability: 'reservation.manage',
    description: 'Danh sách đặt bàn, chi tiết, gán bàn và nhận bàn.',
  },
  {
    key: 'waiting-list',
    label: 'Danh sách chờ',
    path: '/waiting-list',
    capability: 'waiting_list.manage',
    description: 'Xếp hàng, báo khách, đẩy hàng chờ, vào bàn và hủy.',
  },
  {
    key: 'orders',
    label: 'Đơn đang phục vụ',
    path: '/orders',
    capability: 'order.manage',
    description: 'Tạo đơn, thêm món, sửa dòng món và chuyển bếp.',
  },
  {
    key: 'kitchen',
    label: 'Bếp',
    path: '/kitchen',
    capability: 'kitchen.manage',
    description: 'Khu bếp, phiếu bếp và chuyển trạng thái.',
  },
  {
    key: 'checkout',
    label: 'Thanh toán',
    path: '/checkout',
    capability: 'settlement.manage',
    description: 'Chốt bill, xem trước và hoàn tất thanh toán.',
  },
  {
    key: 'cashier-shift',
    label: 'Ca thu ngân',
    path: '/cashier-shift',
    capability: 'cashier.shift.manage',
    description: 'Mở ca, theo dõi và đóng ca thu ngân.',
  },
  {
    key: 'finance-review',
    label: 'Đối soát tài chính',
    path: '/finance-review',
    capability: 'settlement.manage',
    description: 'Đối soát, theo dõi hoàn tiền và phát hành hóa đơn.',
  },
  {
    key: 'conversations',
    label: 'Hộp thư hội thoại',
    path: '/conversations',
    capability: 'conversation.manage',
    description: 'Phân loại, nhận xử lý, ghi chú nội bộ và phản hồi ra ngoài.',
  },
  {
    key: 'audit-trail',
    label: 'Nhật ký thao tác',
    path: '/audit-trail',
    capability: 'audit.view',
    description: 'Lịch sử thao tác để soát lỗi và điều tra vận hành.',
  },
  {
    key: 'reporting',
    label: 'Báo cáo',
    path: '/reporting',
    capability: 'reporting.view',
    description: 'Ảnh chụp nhanh bán hàng, vận hành và tồn kho theo ngày.',
  },
];

export function visibleNavigation(session: StaffSession | null): Array<StaffNavItem> {
  if (!session) {
    return [];
  }

  return staffNavigation.filter((item) => can(session, item.capability));
}
