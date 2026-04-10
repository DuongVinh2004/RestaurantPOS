import { useMemo, useState } from 'react';
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { Clock3, LogOut, RefreshCcw, ShieldCheck, Store } from 'lucide-react';
import { formatApiError, isApiStatus } from '../../core/api/errors';
import { useStaffSession } from '../../app/session-context';
import { visibleStaffSections } from '../../app/sections';
import { ActionButton, Banner, MetricCard, PageHeader, Panel, StatusPill } from '../ui';

export function StaffShell() {
  const location = useLocation();
  const navigate = useNavigate();
  const { session, notice, noticeTone, clearNotice, refresh, logout, expire, setNotice } = useStaffSession();
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const sections = useMemo(() => (session ? visibleStaffSections(session) : []), [session]);
  const navItems = useMemo(
    () => sections.map((section) => ({
      ...section,
      active: location.pathname === section.path || location.pathname.startsWith(`${section.path}/`),
    })),
    [location.pathname, sections],
  );
  const activeSection = sections.find((section) => location.pathname === section.path || location.pathname.startsWith(`${section.path}/`)) ?? sections[0] ?? null;

  if (!session) {
    return null;
  }

  const readiness = session.startup.readiness;
  const defaultBranch = session.startup.default_branch;
  const activeShift = session.startup.active_cashier_shift;
  const startupTone = readiness.operator_ready ? 'success' : 'warning';
  const branchTone = readiness.branch === 'ready' ? 'success' : 'warning';
  const shiftTone = readiness.cashier_shift === 'ready'
    ? 'success'
    : readiness.cashier_shift === 'action_required'
      ? 'warning'
      : 'neutral';
  const startupSummary = [
    `Quyền ${translateReadiness(readiness.access)}`,
    `Chi nhánh ${defaultBranch?.branch_code ?? translateReadiness(readiness.branch)}`,
    `Ca ${activeShift?.shift_code ?? translateReadiness(readiness.cashier_shift)}`,
  ].join(' | ');

  return (
    <div className="mx-auto max-w-[1600px] px-4 py-4 sm:px-6 lg:px-8">
      {notice ? (
        <div className="mb-4">
          <Banner tone={noticeTone}>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <span>{notice}</span>
              <button type="button" onClick={clearNotice} className="font-semibold text-inherit underline underline-offset-4">
                An thong bao
              </button>
            </div>
          </Banner>
        </div>
      ) : null}

      <div className="grid gap-5 xl:grid-cols-[300px_minmax(0,1fr)]">
        <aside className="space-y-5 xl:sticky xl:top-4 self-start">
          <Panel className="overflow-hidden border-slate-900 bg-[linear-gradient(180deg,#0f172a,#18253b_55%,#0f172a)] px-0 py-0 text-white shadow-[0_30px_100px_-52px_rgba(15,23,42,0.95)]">
            <div className="border-b border-white/10 px-5 py-5">
              <div className="flex items-center gap-3">
                <div className="flex h-12 w-12 items-center justify-center rounded-[18px] bg-[#c46b2d] text-slate-950">
                  <Store className="h-6 w-6" />
                </div>
                <div>
                  <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Màn hình nhân viên</p>
                  <h1 className="workspace-title text-xl font-semibold text-white">Khu phục vụ</h1>
                </div>
              </div>
              <p className="mt-4 text-sm leading-6 text-slate-300">
                Điều hướng gọn, tập trung vào bàn, đơn hàng và ca làm hiện tại.
              </p>
            </div>

            <div className="space-y-2 px-3 py-3">
              {navItems.map((section) => {
                const Icon = section.icon;

                return (
                  <NavLink
                    key={section.path}
                    to={section.path}
                    className={({ isActive }) =>
                      `group flex items-start gap-3 rounded-[24px] px-4 py-4 transition ${
                        isActive
                          ? 'bg-white text-slate-950 shadow-[0_20px_45px_-30px_rgba(255,255,255,0.7)]'
                          : 'text-slate-300 hover:bg-white/10 hover:text-white'
                      }`
                    }
                  >
                    <div className={`mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl ${section.active ? 'bg-slate-100 text-slate-900' : 'bg-white/10 text-white'}`}>
                      <Icon className="h-5 w-5" />
                    </div>
                    <div className="min-w-0">
                      <p className="text-sm font-semibold">{section.label}</p>
                      <p className={`mt-1 text-xs leading-5 ${section.active ? 'text-slate-600' : 'text-slate-400'}`}>{section.description}</p>
                    </div>
                  </NavLink>
                );
              })}
            </div>
          </Panel>

          <Panel className="px-4 py-4">
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="eyebrow">Tình trạng ca</p>
                <h2 className="workspace-title mt-2 text-xl font-semibold text-slate-950">Thông tin hiện tại</h2>
              </div>
              <div className="rounded-2xl bg-slate-100 p-2 text-slate-600">
                <ShieldCheck className="h-4 w-4" />
              </div>
            </div>

            <div className="mt-4 flex flex-wrap gap-2">
              <StatusPill value={session.user?.full_name ?? session.user?.username ?? 'Nhân viên'} tone="info" />
              <StatusPill value={session.user?.role_name ?? 'Nhân viên'} />
              <StatusPill value={readiness.operator_ready ? 'Sẵn sàng làm việc' : 'Cần chuẩn bị thêm'} tone={startupTone} />
              <StatusPill value={`Chi nhánh ${defaultBranch?.branch_code ?? translateReadiness(readiness.branch)}`} tone={branchTone} />
              <StatusPill value={`Ca ${activeShift?.shift_code ?? translateReadiness(readiness.cashier_shift)}`} tone={shiftTone} />
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
              <MetricCard label="Chi nhánh" value={defaultBranch ? `${defaultBranch.branch_name} (${defaultBranch.branch_code})` : 'Chưa có chi nhánh mặc định'} />
              <MetricCard label="Ca thu ngân" value={activeShift?.shift_code ?? translateReadiness(readiness.cashier_shift)} />
              <MetricCard label="Thiết bị" value={activeShift?.terminal_code ?? 'Chưa mở ca'} />
            </div>

            <div className="mt-4 flex items-center gap-2 text-xs text-slate-500">
              <Clock3 className="h-3.5 w-3.5" />
              <span>{startupSummary}</span>
            </div>
          </Panel>
        </aside>

        <main className="space-y-5">
          <PageHeader
            eyebrow="Màn hình làm việc"
            title={activeSection ? activeSection.label : 'Màn hình nhân viên'}
            description={activeSection?.description ?? 'Chỉ hiển thị những mục bạn có thể dùng ngay trong ca hiện tại.'}
            actions={
              <>
                <ActionButton
                  onClick={refreshSession}
                  busy={busyKey === 'refresh'}
                  icon={<RefreshCcw className="h-4 w-4" />}
                  variant="secondary"
                >
                  Làm mới phiên
                </ActionButton>
                <ActionButton
                  onClick={() => void logoutSession()}
                  busy={busyKey === 'logout'}
                  icon={<LogOut className="h-4 w-4" />}
                  variant="secondary"
                >
                  Dang xuat
                </ActionButton>
              </>
            }
          />

          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Nhân viên" value={session.user?.full_name ?? session.user?.username ?? 'Nhân viên'} />
            <MetricCard label="Tình trạng" value={readiness.operator_ready ? 'Sẵn sàng thao tác' : 'Cần xử lý thêm'} />
            <MetricCard label="Chi nhánh" value={defaultBranch?.branch_code ?? translateReadiness(readiness.branch)} />
            <MetricCard label="Ca thu ngân" value={activeShift?.shift_code ?? translateReadiness(readiness.cashier_shift)} />
          </div>

          <Outlet />
        </main>
      </div>
    </div>
  );

  async function refreshSession() {
    setBusyKey('refresh');

    try {
      await refresh();
      setNotice('Đã làm mới phiên đăng nhập.', 'success');
    } catch (cause) {
      if (isApiStatus(cause, 401)) {
        expire('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
        navigate('/login', { replace: true });
        return;
      }

      setNotice(formatApiError(cause, 'Không thể làm mới phiên. Hãy thử lại.'), 'error');
    } finally {
      setBusyKey(null);
    }
  }

  async function logoutSession() {
    setBusyKey('logout');

    try {
      await logout();
      navigate('/login', { replace: true });
    } finally {
      setBusyKey(null);
    }
  }
}

function translateReadiness(value: string) {
  const normalized = value.trim().toLowerCase();

  switch (normalized) {
    case 'ready':
      return 'sẵn sàng';
    case 'action_required':
      return 'cần xử lý';
    case 'not_applicable':
      return 'không áp dụng';
    case 'missing':
      return 'còn thiếu';
    case 'capability_missing':
      return 'thiếu quyền';
    case 'open':
      return 'đang mở';
    default:
      return value.replace(/_/g, ' ');
  }
}
