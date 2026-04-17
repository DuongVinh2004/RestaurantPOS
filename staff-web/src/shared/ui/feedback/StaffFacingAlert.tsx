import type { ReactNode } from 'react';
import {
  AlertCircle,
  AlertTriangle,
  CheckCircle2,
  Info,
  X,
} from 'lucide-react';

export function StaffFacingAlert({
  tone,
  title,
  description,
  closable = false,
  onClose,
  eyebrow,
  meta,
  actions,
}: {
  tone: 'success' | 'warning' | 'error' | 'info';
  title: string;
  description?: string | null;
  closable?: boolean;
  onClose?: () => void;
  eyebrow?: string;
  meta?: string | null;
  actions?: ReactNode;
}) {
  const Icon = tone === 'success'
    ? CheckCircle2
    : tone === 'warning'
      ? AlertTriangle
      : tone === 'error'
        ? AlertCircle
        : Info;

  return (
    <div
      className={`staff-facing-alert staff-facing-alert-${tone}`}
      role={tone === 'error' || tone === 'warning' ? 'alert' : 'status'}
    >
      <div className="staff-facing-alert-body">
        <span className="staff-facing-alert-icon" aria-hidden="true">
          <Icon size={18} strokeWidth={2.1} />
        </span>

        <div className="staff-facing-alert-content">
          <div className="staff-facing-alert-head">
            {eyebrow ? <span className="staff-facing-alert-eyebrow">{eyebrow}</span> : null}
            <span className="staff-facing-alert-title">{title}</span>
          </div>

          {description || meta || actions ? (
            <div className="staff-facing-alert-details">
              {description ? <p className="staff-facing-alert-description">{description}</p> : null}
              {meta ? <p className="staff-facing-alert-meta">{meta}</p> : null}
              {actions ? <div className="staff-facing-alert-actions">{actions}</div> : null}
            </div>
          ) : null}
        </div>

        {closable ? (
          <button type="button" className="staff-facing-alert-close" onClick={onClose} aria-label="Đóng thông báo">
            <X size={16} strokeWidth={2.1} />
          </button>
        ) : null}
      </div>
    </div>
  );
}
