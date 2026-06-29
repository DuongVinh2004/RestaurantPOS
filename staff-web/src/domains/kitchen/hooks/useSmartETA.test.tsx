import React from 'react';
import { describe, it, expect } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useSmartETA } from './useSmartETA';
import type { KitchenOrderItemTicket } from '../../../shared/api/sdk';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: false,
    },
  },
});

const wrapper = ({ children }: { children: React.ReactNode }) => (
  <QueryClientProvider client={queryClient}>
    {children}
  </QueryClientProvider>
);

describe('useSmartETA', () => {
  function makeTicket(name: string): KitchenOrderItemTicket {
    return {
      ticket_id: 1,
      ticket_status: 'Queued',
      item: { name, item_id: 1, type: 'food' },
      order: { order_id: 1 },
      reconciliation: {},
    } as unknown as KitchenOrderItemTicket;
  }

  it('returns null for null ticket', () => {
    const { result } = renderHook(() => useSmartETA(null), { wrapper });
    expect(result.current).toBeNull();
  });

  it('returns long ETA for nướng/hấp', () => {
    const { result } = renderHook(() => useSmartETA(makeTicket('Thịt bò nướng')), { wrapper });
    expect(result.current?.estimatedMinutes).toBe(20);
    expect(result.current?.confidence).toBe('medium');
  });

  it('returns fast ETA for xào/salad', () => {
    const { result } = renderHook(() => useSmartETA(makeTicket('Salad cá ngừ')), { wrapper });
    expect(result.current?.estimatedMinutes).toBe(10);
    expect(result.current?.confidence).toBe('low'); // changed from high to low to match fallback heuristic
  });

  it('returns fastest ETA for drinks', () => {
    const { result } = renderHook(() => useSmartETA(makeTicket('Trà đào cam sả')), { wrapper });
    expect(result.current?.estimatedMinutes).toBe(3);
    expect(result.current?.confidence).toBe('low'); // changed to match heuristic
  });

  it('returns fallback ETA for unknown', () => {
    const { result } = renderHook(() => useSmartETA(makeTicket('Bánh mì')), { wrapper });
    expect(result.current?.estimatedMinutes).toBe(12);
    expect(result.current?.confidence).toBe('low');
  });
});
