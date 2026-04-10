import {
  BarChart3,
  Boxes,
  ChefHat,
  LayoutGrid,
  MessageSquareMore,
  ReceiptText,
  RotateCcw,
  Settings2,
  WalletCards,
  WalletMinimal,
  type LucideIcon,
} from 'lucide-react';
import type { StaffSession } from '../core/auth/storage';
import { hasAnyCapability } from '../lib/capabilities';

export type StaffSection = {
  path: string;
  label: string;
  description: string;
  icon: LucideIcon;
  capabilities: Array<string>;
  requiresCashierShift?: boolean;
};

export const staffSections: Array<StaffSection> = [
  {
    path: '/board',
    label: 'Sơ đồ bàn',
    description: 'Theo dõi bàn trống, khách chờ và việc cần làm ngay.',
    icon: LayoutGrid,
    capabilities: ['table.board.view', 'waiting_list.manage'],
  },
  {
    path: '/orders',
    label: 'Đơn hàng',
    description: 'Mở đơn, thêm món và phục vụ ngay tại bàn.',
    icon: ReceiptText,
    capabilities: ['order.manage'],
  },
  {
    path: '/kitchen',
    label: 'Bếp',
    description: 'Xem phiếu bếp và xử lý món theo từng khu.',
    icon: ChefHat,
    capabilities: ['order.manage'],
  },
  {
    path: '/settlement',
    label: 'Thanh toán',
    description: 'Chốt hóa đơn và thu tiền theo ca.',
    icon: WalletCards,
    capabilities: ['settlement.manage'],
    requiresCashierShift: true,
  },
  {
    path: '/refunds',
    label: 'Hoàn tiền',
    description: 'Xem và xử lý yêu cầu hoàn tiền.',
    icon: RotateCcw,
    capabilities: ['payment.refund'],
    requiresCashierShift: true,
  },
  {
    path: '/cashier',
    label: 'Thu ngân',
    description: 'Theo dõi mở ca, đóng ca và tiền mặt.',
    icon: WalletMinimal,
    capabilities: ['settlement.manage'],
    requiresCashierShift: true,
  },
  {
    path: '/reporting',
    label: 'Báo cáo',
    description: 'Xem nhanh bán hàng và vận hành trong ngày.',
    icon: BarChart3,
    capabilities: ['settlement.manage'],
  },
  {
    path: '/inventory',
    label: 'Kho',
    description: 'Kiểm tra tồn kho, nhà cung cấp và phiếu nhập.',
    icon: Boxes,
    capabilities: ['inventory.manage'],
  },
  {
    path: '/settings',
    label: 'Thiết lập',
    description: 'Theo dõi chi nhánh và cấu hình cơ bản.',
    icon: Settings2,
    capabilities: ['settings.manage'],
  },
  {
    path: '/conversations',
    label: 'Hộp thư',
    description: 'Nhận hội thoại và ghi chú nội bộ.',
    icon: MessageSquareMore,
    capabilities: ['conversation.manage'],
  },
];

export function canAccessStaffSection(session: StaffSession | null, section: StaffSection): boolean {
  if (!session) {
    return false;
  }

  if (!session.startup.readiness.operator_ready) {
    return false;
  }

  if (!hasAnyCapability(session, section.capabilities)) {
    return false;
  }

  if (!section.requiresCashierShift) {
    return true;
  }

  return !session.startup.readiness.requires_cashier_shift || session.startup.readiness.cashier_shift === 'ready';
}

export function visibleStaffSections(session: StaffSession | null): Array<StaffSection> {
  if (!session) {
    return [];
  }

  return staffSections.filter((section) => canAccessStaffSection(session, section));
}

export function defaultStaffPath(session: StaffSession | null): string {
  return visibleStaffSections(session)[0]?.path ?? '/access';
}
