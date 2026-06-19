import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import { useOnlineStatus } from '../hooks/useOnlineStatus';
import { useOfflineQueue } from './useOfflineQueue';

export type SafeOfflineConfig = {
  commandType: string;
  isSafeOffline: boolean;
  getAggregateId?: (variables: any) => string | number | null;
  getBaseRowVersion?: (variables: any) => number | null;
};

export function useSafeOfflineMutation<TData = unknown, TError = unknown, TVariables = void, TContext = unknown>(
  options: UseMutationOptions<TData, TError, TVariables, TContext>,
  offlineConfig: SafeOfflineConfig
): UseMutationResult<TData, TError, TVariables, TContext> {
  const isOnline = useOnlineStatus();
  const { enqueue } = useOfflineQueue();

  const originalMutationFn = options.mutationFn;

  return useMutation({
    ...options,
    mutationFn: async (variables: TVariables) => {
      if (!isOnline) {
        if (!offlineConfig.isSafeOffline) {
          throw new Error('Thao tác này không được phép khi mất kết nối mạng (Ngoại tuyến).');
        }

        const clientId = Math.random().toString(36).substring(2, 15); // Simple UUID equivalent for stub

        enqueue({
          client_operation_id: clientId,
          command_type: offlineConfig.commandType,
          aggregate_id: offlineConfig.getAggregateId ? offlineConfig.getAggregateId(variables) : null,
          base_row_version: offlineConfig.getBaseRowVersion ? offlineConfig.getBaseRowVersion(variables) : null,
          payload: variables,
        });

        return { offline_queued: true } as unknown as TData;
      }

      if (!originalMutationFn) {
        throw new Error('mutationFn is required');
      }

      return (originalMutationFn as any)(variables, undefined);
    },
  });
}
