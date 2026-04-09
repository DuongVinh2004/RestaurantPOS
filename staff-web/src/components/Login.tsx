import type { StaffNoticeTone } from '../app/session-context';
import { useState, type ReactNode } from 'react';
import { KeyRound, LoaderCircle, UserRound, UtensilsCrossed } from 'lucide-react';
import { formatApiError, loginStaff, type StaffSession } from '../api/client';
import { Banner } from './ui';

interface LoginProps {
  notice?: string | null;
  noticeTone?: StaffNoticeTone;
  onSubmit?: (identifier: string, password: string, deviceName: string) => Promise<StaffSession>;
  onSuccess: (session: StaffSession) => void;
}

export function Login({ notice, noticeTone = 'success', onSubmit = loginStaff, onSuccess }: LoginProps) {
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [deviceName, setDeviceName] = useState('staff-web');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (identifier.trim() === '' || password.trim() === '') {
      setError('Can nhap tai khoan va mat khau staff.');
      return;
    }

    setLoading(true);
    setError('');

    try {
      onSuccess(await onSubmit(identifier.trim(), password, deviceName.trim() || 'staff-web'));
    } catch (cause) {
      setError(formatApiError(cause, 'Dang nhap staff that bai.'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(244,162,97,0.22),_transparent_35%),linear-gradient(145deg,#f7f3eb_0%,#fffdf9_52%,#ebf4ff_100%)]">
      <div className="mx-auto flex min-h-screen max-w-6xl items-center px-6 py-12">
        <div className="grid w-full gap-8 lg:grid-cols-[1.1fr_0.9fr]">
          <section className="rounded-[32px] bg-slate-950 px-8 py-10 text-white shadow-[0_30px_120px_-40px_rgba(15,23,42,0.85)]">
            <div className="inline-flex items-center gap-4">
              <div className="flex h-14 w-14 items-center justify-center rounded-3xl bg-amber-500 text-slate-950">
                <UtensilsCrossed className="h-7 w-7" />
              </div>
              <img
                src="/staff-mark.svg"
                alt="RestaurantPOS Staff Web"
                className="h-14 w-14 rounded-3xl bg-white/5 p-2"
              />
            </div>
            <p className="mt-8 text-xs font-semibold uppercase tracking-[0.32em] text-slate-400">
              Staff Web Connector
            </p>
            <h1 className="mt-4 text-4xl font-bold tracking-tight">
              Bo stub va dang nhap theo contract that cua backend.
            </h1>
            <p className="mt-4 max-w-xl text-sm leading-7 text-slate-300">
              Form nay dung `identifier` + `password`, nhan opaque staff token tu `POST /api/v1/auth/staff/login`,
              roi nap startup readiness, branch mac dinh, va active cashier shift ngay sau khi xac thuc.
            </p>
          </section>

          <section className="panel-shell px-6 py-7">
            <p className="eyebrow">Dang nhap staff</p>
            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">
              Auth that, khong con PIN fallback
            </h2>
            {notice ? <Banner tone={noticeTone}>{notice}</Banner> : null}
            <form onSubmit={handleSubmit} className="mt-8 space-y-5">
              <Field label="Tai khoan" icon={<UserRound className="h-4 w-4" />}>
                <input
                  value={identifier}
                  onChange={(event) => setIdentifier(event.target.value)}
                  className="w-full bg-transparent text-base text-slate-950 outline-none placeholder:text-slate-400"
                  placeholder="vd: staff-auth-http"
                  autoFocus
                />
              </Field>
              <Field label="Mat khau" icon={<KeyRound className="h-4 w-4" />}>
                <input
                  type="password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  className="w-full bg-transparent text-base text-slate-950 outline-none placeholder:text-slate-400"
                  placeholder="Nhap mat khau staff"
                />
              </Field>
              <Field label="Device label" icon={<UtensilsCrossed className="h-4 w-4" />}>
                <input
                  value={deviceName}
                  onChange={(event) => setDeviceName(event.target.value)}
                  className="w-full bg-transparent text-base text-slate-950 outline-none placeholder:text-slate-400"
                  placeholder="staff-web"
                />
              </Field>

              {error ? <p className="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</p> : null}

              <button
                type="submit"
                disabled={loading}
                className="inline-flex w-full items-center justify-center gap-2 rounded-[22px] bg-slate-950 px-4 py-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
              >
                {loading ? (
                  <LoaderCircle className="h-4 w-4 animate-spin" />
                ) : (
                  <span className="inline-block h-2.5 w-2.5 rounded-full bg-emerald-400" />
                )}
                {loading ? 'Dang xac thuc...' : 'Dang nhap staff'}
              </button>
            </form>
          </section>
        </div>
      </div>
    </div>
  );
}

function Field({ label, icon, children }: { label: string; icon: ReactNode; children: ReactNode }) {
  return (
    <label className="block rounded-[24px] border border-slate-200 bg-slate-50 px-4 py-3">
      <span className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">
        {icon}
        {label}
      </span>
      <div className="mt-3">{children}</div>
    </label>
  );
}
