import { AlertTriangle, CheckCircle2, Info, LoaderCircle } from 'lucide-react';
import type { ReactNode } from 'react';

export function Panel({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <section className={`panel-shell panel-grid relative overflow-hidden ${className}`}>{children}</section>;
}

export function SummaryCard({ icon, label, value }: { icon: ReactNode; label: string; value: string }) {
  return (
    <Panel className="h-full px-5 py-5">
      <div className="flex items-center justify-between gap-3">
        <div className="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[rgba(196,107,45,0.12)] text-[#8c4b20]">
          {icon}
        </div>
        <span className="eyebrow">{label}</span>
      </div>
      <p className="workspace-title mt-6 text-3xl font-semibold text-slate-950">{value}</p>
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
    <div
      className={`rounded-[26px] border px-4 py-4 ${
        dark
          ? 'border-white/10 bg-white/5 text-white'
          : 'border-slate-200/70 bg-white/75 text-slate-900'
      }`}
    >
      <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${dark ? 'text-slate-400' : 'text-slate-500'}`}>{label}</p>
      <p className={`mt-2 text-sm font-semibold leading-6 ${dark ? 'text-white' : 'text-slate-900'}`}>{value}</p>
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
  variant = 'primary',
}: {
  onClick?: () => void | Promise<void>;
  busy: boolean;
  icon: ReactNode;
  children: ReactNode;
  disabled?: boolean;
  type?: 'button' | 'submit';
  className?: string;
  variant?: 'primary' | 'secondary' | 'ghost';
}) {
  const variants = {
    primary: 'border border-[#c46b2d] bg-[#c46b2d] text-white shadow-[0_18px_48px_-28px_rgba(196,107,45,0.9)] hover:bg-[#af5d25]',
    secondary: 'border border-slate-300 bg-white/[0.85] text-slate-800 hover:bg-white',
    ghost: 'border border-white/10 bg-white/10 text-white hover:bg-white/20',
  } as const;

  return (
    <button
      type={type}
      onClick={onClick ? () => void onClick() : undefined}
      disabled={busy || disabled}
      className={`inline-flex items-center gap-2 rounded-[22px] px-4 py-3 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 ${variants[variant]} ${className}`}
    >
      {busy ? <LoaderCircle className="h-4 w-4 animate-spin" /> : icon}
      {busy ? 'Đang xử lý...' : children}
    </button>
  );
}

export function Banner({
  tone,
  children,
}: {
  tone: 'success' | 'error' | 'warning';
  children: ReactNode;
}) {
  const icon = tone === 'success'
    ? <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
    : <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />;
  const theme = tone === 'success'
    ? 'border-emerald-200/80 bg-emerald-50/90 text-emerald-800'
    : tone === 'warning'
      ? 'border-amber-200/80 bg-amber-50/92 text-amber-800'
      : 'border-rose-200/80 bg-rose-50/92 text-rose-700';

  return (
    <div className={`flex items-start gap-3 rounded-[24px] border px-4 py-4 text-sm shadow-[0_16px_36px_-30px_rgba(15,23,42,0.5)] ${theme}`}>
      {icon}
      <div className="min-w-0 flex-1">{children}</div>
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
    neutral: 'border border-slate-200 bg-white/70 text-slate-700',
    success: 'border border-emerald-200 bg-emerald-50 text-emerald-700',
    warning: 'border border-amber-200 bg-amber-50 text-amber-700',
    danger: 'border border-rose-200 bg-rose-50 text-rose-700',
    info: 'border border-sky-200 bg-sky-50 text-sky-700',
  } as const;

  return (
    <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] ${tones[tone]}`}>
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
    <Panel className="px-6 py-6">
      <div className="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div className="max-w-3xl">
          <p className="eyebrow">{eyebrow}</p>
          <h1 className="workspace-title mt-3 text-[clamp(1.75rem,2.7vw,2.9rem)] font-semibold text-slate-950">{title}</h1>
          <p className="mt-3 text-sm leading-7 text-slate-600">{description}</p>
        </div>
        {actions ? <div className="flex flex-wrap items-center gap-3">{actions}</div> : null}
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
    <div className="rounded-[28px] border border-slate-200/80 bg-[rgba(255,255,255,0.76)] px-5 py-5 text-sm text-slate-600">
      <div className="flex items-start gap-3">
        <div className="rounded-2xl bg-slate-100 p-2 text-slate-500">
          <Info className="h-4 w-4" />
        </div>
        <div className="min-w-0">
          <p className="font-semibold text-slate-900">{title}</p>
          <p className="mt-2 leading-6">{description}</p>
        </div>
      </div>
    </div>
  );
}
