import { describe, it, expect } from 'vitest';
import { renderHook } from '@testing-library/react';
import { useSmartETA } from './useSmartETA';
import type { KitchenOrderItemTicket } from '../../../shared/api/sdk';

describe('useSmartETA', () => {
  function makeTicket(name: string): KitchenOrderItemTicket {
    return {
      ticket_id: 1,
      ticket_status: 'Queued',
      item: { name, id: 1, type: 'food' },
      order: { order_id: 1 },
      reconciliation: {},
    } as unknown as KitchenOrderItemTicket;
  }

  it('returns null for null ticket', () => {
    const { result } = renderHook(() => useSmartETA(null));
    expect(result.current).toBeNull();
  });

  it('returns long ETA for nướng/hấp', () => {
    const { result } = renderHook(() => useSmartETA(makeTicket('Thịt bò nướng')));
    expect(result.current?.estimatedMinutes).toBe(20);
    expect(result.current?.confidence).toBe('medium');
  });

  it('returns fast ETA for xào/salad', () => {
    const { result } = renderHook(() => useSmartETA(makeTicket('Salad cá ngừ')));
    expect(result.current?.estimatedMinutes).toBe(10);
    expect(result.current?.confidence).toBe('high');
  });

  it('returns fastest ETA for drinks', () => {
    const { result } = renderHook(() => useSmartETA(makeTicket('Trà đào cam sả')));
    expect(result.current?.estimatedMinutes).toBe(3);
    expect(result.current?.confidence).toBe('high');
  });

  it('returns fallback ETA for unknown', () => {
    const { result } = renderHook(() => useSmartETA(makeTicket('Bánh mì')));
    expect(result.current?.estimatedMinutes).toBe(12);
    expect(result.current?.confidence).toBe('low');
  });
});
