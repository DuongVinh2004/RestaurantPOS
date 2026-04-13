import { describe, expect, it } from 'vitest';
import { canEditOrderItem, getAllowedOrderItemStatuses, isStaffOrderItemStatus } from './order-item-workflow';

describe('order-item-workflow', () => {
  it('returns only valid next transitions for each mutable status', () => {
    expect(getAllowedOrderItemStatuses('Ordered')).toEqual(['InProgress', 'Served', 'Cancelled']);
    expect(getAllowedOrderItemStatuses('InProgress')).toEqual(['Served', 'Cancelled']);
    expect(getAllowedOrderItemStatuses('Served')).toEqual([]);
    expect(getAllowedOrderItemStatuses('Cancelled')).toEqual([]);
  });

  it('marks only ordered and in-progress items as editable', () => {
    expect(canEditOrderItem('Ordered')).toBe(true);
    expect(canEditOrderItem('InProgress')).toBe(true);
    expect(canEditOrderItem('Served')).toBe(false);
    expect(canEditOrderItem('Cancelled')).toBe(false);
  });

  it('guards unsupported statuses', () => {
    expect(getAllowedOrderItemStatuses('Queued')).toEqual([]);
    expect(canEditOrderItem('Queued')).toBe(false);
    expect(isStaffOrderItemStatus('Queued')).toBe(false);
  });
});
