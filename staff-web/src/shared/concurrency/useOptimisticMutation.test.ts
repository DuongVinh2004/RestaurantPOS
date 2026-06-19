import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useOptimisticMutation } from './useOptimisticMutation';
import * as ConcurrencyContextModule from './ConcurrencyContext';
import * as ClientModule from '../api/client';
import * as ReactQueryModule from '@tanstack/react-query';

vi.mock('@tanstack/react-query', () => ({
  useMutation: vi.fn((opts) => opts),
}));

vi.mock('../api/client', () => ({
  isConflictError: vi.fn(),
}));

vi.mock('./ConcurrencyContext', () => ({
  useConcurrencyContext: vi.fn(),
}));

describe('useOptimisticMutation', () => {
  const reportConflictMock = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(ConcurrencyContextModule.useConcurrencyContext).mockReturnValue({
      reportConflict: reportConflictMock,
    });
  });

  it('calls reportConflict when error is a conflict', () => {
    vi.mocked(ClientModule.isConflictError).mockReturnValue(true);
    
    const mutationOpts: any = useOptimisticMutation({});
    mutationOpts.onError?.(new Error('Conflict'), null, null);
    
    expect(reportConflictMock).toHaveBeenCalled();
  });

  it('does not call reportConflict when error is not a conflict', () => {
    vi.mocked(ClientModule.isConflictError).mockReturnValue(false);
    
    const mutationOpts: any = useOptimisticMutation({});
    mutationOpts.onError?.(new Error('Other'), null, null);
    
    expect(reportConflictMock).not.toHaveBeenCalled();
  });

  it('preserves the original onError callback', () => {
    vi.mocked(ClientModule.isConflictError).mockReturnValue(false);
    const originalOnError = vi.fn();
    
    const mutationOpts: any = useOptimisticMutation({ onError: originalOnError });
    mutationOpts.onError?.(new Error('Other'), 'vars', 'ctx');
    
    expect(originalOnError).toHaveBeenCalledWith(expect.any(Error), 'vars', 'ctx');
  });

  it('calls both reportConflict and original onError for conflicts', () => {
    vi.mocked(ClientModule.isConflictError).mockReturnValue(true);
    const originalOnError = vi.fn();
    
    const mutationOpts: any = useOptimisticMutation({ onError: originalOnError });
    mutationOpts.onError?.(new Error('Conflict'), 'vars', 'ctx');
    
    expect(reportConflictMock).toHaveBeenCalled();
    expect(originalOnError).toHaveBeenCalledWith(expect.any(Error), 'vars', 'ctx');
  });
});
