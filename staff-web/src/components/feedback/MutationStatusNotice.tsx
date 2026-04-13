import { Button } from 'antd';
import type { StaffMutationFeedback } from '../../core/mutations/mutation-ux';
import {
  BranchPolicyState,
  ConflictState,
  InlineState,
  PermissionDeniedState,
  TransientFailureState,
} from '../states/StateBlocks';

export function MutationStatusNotice({
  feedback,
  onDismiss,
  onRetry,
}: {
  feedback: StaffMutationFeedback;
  onDismiss?: () => void;
  onRetry?: () => void;
}) {
  if (feedback.phase === 'idle') {
    return null;
  }

  const meta = buildMeta(feedback);
  const dismissAction = onDismiss ? <Button onClick={onDismiss}>Ẩn</Button> : undefined;
  const retryAction = onRetry && feedback.retryable ? <Button onClick={onRetry}>Làm mới</Button> : undefined;
  const primaryAction = retryAction ?? dismissAction;
  const secondaryAction = retryAction && dismissAction ? dismissAction : undefined;

  let content = null;

  switch (feedback.phase) {
    case 'submitting':
      content = (
        <InlineState
          tone="loading"
          eyebrow="Đang xử lý mutation"
          title={feedback.title}
          description={feedback.description}
        />
      );
      break;
    case 'succeeded':
      content = (
        <InlineState
          tone="success"
          eyebrow="Đã hoàn tất"
          title={feedback.title}
          description={feedback.description}
          primaryAction={primaryAction}
          secondaryAction={secondaryAction}
        />
      );
      break;
    case 'validation_failed':
      content = (
        <InlineState
          tone="warning"
          eyebrow="Cần kiểm tra lại trước khi gửi"
          title={feedback.title}
          description={feedback.description}
          meta={meta}
          primaryAction={primaryAction}
          secondaryAction={secondaryAction}
        />
      );
      break;
    case 'conflict':
      content = (
        <ConflictState
          title={feedback.title}
          description={feedback.description}
          meta={meta}
          primaryAction={primaryAction}
          secondaryAction={secondaryAction}
        />
      );
      break;
    case 'denied':
      content = feedback.categoryCode === 'policy_denied' ? (
        <BranchPolicyState
          title={feedback.title}
          description={feedback.description}
          meta={meta}
          primaryAction={primaryAction}
          secondaryAction={secondaryAction}
        />
      ) : (
        <PermissionDeniedState
          title={feedback.title}
          description={feedback.description}
          meta={meta}
          primaryAction={primaryAction}
          secondaryAction={secondaryAction}
        />
      );
      break;
    case 'retriable_failure':
      content = (
        <TransientFailureState
          title={feedback.title}
          description={feedback.description}
          meta={meta}
          primaryAction={primaryAction}
          secondaryAction={secondaryAction}
        />
      );
      break;
    default:
      content = null;
  }

  if (!content) {
    return null;
  }

  return (
    <div
      data-testid="mutation-status-notice"
      data-phase={feedback.phase}
      aria-live={feedback.phase === 'submitting' ? 'polite' : 'assertive'}
    >
      {content}
    </div>
  );
}

function buildMeta(feedback: StaffMutationFeedback): string | undefined {
  const parts = [
    feedback.errorCode ? `Mã lỗi: ${feedback.errorCode}` : null,
    feedback.requestId ? `Mã truy vết: ${feedback.requestId}` : null,
  ].filter((value): value is string => value !== null);

  return parts.length > 0 ? parts.join(' • ') : undefined;
}
