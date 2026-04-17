
export type StatusTone = 'default' | 'processing' | 'success' | 'warning' | 'error';
export type StatusChipVariant = 'entity' | 'severity' | 'freshness' | 'count';
export type StatusChipAppearance = 'soft' | 'filled' | 'outline';

export function tableTone(status: string | null | undefined): StatusTone {
  switch ((status ?? '').toLowerCase()) {
    case 'occupied':
    case 'open':
      return 'warning';
    case 'reserved':
      return 'processing';
    case 'available':
      return 'success';
    default:
      return 'default';
  }
}

export function reservationTone(status: string | null | undefined): StatusTone {
  switch ((status ?? '').toLowerCase()) {
    case 'checkedin':
    case 'checked_in':
    case 'seated':
    case 'completed':
      return 'success';
    case 'confirmed':
    case 'pending':
      return 'processing';
    case 'noshow':
    case 'no_show':
    case 'cancelled':
      return 'error';
    default:
      return 'default';
  }
}

export function orderTone(status: string | null | undefined): StatusTone {
  switch ((status ?? '').toLowerCase()) {
    case 'ordered':
      return 'warning';
    case 'open':
    case 'inprogress':
    case 'in_progress':
      return 'processing';
    case 'served':
    case 'paid':
    case 'completed':
      return 'success';
    case 'cancelled':
      return 'error';
    default:
      return 'default';
  }
}

export function kitchenTone(status: string | null | undefined): StatusTone {
  switch ((status ?? '').toLowerCase()) {
    case 'queued':
      return 'warning';
    case 'fired':
      return 'processing';
    case 'ready':
    case 'completed':
      return 'success';
    case 'cancelled':
      return 'error';
    default:
      return 'default';
  }
}

export function paymentTone(status: string | null | undefined): StatusTone {
  switch ((status ?? '').toLowerCase()) {
    case 'paid':
    case 'settled':
      return 'success';
    case 'partiallyrefunded':
    case 'partially_refunded':
    case 'partial':
    case 'partial_paid':
      return 'warning';
    case 'notrequired':
    case 'not_required':
      return 'default';
    case 'unpaid':
    case 'pending':
      return 'processing';
    case 'refunded':
    case 'forfeited':
    case 'cancelled':
      return 'error';
    default:
      return 'default';
  }
}

export function conversationTone(status: string | null | undefined): StatusTone {
  switch ((status ?? '').toLowerCase()) {
    case 'open':
      return 'warning';
    case 'pending':
      return 'processing';
    case 'closed':
      return 'success';
    case 'spam':
      return 'error';
    default:
      return 'default';
  }
}

export function waitingTone(status: string | null | undefined): StatusTone {
  switch ((status ?? '').toLowerCase()) {
    case 'waiting':
      return 'warning';
    case 'notified':
      return 'processing';
    case 'seated':
      return 'success';
    case 'cancelled':
      return 'error';
    default:
      return 'default';
  }
}

export function cashierShiftTone(status: string | null | undefined): StatusTone {
  switch ((status ?? '').toLowerCase()) {
    case 'open':
      return 'processing';
    case 'closed':
      return 'success';
    default:
      return 'default';
  }
}

