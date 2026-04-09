export type OperatorJourneySource = 'board';

export type OperatorJourneyContext = {
  source?: OperatorJourneySource;
  tableId?: number;
  reservationId?: number;
  reservationRowVersion?: number;
  orderId?: number;
  orderRowVersion?: number;
};

export function buildOperatorJourneySearch(context: OperatorJourneyContext): string {
  const params = new URLSearchParams();

  if (context.source) {
    params.set('source', context.source);
  }

  setPositiveInteger(params, 'table_id', context.tableId);
  setPositiveInteger(params, 'reservation_id', context.reservationId);
  setPositiveInteger(params, 'reservation_row_version', context.reservationRowVersion);
  setPositiveInteger(params, 'order_id', context.orderId);
  setPositiveInteger(params, 'order_row_version', context.orderRowVersion);

  return params.toString();
}

export function readOperatorJourneyContext(search: string | URLSearchParams): OperatorJourneyContext {
  const params = typeof search === 'string'
    ? new URLSearchParams(search.startsWith('?') ? search.slice(1) : search)
    : search;
  const source = params.get('source');

  return {
    source: source === 'board' ? source : undefined,
    tableId: readPositiveInteger(params.get('table_id')),
    reservationId: readPositiveInteger(params.get('reservation_id')),
    reservationRowVersion: readPositiveInteger(params.get('reservation_row_version')),
    orderId: readPositiveInteger(params.get('order_id')),
    orderRowVersion: readPositiveInteger(params.get('order_row_version')),
  };
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
