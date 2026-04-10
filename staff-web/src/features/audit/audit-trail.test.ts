import { describe, expect, it } from 'vitest';
import type { AuditTrailEntry } from '../../core/api/staff-api';
import {
  auditActorDetail,
  auditActorLabel,
  auditRelatedSubjects,
  auditSubjectLabel,
  auditSummaryLine,
  buildAuditTrailQuery,
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
    method: 'POST',
    path: '/api/v1/staff/reservations/101/check-in',
  },
  before: { status: 'Confirmed' },
  after: { status: 'CheckedIn' },
  summary: { table_count: 1, checked_in_at: '2026-04-10T01:00:00Z' },
  meta: { request: { method: 'POST' } },
};

describe('audit trail helpers', () => {
  it('maps reference filters to the backend query surface', () => {
    expect(buildAuditTrailQuery({
      action: 'reservation.checked_in',
      actorType: 'staff_user',
      actorUserId: '3',
      referenceType: 'reservation',
      referenceId: '101',
      subjectType: '',
      dateFrom: '2026-04-01',
      dateTo: '2026-04-10',
    }, 2, 20)).toEqual({
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
      action: '',
      actorType: '',
      actorUserId: '',
      referenceType: 'subject',
      referenceId: '55',
      subjectType: 'payment',
      dateFrom: '',
      dateTo: '',
    }, 1, 20)).toEqual({
      subject_type: 'payment',
      subject_id: '55',
      per_page: 20,
      page: 1,
    });
  });

  it('formats actor, subject and summary labels for the table/detail views', () => {
    expect(auditActorLabel(sampleEntry)).toBe('Tran Minh');
    expect(auditActorDetail(sampleEntry)).toContain('Nhân viên');
    expect(auditSubjectLabel(sampleEntry)).toBe('Đặt bàn #101');
    expect(auditSummaryLine(sampleEntry)).toContain('Số bàn: 1');
    expect(auditRelatedSubjects(sampleEntry)).toContain('Bàn #7 (Bàn)');
  });
});
