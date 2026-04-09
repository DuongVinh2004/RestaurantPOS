import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { RefreshCcw } from 'lucide-react';
import { apiBaseUrl, formatApiError, isUnauthorized } from '../../api/client';
import { useStaffSession } from '../../app/session-context';
import { visibleStaffSections } from '../../app/sections';
import { ActionButton, Banner, PageHeader, Panel, StatusPill } from '../ui';

export function StaffShell() {
  const navigate = useNavigate();
  const { session, notice, noticeTone, clearNotice, refresh, logout, expire, setNotice } = useStaffSession();
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const sections = visibleStaffSections(session);

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
    `Access ${readiness.access}`,
    `Branch ${defaultBranch?.branch_code ?? readiness.branch}`,
    `Shift ${activeShift?.shift_code ?? readiness.cashier_shift}`,
  ].join(' · ');

  return (
    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
      <PageHeader
        eyebrow="RestaurantPOS Staff Web"
        title="Staff app bind truc tiep vao canonical backend contracts"
        description="Session bootstrap, capability-aware navigation, row_version conflict handling va finance-safe flows deu chay tren SDK generate tu source of truth."
        actions={
          <div className="flex flex-wrap items-center gap-3">
            <ActionButton onClick={refreshSession} busy={busyKey === 'refresh'} icon={<RefreshCcw className="h-4 w-4" />}>
              Refresh token
            </ActionButton>
            <button
              type="button"
              onClick={() => void logoutSession()}
              disabled={busyKey === 'logout'}
              className="rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
            >
              {busyKey === 'logout' ? 'Dang dang xuat...' : 'Dang xuat'}
            </button>
          </div>
        }
      />

      {notice ? (
        <div className="mt-4">
          <Banner tone={noticeTone}>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <span>{notice}</span>
              <button type="button" onClick={clearNotice} className="font-semibold text-inherit underline">
                An thong bao
              </button>
            </div>
          </Banner>
        </div>
      ) : null}

      <Panel className="mt-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div className="space-y-3">
            <div className="flex flex-wrap gap-2">
              <StatusPill value={`User ${session.user?.full_name ?? session.user?.username ?? 'Staff'}`} tone="info" />
              <StatusPill value={`Role ${session.user?.role_name ?? 'Staff'}`} />
              <StatusPill value={`Key ${session.staff_api_key_id}`} />
              <StatusPill value={`Caps ${session.capabilities.length}`} tone="success" />
              <StatusPill value={`Known ${session.known_capabilities.length}`} />
              <StatusPill value={readiness.operator_ready ? 'Operator ready' : 'Setup required'} tone={startupTone} />
              <StatusPill value={`Branch ${defaultBranch?.branch_code ?? readiness.branch}`} tone={branchTone} />
              <StatusPill value={`Shift ${activeShift?.shift_code ?? readiness.cashier_shift}`} tone={shiftTone} />
            </div>
            <p className="text-xs text-slate-500">Backend host: {apiBaseUrl}</p>
            <p className="text-xs text-slate-500">
              {startupSummary}
              {defaultBranch ? ` · ${defaultBranch.branch_name}` : ''}
              {activeShift?.terminal_code ? ` · Terminal ${activeShift.terminal_code}` : ''}
            </p>
          </div>

          <nav className="flex flex-wrap gap-2">
            {sections.map((section) => {
              const Icon = section.icon;
              return (
                <NavLink
                  key={section.path}
                  to={section.path}
                  className={({ isActive }) =>
                    `inline-flex items-center gap-2 rounded-2xl px-4 py-3 text-sm font-semibold transition ${
                      isActive ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                    }`
                  }
                >
                  <Icon className="h-4 w-4" />
                  {section.label}
                </NavLink>
              );
            })}
          </nav>
        </div>
      </Panel>

      <div className="mt-6">
        <Outlet />
      </div>
    </div>
  );

  async function refreshSession() {
    setBusyKey('refresh');

    try {
      await refresh();
      setNotice('Da refresh token staff.', 'success');
    } catch (cause) {
      if (isUnauthorized(cause)) {
        expire('Token staff da het han. Dang nhap lai de tiep tuc.');
        navigate('/login', { replace: true });
        return;
      }

      setNotice(formatApiError(cause, 'Khong the refresh token staff. Thu lai.'), 'error');
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
