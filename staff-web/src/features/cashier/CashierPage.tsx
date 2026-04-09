import { useCallback, useEffect, useMemo, useState } from 'react';
import { Lock, Search, WalletCards } from 'lucide-react';
import {
  closeCashierShift,
  isMissingResource,
  isUnauthorized,
  loadCashierShifts,
  loadCashierShift,
  loadCurrentCashierShift,
  openCashierShift,
} from '../../api/client';
import { useStaffSession } from '../../app/session-context';
import { isRowVersionConflict, rowVersionConflictMessage } from '../../lib/conflicts';
import { formatFinanceOperatorError } from '../../lib/financeErrors';
import { formatDateTime, formatMoney, humanizeCode } from '../../lib/format';
import type { CashierShiftCollectionEnvelope, CashierShiftEnvelope } from '../../api/sdk';
import { ActionButton, Banner, EmptyState, MetricCard, Panel, StatusPill } from '../../components/ui';

export function CashierPage() {
  const { expire } = useStaffSession();
  const [currentShift, setCurrentShift] = useState<CashierShiftEnvelope | null>(null);
  const [selectedShift, setSelectedShift] = useState<CashierShiftEnvelope | null>(null);
  const [shiftHistory, setShiftHistory] = useState<CashierShiftCollectionEnvelope | null>(null);
  const [shiftIdInput, setShiftIdInput] = useState('');
  const [shiftLookupQuery, setShiftLookupQuery] = useState('');
  const [shiftStatusFilter, setShiftStatusFilter] = useState<'all' | 'Open' | 'Closed'>('all');
  const [openingFloatAmount, setOpeningFloatAmount] = useState('100000');
  const [openCurrency, setOpenCurrency] = useState('VND');
  const [branchId, setBranchId] = useState('');
  const [terminalCode, setTerminalCode] = useState('staff-web-main');
  const [openNotes, setOpenNotes] = useState('');
  const [actualCashAmount, setActualCashAmount] = useState('');
  const [closeNotes, setCloseNotes] = useState('');
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleError = useCallback(
    (cause: unknown, fallback: string) => {
      if (isUnauthorized(cause)) {
        expire('Phien staff da het han. Dang nhap lai de tiep tuc.');
        return;
      }

      setError(formatFinanceOperatorError(cause, fallback));
    },
    [expire],
  );

  const refreshCurrent = useCallback(async () => {
    setBusyKey('refresh-current');
    setError(null);

    try {
      const nextCurrent = await loadCurrentCashierShift();
      setCurrentShift(nextCurrent);
      setSelectedShift(nextCurrent);
      setShiftIdInput(String(nextCurrent.data.cashier_shift_id));
      setBranchId(String(nextCurrent.data.branch_id ?? ''));
      setTerminalCode((current) => nextCurrent.data.terminal_code ?? current);
      setActualCashAmount(String(nextCurrent.data.expected_cash_amount ?? nextCurrent.data.opening_float_amount ?? '0'));
    } catch (cause) {
      if (isMissingResource(cause)) {
        setCurrentShift(null);
        setSelectedShift(null);
        setNotice('Staff nay hien chua co open cashier shift.');
      } else {
        handleError(cause, 'Khong tai duoc current cashier shift.');
      }
    } finally {
      setBusyKey(null);
    }
  }, [handleError]);

  const loadHistory = useCallback(async (query: string, statusFilter: 'all' | 'Open' | 'Closed') => {
    setBusyKey('refresh-history');

    try {
      const nextHistory = await loadCashierShifts({
        q: query.trim() || undefined,
        status: statusFilter === 'all' ? undefined : statusFilter,
        per_page: 8,
        sort: '-opened_at',
      });

      setShiftHistory(nextHistory);
    } catch (cause) {
      handleError(cause, 'Khong tai duoc lich su cashier shifts.');
    } finally {
      setBusyKey(null);
    }
  }, [handleError]);

  const refreshHistory = useCallback(async () => {
    await loadHistory(shiftLookupQuery, shiftStatusFilter);
  }, [loadHistory, shiftLookupQuery, shiftStatusFilter]);

  useEffect(() => {
    void Promise.all([refreshCurrent(), loadHistory('', 'all')]);
  }, [loadHistory, refreshCurrent]);

  const shift = selectedShift ?? currentShift;
  const shiftPaymentSummary = shift?.data.summary?.payments;
  const shiftCashSummary = shift?.data.summary?.cash;
  const shiftMethodSummary = Array.isArray(shift?.data.summary?.methods) ? shift.data.summary.methods : [];
  const closePreview = useMemo(() => {
    const expected = Number(shift?.data.expected_cash_amount ?? shiftCashSummary?.expected_cash_amount ?? shift?.data.opening_float_amount ?? 0);
    const actual = actualCashAmount.trim() === '' ? null : Number(actualCashAmount);

    if (actual === null || Number.isNaN(actual)) {
      return {
        expected,
        actual: null,
        variance: null,
      };
    }

    return {
      expected,
      actual,
      variance: actual - expected,
    };
  }, [actualCashAmount, shift?.data.expected_cash_amount, shift?.data.opening_float_amount, shiftCashSummary?.expected_cash_amount]);
  const closePreviewLabel = shift?.data.status !== 'Open'
    ? 'Shift da dong'
    : closePreview.actual === null
      ? 'Nhap counted cash'
      : closePreview.variance === 0
        ? 'Khong lech'
        : closePreview.variance > 0
          ? 'Thua quy'
          : 'Thieu quy';

  return (
    <div className="space-y-6">
      <Panel>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p className="eyebrow">Cashier shift</p>
            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Current, open, show, close</h2>
            <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
              Page nay bind vao `GET /cashier/shifts`, `GET /cashier/shifts/current`, `POST /open`, `GET /{ '{shift_id}' }`, `POST /close`.
              Open chi can Idempotency-Key, close bat buoc row_version moi. Current shift va recent shift history deu duoc nap san vao lookup/close context de giam phu thuoc manual shift ID.
            </p>
          </div>
          <ActionButton
            onClick={async () => {
              await Promise.all([refreshCurrent(), refreshHistory()]);
            }}
            busy={busyKey === 'refresh-current' || busyKey === 'refresh-history'}
            icon={<WalletCards className="h-4 w-4" />}
          >
            Refresh current shift
          </ActionButton>
        </div>
      </Panel>

      {notice ? <Banner tone="success">{notice}</Banner> : null}
      {error ? <Banner tone="error">{error}</Banner> : null}

      <div className="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <Panel>
          <p className="eyebrow">Open shift</p>
          <h3 className="text-xl font-semibold text-slate-950">Cash drawer bootstrap</h3>
          <div className="mt-4 grid gap-3 md:grid-cols-2">
            <label className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Opening float</span>
              <input
                value={openingFloatAmount}
                onChange={(event) => setOpeningFloatAmount(event.target.value)}
                className="mt-3 w-full bg-transparent text-sm outline-none"
                inputMode="decimal"
              />
            </label>
            <label className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Currency</span>
              <input
                value={openCurrency}
                onChange={(event) => setOpenCurrency(event.target.value.toUpperCase())}
                className="mt-3 w-full bg-transparent text-sm outline-none"
              />
            </label>
            <label className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Branch ID</span>
              <input
                value={branchId}
                onChange={(event) => setBranchId(event.target.value)}
                className="mt-3 w-full bg-transparent text-sm outline-none"
                inputMode="numeric"
              />
            </label>
            <label className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Terminal code</span>
              <input
                value={terminalCode}
                onChange={(event) => setTerminalCode(event.target.value)}
                className="mt-3 w-full bg-transparent text-sm outline-none"
              />
            </label>
          </div>
          <label className="mt-3 block rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Open notes</span>
            <textarea
              value={openNotes}
              onChange={(event) => setOpenNotes(event.target.value)}
              rows={3}
              className="mt-3 w-full bg-transparent text-sm outline-none"
            />
          </label>
          <div className="mt-4">
            <ActionButton onClick={handleOpenShift} busy={busyKey === 'open-shift'} icon={<WalletCards className="h-4 w-4" />}>
              Open cashier shift
            </ActionButton>
          </div>
        </Panel>

        <Panel>
          <p className="eyebrow">Lookup + close</p>
          <h3 className="text-xl font-semibold text-slate-950">Shift detail va counted cash</h3>

          <div className="mt-4 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-sm font-semibold text-slate-900">Recent shift lookup</p>
                <p className="mt-1 text-sm text-slate-600">
                  `GET /staff/cashier/shifts` cung cap recent/history lookup theo shift code, terminal hoac status. Current shift van la fast path; manual shift ID chi con la fallback.
                </p>
              </div>
              <StatusPill value={`Recent ${shiftHistory?.meta?.total ?? shiftHistory?.data.length ?? 0}`} tone="info" />
            </div>

            <div className="mt-4 grid gap-3 md:grid-cols-[1fr_180px_auto]">
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Shift search</span>
                <input
                  value={shiftLookupQuery}
                  onChange={(event) => setShiftLookupQuery(event.target.value)}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                  placeholder="Shift code, terminal, branch..."
                />
              </label>
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Status</span>
                <select
                  value={shiftStatusFilter}
                  onChange={(event) => setShiftStatusFilter(event.target.value as 'all' | 'Open' | 'Closed')}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                >
                  <option value="all">All</option>
                  <option value="Open">Open</option>
                  <option value="Closed">Closed</option>
                </select>
              </label>
              <div className="flex items-end">
                <ActionButton onClick={refreshHistory} busy={busyKey === 'refresh-history'} icon={<Search className="h-4 w-4" />}>
                  Tim shifts
                </ActionButton>
              </div>
            </div>

            {shiftHistory?.data.length ? (
              <div className="mt-4 grid gap-3">
                {shiftHistory.data.map((shiftEntry) => (
                  <button
                    key={shiftEntry.cashier_shift_id}
                    type="button"
                    onClick={() => {
                      setSelectedShift({ data: shiftEntry });
                      setShiftIdInput(String(shiftEntry.cashier_shift_id));
                      setBranchId(String(shiftEntry.branch_id ?? ''));
                      setTerminalCode((current) => shiftEntry.terminal_code ?? current);
                      setActualCashAmount(String(shiftEntry.expected_cash_amount ?? shiftEntry.opening_float_amount ?? '0'));
                    }}
                    className={`rounded-[20px] border px-4 py-3 text-left transition ${
                      shift?.data.cashier_shift_id === shiftEntry.cashier_shift_id
                        ? 'border-amber-300 bg-amber-50'
                        : 'border-slate-200 bg-white hover:bg-slate-50'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="font-semibold text-slate-900">{shiftEntry.shift_code}</p>
                        <p className="mt-1 text-xs text-slate-500">
                          {shiftEntry.branch?.branch_code ?? shiftEntry.branch_id} | {shiftEntry.terminal_code ?? 'No terminal'}
                        </p>
                      </div>
                      <StatusPill value={humanizeCode(shiftEntry.status)} tone={shiftEntry.status === 'Open' ? 'success' : 'neutral'} />
                    </div>
                  </button>
                ))}
              </div>
            ) : (
              <div className="mt-4">
                <EmptyState
                  title="Chua co shift history source"
                  description="Current shift va manual shift ID van kha dung ngay ca khi recent history lookup chua tra ket qua."
                />
              </div>
            )}
          </div>

          <div className="mt-4 grid gap-3 md:grid-cols-[1fr_auto]">
            <label className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Shift ID</span>
              <input
                value={shiftIdInput}
                onChange={(event) => setShiftIdInput(event.target.value)}
                className="mt-3 w-full bg-transparent text-sm outline-none"
                inputMode="numeric"
              />
            </label>
            <div className="flex items-end">
              <ActionButton onClick={handleLookupShift} busy={busyKey === 'lookup-shift'} icon={<Search className="h-4 w-4" />}>
                Show shift
              </ActionButton>
            </div>
          </div>

          {!shift ? (
            <div className="mt-5">
              <EmptyState
                title="Chua co shift duoc nap"
                description="`GET /current` co the tra 404 neu staff chua mo ca. Ban van co the load shift cu bang ID."
              />
            </div>
          ) : (
            <>
              <div className="mt-5 flex flex-wrap gap-2">
                <StatusPill value={`Shift ${shift.data.shift_code}`} tone="info" />
                <StatusPill value={humanizeCode(shift.data.status)} tone={shift.data.status === 'Open' ? 'success' : 'neutral'} />
                <StatusPill value={`rv ${shift.data.row_version}`} />
                <StatusPill
                  value={currentShift?.data.cashier_shift_id === shift.data.cashier_shift_id ? 'Current shift' : 'History lookup'}
                  tone={currentShift?.data.cashier_shift_id === shift.data.cashier_shift_id ? 'success' : 'warning'}
                />
              </div>
              <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <MetricCard label="Opened at" value={formatDateTime(shift.data.opened_at)} />
                <MetricCard label="Closed at" value={formatDateTime(shift.data.closed_at)} />
                <MetricCard label="Branch" value={shift.data.branch?.branch_code ?? String(shift.data.branch_id ?? 'N/A')} />
                <MetricCard label="Terminal" value={shift.data.terminal_code ?? 'N/A'} />
                <MetricCard label="Opening float" value={formatMoney(shift.data.opening_float_amount, shift.data.currency)} />
                <MetricCard label="Captured total" value={formatMoney(shiftPaymentSummary?.captured_total, shift.data.currency)} />
                <MetricCard label="Refunded total" value={formatMoney(shiftPaymentSummary?.refunded_total, shift.data.currency)} />
                <MetricCard label="Expected cash" value={formatMoney(shiftCashSummary?.expected_cash_amount ?? shift.data.expected_cash_amount, shift.data.currency)} />
                <MetricCard label="Actual cash" value={formatMoney(shift.data.actual_cash_amount, shift.data.currency)} />
              </div>

              <div className="mt-4 rounded-[24px] border border-slate-200 bg-white p-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-slate-900">Close preview</p>
                    <p className="mt-1 text-sm text-slate-600">
                      Close shift luon dung `row_version` tu loaded shift detail. Preview ben duoi doi chieu expected cash, counted cash, va variance truoc khi dong ca.
                    </p>
                  </div>
                  <StatusPill
                    value={closePreviewLabel}
                    tone={shift.data.status === 'Open' && closePreview.actual !== null ? (closePreview.variance === 0 ? 'success' : 'warning') : 'neutral'}
                  />
                </div>
                <div className="mt-4 grid gap-3 md:grid-cols-3 xl:grid-cols-4">
                  <MetricCard label="Mutation rv source" value={`Shift rv ${shift.data.row_version}`} />
                  <MetricCard label="Cash captured" value={formatMoney(shiftCashSummary?.captured_amount, shift.data.currency)} />
                  <MetricCard label="Cash refunded" value={formatMoney(shiftCashSummary?.refunded_amount, shift.data.currency)} />
                  <MetricCard label="Expected cash" value={formatMoney(closePreview.expected, shift.data.currency)} />
                  <MetricCard label="Counted cash" value={closePreview.actual === null ? 'Nhap counted cash' : formatMoney(closePreview.actual, shift.data.currency)} />
                  <MetricCard label="Variance" value={closePreview.variance === null ? 'N/A' : formatMoney(closePreview.variance, shift.data.currency)} />
                  <MetricCard label="Payment count" value={String(shiftPaymentSummary?.payment_count ?? 0)} />
                  <MetricCard label="Refund count" value={String(shiftPaymentSummary?.refund_count ?? 0)} />
                </div>
                {shiftCashSummary?.has_excluded_cash_currencies ? (
                  <div className="mt-4 rounded-[18px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800">
                    Cash summary dang bo qua currency ngoai ca: {(shiftCashSummary.excluded_cash_currencies ?? []).join(', ')}.
                  </div>
                ) : null}
                {shiftMethodSummary.length > 0 ? (
                  <div className="mt-4 grid gap-3 md:grid-cols-2">
                    {shiftMethodSummary.map((method) => (
                      <div key={`${method.payment_method}-${method.currency}`} className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div className="flex items-start justify-between gap-3">
                          <div>
                            <p className="font-semibold text-slate-900">{method.payment_method}</p>
                            <p className="mt-1 text-xs text-slate-500">{method.currency}</p>
                          </div>
                          <StatusPill value={`refunds ${method.refund_count ?? 0}`} tone="warning" />
                        </div>
                        <div className="mt-3 grid gap-2 md:grid-cols-3">
                          <MetricCard label="Captured" value={formatMoney(method.captured_amount, method.currency)} />
                          <MetricCard label="Refunded" value={formatMoney(method.refunded_amount, method.currency)} />
                          <MetricCard label="Net" value={formatMoney(method.net_amount, method.currency)} />
                        </div>
                      </div>
                    ))}
                  </div>
                ) : null}
              </div>

              <div className="mt-5 rounded-[24px] bg-slate-50 p-5">
                <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3 block">
                  <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Actual cash amount</span>
                  <input
                    value={actualCashAmount}
                    onChange={(event) => setActualCashAmount(event.target.value)}
                    className="mt-3 w-full bg-transparent text-sm outline-none"
                    inputMode="decimal"
                  />
                </label>
                <label className="mt-3 block rounded-2xl border border-slate-200 bg-white px-4 py-3">
                  <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Close notes</span>
                  <textarea
                    value={closeNotes}
                    onChange={(event) => setCloseNotes(event.target.value)}
                    rows={3}
                    className="mt-3 w-full bg-transparent text-sm outline-none"
                  />
                </label>
                <div className="mt-4">
                  <ActionButton
                    onClick={handleCloseShift}
                    busy={busyKey === 'close-shift'}
                    disabled={shift.data.status !== 'Open'}
                    icon={<Lock className="h-4 w-4" />}
                  >
                    Close shift
                  </ActionButton>
                </div>
              </div>
            </>
          )}
        </Panel>
      </div>
    </div>
  );

  async function handleLookupShift() {
    const shiftId = Number(shiftIdInput);

    if (!shiftId) {
      setError('Can nhap shift ID hop le.');
      return;
    }

    setBusyKey('lookup-shift');
    setError(null);

    try {
      const nextShift = await loadCashierShift(shiftId);
      setSelectedShift(nextShift);
      setBranchId(String(nextShift.data.branch_id ?? ''));
      setTerminalCode((current) => nextShift.data.terminal_code ?? current);
      setActualCashAmount(String(nextShift.data.expected_cash_amount ?? nextShift.data.opening_float_amount ?? '0'));
      setNotice(`Da tai cashier shift #${shiftId}.`);
    } catch (cause) {
      handleError(cause, 'Khong tai duoc cashier shift.');
    } finally {
      setBusyKey(null);
    }
  }

  async function handleOpenShift() {
    setBusyKey('open-shift');
    setError(null);

    try {
      const nextShift = await openCashierShift({
        opening_float_amount: Number(openingFloatAmount) || 0,
        currency: openCurrency,
        branch_id: branchId.trim() ? Number(branchId) : undefined,
        terminal_code: terminalCode.trim() || null,
        notes: openNotes.trim() || null,
      });

      setCurrentShift(nextShift);
      setSelectedShift(nextShift);
      setShiftIdInput(String(nextShift.data.cashier_shift_id));
      setBranchId((current) => (nextShift.data.branch_id ? String(nextShift.data.branch_id) : current));
      setTerminalCode((current) => nextShift.data.terminal_code ?? current);
      setActualCashAmount(String(nextShift.data.expected_cash_amount ?? nextShift.data.opening_float_amount ?? '0'));
      setNotice(`Da mo cashier shift ${nextShift.data.shift_code}.`);
      await refreshHistory();
    } catch (cause) {
      handleError(cause, 'Khong the mo cashier shift.');
    } finally {
      setBusyKey(null);
    }
  }

  async function handleCloseShift() {
    const shiftId = shift?.data.cashier_shift_id;
    const rowVersion = shift?.data.row_version;

    if (!shiftId || !rowVersion) {
      setError('Can nap shift co row_version hop le truoc khi close.');
      return;
    }

    setBusyKey('close-shift');
    setError(null);

    try {
      const closed = await closeCashierShift(shiftId, {
        actual_cash_amount: Number(actualCashAmount) || 0,
        notes: closeNotes.trim() || null,
        row_version: rowVersion,
      });

      setCurrentShift(closed.data.status === 'Open' ? closed : null);
      setSelectedShift(closed);
      setNotice(`Da dong cashier shift ${closed.data.shift_code}. Variance ${formatMoney(closed.data.cash_discrepancy_amount, closed.data.currency)}.`);
      await refreshHistory();
    } catch (cause) {
      if (isRowVersionConflict(cause)) {
        setError(rowVersionConflictMessage(`Cashier shift #${shiftId}`));
      } else {
        handleError(cause, 'Khong the dong cashier shift.');
      }
    } finally {
      setBusyKey(null);
    }
  }

}
