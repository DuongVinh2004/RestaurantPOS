import type { StaffNoticeTone } from '../app/session-context';
import { useState, type ReactNode } from 'react';
import { KeyRound, LoaderCircle, UserRound, UtensilsCrossed, WandSparkles } from 'lucide-react';
import { formatApiError } from '../core/api/errors';
import { loginStaff } from '../core/api/staff-api';
import type { StaffSession } from '../core/auth/storage';
import { Banner, StatusPill } from './ui';

interface LoginProps {
  notice?: string | null;
  noticeTone?: StaffNoticeTone;
  onSubmit?: (identifier: string, password: string, deviceName: string) => Promise<StaffSession>;
  onSuccess: (session: StaffSession) => void;
}

const loginPresets = [
  { label: 'Tài khoản thử nhanh', identifier: 'uat.staff', password: 'UatDemo!123' },
  { label: 'Tài khoản dự phòng', identifier: 'bootstrap-staff', password: 'password' },
] as const;

async function defaultLoginSubmit(identifier: string, password: string, deviceName: string): Promise<StaffSession> {
  return loginStaff({
    identifier,
    password,
    device_name: deviceName,
  });
}

export function Login({ notice, noticeTone = 'success', onSubmit = defaultLoginSubmit, onSuccess }: LoginProps) {
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [deviceName, setDeviceName] = useState('Máy phục vụ');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (identifier.trim() === '' || password.trim() === '') {
      setError('Hãy nhập tài khoản và mật khẩu.');
      return;
    }

    setLoading(true);
    setError('');

    try {
      onSuccess(await onSubmit(identifier.trim(), password, deviceName.trim() || 'Máy phục vụ'));
    } catch (cause) {
      setError(formatApiError(cause, 'Đăng nhập không thành công.'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen">
      <div className="mx-auto flex min-h-screen max-w-[1480px] items-center px-5 py-10 sm:px-8">
        <div className="grid w-full gap-6 xl:grid-cols-[1.05fr_0.95fr]">
          <section className="panel-shell overflow-hidden border-slate-900 bg-[linear-gradient(160deg,#0f172a_0%,#1a263c_55%,#0f172a_100%)] px-0 py-0 text-white shadow-[0_32px_120px_-54px_rgba(15,23,42,0.95)]">
            <div className="grid gap-0 lg:grid-cols-[1.1fr_0.9fr]">
              <div className="px-7 py-8 sm:px-9 sm:py-9">
                <div className="flex items-center gap-4">
                  <div className="flex h-14 w-14 items-center justify-center rounded-[20px] bg-[#c46b2d] text-slate-950">
                    <UtensilsCrossed className="h-7 w-7" />
                  </div>
                  <img
                    src="/staff-mark.svg"
                    alt="RestaurantPOS Staff Web"
                    className="h-14 w-14 rounded-[20px] bg-white/[0.06] p-2"
                  />
                </div>

                <p className="mt-8 text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Đăng nhập ca làm</p>
                <h1 className="workspace-title mt-4 text-4xl font-semibold tracking-tight text-white">
                  Vào ca nhanh, nhìn bàn rõ, thao tác gọn.
                </h1>
                <p className="mt-4 max-w-xl text-sm leading-7 text-slate-300">
                  Đăng nhập một lần để theo dõi bàn, đơn hàng, bếp và thu ngân trong cùng một màn hình làm việc.
                </p>

                <div className="mt-8 grid gap-3 sm:grid-cols-3">
                  <FocusTile title="Nhìn bàn trước" description="Bàn, khách chờ và việc cần làm hiện ra ngay." />
                  <FocusTile title="Rõ ca làm" description="Chi nhánh và ca thu ngân luôn hiển thị rõ ràng." />
                  <FocusTile title="Đi tiếp liền mạch" description="Từ bàn sang đơn, bếp và thanh toán không bị rối." />
                </div>
              </div>

              <div className="border-t border-white/10 bg-white/[0.06] px-7 py-8 sm:px-9 lg:border-l lg:border-t-0">
                <p className="eyebrow text-slate-400">Đăng nhập nhanh</p>
                <h2 className="workspace-title mt-2 text-2xl font-semibold text-white">Chọn sẵn tài khoản thử</h2>
                <p className="mt-3 text-sm leading-7 text-slate-300">
                  Dùng nhanh tài khoản có sẵn để thử giao diện mà không phải gõ lại từng lần.
                </p>

                <div className="mt-6 space-y-3">
                  {loginPresets.map((preset) => (
                    <button
                      key={preset.label}
                      type="button"
                      onClick={() => {
                        setIdentifier(preset.identifier);
                        setPassword(preset.password);
                        setError('');
                      }}
                      className="flex w-full items-start justify-between rounded-[24px] border border-white/10 bg-white/[0.06] px-4 py-4 text-left transition hover:bg-white/10"
                    >
                      <div>
                        <p className="text-sm font-semibold text-white">{preset.identifier}</p>
                        <p className="mt-1 text-xs text-slate-400">{preset.label}</p>
                      </div>
                      <div className="rounded-2xl bg-white/10 p-2 text-white">
                        <WandSparkles className="h-4 w-4" />
                      </div>
                    </button>
                  ))}
                </div>

                <div className="mt-6 flex flex-wrap gap-2">
                  <StatusPill value="Đăng nhập bằng tài khoản" tone="info" />
                  <StatusPill value="Không dùng mã PIN" tone="warning" />
                  <StatusPill value="Vào thẳng màn hình làm việc" />
                </div>
              </div>
            </div>
          </section>

          <section className="panel-shell px-6 py-7 sm:px-7 sm:py-8">
            <div className="max-w-2xl">
              <div>
                <p className="eyebrow">Đăng nhập nhân viên</p>
                <h2 className="workspace-title mt-2 text-3xl font-semibold tracking-tight text-slate-950">Bắt đầu ca làm</h2>
                <p className="mt-3 max-w-xl text-sm leading-7 text-slate-600">
                  Nhập tài khoản và mật khẩu để vào ngay màn hình làm việc.
                </p>
              </div>
            </div>

            {notice ? <div className="mt-6"><Banner tone={noticeTone}>{notice}</Banner></div> : null}

            <form onSubmit={handleSubmit} className="mt-8 space-y-5">
              <Field label="Tài khoản" icon={<UserRound className="h-4 w-4" />}>
                <input
                  value={identifier}
                  onChange={(event) => setIdentifier(event.target.value)}
                  className="w-full bg-transparent text-base text-slate-950 outline-none placeholder:text-slate-400"
                  placeholder="Ví dụ: uat.staff"
                  autoFocus
                />
              </Field>
              <Field label="Mật khẩu" icon={<KeyRound className="h-4 w-4" />}>
                <input
                  type="password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  className="w-full bg-transparent text-base text-slate-950 outline-none placeholder:text-slate-400"
                  placeholder="Nhập mật khẩu"
                />
              </Field>
              <Field label="Tên thiết bị" icon={<UtensilsCrossed className="h-4 w-4" />}>
                <input
                  value={deviceName}
                  onChange={(event) => setDeviceName(event.target.value)}
                  className="w-full bg-transparent text-base text-slate-950 outline-none placeholder:text-slate-400"
                  placeholder="Ví dụ: Máy phục vụ 1"
                />
              </Field>

              {error ? <p className="rounded-[22px] border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</p> : null}

              <button
                type="submit"
                disabled={loading}
                className="inline-flex w-full items-center justify-center gap-2 rounded-[24px] bg-[#c46b2d] px-4 py-4 text-sm font-semibold text-white shadow-[0_24px_50px_-30px_rgba(196,107,45,0.95)] transition hover:bg-[#af5d25] disabled:cursor-not-allowed disabled:opacity-60"
              >
                {loading ? (
                  <LoaderCircle className="h-4 w-4 animate-spin" />
                ) : (
                  <span className="inline-block h-2.5 w-2.5 rounded-full bg-emerald-300" />
                )}
                {loading ? 'Đang đăng nhập...' : 'Đăng nhập'}
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
    <label className="block rounded-[26px] border border-slate-200/90 bg-white/80 px-4 py-3 shadow-[0_18px_45px_-36px_rgba(15,23,42,0.48)]">
      <span className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
        {icon}
        {label}
      </span>
      <div className="mt-3">{children}</div>
    </label>
  );
}

function FocusTile({ title, description }: { title: string; description: string }) {
  return (
    <div className="rounded-[24px] border border-white/10 bg-white/[0.06] px-4 py-4">
      <p className="text-sm font-semibold text-white">{title}</p>
      <p className="mt-2 text-xs leading-6 text-slate-300">{description}</p>
    </div>
  );
}
