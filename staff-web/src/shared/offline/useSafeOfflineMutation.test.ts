import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useSafeOfflineMutation } from './useSafeOfflineMutation';
import * as OnlineStatusModule from '../hooks/useOnlineStatus';
import * as OfflineQueueModule from './useOfflineQueue';
import * as ReactQueryModule from '@tanstack/react-query';

vi.mock('@tanstack/react-query', () => ({
  useMutation: vi.fn((opts) => opts),
}));

vi.mock('../hooks/useOnlineStatus', () => ({
  useOnlineStatus: vi.fn(),
}));

vi.mock('./useOfflineQueue', () => ({
  useOfflineQueue: vi.fn(),
}));

describe('useSafeOfflineMutation', () => {
  const enqueueMock = vi.fn();
  const originalMutationFn = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(OfflineQueueModule.useOfflineQueue).mockReturnValue({
      enqueue: enqueueMock,
      queue: [],
      remove: vi.fn(),
      updateStatus: vi.fn(),
      clear: vi.fn(),
    });
  });

  it('executes normally when online', async () => {
    vi.mocked(OnlineStatusModule.useOnlineStatus).mockReturnValue(true);
    originalMutationFn.mockResolvedValue('success');

    const mutationOpts: any = useSafeOfflineMutation(
      { mutationFn: originalMutationFn },
      { commandType: 'test', isSafeOffline: true }
    );

    const result = await mutationOpts.mutationFn('test_vars');
    expect(result).toBe('success');
    expect(originalMutationFn).toHaveBeenCalledWith('test_vars');
    expect(enqueueMock).not.toHaveBeenCalled();
  });

  it('rejects unsafe offline mutations when offline', async () => {
    vi.mocked(OnlineStatusModule.useOnlineStatus).mockReturnValue(false);

    const mutationOpts: any = useSafeOfflineMutation(
      { mutationFn: originalMutationFn },
      { commandType: 'test_unsafe', isSafeOffline: false }
    );

    await expect(mutationOpts.mutationFn('test_vars')).rejects.toThrow(
      'Thao tác này không được phép khi mất kết nối mạng (Ngoại tuyến).'
    );
    expect(enqueueMock).not.toHaveBeenCalled();
  });

  it('queues safe mutations when offline', async () => {
    vi.mocked(OnlineStatusModule.useOnlineStatus).mockReturnValue(false);

    const mutationOpts: any = useSafeOfflineMutation(
      { mutationFn: originalMutationFn },
      { 
        commandType: 'test_safe', 
        isSafeOffline: true,
        getAggregateId: (v) => v.id,
        getBaseRowVersion: (v) => v.version
      }
    );

    const result = await mutationOpts.mutationFn({ id: 99, version: 2 });
    
    expect(result).toEqual({ offline_queued: true });
    expect(enqueueMock).toHaveBeenCalledWith(expect.objectContaining({
      command_type: 'test_safe',
      aggregate_id: 99,
      base_row_version: 2,
      payload: { id: 99, version: 2 }
    }));
    expect(originalMutationFn).not.toHaveBeenCalled();
  });
});
