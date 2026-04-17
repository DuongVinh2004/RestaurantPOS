type TableIdentity = {
  table_id?: number | null;
  table_code?: string | null;
};

type ReservationTableIdentity = {
  table_ids?: Array<number> | null;
  tables?: Array<TableIdentity> | null;
} | null | undefined;

export function getReservationTableIds(reservation: ReservationTableIdentity): Array<number> {
  const idsFromTables = Array.isArray(reservation?.tables)
    ? reservation.tables
      .map((table) => table.table_id)
      .filter((tableId): tableId is number => typeof tableId === 'number' && Number.isInteger(tableId) && tableId > 0)
    : [];

  if (idsFromTables.length > 0) {
    return Array.from(new Set(idsFromTables));
  }

  if (!Array.isArray(reservation?.table_ids)) {
    return [];
  }

  return Array.from(new Set(
    reservation.table_ids.filter((tableId): tableId is number => Number.isInteger(tableId) && tableId > 0),
  ));
}

export function getPrimaryReservationTableId(reservation: ReservationTableIdentity): number | undefined {
  return getReservationTableIds(reservation)[0];
}

export function getReservationTableLabel(
  reservation: ReservationTableIdentity,
  fallback = 'Chưa gán bàn',
): string {
  const tableCodes = Array.isArray(reservation?.tables)
    ? reservation.tables
      .map((table) => table.table_code?.trim())
      .filter((tableCode): tableCode is string => Boolean(tableCode))
    : [];

  if (tableCodes.length > 0) {
    return tableCodes.join(', ');
  }

  const tableIds = getReservationTableIds(reservation);
  return tableIds.length > 0 ? tableIds.join(', ') : fallback;
}
