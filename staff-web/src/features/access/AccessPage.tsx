import { useStaffSession } from '../../app/session-context';
import { EmptyState, Panel, StatusPill } from '../../components/ui';

export function AccessPage() {
  const { session } = useStaffSession();

  if (!session) {
    return null;
  }

  const readiness = session.startup.readiness;
  const defaultBranch = session.startup.default_branch;
  const activeShift = session.startup.active_cashier_shift;
  const moneyPathsBlocked = readiness.requires_cashier_shift && readiness.cashier_shift === 'action_required';
  const blockers = [
    readiness.access !== 'ready'
      ? 'Session nay khong co granted capability de vao staff-web operator shell.'
      : null,
    readiness.branch !== 'ready'
      ? 'Backend chua resolve duoc default branch cho startup contract.'
      : null,
    moneyPathsBlocked
      ? 'Mo cashier shift hien hanh de mo settlement, refund, va cashier flows.'
      : null,
  ].filter((value): value is string => value !== null);
  const title = readiness.access !== 'ready'
    ? 'Session nay chua du granted capability cho staff-web'
    : moneyPathsBlocked
      ? 'Session startup da san sang cho shell nhung finance flows dang khoa'
    : readiness.operator_ready
      ? 'Session startup da san sang cho operator shell'
      : 'Session da xac thuc nhung chua du context van hanh';
  const description = readiness.access !== 'ready'
    ? 'Route tree va shell chi mo khi backend cap capability thuc te cho staff-web. `known_capabilities` chi con la metadata tham chieu contract.'
    : moneyPathsBlocked
      ? 'Board, orders, va inbox van mo theo capability neu co. Settlement, refund, va cashier se chi mo khi startup contract resolve duoc active cashier shift.'
    : 'Staff-web doc startup contract tu login/me/refresh de biet branch mac dinh, cashier shift hien hanh, va readiness can duoc giai quyet truoc khi vao shell.';

  return (
    <div className="space-y-6">
      <Panel>
        <p className="eyebrow">Access boundary</p>
        <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{title}</h2>
        <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">{description}</p>
        <div className="mt-4 flex flex-wrap gap-2">
          <StatusPill value={`Granted ${readiness.granted_capability_count}`} tone={readiness.access === 'ready' ? 'success' : 'warning'} />
          <StatusPill value={`Known ${readiness.known_capability_count}`} />
          <StatusPill value={`Access ${readiness.access}`} tone={readiness.access === 'ready' ? 'success' : 'warning'} />
          <StatusPill value={`Branch ${readiness.branch}`} tone={readiness.branch === 'ready' ? 'success' : 'warning'} />
          <StatusPill
            value={`Shift ${readiness.cashier_shift}`}
            tone={readiness.cashier_shift === 'ready' ? 'success' : readiness.cashier_shift === 'action_required' ? 'warning' : 'neutral'}
          />
          <StatusPill value={readiness.operator_ready ? 'Operator ready' : 'Setup required'} tone={readiness.operator_ready ? 'success' : 'warning'} />
        </div>
      </Panel>

      <Panel>
        <p className="eyebrow">Resolved context</p>
        <div className="mt-4 grid gap-4 lg:grid-cols-2">
          <div className="rounded-[24px] border border-slate-200 bg-slate-50 px-4 py-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Default branch</p>
            <p className="mt-3 text-base font-semibold text-slate-950">
              {defaultBranch ? `${defaultBranch.branch_name} (${defaultBranch.branch_code})` : 'Chua co branch mac dinh'}
            </p>
            <p className="mt-2 text-sm text-slate-600">
              {defaultBranch
                ? `Timezone ${defaultBranch.timezone ?? 'n/a'} · Currency ${defaultBranch.currency ?? 'n/a'}`
                : 'Can bootstrap branch context tu backend de shell co diem vao van hanh ro rang.'}
            </p>
          </div>
          <div className="rounded-[24px] border border-slate-200 bg-slate-50 px-4 py-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Cashier shift</p>
            <p className="mt-3 text-base font-semibold text-slate-950">
              {activeShift ? `${activeShift.shift_code} (${activeShift.status})` : 'Chua co active cashier shift'}
            </p>
            <p className="mt-2 text-sm text-slate-600">
              {activeShift
                ? `Terminal ${activeShift.terminal_code ?? 'n/a'} · Branch ${activeShift.branch?.branch_code ?? activeShift.branch_id}`
                : readiness.cashier_shift === 'not_applicable'
                  ? 'Session nay khong bat buoc active cashier shift cho startup.'
                  : 'Backend dang bao action_required cho cashier shift o startup.'}
            </p>
          </div>
        </div>
      </Panel>

      <EmptyState
        title={blockers.length > 0 ? 'Startup blockers can giai quyet truoc khi vao shell' : 'Access page dang hoat dong nhu route fallback'}
        description={
          blockers.length > 0
            ? blockers.join(' ')
            : 'Session nay da co du startup readiness. Neu van dang o access page, refresh session hoac mo lai route staff mong muon.'
        }
      />
    </div>
  );
}
