import { useCallback, useRef, useState } from 'react';
import { toast } from '../components/feedback/toast';
import {
  createIdleMutationFeedback,
  createSubmittingMutationFeedback,
  createSuccessMutationFeedback,
  mapMutationErrorToFeedback,
  type StaffMutationErrorContext,
  type StaffMutationFeedback,
} from '../core/mutations/mutation-ux';

type MutationFeedbackSuccessOptions = {
  toastDuration?: number;
};

type MutationFeedbackSubmittingOptions = {
  announceToast?: boolean;
};

export function useStaffMutationFeedback(scope: string) {
  const [feedback, setFeedback] = useState<StaffMutationFeedback>(createIdleMutationFeedback);
  const toastKeyRef = useRef(`staff-mutation-${scope}`);

  const resetFeedback = useCallback(() => {
    setFeedback(createIdleMutationFeedback());
    toast.destroy(toastKeyRef.current);
  }, []);

  const setSubmitting = useCallback((
    actionLabel: string,
    description: string,
    options: MutationFeedbackSubmittingOptions = {},
  ) => {
    const next = createSubmittingMutationFeedback(actionLabel, description);
    setFeedback(next);

    if (options.announceToast) {
      toast.loading(next.title, { key: toastKeyRef.current });
    }

    return next;
  }, []);

  const setSuccess = useCallback((
    actionLabel: string,
    description: string,
    options: MutationFeedbackSuccessOptions = {},
  ) => {
    const next = createSuccessMutationFeedback(actionLabel, description);
    setFeedback(next);
    toast.success(description, {
      key: toastKeyRef.current,
      duration: options.toastDuration,
    });

    return next;
  }, []);

  const setFailure = useCallback((error: unknown, context: StaffMutationErrorContext) => {
    const next = mapMutationErrorToFeedback(error, context);
    setFeedback(next);
    toast.error(next.description, { key: toastKeyRef.current });
    return next;
  }, []);

  return {
    feedback,
    resetFeedback,
    setSubmitting,
    setSuccess,
    setFailure,
  };
}
