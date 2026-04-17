import { AlertTriangle, Inbox, LoaderCircle, ShieldAlert, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type AppFrameStateTone = 'default' | 'warning' | 'error' | 'success' | 'info';

const toneIcons: Record<AppFrameStateTone, LucideIcon> = {
  default: Inbox,
  warning: AlertTriangle,
  error: ShieldAlert,
  success: Inbox,
  info: LoaderCircle,
};

function joinClasses(...classes: Array<string | false | null | undefined>) {
  return classes.filter(Boolean).join(' ');
}

export function AppFrameState({
  tone = 'default',
  eyebrow,
  title,
  description,
  meta,
  action,
  body,
  compact = false,
}: {
  tone?: AppFrameStateTone;
  eyebrow?: string;
  title: string;
  description: string;
  meta?: string;
  action?: ReactNode;
  body?: ReactNode;
  compact?: boolean;
}) {
  const Icon = toneIcons[tone];

  return (
    <div className={joinClasses('staff-state-surface', `staff-state-surface-${tone}`, compact && 'staff-state-surface-compact')}>
      <span className="staff-state-surface-icon" aria-hidden="true">
        <Icon size={18} />
      </span>

      <div className="staff-state-surface-copy">
        {eyebrow ? <span className="staff-state-surface-eyebrow">{eyebrow}</span> : null}
        <strong className="staff-state-surface-title">{title}</strong>
        <p className="staff-state-surface-description">{description}</p>
        {meta ? <p className="staff-state-surface-meta">{meta}</p> : null}
      </div>

      {body ? <div className="staff-state-surface-body">{body}</div> : null}
      {action ? <div className="staff-state-surface-action">{action}</div> : null}
    </div>
  );
}

export function AppFrameLoadingState({
  title = 'Loading workspace',
  description = 'Shared shell state is still synchronizing before this workspace can render.',
}: {
  title?: string;
  description?: string;
}) {
  return (
    <AppFrameState
      tone="info"
      eyebrow="Loading"
      title={title}
      description={description}
    />
  );
}

export function AppFrameEmptyState({
  title = 'Nothing to show yet',
  description = 'This workspace does not have any active content in the current context.',
}: {
  title?: string;
  description?: string;
}) {
  return (
    <AppFrameState
      eyebrow="Empty"
      title={title}
      description={description}
    />
  );
}

export function AppFramePermissionState({
  title = 'Workspace access is limited',
  description = 'The current session can load the shell, but it does not expose any allowed destination here.',
}: {
  title?: string;
  description?: string;
}) {
  return (
    <AppFrameState
      tone="warning"
      eyebrow="Access"
      title={title}
      description={description}
    />
  );
}
