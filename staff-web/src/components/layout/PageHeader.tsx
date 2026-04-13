import type { ReactNode } from 'react';

export function PageHeader({
  eyebrow,
  title,
  description,
  extra,
  meta,
  context,
  className,
}: {
  eyebrow?: string;
  title: string;
  description: string;
  extra?: ReactNode;
  meta?: ReactNode;
  context?: ReactNode;
  className?: string;
}) {
  return (
    <div className={['staff-page-header', className].filter(Boolean).join(' ')}>
      <div className="staff-page-header-main">
        {eyebrow ? <span className="staff-eyebrow">{eyebrow}</span> : null}

        <div className="staff-page-header-copy">
          <h3>{title}</h3>
          <p className="staff-page-header-description">{description}</p>
        </div>

        {meta || context ? (
          <div className="staff-page-header-support">
            {meta ? <div className="staff-page-header-meta">{meta}</div> : null}
            {context ? <div className="staff-page-header-context">{context}</div> : null}
          </div>
        ) : null}
      </div>

      {extra ? (
        <div className="staff-page-header-actions-wrap">
          <div className="staff-page-header-actions">{extra}</div>
        </div>
      ) : null}
    </div>
  );
}
