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
import type { StaffSession } from '../api/client';
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
    label: 'Board',
    description: 'Table board, waiting notify/seat, va check-in theo row_version.',
    icon: LayoutGrid,
    capabilities: ['table.board.view', 'waiting_list.manage'],
  },
  {
    path: '/orders',
    label: 'Orders',
    description: 'Create order, add items, va read order detail tu canonical routes.',
    icon: ReceiptText,
    capabilities: ['order.manage'],
  },
  {
    path: '/kitchen',
    label: 'Kitchen',
    description: 'Station list, ticket state, dispatch, fire, bump, recall cho KDS day-1.',
    icon: ChefHat,
    capabilities: ['order.manage'],
  },
  {
    path: '/settlement',
    label: 'Settlement',
    description: 'Bill snapshot, settlement preview, finalize va conflict handling.',
    icon: WalletCards,
    capabilities: ['settlement.manage'],
    requiresCashierShift: true,
  },
  {
    path: '/refunds',
    label: 'Refunds',
    description: 'Refund preview, refund-only, va refund-cancel theo reservation.',
    icon: RotateCcw,
    capabilities: ['payment.refund'],
    requiresCashierShift: true,
  },
  {
    path: '/cashier',
    label: 'Cashier',
    description: 'Current/open/show/close cashier shift va cash summary.',
    icon: WalletMinimal,
    capabilities: ['settlement.manage'],
    requiresCashierShift: true,
  },
  {
    path: '/reporting',
    label: 'Reporting',
    description: 'Daily sales, operations, va inventory snapshots cho branch lead reads.',
    icon: BarChart3,
    capabilities: ['settlement.manage'],
  },
  {
    path: '/inventory',
    label: 'Inventory',
    description: 'Ingredient stock, supplier contacts, va purchase-order status theo rollout uplift.',
    icon: Boxes,
    capabilities: ['inventory.manage'],
  },
  {
    path: '/settings',
    label: 'Settings',
    description: 'Branch state, timezone, closure windows, va booking policy read-only.',
    icon: Settings2,
    capabilities: ['settings.manage'],
  },
  {
    path: '/conversations',
    label: 'Inbox',
    description: 'Conversation inbox, take-over, internal note, outbound reply.',
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
