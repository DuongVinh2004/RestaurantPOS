import type { AuditTrailEntry, AuditTrailQuery } from '../../core/api/staff-api';
import { translateUiCode } from '../../core/utils/translation';

export type AuditReferenceFilter =
  | 'reservation'
  | 'order'
  | 'payment'
  | 'waiting'
  | 'table'
  | 'cashier_shift'
  | 'subject';

export type AuditFilterState = {
  action: string;
  actorType: string;
  actorUserId: string;
  referenceType: AuditReferenceFilter;
  referenceId: string;
  subjectType: string;
  dateFrom: string;
  dateTo: string;
};

export function buildAuditTrailQuery(filters: AuditFilterState, page: number, perPage: number): AuditTrailQuery {
  const query: AuditTrailQuery = {
    action: nullableString(filters.action),
    actor_type: nullableString(filters.actorType),
    actor_user_id: parsePositiveInteger(filters.actorUserId),
    date_from: nullableString(filters.dateFrom),
    date_to: nullableString(filters.dateTo),
    per_page: perPage,
    page,
  };

  if (filters.referenceType === 'subject') {
    const subjectId = nullableString(filters.referenceId);
    if (subjectId) {
      query.subject_type = nullableString(filters.subjectType);
      query.subject_id = subjectId;
    }

    return query;
  }

  const referenceId = parsePositiveInteger(filters.referenceId);
  if (referenceId === undefined) {
    return query;
  }

  switch (filters.referenceType) {
    case 'reservation':
      query.reservation_id = referenceId;
      break;
    case 'order':
      query.order_id = referenceId;
      break;
    case 'payment':
      query.payment_id = referenceId;
      break;
    case 'waiting':
      query.waiting_id = referenceId;
      break;
    case 'table':
      query.table_id = referenceId;
      break;
    case 'cashier_shift':
      query.cashier_shift_id = referenceId;
      break;
    default:
      break;
  }

  return query;
}

export function auditActorLabel(entry: AuditTrailEntry): string {
  return entry.actor.user?.full_name ?? translateUiCode(entry.actor.type) ?? 'Tác nhân chưa rõ';
}

export function auditActorDetail(entry: AuditTrailEntry): string {
  const parts = [
    nullableString(entry.actor.type) ? translateUiCode(entry.actor.type) : null,
    entry.actor.user_id ? `Người dùng #${entry.actor.user_id}` : null,
    nullableString(entry.actor.key),
  ].filter((value): value is string => !!value);

  return parts.join(' | ') || 'Chưa có chi tiết tác nhân';
}

export function auditSubjectLabel(entry: AuditTrailEntry): string {
  return `${humanizeCode(entry.primary_subject.type)} #${entry.primary_subject.id}`;
}

export function auditSummaryLine(entry: AuditTrailEntry): string {
  if (entry.summary && Object.keys(entry.summary).length > 0) {
    return Object.entries(entry.summary)
      .slice(0, 3)
      .map(([key, value]) => `${humanizeCode(key)}: ${stringifyValue(value)}`)
      .join(' | ');
  }

  return 'Không có dữ liệu tóm tắt';
}

export function auditRelatedSubjects(entry: AuditTrailEntry): Array<string> {
  return entry.subjects.map((subject) => {
    const role = nullableString(subject.role);
    return `${humanizeCode(subject.type)} #${subject.id}${role ? ` (${humanizeCode(role)})` : ''}`;
  });
}

function parsePositiveInteger(value: string): number | undefined {
  const parsed = Number(value);
  if (!Number.isInteger(parsed) || parsed <= 0) {
    return undefined;
  }

  return parsed;
}

function nullableString(value: string | null | undefined): string | undefined {
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : undefined;
}

function humanizeCode(value: string): string {
  return translateUiCode(value);
}

function stringifyValue(value: unknown): string {
  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return String(value);
  }

  if (value === null || value === undefined) {
    return 'Không có';
  }

  return JSON.stringify(value);
}
