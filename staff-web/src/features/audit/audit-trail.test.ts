import { describe, expect, it } from 'vitest';
import type { AuditTrailEntry } from '../../core/api/staff-api';
import {
  auditActorDetail,
  auditActorLabel,
  auditLinkedEntityTarget,
  auditRequestSummary,
  auditRelatedSubjects,
  auditSubjectLabel,
  auditSummaryLine,
  buildAuditTrailQuery,
  buildInitialAuditFilters,
} from './audit-trail';

const sampleEntry: AuditTrailEntry = {
  audit_id: 9,
  action: 'reservation.checked_in',
  occurred_at: '2026-04-10T01:00:00Z',
  primary_subject: {
    type: 'reservation',
    id: '101',
  },
  subjects: [
    { type: 'reservation', id: '101', role: 'primary' },
    { type: 'restaurant_table', id: '7', role: 'table' },
  ],
  actor: {
    user_id: 3,
    type: 'staff_user',
    key: 'staff_api_key:11',
    user: {
      user_id: 3,
      full_name: 'Tran Minh',
    },
  },
  request: {
    request_id: 'req-1',
    branch_id: 4,
    ip: '127.0.0.1',
    user_agent: 'Vitest',
    method: 'POST',
    path: '/api/v1/staff/reservations/101/check-in',
  },
  before: { status: 'Confirmed' },
  after: { status: 'CheckedIn' },
  summary: { table_count: 1, checked_in_at: '2026-04-10T01:00:00Z' },
  meta: { request: { method: 'POST' } },
};

describe('audit trail helpers', () => {
  it('builds shell-scoped audit queries with request and free-text filters', () => {
    expect(buildAuditTrailQuery({
      ...buildInitialAuditFilters(3),
      requestId: 'req-9',
      searchText: 'refund',
      action: 'reservation.checked_in',
      actorType: 'staff_user',
      actorUserId: '3',
      referenceType: 'reservation',
      referenceId: '101',
      dateFrom: '2026-04-01',
      dateTo: '2026-04-10',
    }, 3, 2, 20)).toEqual({
      branch_id: 3,
      request_id: 'req-9',
      q: 'refund',
      action: 'reservation.checked_in',
      actor_type: 'staff_user',
      actor_user_id: 3,
      reservation_id: 101,
      date_from: '2026-04-01',
      date_to: '2026-04-10',
      per_page: 20,
      page: 2,
    });
  });

  it('maps custom subject filters only when subject mode is selected', () => {
    expect(buildAuditTrailQuery({
      ...buildInitialAuditFilters(null),
      referenceType: 'subject',
      referenceId: '55',
      subjectType: 'payment',
    }, null, 1, 20)).toEqual({
      subject_type: 'payment',
      subject_id: '55',
      per_page: 20,
      page: 1,
    });
  });

  it('formats actor, subject, request and summary labels for the table/detail views', () => {
    expect(auditActorLabel(sampleEntry)).toBe('Tran Minh');
    expect(auditActorDetail(sampleEntry)).toContain('Nhân viên');
    expect(auditSubjectLabel(sampleEntry)).toBe('Đặt bàn #101');
    expect(auditSummaryLine(sampleEntry)).toContain('Số bàn: 1');
    expect(auditRequestSummary(sampleEntry)).toContain('branch #4');
    expect(auditRelatedSubjects(sampleEntry)).toContain('Bàn #7 (Bàn)');
  });

  it('builds journey-aware reservation links for operational follow-up', () => {
    expect(auditLinkedEntityTarget(sampleEntry, {
      canManageReservations: true,
      canManageOrders: true,
    })).toEqual({
      kind: 'reservation',
      id: 101,
      path: '/reservations?source=audit&reservation_id=101',
    });
  });

  it('falls back to an order journey link when reservation follow-up is not allowed', () => {
    const orderEntry: AuditTrailEntry = {
      ...sampleEntry,
      primary_subject: {
        type: 'reservation_order',
        id: '9001',
      },
      subjects: [
        { type: 'reservation_order', id: '9001', role: 'primary' },
      ],
    };

    expect(auditLinkedEntityTarget(orderEntry, {
      canManageReservations: false,
      canManageOrders: true,
    })).toEqual({
      kind: 'order',
      id: 9001,
      path: '/orders?source=audit&order_id=9001',
    });
  });
});
