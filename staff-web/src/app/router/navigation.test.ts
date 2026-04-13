import { describe, expect, it } from 'vitest';
import type { StaffSession } from '../../core/auth/storage';
import { visibleNavigation, visibleNavigationGroups } from './navigation';

function makeSession(capabilities: Array<string>): StaffSession {
  return {
    auth_mode: 'staff_api_key',
    token_type: 'opaque',
    auth_header: 'X-Staff-Key',
    staff_api_key_id: 1,
    capabilities,
    known_capabilities: capabilities,
    capability_source: 'role_capabilities',
    startup: {
      default_branch: null,
      active_cashier_shift: null,
      readiness: {
        access: 'ready',
        branch: 'missing',
        cashier_shift: 'not_applicable',
        operator_ready: true,
        requires_cashier_shift: false,
        granted_capability_count: capabilities.length,
        known_capability_count: capabilities.length,
      },
    },
  };
}

describe('navigation visibility', () => {
  it('returns only routes the session can access and keeps dashboard first', () => {
    const items = visibleNavigation(makeSession(['table.board.view', 'order.manage']));
    expect(items.map((item) => item.path)).toEqual(['/dashboard', '/tables', '/orders']);
  });

  it('includes waiting list and cashier shift only when those capabilities exist', () => {
    const items = visibleNavigation(makeSession(['waiting_list.manage', 'cashier.shift.manage']));
    expect(items.map((item) => item.path)).toEqual(['/dashboard', '/waiting-list', '/cashier-shift']);
  });

  it('shows checkout and finance review under settlement capability', () => {
    const items = visibleNavigation(makeSession(['settlement.manage']));
    expect(items.map((item) => item.path)).toEqual(['/dashboard', '/checkout', '/finance-review']);
  });

  it('uses checkout as the canonical settlement and refund workspace', () => {
    const items = visibleNavigation(makeSession(['settlement.manage', 'payment.refund']));
    const checkoutItem = items.find((item) => item.key === 'checkout');

    expect(checkoutItem?.label).toBe('Thanh toán & hoàn tiền');
    expect(checkoutItem?.description).toContain('hoàn tiền');
    expect(items.some((item) => item.path === '/refunds' || item.path === '/settlement')).toBe(false);
  });

  it('shows reporting only when reporting capability exists', () => {
    const items = visibleNavigation(makeSession(['reporting.view']));
    expect(items.map((item) => item.path)).toEqual(['/dashboard', '/reporting']);
  });

  it('uses Vietnamese reservation copy that matches restaurant operations', () => {
    const item = visibleNavigation(makeSession(['reservation.manage'])).find((entry) => entry.key === 'reservations');
    expect(item?.label).toBe('Đặt bàn');
    expect(item?.description).toContain('lịch đến');
  });

  it('groups navigation by workstream with dashboard at the top', () => {
    const groups = visibleNavigationGroups(makeSession(['table.board.view', 'kitchen.manage', 'reporting.view']));
    expect(groups.map((group) => group.label)).toEqual(['Điều phối sàn', 'Bếp & Thanh toán', 'Giám sát']);
    expect(groups[0]?.items.map((item) => item.path)).toEqual(['/dashboard', '/tables']);
    expect(groups[0]?.items[0]?.label).toBe('Tổng quan');
  });
});
