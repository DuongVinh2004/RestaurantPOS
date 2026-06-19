import { renderHook, act } from '@testing-library/react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useOfflineQueue } from './useOfflineQueue';

describe('useOfflineQueue', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('initializes with empty queue if localStorage is empty', () => {
    const { result } = renderHook(() => useOfflineQueue());
    expect(result.current.queue).toEqual([]);
  });

  it('loads existing queue from localStorage', () => {
    localStorage.setItem(
      'pos_offline_queue',
      JSON.stringify([
        {
          client_operation_id: 'op-1',
          command_type: 'test_draft',
          status: 'pending_sync',
          created_at: '2026-06-05T00:00:00.000Z',
        },
      ])
    );

    const { result } = renderHook(() => useOfflineQueue());
    expect(result.current.queue.length).toBe(1);
    expect(result.current.queue[0].client_operation_id).toBe('op-1');
  });

  it('can enqueue new operations', () => {
    const { result } = renderHook(() => useOfflineQueue());

    act(() => {
      result.current.enqueue({
        client_operation_id: 'op-2',
        command_type: 'save_draft',
        aggregate_id: 123,
        base_row_version: 1,
        payload: { text: 'test' },
      });
    });

    expect(result.current.queue.length).toBe(1);
    expect(result.current.queue[0].client_operation_id).toBe('op-2');
    expect(result.current.queue[0].status).toBe('pending_sync');
    expect(result.current.queue[0].created_at).toBeDefined();
    
    // verifies localStorage syncs
    expect(JSON.parse(localStorage.getItem('pos_offline_queue') || '[]').length).toBe(1);
  });

  it('can update status of an operation', () => {
    const { result } = renderHook(() => useOfflineQueue());

    act(() => {
      result.current.enqueue({
        client_operation_id: 'op-3',
        command_type: 'save_draft',
        aggregate_id: null,
        base_row_version: null,
        payload: {},
      });
    });

    act(() => {
      result.current.updateStatus('op-3', 'syncing');
    });

    expect(result.current.queue[0].status).toBe('syncing');
  });

  it('can remove an operation', () => {
    const { result } = renderHook(() => useOfflineQueue());

    act(() => {
      result.current.enqueue({
        client_operation_id: 'op-4',
        command_type: 'save_draft',
        aggregate_id: null,
        base_row_version: null,
        payload: {},
      });
    });

    act(() => {
      result.current.remove('op-4');
    });

    expect(result.current.queue.length).toBe(0);
  });

  it('can clear the queue', () => {
    const { result } = renderHook(() => useOfflineQueue());

    act(() => {
      result.current.enqueue({
        client_operation_id: 'op-5',
        command_type: 'save_draft',
        aggregate_id: null,
        base_row_version: null,
        payload: {},
      });
    });

    act(() => {
      result.current.clear();
    });

    expect(result.current.queue.length).toBe(0);
  });
});
