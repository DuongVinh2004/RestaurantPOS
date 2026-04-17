import { staffRoutePaths } from './workspace-paths';

export type JourneySource = 'board' | 'reservation' | 'order' | 'kitchen' | 'checkout' | 'refund' | 'audit';

export type JourneyContext = {
  source?: JourneySource;
  tableId?: number;
  tableIds?: Array<number>;
  reservationId?: number;
  reservationRowVersion?: number;
  orderId?: number;
  orderRowVersion?: number;
  stationId?: number;
};

export type JourneyResumeTarget = {
  path: string;
  label: string;
};

export function mergeJourneyContext(primary: JourneyContext, fallback: JourneyContext): JourneyContext {
  return {
    source: primary.source ?? fallback.source,
    tableId: primary.tableId ?? primary.tableIds?.[0] ?? fallback.tableId ?? fallback.tableIds?.[0],
    tableIds: primary.tableIds ?? fallback.tableIds,
    reservationId: primary.reservationId ?? fallback.reservationId,
    reservationRowVersion: primary.reservationRowVersion ?? fallback.reservationRowVersion,
    orderId: primary.orderId ?? fallback.orderId,
    orderRowVersion: primary.orderRowVersion ?? fallback.orderRowVersion,
    stationId: primary.stationId ?? fallback.stationId,
  };
}

export function buildJourneySearch(context: JourneyContext): string {
  const params = new URLSearchParams();
  const normalizedTableIds = normalizePositiveIntegerList(context.tableIds);

  if (context.source) {
    params.set('source', context.source);
  }

  setPositiveInteger(params, 'table_id', context.tableId ?? normalizedTableIds[0]);
  setPositiveIntegerList(params, 'table_ids', normalizedTableIds);
  setPositiveInteger(params, 'reservation_id', context.reservationId);
  setPositiveInteger(params, 'reservation_row_version', context.reservationRowVersion);
  setPositiveInteger(params, 'order_id', context.orderId);
  setPositiveInteger(params, 'order_row_version', context.orderRowVersion);
  setPositiveInteger(params, 'station_id', context.stationId);

  return params.toString();
}

export function buildJourneyResumeTarget(context: JourneyContext): JourneyResumeTarget | null {
  const resolvedPath = resolveJourneyPath(context);
  if (!resolvedPath) {
    return null;
  }

  const search = buildJourneySearch(context);

  return {
    path: search ? `${resolvedPath}?${search}` : resolvedPath,
    label: resumeLabelForPath(resolvedPath),
  };
}

export function readJourneyContext(search: string | URLSearchParams): JourneyContext {
  const params = toSearchParams(search);
  const source = params.get('source');
  const tableIds = readPositiveIntegerList(params.get('table_ids'));
  const tableId = readPositiveInteger(params.get('table_id')) ?? tableIds[0];

  return {
    source: isJourneySource(source) ? source : undefined,
    tableId,
    tableIds: tableIds.length > 0 ? tableIds : undefined,
    reservationId: readPositiveInteger(params.get('reservation_id')),
    reservationRowVersion: readPositiveInteger(params.get('reservation_row_version')),
    orderId: readPositiveInteger(params.get('order_id')),
    orderRowVersion: readPositiveInteger(params.get('order_row_version')),
    stationId: readPositiveInteger(params.get('station_id')),
  };
}

export function stripJourneySearch(search: string | URLSearchParams): string {
  const params = toSearchParams(search);

  params.delete('source');
  params.delete('table_id');
  params.delete('table_ids');
  params.delete('reservation_id');
  params.delete('reservation_row_version');
  params.delete('order_id');
  params.delete('order_row_version');
  params.delete('station_id');

  return params.toString();
}

export function mergeJourneySearch(
  currentSearch: string | URLSearchParams,
  context: JourneyContext,
): string {
  const params = toSearchParams(stripJourneySearch(currentSearch));
  const journeyParams = toSearchParams(buildJourneySearch(context));

  for (const [key, value] of journeyParams.entries()) {
    params.set(key, value);
  }

  return params.toString();
}

function setPositiveInteger(params: URLSearchParams, key: string, value: number | undefined): void {
  if (typeof value === 'number' && Number.isInteger(value) && value > 0) {
    params.set(key, String(value));
  }
}

function setPositiveIntegerList(params: URLSearchParams, key: string, value: Array<number>): void {
  if (value.length > 0) {
    params.set(key, value.join(','));
  }
}

function readPositiveInteger(value: string | null): number | undefined {
  if (!value) {
    return undefined;
  }

  const parsed = Number(value);
  if (!Number.isInteger(parsed) || parsed <= 0) {
    return undefined;
  }

  return parsed;
}

function readPositiveIntegerList(value: string | null): Array<number> {
  if (!value) {
    return [];
  }

  return normalizePositiveIntegerList(
    value
      .split(',')
      .map((entry) => Number(entry.trim())),
  );
}

function normalizePositiveIntegerList(value: Array<number> | undefined): Array<number> {
  if (!Array.isArray(value)) {
    return [];
  }

  return Array.from(new Set(
    value.filter((entry): entry is number => Number.isInteger(entry) && entry > 0),
  ));
}

function isJourneySource(value: string | null): value is JourneySource {
  return value === 'board'
    || value === 'reservation'
    || value === 'order'
    || value === 'kitchen'
    || value === 'checkout'
    || value === 'refund'
    || value === 'audit';
}

function toSearchParams(search: string | URLSearchParams): URLSearchParams {
  if (search instanceof URLSearchParams) {
    return new URLSearchParams(search);
  }

  return new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);
}

function resolveJourneyPath(context: JourneyContext): string | null {
  if (context.source === 'refund' && (context.reservationId || context.orderId)) {
    return staffRoutePaths.ops.refunds;
  }

  if (context.source === 'checkout' && context.orderId) {
    return staffRoutePaths.ops.checkout;
  }

  if (context.source === 'kitchen' && (context.stationId || context.orderId)) {
    return staffRoutePaths.kitchen.landing;
  }

  if (context.source === 'order' && (context.orderId || context.tableId || context.reservationId)) {
    return staffRoutePaths.ops.orders;
  }

  if (context.source === 'reservation' && context.reservationId) {
    return staffRoutePaths.ops.reservations;
  }

  if (context.source === 'board' && context.tableId) {
    return staffRoutePaths.ops.tables;
  }

  if (context.orderId) {
    return staffRoutePaths.ops.orders;
  }

  if (context.reservationId) {
    return staffRoutePaths.ops.reservations;
  }

  if (context.tableId) {
    return staffRoutePaths.ops.tables;
  }

  if (context.stationId) {
    return staffRoutePaths.kitchen.landing;
  }

  return null;
}

function resumeLabelForPath(path: string): string {
  switch (path) {
    case staffRoutePaths.ops.refunds:
      return 'Tiếp tục hoàn tiền';
    case staffRoutePaths.ops.checkout:
      return 'Tiếp tục thanh toán';
    case staffRoutePaths.kitchen.landing:
      return 'Tiếp tục bếp';
    case staffRoutePaths.ops.orders:
      return 'Tiếp tục đơn hàng';
    case staffRoutePaths.ops.reservations:
      return 'Tiếp tục đặt bàn';
    default:
      return 'Tiếp tục sơ đồ bàn';
  }
}
