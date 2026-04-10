import { describe, expect, it } from 'vitest';
import { visibleNavigation } from './navigation';
import type { StaffSession } from '../../core/auth/storage';

function makeSession(capabilities: Array<string>): StaffSession {
  return {
    auth_mode: 'staff_api_key',
    token_type: 'opaque',
    auth_header: 'X-Staff-Key',
    staff_api_key_id: 1,
    capabilities,
    known_capabilities: capabilities,
    capability_source: 'role',
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
  it('returns only routes the session can access', () => {
    const items = visibleNavigation(makeSession(['table.board.view', 'order.manage']));
    expect(items.map((item) => item.path)).toEqual(['/tables', '/orders']);
  });

  it('includes waiting list and cashier shift only when those capabilities exist', () => {
    const items = visibleNavigation(makeSession(['waiting_list.manage', 'cashier.shift.manage']));
    expect(items.map((item) => item.path)).toEqual(['/waiting-list', '/cashier-shift']);
  });

  it('shows checkout and finance review under settlement capability', () => {
    const items = visibleNavigation(makeSession(['settlement.manage']));
    expect(items.map((item) => item.path)).toEqual(['/checkout', '/finance-review']);
  });

  it('shows the conversation inbox only when conversation capability exists', () => {
    const items = visibleNavigation(makeSession(['conversation.manage']));
    expect(items.map((item) => item.path)).toEqual(['/conversations']);
  });

  it('shows the audit trail only when audit capability exists', () => {
    const items = visibleNavigation(makeSession(['audit.view']));
    expect(items.map((item) => item.path)).toEqual(['/audit-trail']);
  });

  it('shows reporting only when reporting capability exists', () => {
    const items = visibleNavigation(makeSession(['reporting.view']));
    expect(items.map((item) => item.path)).toEqual(['/reporting']);
  });

  it('uses Vietnamese reservation copy that matches restaurant operations', () => {
    const [item] = visibleNavigation(makeSession(['reservation.manage']));
    expect(item?.label).toBe('Đặt bàn');
    expect(item?.description).toContain('nhận bàn');
  });
});
