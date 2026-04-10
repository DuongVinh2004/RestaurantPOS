export type JourneySource = 'board' | 'reservation' | 'order' | 'kitchen' | 'checkout';

export type JourneyContext = {
  source?: JourneySource;
  tableId?: number;
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
    tableId: primary.tableId ?? fallback.tableId,
    reservationId: primary.reservationId ?? fallback.reservationId,
    reservationRowVersion: primary.reservationRowVersion ?? fallback.reservationRowVersion,
    orderId: primary.orderId ?? fallback.orderId,
    orderRowVersion: primary.orderRowVersion ?? fallback.orderRowVersion,
    stationId: primary.stationId ?? fallback.stationId,
  };
}

export function buildJourneySearch(context: JourneyContext): string {
  const params = new URLSearchParams();

  if (context.source) {
    params.set('source', context.source);
  }

  setPositiveInteger(params, 'table_id', context.tableId);
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

  return {
    source: isJourneySource(source) ? source : undefined,
    tableId: readPositiveInteger(params.get('table_id')),
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

function isJourneySource(value: string | null): value is JourneySource {
  return value === 'board' || value === 'reservation' || value === 'order' || value === 'kitchen' || value === 'checkout';
}

function toSearchParams(search: string | URLSearchParams): URLSearchParams {
  if (search instanceof URLSearchParams) {
    return new URLSearchParams(search);
  }

  return new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);
}

function resolveJourneyPath(context: JourneyContext): string | null {
  if (context.source === 'checkout' && context.orderId) {
    return '/checkout';
  }

  if (context.source === 'kitchen' && (context.stationId || context.orderId)) {
    return '/kitchen';
  }

  if (context.source === 'order' && (context.orderId || context.tableId || context.reservationId)) {
    return '/orders';
  }

  if (context.source === 'reservation' && context.reservationId) {
    return '/reservations';
  }

  if (context.source === 'board' && context.tableId) {
    return '/tables';
  }

  if (context.orderId) {
    return '/orders';
  }

  if (context.reservationId) {
    return '/reservations';
  }

  if (context.tableId) {
    return '/tables';
  }

  if (context.stationId) {
    return '/kitchen';
  }

  return null;
}

function resumeLabelForPath(path: string): string {
  switch (path) {
    case '/checkout':
      return 'Tiếp tục thanh toán';
    case '/kitchen':
      return 'Tiếp tục bếp';
    case '/orders':
      return 'Tiếp tục đơn hàng';
    case '/reservations':
      return 'Tiếp tục đặt bàn';
    default:
      return 'Tiếp tục sơ đồ bàn';
  }
}
