import type { ReactNode } from 'react';
import { Button } from 'antd';
import {
  AlertTriangle,
  BadgeAlert,
  Building2,
  CircleAlert,
  FileSearch,
  RefreshCcw,
  ShieldAlert,
  WifiOff,
} from 'lucide-react';
import { normalizeApiError } from '../../api/errors';

type StateTone = 'empty' | 'loading' | 'warning' | 'error' | 'info' | 'success';
type StateVariant = 'inline' | 'page';

type SharedStateProps = {
  title: string;
  description?: string;
  meta?: string;
  primaryAction?: ReactNode;
  secondaryAction?: ReactNode;
  body?: ReactNode;
  variant?: StateVariant;
  className?: string;
};

type SemanticStateProps = Omit<SharedStateProps, 'title' | 'description'> & {
  title?: string;
  description?: string;
};

type ApiStateBlockProps = {
  error: unknown;
  fallback: string;
  onRetry?: () => void;
  retryLabel?: string;
  variant?: StateVariant;
  notFoundTitle?: string;
  notFoundDescription?: string;
  forbiddenTitle?: string;
  forbiddenDescription?: string;
  conflictTitle?: string;
  conflictDescription?: string;
  validationTitle?: string;
  validationDescription?: string;
  authTitle?: string;
  authDescription?: string;
};

// Shared operational state contract:
// every page or section state should present a short title, a next-step explanation,
// and a clear recovery action surface instead of ad-hoc alert copy.
export function InlineState({
  tone,
  eyebrow,
  title,
  description,
  meta,
  primaryAction,
  secondaryAction,
  body,
  icon,
  className,
}: SharedStateProps & {
  tone: StateTone;
  eyebrow?: string;
  icon?: ReactNode;
}) {
  return (
    <StateSurface
      tone={tone}
      eyebrow={eyebrow}
      icon={icon ?? defaultIconForTone(tone)}
      title={title}
      description={description}
      meta={meta}
      primaryAction={primaryAction}
      secondaryAction={secondaryAction}
      body={body}
      className={className}
    />
  );
}

export function PageState({
  tone,
  eyebrow,
  title,
  description,
  meta,
  primaryAction,
  secondaryAction,
  body,
  icon,
  className,
}: SharedStateProps & {
  tone: StateTone;
  eyebrow?: string;
  icon?: ReactNode;
}) {
  return (
    <div className="staff-full-page-state-wrap">
      <StateSurface
        tone={tone}
        eyebrow={eyebrow}
        icon={icon ?? defaultIconForTone(tone)}
        title={title}
        description={description}
        meta={meta}
        primaryAction={primaryAction}
        secondaryAction={secondaryAction}
        body={body}
        className={['staff-full-page-state', className].filter(Boolean).join(' ')}
      />
    </div>
  );
}

export function InlineError({
  message,
  extra,
}: {
  message: string;
  extra?: ReactNode;
}) {
  return (
    <InlineState
      tone="error"
      eyebrow="Cần xử lý"
      icon={<CircleAlert size={18} strokeWidth={2.1} />}
      title={message}
      description={typeof extra === 'string' ? extra : undefined}
      primaryAction={typeof extra === 'string' ? undefined : extra}
    />
  );
}

export function InlineWarning({
  title,
  description,
  action,
}: {
  title: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <InlineState
      tone="warning"
      eyebrow="Lưu ý vận hành"
      icon={<AlertTriangle size={18} strokeWidth={2.1} />}
      title={title}
      description={description}
      primaryAction={action}
    />
  );
}

export function InlineLoading({
  tip = 'Đang tải dữ liệu…',
  description,
}: {
  tip?: string;
  description?: string;
}) {
  return (
    <InlineState
      tone="loading"
      eyebrow="Đang tải"
      icon={<RefreshCcw size={18} strokeWidth={2.1} />}
      title={tip}
      description={description}
      className="staff-inline-loading"
      body={(
        <div className="staff-loading-skeleton" aria-hidden="true">
          <span className="staff-loading-skeleton-line staff-loading-skeleton-line-wide" />
          <span className="staff-loading-skeleton-line" />
          <span className="staff-loading-skeleton-line staff-loading-skeleton-line-short" />
        </div>
      )}
    />
  );
}

export function PageLoadingState({
  title = 'Đang tải màn hình…',
  description = 'Hệ thống đang khôi phục ngữ cảnh làm việc và dữ liệu cần thiết cho thao tác tiếp theo.',
}: {
  title?: string;
  description?: string;
}) {
  return (
    <PageState
      tone="loading"
      eyebrow="Đang tải"
      icon={<RefreshCcw size={24} strokeWidth={2.1} />}
      title={title}
      description={description}
      body={(
        <div className="staff-loading-skeleton" aria-hidden="true">
          <span className="staff-loading-skeleton-line staff-loading-skeleton-line-wide" />
          <span className="staff-loading-skeleton-line" />
          <span className="staff-loading-skeleton-line staff-loading-skeleton-line-short" />
        </div>
      )}
    />
  );
}

export function EmptyBlock({
  title,
  description,
  action,
}: {
  title: string;
  description: string;
  action?: ReactNode;
}) {
  return (
    <InlineState
      tone="empty"
      eyebrow="Chưa có dữ liệu"
      icon={<FileSearch size={18} strokeWidth={2.1} />}
      title={title}
      description={description}
      primaryAction={action}
      className="staff-empty-block"
    />
  );
}

export function PermissionDeniedState({
  title = 'Phiên hiện tại chưa có quyền',
  description = 'Hãy chuyển sang màn hình được cấp hoặc dùng đúng phiên vai trò trước khi thử lại.',
  meta,
  primaryAction,
  secondaryAction,
  body,
  variant = 'inline',
  className,
}: SemanticStateProps) {
  return renderSemanticState({
    tone: 'warning',
    eyebrow: 'Quyền truy cập',
    icon: <ShieldAlert size={variant === 'page' ? 24 : 18} strokeWidth={2.1} />,
    title,
    description,
    meta,
    primaryAction,
    secondaryAction,
    body,
    variant,
    className,
  });
}

export function BranchPolicyState({
  title = 'Ngữ cảnh chi nhánh hiện tại đang chặn thao tác này',
  description = 'Hãy kiểm tra lại chi nhánh, policy hoặc điều kiện vận hành trước khi tiếp tục.',
  meta,
  primaryAction,
  secondaryAction,
  body,
  variant = 'inline',
  className,
}: SemanticStateProps) {
  return renderSemanticState({
    tone: 'warning',
    eyebrow: 'Chi nhánh / policy',
    icon: <Building2 size={variant === 'page' ? 24 : 18} strokeWidth={2.1} />,
    title,
    description,
    meta,
    primaryAction,
    secondaryAction,
    body,
    variant,
    className,
  });
}

export function ConflictState({
  title = 'Dữ liệu vừa thay đổi',
  description = 'Làm mới để lấy phiên bản mới nhất trước khi tiếp tục thao tác.',
  meta,
  primaryAction,
  secondaryAction,
  body,
  variant = 'inline',
  className,
}: SemanticStateProps) {
  return renderSemanticState({
    tone: 'warning',
    eyebrow: 'Xung đột / stale',
    icon: <BadgeAlert size={variant === 'page' ? 24 : 18} strokeWidth={2.1} />,
    title,
    description,
    meta,
    primaryAction,
    secondaryAction,
    body,
    variant,
    className,
  });
}

export function TransientFailureState({
  title = 'Không thể hoàn tất yêu cầu lúc này',
  description = 'Đây có thể là lỗi tạm thời. Hãy thử lại sau ít phút hoặc tải lại dữ liệu đang xem.',
  meta,
  primaryAction,
  secondaryAction,
  body,
  variant = 'inline',
  className,
}: SemanticStateProps) {
  return renderSemanticState({
    tone: 'error',
    eyebrow: 'Lỗi tạm thời',
    icon: <WifiOff size={variant === 'page' ? 24 : 18} strokeWidth={2.1} />,
    title,
    description,
    meta,
    primaryAction,
    secondaryAction,
    body,
    variant,
    className,
  });
}

export function NotFoundState({
  title = 'Không còn tìm thấy dữ liệu cần xem',
  description = 'Bản ghi này có thể đã đổi trạng thái, bị gỡ khỏi phạm vi hiện tại hoặc không còn nằm trong branch đang dùng.',
  meta,
  primaryAction,
  secondaryAction,
  body,
  variant = 'inline',
  className,
}: SemanticStateProps) {
  return renderSemanticState({
    tone: 'empty',
    eyebrow: 'Không tìm thấy',
    icon: <FileSearch size={variant === 'page' ? 24 : 18} strokeWidth={2.1} />,
    title,
    description,
    meta,
    primaryAction,
    secondaryAction,
    body,
    variant,
    className,
  });
}

export function StaleDataNotice({
  title = 'Dữ liệu có thể đã cũ',
  description,
  lastUpdatedLabel,
  onRefresh,
}: {
  title?: string;
  description: string;
  lastUpdatedLabel?: string;
  onRefresh?: () => void;
}) {
  return (
    <ConflictState
      title={title}
      description={description}
      meta={lastUpdatedLabel}
      primaryAction={onRefresh ? <Button onClick={onRefresh}>Làm mới ngay</Button> : undefined}
    />
  );
}

export function RetryState({
  title,
  description,
  onRetry,
}: {
  title: string;
  description: string;
  onRetry: () => void;
}) {
  return (
    <TransientFailureState
      title={title}
      description={description}
      primaryAction={<Button onClick={onRetry}>Tải lại</Button>}
    />
  );
}

export function ApiStateBlock({
  error,
  fallback,
  onRetry,
  retryLabel = 'Tải lại',
  variant = 'inline',
  notFoundTitle,
  notFoundDescription,
  forbiddenTitle,
  forbiddenDescription,
  conflictTitle,
  conflictDescription,
  validationTitle,
  validationDescription,
  authTitle,
  authDescription,
}: ApiStateBlockProps) {
  const normalized = normalizeApiError(error, fallback);
  const retryAction = onRetry ? <Button onClick={onRetry}>{retryLabel}</Button> : undefined;
  const meta = normalized.requestId ? `Mã truy vết: ${normalized.requestId}` : undefined;

  if (normalized.kind === 'auth') {
    return (
      <PermissionDeniedState
        variant={variant}
        title={authTitle ?? 'Phiên làm việc đã thay đổi'}
        description={authDescription ?? 'Hãy làm mới phiên đăng nhập hoặc đăng nhập lại trước khi thử thao tác này.'}
        meta={meta}
        primaryAction={retryAction}
      />
    );
  }

  if (normalized.kind === 'forbidden') {
    const capabilityDescription = normalized.requiredCapability
      ? `Phiên hiện tại thiếu quyền ${normalized.requiredCapability}. Hãy chuyển sang vai trò hoặc màn hình được cấp trước khi thử lại.`
      : forbiddenDescription ?? 'Phiên hiện tại không được phép thực hiện thao tác này.';

    if (normalized.code === 'owner_scope_denied' || normalized.code === 'policy_denied') {
      return (
        <BranchPolicyState
          variant={variant}
          title={forbiddenTitle ?? 'Ngữ cảnh branch hoặc policy đang chặn thao tác này'}
          description={forbiddenDescription ?? capabilityDescription}
          meta={meta}
          primaryAction={retryAction}
        />
      );
    }

    return (
      <PermissionDeniedState
        variant={variant}
        title={forbiddenTitle ?? 'Phiên hiện tại chưa có quyền'}
        description={capabilityDescription}
        meta={meta}
        primaryAction={retryAction}
      />
    );
  }

  if (normalized.kind === 'not-found') {
    return (
      <NotFoundState
        variant={variant}
        title={notFoundTitle}
        description={notFoundDescription}
        meta={meta}
        primaryAction={retryAction}
      />
    );
  }

  if (normalized.kind === 'conflict') {
    const staleWrite = normalized.code === 'stale_write';
    const idempotencyConflict = normalized.code === 'idempotency_conflict';

    return (
      <ConflictState
        variant={variant}
        title={
          conflictTitle
          ?? (
            staleWrite
              ? 'Dữ liệu đang dùng đã cũ'
              : idempotencyConflict
                ? 'Yêu cầu trước đó vẫn đang chiếm giao dịch này'
                : 'Dữ liệu vừa thay đổi ở nơi khác'
          )
        }
        description={
          conflictDescription
          ?? (
            staleWrite
              ? 'Tải lại để lấy phiên bản mới nhất trước khi thao tác lại.'
              : idempotencyConflict
                ? 'Hãy chờ mutation trước hoàn tất hoặc làm mới trạng thái giao dịch rồi thử lại.'
                : 'Làm mới dữ liệu hiện tại để đồng bộ lại trạng thái trước khi tiếp tục.'
          )
        }
        meta={meta}
        primaryAction={retryAction}
      />
    );
  }

  if (normalized.kind === 'validation') {
    return (
      <InlineState
        tone="warning"
        eyebrow="Cần kiểm tra lại dữ liệu"
        icon={<CircleAlert size={variant === 'page' ? 24 : 18} strokeWidth={2.1} />}
        title={validationTitle ?? 'Yêu cầu chưa hợp lệ'}
        description={validationDescription ?? normalized.message}
        meta={meta}
        primaryAction={retryAction}
      />
    );
  }

  if (normalized.kind === 'rate-limit') {
    return (
      <TransientFailureState
        variant={variant}
        title="Hệ thống đang bận"
        description="Hệ thống đang xử lý nhiều yêu cầu cùng lúc. Hãy chờ một lát rồi tải lại."
        meta={meta}
        primaryAction={retryAction}
      />
    );
  }

  return (
    <TransientFailureState
      variant={variant}
      title="Chưa thể tải dữ liệu"
      description={fallback}
      meta={meta}
      primaryAction={retryAction}
    />
  );
}

export function FullPageState({
  title,
  description,
  status,
  extra,
}: {
  title: string;
  description: string;
  status: '403' | '404' | '500' | 'warning' | 'info' | 'success';
  extra?: ReactNode;
}) {
  if (status === '403') {
    return (
      <PermissionDeniedState
        variant="page"
        title={title}
        description={description}
        primaryAction={extra}
      />
    );
  }

  if (status === '404') {
    return (
      <NotFoundState
        variant="page"
        title={title}
        description={description}
        primaryAction={extra}
      />
    );
  }

  if (status === '500') {
    return (
      <TransientFailureState
        variant="page"
        title={title}
        description={description}
        primaryAction={extra}
      />
    );
  }

  return (
    <PageState
      tone={mapFullPageTone(status)}
      icon={mapFullPageIcon(status)}
      title={title}
      description={description}
      primaryAction={extra}
    />
  );
}

function renderSemanticState({
  variant = 'inline',
  ...props
}: SharedStateProps & {
  tone: StateTone;
  eyebrow?: string;
  icon?: ReactNode;
}) {
  if (variant === 'page') {
    return <PageState variant={variant} {...props} />;
  }

  return <InlineState variant={variant} {...props} />;
}

function StateSurface({
  tone,
  eyebrow,
  icon,
  title,
  description,
  meta,
  primaryAction,
  secondaryAction,
  body,
  className,
}: SharedStateProps & {
  tone: StateTone;
  eyebrow?: string;
  icon: ReactNode;
}) {
  return (
    <div className={['staff-state-surface', `staff-state-surface-${tone}`, className].filter(Boolean).join(' ')}>
      <div className="staff-state-surface-icon" aria-hidden="true">{icon}</div>
      <div className="staff-state-surface-copy">
        {eyebrow ? <span className="staff-state-surface-eyebrow">{eyebrow}</span> : null}
        <span className="staff-state-surface-title">{title}</span>
        {description ? <p className="staff-state-surface-description">{description}</p> : null}
        {meta ? <p className="staff-state-surface-meta">{meta}</p> : null}
      </div>
      {body ? <div className="staff-state-surface-body">{body}</div> : null}
      {primaryAction || secondaryAction ? (
        <div className="staff-state-surface-actions">
          {primaryAction}
          {secondaryAction}
        </div>
      ) : null}
    </div>
  );
}

function defaultIconForTone(tone: StateTone) {
  switch (tone) {
    case 'loading':
      return <RefreshCcw size={18} strokeWidth={2.1} />;
    case 'warning':
      return <AlertTriangle size={18} strokeWidth={2.1} />;
    case 'error':
      return <CircleAlert size={18} strokeWidth={2.1} />;
    case 'success':
      return <BadgeAlert size={18} strokeWidth={2.1} />;
    case 'empty':
      return <FileSearch size={18} strokeWidth={2.1} />;
    default:
      return <CircleAlert size={18} strokeWidth={2.1} />;
  }
}

function mapFullPageTone(status: 'warning' | 'info' | 'success') {
  switch (status) {
    case 'warning':
      return 'warning';
    case 'success':
      return 'success';
    default:
      return 'info';
  }
}

function mapFullPageIcon(status: 'warning' | 'info' | 'success') {
  switch (status) {
    case 'warning':
      return <AlertTriangle size={24} strokeWidth={2.1} />;
    case 'success':
      return <BadgeAlert size={24} strokeWidth={2.1} />;
    default:
      return <CircleAlert size={24} strokeWidth={2.1} />;
  }
}
