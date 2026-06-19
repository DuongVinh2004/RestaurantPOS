import { useState, useEffect, useCallback } from 'react';

export type OfflineOperationStatus = 'draft' | 'pending_sync' | 'syncing' | 'conflict' | 'rejected' | 'confirmed';

export type OfflineOperation = {
  client_operation_id: string;
  command_type: string;
  aggregate_id: string | number | null;
  base_row_version: number | null;
  payload: unknown;
  status: OfflineOperationStatus;
  created_at: string;
};

const QUEUE_STORAGE_KEY = 'pos_offline_queue';

export function useOfflineQueue() {
  const [queue, setQueue] = useState<OfflineOperation[]>(() => {
    try {
      const stored = localStorage.getItem(QUEUE_STORAGE_KEY);
      return stored ? JSON.parse(stored) : [];
    } catch {
      return [];
    }
  });

  useEffect(() => {
    localStorage.setItem(QUEUE_STORAGE_KEY, JSON.stringify(queue));
  }, [queue]);

  const enqueue = useCallback((op: Omit<OfflineOperation, 'status' | 'created_at'>) => {
    setQueue((prev) => [
      ...prev,
      { ...op, status: 'pending_sync', created_at: new Date().toISOString() },
    ]);
  }, []);

  const remove = useCallback((clientOpId: string) => {
    setQueue((prev) => prev.filter((op) => op.client_operation_id !== clientOpId));
  }, []);

  const updateStatus = useCallback((clientOpId: string, status: OfflineOperationStatus) => {
    setQueue((prev) =>
      prev.map((op) => (op.client_operation_id === clientOpId ? { ...op, status } : op))
    );
  }, []);

  const clear = useCallback(() => {
    setQueue([]);
  }, []);

  return { queue, enqueue, remove, updateStatus, clear };
}
