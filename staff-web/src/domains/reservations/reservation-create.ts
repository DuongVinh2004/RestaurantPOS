import type { CreateReservationPayload } from '../../shared/api/staff-api';

export type ReservationCreateFormValues = {
  guest_name: string;
  guest_phone: string;
  guest_email?: string;
  guest_count: number;
  start_time_local: string;
  duration_minutes: number;
  table_ids?: Array<number>;
  notes?: string;
};

export const DEFAULT_RESERVATION_CREATE_GUEST_COUNT = 2;
export const DEFAULT_RESERVATION_CREATE_DURATION_MINUTES = 120;

export function buildDefaultReservationCreateFormValues(
  reference = new Date(),
  defaults: Partial<ReservationCreateFormValues> = {},
): ReservationCreateFormValues {
  const start = new Date(reference.getTime() + 2 * 60 * 60 * 1000);
  start.setMinutes(Math.ceil(start.getMinutes() / 15) * 15, 0, 0);

  return {
    guest_name: '',
    guest_phone: '',
    guest_email: '',
    guest_count: DEFAULT_RESERVATION_CREATE_GUEST_COUNT,
    start_time_local: toDateTimeLocalInputValue(start),
    duration_minutes: DEFAULT_RESERVATION_CREATE_DURATION_MINUTES,
    table_ids: [],
    notes: '',
    ...defaults,
  };
}

export function toDateTimeLocalInputValue(value: Date): string {
  const offsetMinutes = value.getTimezoneOffset();
  return new Date(value.getTime() - offsetMinutes * 60 * 1000).toISOString().slice(0, 16);
}

export function normalizeOptionalText(value: string | undefined): string | null {
  const trimmed = value?.trim() ?? '';
  return trimmed === '' ? null : trimmed;
}

export function buildReservationCreateWindow(
  values: Pick<ReservationCreateFormValues, 'start_time_local' | 'duration_minutes'>,
): { from: string; to: string } | null {
  const startAt = new Date(values.start_time_local);
  if (Number.isNaN(startAt.getTime())) {
    return null;
  }

  const durationMinutes = Number.isFinite(values.duration_minutes)
    ? Math.max(30, Math.min(480, values.duration_minutes))
    : DEFAULT_RESERVATION_CREATE_DURATION_MINUTES;
  const endAt = new Date(startAt.getTime() + durationMinutes * 60 * 1000);

  return {
    from: startAt.toISOString(),
    to: endAt.toISOString(),
  };
}

export function buildReservationCreatePayload(
  values: ReservationCreateFormValues,
  options: {
    branchId?: number | null;
    tableIds?: Array<number> | null;
  },
): CreateReservationPayload {
  const windowRange = buildReservationCreateWindow(values);
  if (!windowRange) {
    throw new Error('Chọn giờ bắt đầu hợp lệ.');
  }

  const tableIds = normalizeTableIds(options.tableIds ?? values.table_ids);
  if (tableIds.length === 0) {
    throw new Error('Chọn ít nhất một bàn phục vụ.');
  }

  return {
    branch_id: options.branchId ?? undefined,
    guest_name: values.guest_name.trim(),
    guest_phone: values.guest_phone.trim(),
    guest_email: normalizeOptionalText(values.guest_email) ?? undefined,
    start_time: windowRange.from,
    end_time: windowRange.to,
    guest_count: values.guest_count,
    table_ids: tableIds,
    notes: normalizeOptionalText(values.notes),
  };
}

function normalizeTableIds(value: Array<number> | null | undefined): Array<number> {
  if (!Array.isArray(value)) {
    return [];
  }

  return Array.from(new Set(value.filter((tableId) => Number.isInteger(tableId) && tableId > 0)));
}
