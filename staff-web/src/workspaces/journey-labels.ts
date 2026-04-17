function normalizeJourneyLabel(label: string | null | undefined): string | null {
  const normalized = label?.trim();
  return normalized ? normalized : null;
}

export function buildTableContextLabel(
  label: string | null | undefined,
  tableId: number | null | undefined,
): string | null {
  return normalizeJourneyLabel(label) ?? (tableId ? `Bàn #${tableId}` : null);
}

export function buildReservationContextLabel(
  label: string | null | undefined,
  reservationId: number | null | undefined,
): string | null {
  return normalizeJourneyLabel(label) ?? (reservationId ? `Đặt bàn #${reservationId}` : null);
}

export function buildOrderContextLabel(
  orderId: number | null | undefined,
  label?: string | null,
): string | null {
  return normalizeJourneyLabel(label) ?? (orderId ? `Đơn #${orderId}` : null);
}
