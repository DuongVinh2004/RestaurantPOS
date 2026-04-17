export type StaffOrderItemStatus = 'Ordered' | 'InProgress' | 'Served' | 'Cancelled';
export type StaffOrderItemTransitionStatus = Exclude<StaffOrderItemStatus, 'Ordered'>;

const ORDER_ITEM_STATUS_TRANSITIONS: Record<StaffOrderItemStatus, Array<StaffOrderItemTransitionStatus>> = {
  Ordered: ['InProgress', 'Served', 'Cancelled'],
  InProgress: ['Served', 'Cancelled'],
  Served: [],
  Cancelled: [],
};

export function getAllowedOrderItemStatuses(status: string | null | undefined): Array<StaffOrderItemTransitionStatus> {
  if (!isStaffOrderItemStatus(status)) {
    return [];
  }

  return ORDER_ITEM_STATUS_TRANSITIONS[status];
}

export function canEditOrderItem(status: string | null | undefined): boolean {
  return status === 'Ordered' || status === 'InProgress';
}

export function isStaffOrderItemStatus(status: string | null | undefined): status is StaffOrderItemStatus {
  return status === 'Ordered' || status === 'InProgress' || status === 'Served' || status === 'Cancelled';
}
