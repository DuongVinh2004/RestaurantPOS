import { AlertTriangle, LoaderCircle } from 'lucide-react';
import type { ReactNode } from 'react';

export function Panel({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <section className={`panel-shell px-5 py-5 ${className}`}>{children}</section>;
}

export function SummaryCard({ icon, label, value }: { icon: ReactNode; label: string; value: string }) {
  return (
    <Panel>
      <div className="flex items-center justify-between gap-3">
        <div className="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">{icon}</div>
        <span className="eyebrow">{label}</span>
      </div>
      <p className="mt-4 text-3xl font-bold tracking-tight text-slate-950">{value}</p>
    </Panel>
  );
}

export function MetricCard({
  label,
  value,
  dark = false,
}: {
  label: string;
  value: string;
  dark?: boolean;
}) {
  return (
    <div className={`rounded-2xl px-4 py-3 ${dark ? 'bg-white/10' : 'bg-white'}`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${dark ? 'text-slate-400' : 'text-slate-500'}`}>{label}</p>
      <p className={`mt-2 text-sm font-semibold ${dark ? 'text-white' : 'text-slate-900'}`}>{value}</p>
    </div>
  );
}

export function ActionButton({
  onClick,
  busy,
  icon,
  children,
  disabled = false,
  type = 'button',
  className = '',
}: {
  onClick?: () => void | Promise<void>;
  busy: boolean;
  icon: ReactNode;
  children: ReactNode;
  disabled?: boolean;
  type?: 'button' | 'submit';
  className?: string;
}) {
  return (
    <button
      type={type}
      onClick={onClick ? () => void onClick() : undefined}
      disabled={busy || disabled}
      className={`inline-flex items-center gap-2 rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60 ${className}`}
    >
      {busy ? <LoaderCircle className="h-4 w-4 animate-spin" /> : icon}
      {busy ? 'Dang xu ly...' : children}
    </button>
  );
}

export function Banner({
  tone,
  children,
}: {
  tone: 'success' | 'error';
  children: ReactNode;
}) {
  return (
    <div
      className={`mt-4 flex items-start gap-3 rounded-[22px] border px-4 py-4 text-sm ${
        tone === 'success'
          ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
          : 'border-rose-200 bg-rose-50 text-rose-700'
      }`}
    >
      <div>{children}</div>
    </div>
  );
}

export function StatusPill({
  value,
  tone = 'neutral',
}: {
  value: string;
  tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'info';
}) {
  const tones = {
    neutral: 'bg-slate-100 text-slate-700',
    success: 'bg-emerald-100 text-emerald-700',
    warning: 'bg-amber-100 text-amber-700',
    danger: 'bg-rose-100 text-rose-700',
    info: 'bg-sky-100 text-sky-700',
  };

  return (
    <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] ${tones[tone]}`}>
      {value}
    </span>
  );
}

export function PageHeader({
  eyebrow,
  title,
  description,
  actions,
}: {
  eyebrow: string;
  title: string;
  description: string;
  actions?: ReactNode;
}) {
  return (
    <Panel className="overflow-hidden px-0 py-0">
      <div className="grid gap-5 bg-[radial-gradient(circle_at_top_right,_rgba(15,23,42,0.12),_transparent_35%),linear-gradient(135deg,#fffaf1,#ffffff_52%,#eef6ff)] px-6 py-6 lg:grid-cols-[1.2fr_0.8fr]">
        <div>
          <p className="eyebrow">{eyebrow}</p>
          <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-950">{title}</h1>
          <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">{description}</p>
        </div>
        {actions ? <div className="flex items-start justify-start gap-3 lg:justify-end">{actions}</div> : null}
      </div>
    </Panel>
  );
}

export function EmptyState({
  title,
  description,
}: {
  title: string;
  description: string;
}) {
  return (
    <div className="rounded-[24px] border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-sm text-slate-600">
      <div className="flex items-start gap-3">
        <div className="rounded-2xl bg-white p-2 text-slate-500">
          <AlertTriangle className="h-4 w-4" />
        </div>
        <div>
          <p className="font-semibold text-slate-900">{title}</p>
          <p className="mt-2 leading-6">{description}</p>
        </div>
      </div>
    </div>
  );
}
