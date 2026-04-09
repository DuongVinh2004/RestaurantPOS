import { useCallback, useEffect, useMemo, useState } from 'react';
import { CalendarX2, Clock3, MapPin, RefreshCcw, Settings2 } from 'lucide-react';
import { isUnauthorized, loadAdminBranches } from '../../api/client';
import { useStaffSession } from '../../app/session-context';
import { formatApiError } from '../../lib/api-errors';
import { asRecord, formatDateTime, readBoolean, readNumber, readString } from '../../lib/format';
import type { BranchCollectionEnvelope } from '../../api/sdk';
import { ActionButton, Banner, EmptyState, MetricCard, PageHeader, Panel, StatusPill } from '../../components/ui';

type BranchEntry = BranchCollectionEnvelope['data'][number];

export function SettingsPage() {
  const { expire, session } = useStaffSession();
  const [searchQuery, setSearchQuery] = useState('');
  const [activeMode, setActiveMode] = useState<'active' | 'all'>('active');
  const [branches, setBranches] = useState<BranchCollectionEnvelope | null>(null);
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(session?.startup.default_branch?.branch_id ?? null);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const selectedBranch = useMemo(
    () => branches?.data.find((branch) => branch.branch_id === selectedBranchId) ?? null,
    [branches, selectedBranchId],
  );

  const refreshBranches = useCallback(async (query: string, activeOnly: boolean, nextNotice: string | null) => {
    setBusyKey('refresh');
    setError(null);

    try {
      const nextBranches = await loadAdminBranches({
        q: query.trim() || undefined,
        is_active: activeOnly ? true : undefined,
      });

      setBranches(nextBranches);
      setSelectedBranchId((currentSelectedBranchId) => pickBranchId(
        nextBranches.data,
        currentSelectedBranchId,
        session?.startup.default_branch?.branch_id ?? null,
      ));
      setNotice(nextNotice);
    } catch (cause) {
      if (isUnauthorized(cause)) {
        expire('Phien staff da het han. Dang nhap lai de tiep tuc.');
        return;
      }

      setError(formatApiError(cause, 'Khong tai duoc branch settings.'));
    } finally {
      setBusyKey(null);
    }
  }, [expire, session?.startup.default_branch?.branch_id]);

  useEffect(() => {
    void refreshBranches(searchQuery, activeMode === 'active', null);
  }, [refreshBranches, session?.staff_api_key_id]);

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Settings"
        title="Branch state va scheduling context"
        description="Batch 5 chi mo branch-state surface toi thieu: chon branch, doc timezone/currency, business hours, closure windows, va booking policy can thiet de branch lead co operational context ngay trong staff-web."
        actions={(
          <ActionButton
            onClick={() => refreshBranches(searchQuery, activeMode === 'active', 'Da reload branch settings.')}
            busy={busyKey === 'refresh'}
            icon={<RefreshCcw className="h-4 w-4" />}
          >
            Reload branches
          </ActionButton>
        )}
      />

      {notice ? <Banner tone="success">{notice}</Banner> : null}
      {error ? <Banner tone="error">{error}</Banner> : null}

      <Panel>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="eyebrow">Branch list</p>
            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Read-first branch state</h2>
            <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
              Page nay khong co write path. Muc tieu la cho branch lead thay startup default branch, branch active state, va scheduling policy hien hanh ma khong can mo admin API surface day du.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <StatusPill value={`Branches ${branches?.data.length ?? 0}`} tone="success" />
            {selectedBranch ? <StatusPill value={`Selected ${selectedBranch.branch_code}`} tone="info" /> : null}
          </div>
        </div>

        <div className="mt-5 grid gap-3 md:grid-cols-[1fr_220px_auto]">
          <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Branch search</span>
            <input
              value={searchQuery}
              onChange={(event) => setSearchQuery(event.target.value)}
              className="mt-3 w-full bg-transparent text-sm outline-none"
              placeholder="Code, name..."
            />
          </label>
          <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Active scope</span>
            <select
              value={activeMode}
              onChange={(event) => setActiveMode(event.target.value as 'active' | 'all')}
              className="mt-3 w-full bg-transparent text-sm outline-none"
            >
              <option value="active">Active only</option>
              <option value="all">All</option>
            </select>
          </label>
          <div className="flex items-end">
            <ActionButton
              onClick={() => refreshBranches(searchQuery, activeMode === 'active', 'Da ap dung branch filters moi.')}
              busy={busyKey === 'refresh'}
              icon={<RefreshCcw className="h-4 w-4" />}
            >
              Apply filters
            </ActionButton>
          </div>
        </div>
      </Panel>

      <div className="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <Panel>
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Available branches</p>
              <h3 className="text-xl font-semibold text-slate-950">Selection surface</h3>
            </div>
            <MapPin className="h-5 w-5 text-slate-400" />
          </div>

          {(branches?.data ?? []).length === 0 ? (
            <div className="mt-5">
              <EmptyState
                title="Chua co branch read model"
                description="Khi admin branch foundation da san sang, page nay se cho chon branch va doc scheduling policy/closure windows ngay trong staff-web."
              />
            </div>
          ) : (
            <div className="mt-5 space-y-3">
              {(branches?.data ?? []).map((branch) => (
                <button
                  key={branch.branch_id}
                  type="button"
                  onClick={() => setSelectedBranchId(branch.branch_id)}
                  className={`w-full rounded-[24px] border p-4 text-left transition ${
                    selectedBranchId === branch.branch_id
                      ? 'border-amber-300 bg-amber-50'
                      : 'border-slate-200 bg-white hover:bg-slate-50'
                  }`}
                >
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-slate-900">{branch.branch_name}</p>
                      <p className="mt-1 text-xs text-slate-500">{branch.branch_code} | {branch.timezone ?? 'Chua cai dat mui gio'}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      {branch.is_default ? <StatusPill value="Mac dinh" tone="info" /> : null}
                      <StatusPill value={branch.is_active ? 'Dang hoat dong' : 'Tam dung'} tone={branch.is_active ? 'success' : 'danger'} />
                    </div>
                  </div>
                </button>
              ))}
            </div>
          )}
        </Panel>

        <Panel>
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Branch detail</p>
              <h3 className="text-xl font-semibold text-slate-950">Operational context</h3>
            </div>
            <Settings2 className="h-5 w-5 text-slate-400" />
          </div>

          {!selectedBranch ? (
            <div className="mt-5">
              <EmptyState
                title="Chua chon branch"
                description="Chon mot branch ben trai de xem business hours, closure windows, va booking policy."
              />
            </div>
          ) : (
            <div className="mt-5 space-y-5">
              <div className="rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="font-semibold text-slate-900">{selectedBranch.branch_name}</p>
                    <p className="mt-1 text-sm text-slate-600">{selectedBranch.branch_code} | {selectedBranch.description ?? 'Chua co mo ta branch'}</p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {selectedBranch.is_default ? <StatusPill value="Branch mac dinh" tone="info" /> : null}
                    <StatusPill value={selectedBranch.is_active ? 'Dang hoat dong' : 'Tam dung'} tone={selectedBranch.is_active ? 'success' : 'danger'} />
                  </div>
                </div>
                <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                  <MetricCard label="Timezone" value={selectedBranch.timezone ?? 'N/A'} />
                  <MetricCard label="Currency" value={selectedBranch.currency ?? 'N/A'} />
                  <MetricCard label="Row version" value={String(selectedBranch.row_version ?? 'N/A')} />
                  <MetricCard label="Updated" value={formatDateTime(selectedBranch.updated_at ?? selectedBranch.created_at ?? null, selectedBranch.timezone ?? undefined)} />
                </div>
              </div>

              <div className="grid gap-4 xl:grid-cols-2">
                <div className="rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="eyebrow">Business hours</p>
                      <h4 className="text-lg font-semibold text-slate-950">Opening windows</h4>
                    </div>
                    <Clock3 className="h-5 w-5 text-slate-400" />
                  </div>
                  {selectedBranch.business_hours.length === 0 ? (
                    <div className="mt-4">
                      <EmptyState
                        title="Chua khai bao business hours"
                        description="Branch nay chua tra ve opening periods."
                      />
                    </div>
                  ) : (
                    <div className="mt-4 space-y-3">
                      {selectedBranch.business_hours.map((businessHour) => (
                        <div key={businessHour.day_of_week} className="rounded-[20px] border border-slate-200 bg-white px-4 py-3">
                          <p className="font-semibold text-slate-900">{dayLabel(businessHour.day_of_week)}</p>
                          <p className="mt-1 text-sm text-slate-600">{formatPeriods(businessHour.periods)}</p>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                <div className="rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="eyebrow">Closure windows</p>
                      <h4 className="text-lg font-semibold text-slate-950">Temporary closures</h4>
                    </div>
                    <CalendarX2 className="h-5 w-5 text-slate-400" />
                  </div>
                  {selectedBranch.closure_windows.length === 0 ? (
                    <div className="mt-4">
                      <EmptyState
                        title="Khong co closure window"
                        description="Branch nay hien khong co closure override."
                      />
                    </div>
                  ) : (
                    <div className="mt-4 space-y-3">
                      {selectedBranch.closure_windows.map((closureWindow, index) => (
                        <div key={`${closureWindow.start_local}-${index}`} className="rounded-[20px] border border-slate-200 bg-white px-4 py-3">
                          <p className="font-semibold text-slate-900">{closureWindow.reason}</p>
                          <p className="mt-1 text-sm text-slate-600">
                            {formatDateTime(closureWindow.start_local, selectedBranch.timezone ?? undefined)} {'->'} {formatDateTime(closureWindow.end_local, selectedBranch.timezone ?? undefined)}
                          </p>
                          <p className="mt-1 text-xs text-slate-500">{closureWindow.type}</p>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>

              <div className="rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="eyebrow">Booking policy</p>
                    <h4 className="text-lg font-semibold text-slate-950">Reservation and waiting-list defaults</h4>
                  </div>
                  <Settings2 className="h-5 w-5 text-slate-400" />
                </div>
                <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                  <MetricCard label="Reservation lead" value={reservationLeadLabel(selectedBranch)} />
                  <MetricCard label="Same-day cutoff" value={sameDayCutoffLabel(selectedBranch)} />
                  <MetricCard label="Waiting list" value={waitingListLabel(selectedBranch)} />
                  <MetricCard label="Policy keys" value={String(Object.keys(asRecord(selectedBranch.booking_policy) ?? {}).length)} />
                </div>
              </div>
            </div>
          )}
        </Panel>
      </div>
    </div>
  );
}

function pickBranchId(
  branches: Array<BranchEntry>,
  currentSelectedBranchId: number | null,
  preferredBranchId: number | null,
): number | null {
  if (preferredBranchId && branches.some((branch) => branch.branch_id === preferredBranchId)) {
    return preferredBranchId;
  }

  if (currentSelectedBranchId && branches.some((branch) => branch.branch_id === currentSelectedBranchId)) {
    return currentSelectedBranchId;
  }

  return branches[0]?.branch_id ?? null;
}

function dayLabel(dayOfWeek: number): string {
  const labels = ['Chu nhat', 'Thu hai', 'Thu ba', 'Thu tu', 'Thu nam', 'Thu sau', 'Thu bay'];
  return labels[dayOfWeek] ?? `Ngay ${dayOfWeek}`;
}

function formatPeriods(periods: Array<{ start_time: string; end_time: string }>): string {
  if (periods.length === 0) {
    return 'Dong cua';
  }

  return periods.map((period) => `${period.start_time} - ${period.end_time}`).join(', ');
}

function reservationLeadLabel(branch: BranchEntry): string {
  const value = readNumber(branch.booking_policy.reservation, 'min_lead_time_minutes');
  return value === null ? 'N/A' : `${value} phut`;
}

function sameDayCutoffLabel(branch: BranchEntry): string {
  return readString(branch.booking_policy.reservation, 'same_day_cutoff_time') ?? 'N/A';
}

function waitingListLabel(branch: BranchEntry): string {
  const waitingListPolicy = asRecord(branch.booking_policy.waiting_list);
  const enabled = readBoolean(waitingListPolicy, 'enabled');

  if (enabled === null) {
    return 'N/A';
  }

  return enabled ? 'Bat' : 'Tat';
}
