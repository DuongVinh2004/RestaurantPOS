import { useMutation, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';
import { isConflictError } from '../api/client';
import { useConcurrencyContext } from './ConcurrencyContext';

export function useOptimisticMutation<TData = unknown, TError = unknown, TVariables = void, TContext = unknown>(
  options: UseMutationOptions<TData, TError, TVariables, TContext>,
): UseMutationResult<TData, TError, TVariables, TContext> {
  const { reportConflict } = useConcurrencyContext();

  return useMutation({
    ...options,
    onError: (error: TError, variables: TVariables, context: TContext | undefined) => {
      if (isConflictError(error)) {
        reportConflict();
      }
      if (options.onError) {
        return (options.onError as any)(error, variables, context, undefined);
      }
    },
  });
}
