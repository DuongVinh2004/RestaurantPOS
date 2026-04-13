import type { ReactNode } from 'react';
import { Button } from 'antd';
import {
  AlertTriangle,
  BadgeAlert,
  CircleAlert,
  FileSearch,
  RefreshCcw,
  ShieldAlert,
} from 'lucide-react';
import { StaffFacingAlert } from '../feedback/StaffFacingAlert';

export function InlineError({
  message,
  extra,
}: {
  message: string;
  extra?: ReactNode;
}) {
  return (
    <StaffFacingAlert
      tone="error"
      eyebrow="Cần xử lý"
      title={message}
      description={typeof extra === 'string' ? extra : undefined}
      actions={typeof extra === 'string' ? undefined : extra}
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
    <StaffFacingAlert
      tone="warning"
      eyebrow="Lưu ý vận hành"
      title={title}
      description={description}
      actions={action}
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
    <StateSurface
      tone="loading"
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
    <StateSurface
      tone="empty"
      icon={<FileSearch size={18} strokeWidth={2.1} />}
      title={title}
      description={description}
      action={action}
      className="staff-empty-block"
    />
  );
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
    <StaffFacingAlert
      tone="warning"
      eyebrow="Freshness"
      title={title}
      description={description}
      meta={lastUpdatedLabel}
      actions={onRefresh ? <Button onClick={onRefresh}>Làm mới ngay</Button> : undefined}
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
    <StaffFacingAlert
      tone="warning"
      eyebrow="Thử lại"
      title={title}
      description={description}
      actions={<Button onClick={onRetry}>Tải lại</Button>}
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
  const tone = mapFullPageTone(status);
  const icon = mapFullPageIcon(status);

  return (
    <div className="staff-full-page-state-wrap">
      <StateSurface
        tone={tone}
        icon={icon}
        title={title}
        description={description}
        action={extra}
      className="staff-full-page-state"
      />
    </div>
  );
}

function StateSurface({
  tone,
  icon,
  title,
  description,
  action,
  body,
  className,
}: {
  tone: 'empty' | 'loading' | 'warning' | 'error' | 'info' | 'success';
  icon: ReactNode;
  title: string;
  description?: string;
  action?: ReactNode;
  body?: ReactNode;
  className?: string;
}) {
  return (
    <div className={['staff-state-surface', `staff-state-surface-${tone}`, className].filter(Boolean).join(' ')}>
      <div className="staff-state-surface-icon" aria-hidden="true">{icon}</div>
      <div className="staff-state-surface-copy">
        <span className="staff-state-surface-title">{title}</span>
        {description ? (
          <p className="staff-state-surface-description">{description}</p>
        ) : null}
      </div>
      {body ? <div className="staff-state-surface-body">{body}</div> : null}
      {action ? <div className="staff-state-surface-action">{action}</div> : null}
    </div>
  );
}

function mapFullPageTone(status: '403' | '404' | '500' | 'warning' | 'info' | 'success') {
  switch (status) {
    case '403':
    case '500':
      return 'error';
    case '404':
      return 'empty';
    case 'warning':
      return 'warning';
    case 'success':
      return 'success';
    default:
      return 'info';
  }
}

function mapFullPageIcon(status: '403' | '404' | '500' | 'warning' | 'info' | 'success') {
  switch (status) {
    case '403':
      return <ShieldAlert size={24} strokeWidth={2.1} />;
    case '500':
      return <BadgeAlert size={24} strokeWidth={2.1} />;
    case '404':
      return <FileSearch size={24} strokeWidth={2.1} />;
    case 'warning':
      return <AlertTriangle size={24} strokeWidth={2.1} />;
    case 'success':
      return <RefreshCcw size={24} strokeWidth={2.1} />;
    default:
      return <CircleAlert size={24} strokeWidth={2.1} />;
  }
}
