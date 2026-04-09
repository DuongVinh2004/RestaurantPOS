import { useCallback, useEffect, useMemo, useState } from 'react';
import { Ban, RotateCcw, Search } from 'lucide-react';
import {
  boardWindow,
  isUnauthorized,
  loadRefundPreview,
  loadStaffReservations,
  loadTableBoard,
  refundAndCancelReservation,
  refundReservation,
  type StaffBoardWindow,
} from '../../api/client';
import { useStaffSession } from '../../app/session-context';
import { hasCapability } from '../../lib/capabilities';
import { isRowVersionConflict, rowVersionConflictMessage } from '../../lib/conflicts';
import { normalizeApiError } from '../../lib/api-errors';
import { formatFinanceOperatorError } from '../../lib/financeErrors';
import { formatMoney, humanizeCode, readNumber, readString } from '../../lib/format';
import type { StaffRefundPreviewEnvelope, StaffReservationLookupCollectionEnvelope, StaffTableBoardEnvelope } from '../../api/sdk';
import { ActionButton, Banner, EmptyState, MetricCard, Panel, StatusPill } from '../../components/ui';

const refundScopes = ['deposit', 'final', 'all'] as const;
const paymentOptions = ['Cash', 'Card', 'BankTransfer', 'Other'] as const;
type RefundPreviewFormState = {
  reservationId: string;
  refundScope: (typeof refundScopes)[number];
  refundAmount: string;
  cancelAfterPayment: boolean;
};

export function RefundsPage() {
  const { expire, session } = useStaffSession();
  const [window] = useState<StaffBoardWindow>(() => boardWindow());
  const [board, setBoard] = useState<StaffTableBoardEnvelope | null>(null);
  const [reservationLookup, setReservationLookup] = useState<StaffReservationLookupCollectionEnvelope | null>(null);
  const [reservationIdInput, setReservationIdInput] = useState('');
  const [refundScope, setRefundScope] = useState<(typeof refundScopes)[number]>('all');
  const [refundAmount, setRefundAmount] = useState('');
  const [cancelAfterPayment, setCancelAfterPayment] = useState(true);
  const [preview, setPreview] = useState<StaffRefundPreviewEnvelope | null>(null);
  const [previewFormState, setPreviewFormState] = useState<RefundPreviewFormState | null>(null);
  const [paymentMethod, setPaymentMethod] = useState<(typeof paymentOptions)[number]>('Cash');
  const [paymentProvider, setPaymentProvider] = useState<(typeof paymentOptions)[number]>('Cash');
  const [transactionCode, setTransactionCode] = useState('');
  const [reason, setReason] = useState('customer_request');
  const [notes, setNotes] = useState('');
  const [cancelReason, setCancelReason] = useState('customer_request');
  const [reservationLookupQuery, setReservationLookupQuery] = useState('');
  const [lookupNotice, setLookupNotice] = useState<string | null>(null);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const canViewBoard = hasCapability(session, 'table.board.view');
  const canLookupReservations = hasCapability(session, 'reservation.manage');
  const reservationSuggestions = useMemo(() => {
    const deduped = new Map<number, { reservation_id: number; reservation_code: string; row_version: number; table_code: string; guest_name: string | null }>();

    for (const reservation of reservationLookup?.data ?? []) {
      deduped.set(reservation.reservation_id, {
        reservation_id: reservation.reservation_id,
        reservation_code: reservation.reservation_code,
        row_version: reservation.row_version,
        table_code: reservation.tables[0]?.table_code ?? 'N/A',
        guest_name: reservation.user?.full_name ?? reservation.user?.phone ?? null,
      });
    }

    for (const row of board?.data ?? []) {
      if (!row.reservation) {
        continue;
      }

      deduped.set(row.reservation.reservation_id, {
        reservation_id: row.reservation.reservation_id,
        reservation_code: row.reservation.reservation_code,
        row_version: row.reservation.row_version,
        table_code: row.table_code,
        guest_name: readString(row.reservation.user, 'full_name'),
      });
    }

    return Array.from(deduped.values());
  }, [board, reservationLookup]);
  const selectedReservationSuggestion = useMemo(
    () => reservationSuggestions.find((reservation) => String(reservation.reservation_id) === reservationIdInput.trim()) ?? null,
    [reservationIdInput, reservationSuggestions],
  );
  const currentPreviewFormState: RefundPreviewFormState = {
    reservationId: reservationIdInput.trim(),
    refundScope,
    refundAmount: refundAmount.trim(),
    cancelAfterPayment,
  };
  const previewRefundAmount = readNumber(preview?.data.refund, 'refund_amount');
  const previewPaymentSummary = preview?.data.refund.payment_summary as {
    refunded_total?: string | number | null;
    net_paid_total?: string | number | null;
    deposit_net?: string | number | null;
    final_net?: string | number | null;
  } | undefined;
  const previewMatchesCurrentInputs = previewFormState !== null
    && previewFormState.reservationId === currentPreviewFormState.reservationId
    && previewFormState.refundScope === currentPreviewFormState.refundScope
    && previewFormState.refundAmount === currentPreviewFormState.refundAmount
    && previewFormState.cancelAfterPayment === currentPreviewFormState.cancelAfterPayment;
  const refundMutationGuardReason = !preview
    ? 'Can refresh refund preview truoc khi thuc hien refund.'
    : !previewMatchesCurrentInputs
      ? 'Preview hien tai khong con khop voi reservation/scope/amount/cancel flag dang hien tren form. Refresh preview roi moi mutate.'
      : previewRefundAmount === null
        ? 'Preview hien tai khong tra ve refund amount hop le.'
        : previewRefundAmount <= 0
          ? 'Preview hien tai cho thay reservation khong con so tien nao duoc refund.'
          : null;
  const canExecuteRefundMutation = refundMutationGuardReason === null;

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

  const refreshBoard = useCallback(async () => {
    if (!canViewBoard) {
      setBoard(null);
      return;
    }

    setBusyKey('refresh-board');

    try {
      const nextBoard = await loadTableBoard(window);
      const firstReservationId = nextBoard.data.find((row) => row.reservation)?.reservation?.reservation_id;

      setBoard(nextBoard);
      setReservationIdInput((current) => {
        if (current.trim() !== '' && nextBoard.data.some((row) => String(row.reservation?.reservation_id ?? '') === current.trim())) {
          return current;
        }

        return firstReservationId ? String(firstReservationId) : current;
      });
    } catch (cause) {
      handleError(cause, 'Khong tai duoc reservation sources tu board.');
    } finally {
      setBusyKey(null);
    }
  }, [canViewBoard, handleError, window]);

  const loadReservationLookup = useCallback(async (query: string) => {
    if (!canLookupReservations) {
      setReservationLookup(null);
      setLookupNotice('Session nay khong co reservation.manage. Refund lookup se dung manual ID hoac board quick-picks neu co.');
      return;
    }

    setBusyKey('refresh-reservations');

    try {
      const nextLookup = await loadStaffReservations({
        bucket: 'all',
        q: query.trim() || undefined,
        per_page: 8,
        sort: '-start_time',
        include_financials: false,
      });

      setReservationLookup(nextLookup);
      setLookupNotice(null);
      setReservationIdInput((current) => {
        if (current.trim() !== '' && nextLookup.data.some((reservation) => String(reservation.reservation_id) === current.trim())) {
          return current;
        }

        return nextLookup.data[0]?.reservation_id ? String(nextLookup.data[0].reservation_id) : current;
      });
    } catch (cause) {
      const normalized = normalizeApiError(cause, 'Khong tai duoc reservation lookup.');
      if (normalized.status === 403) {
        setReservationLookup(null);
        setLookupNotice(
          normalized.requiredCapability
            ? `Reservation lookup bi chan boi capability ${normalized.requiredCapability}. Manual ID van kha dung.`
            : 'Reservation lookup bi tu choi. Manual ID van kha dung.',
        );
        return;
      }

      handleError(cause, 'Khong tai duoc reservation lookup.');
    } finally {
      setBusyKey(null);
    }
  }, [canLookupReservations, handleError]);

  const refreshReservationLookup = useCallback(async () => {
    await loadReservationLookup(reservationLookupQuery);
  }, [loadReservationLookup, reservationLookupQuery]);

  useEffect(() => {
    void Promise.all([refreshBoard(), loadReservationLookup('')]);
  }, [loadReservationLookup, refreshBoard, session?.staff_api_key_id]);

  return (
    <div className="space-y-6">
      <Panel>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p className="eyebrow">Refund flows</p>
            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Preview truoc, refund hoac refund-cancel sau</h2>
            <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
              Page nay bind vao `GET /staff/reservations/{'{reservation_id}'}/refund-preview`, `POST /refund` va `POST /refund-cancel`. Row version duoc lay tu preview envelope,
              khong hardcode tu state frontend. Reservation selector nay uu tien `GET /staff/reservations` lam canonical lookup source, giu board quick-picks va manual ID lam fallback.
            </p>
          </div>
          <ActionButton
            onClick={async () => {
              await Promise.all([refreshBoard(), refreshReservationLookup()]);
            }}
            busy={busyKey === 'refresh-board' || busyKey === 'refresh-reservations'}
            icon={<Search className="h-4 w-4" />}
          >
            Refresh reservation sources
          </ActionButton>
        </div>
        <div className="mt-4 flex flex-wrap gap-2">
          <StatusPill
            value={preview ? `Preview ${humanizeCode(preview.data.refund.refund_scope)}` : 'Chua preview'}
            tone={canExecuteRefundMutation ? 'success' : 'warning'}
          />
          {preview ? <StatusPill value={`Reservation rv ${preview.data.reservation.row_version}`} tone="info" /> : null}
        </div>
      </Panel>

      {notice ? <Banner tone="success">{notice}</Banner> : null}
      {error ? <Banner tone="error">{error}</Banner> : null}

      <div className="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <Panel>
          <p className="eyebrow">Reservation selector</p>
          <h3 className="text-xl font-semibold text-slate-950">Canonical lookup truoc, fallback sau</h3>
          <div className="mt-4 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-sm font-semibold text-slate-900">Reservation lookup</p>
                <p className="mt-1 text-sm text-slate-600">
                  Search theo reservation code, guest, phone hoac table tu canonical backend truth. Board quick-picks chi bo sung cho case dang o floor.
                </p>
              </div>
              <StatusPill
                value={canLookupReservations ? 'Lookup enabled' : canViewBoard ? 'Board fallback' : 'Manual fallback'}
                tone={canLookupReservations ? 'success' : 'warning'}
              />
            </div>

            {canLookupReservations ? (
              <div className="mt-4 grid gap-3 md:grid-cols-[1fr_auto]">
                <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                  <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Reservation search</span>
                  <input
                    value={reservationLookupQuery}
                    onChange={(event) => setReservationLookupQuery(event.target.value)}
                    className="mt-3 w-full bg-transparent text-sm outline-none"
                    placeholder="Reservation code, guest, phone, table..."
                  />
                </label>
                <div className="flex items-end">
                  <ActionButton onClick={refreshReservationLookup} busy={busyKey === 'refresh-reservations'} icon={<Search className="h-4 w-4" />}>
                    Tim reservation
                  </ActionButton>
                </div>
              </div>
            ) : null}

            {lookupNotice ? (
              <div className="mt-4">
                <EmptyState title="Reservation lookup khong kha dung" description={lookupNotice} />
              </div>
            ) : null}

            {reservationSuggestions.length > 0 ? (
              <div className="mt-4 space-y-3">
                {reservationSuggestions.map((reservation) => (
                  <button
                    key={reservation.reservation_id}
                    type="button"
                    onClick={() => {
                      setReservationIdInput(String(reservation.reservation_id));
                      void handlePreview(reservation.reservation_id);
                    }}
                    className={`w-full rounded-[24px] border p-4 text-left transition ${
                      String(reservation.reservation_id) === reservationIdInput.trim()
                        ? 'border-amber-300 bg-amber-50'
                        : 'border-slate-200 bg-white hover:bg-slate-50'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="font-semibold text-slate-900">{reservation.reservation_code}</p>
                        <p className="mt-1 text-xs text-slate-500">
                          Table {reservation.table_code} | {reservation.guest_name ?? 'Unknown guest'}
                        </p>
                      </div>
                      <StatusPill value={`rv ${reservation.row_version}`} tone="info" />
                    </div>
                  </button>
                ))}
              </div>
            ) : (
              <div className="mt-4">
                <EmptyState
                  title="Chua co reservation source"
                  description="Neu canonical lookup khong kha dung thi board quick-picks va manual reservation ID van duoc giu lam fallback."
                />
              </div>
            )}
          </div>

          {selectedReservationSuggestion ? (
            <div className="mt-5 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
              <div className="grid gap-3 md:grid-cols-3">
                <MetricCard label="Reservation" value={selectedReservationSuggestion.reservation_code} />
                <MetricCard label="Table" value={selectedReservationSuggestion.table_code} />
                <MetricCard label="Row version" value={String(selectedReservationSuggestion.row_version)} />
              </div>
            </div>
          ) : null}

          <div className="mt-5 rounded-[24px] bg-slate-50 p-5">
            <div className="grid gap-3 md:grid-cols-2">
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Reservation ID</span>
                <input
                  value={reservationIdInput}
                  onChange={(event) => setReservationIdInput(event.target.value)}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                  inputMode="numeric"
                />
              </label>
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Refund scope</span>
                <select
                  value={refundScope}
                  onChange={(event) => setRefundScope(event.target.value as (typeof refundScopes)[number])}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                >
                  {refundScopes.map((scope) => (
                    <option key={scope} value={scope}>
                      {scope}
                    </option>
                  ))}
                </select>
              </label>
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Refund amount</span>
                <input
                  value={refundAmount}
                  onChange={(event) => setRefundAmount(event.target.value)}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                  inputMode="decimal"
                  placeholder="Bo trong de backend tinh"
                />
              </label>
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Cancel after payment</span>
                <select
                  value={cancelAfterPayment ? 'yes' : 'no'}
                  onChange={(event) => setCancelAfterPayment(event.target.value === 'yes')}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                >
                  <option value="yes">Yes</option>
                  <option value="no">No</option>
                </select>
              </label>
            </div>
            <div className="mt-4">
              <ActionButton onClick={() => handlePreview()} busy={busyKey === 'preview'} icon={<Search className="h-4 w-4" />}>
                Refund preview
              </ActionButton>
            </div>
          </div>
        </Panel>

        <Panel>
          <p className="eyebrow">Refund execution</p>
          <h3 className="text-xl font-semibold text-slate-950">Mutations deu dung row_version tu preview</h3>

          {!preview ? (
            <div className="mt-4">
              <EmptyState
                title="Chua co refund preview"
                description="Tai preview truoc khi goi refund-only hoac refund-cancel."
              />
            </div>
          ) : (
            <>
              <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <MetricCard label="Reservation" value={preview.data.reservation.reservation_code} />
                <MetricCard label="Row version" value={String(preview.data.reservation.row_version)} />
                <MetricCard label="Scope" value={preview.data.refund.refund_scope} />
                <MetricCard label="Amount" value={formatMoney(preview.data.refund.refund_amount, preview.data.refund.currency)} />
                <MetricCard label="Status" value={humanizeCode(preview.data.refund.reservation_status)} />
                <MetricCard label="Cancelled" value={preview.data.refund.cancelled ? 'Yes' : 'No'} />
                <MetricCard label="Refunded total" value={formatMoney(previewPaymentSummary?.refunded_total, preview.data.refund.currency)} />
                <MetricCard label="Net paid total" value={formatMoney(previewPaymentSummary?.net_paid_total, preview.data.refund.currency)} />
                <MetricCard label="Deposit net" value={formatMoney(previewPaymentSummary?.deposit_net, preview.data.refund.currency)} />
                <MetricCard label="Final net" value={formatMoney(previewPaymentSummary?.final_net, preview.data.refund.currency)} />
              </div>

              <div className="mt-4 rounded-[20px] border border-slate-200 bg-white p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-slate-900">Mutation guard</p>
                    <p className="mt-1 text-sm text-slate-600">
                      Refund mutations se execute dung payload da duoc preview. Neu doi `scope`, `amount`, hoac `cancel-after-payment`, page se buoc preview lai truoc khi mutate.
                    </p>
                  </div>
                  <StatusPill value={canExecuteRefundMutation ? 'Preview ready' : 'Preview stale'} tone={canExecuteRefundMutation ? 'success' : 'warning'} />
                </div>
                <div className="mt-4 grid gap-3 md:grid-cols-3">
                  <MetricCard label="Row version source" value={`Preview rv ${preview.data.reservation.row_version}`} />
                  <MetricCard label="Refundability" value={previewRefundAmount !== null ? formatMoney(previewRefundAmount, preview.data.refund.currency) : 'N/A'} />
                  <MetricCard label="Next action" value={canExecuteRefundMutation ? 'Co the execute refund' : 'Refresh preview'} />
                </div>
                {refundMutationGuardReason ? (
                  <div className="mt-4 rounded-[18px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800">
                    {refundMutationGuardReason}
                  </div>
                ) : null}
              </div>

              <div className="mt-5 rounded-[24px] bg-slate-50 p-5">
                <div className="grid gap-3 md:grid-cols-2">
                  <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                    <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Payment method</span>
                    <select
                      value={paymentMethod}
                      onChange={(event) => {
                        const value = event.target.value as (typeof paymentOptions)[number];
                        setPaymentMethod(value);
                        setPaymentProvider(value);
                      }}
                      className="mt-3 w-full bg-transparent text-sm outline-none"
                    >
                      {paymentOptions.map((option) => (
                        <option key={option} value={option}>
                          {option}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                    <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Payment provider</span>
                    <select
                      value={paymentProvider}
                      onChange={(event) => setPaymentProvider(event.target.value as (typeof paymentOptions)[number])}
                      className="mt-3 w-full bg-transparent text-sm outline-none"
                    >
                      {paymentOptions.map((option) => (
                        <option key={option} value={option}>
                          {option}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                    <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Transaction code</span>
                    <input
                      value={transactionCode}
                      onChange={(event) => setTransactionCode(event.target.value)}
                      className="mt-3 w-full bg-transparent text-sm outline-none"
                    />
                  </label>
                  <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                    <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Reason</span>
                    <input
                      value={reason}
                      onChange={(event) => setReason(event.target.value)}
                      className="mt-3 w-full bg-transparent text-sm outline-none"
                    />
                  </label>
                </div>
                <label className="mt-3 block rounded-2xl border border-slate-200 bg-white px-4 py-3">
                  <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Notes</span>
                  <textarea
                    value={notes}
                    onChange={(event) => setNotes(event.target.value)}
                    rows={3}
                    className="mt-3 w-full bg-transparent text-sm outline-none"
                  />
                </label>
                <label className="mt-3 block rounded-2xl border border-slate-200 bg-white px-4 py-3">
                  <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Cancel reason</span>
                  <input
                    value={cancelReason}
                    onChange={(event) => setCancelReason(event.target.value)}
                    className="mt-3 w-full bg-transparent text-sm outline-none"
                  />
                </label>
                <div className="mt-4 flex flex-wrap gap-3">
                  <ActionButton onClick={handleRefundOnly} busy={busyKey === 'refund'} disabled={!canExecuteRefundMutation} icon={<RotateCcw className="h-4 w-4" />}>
                    Refund only
                  </ActionButton>
                  <ActionButton onClick={handleRefundCancel} busy={busyKey === 'refund-cancel'} disabled={!canExecuteRefundMutation} icon={<Ban className="h-4 w-4" />}>
                    Refund + cancel
                  </ActionButton>
                </div>
              </div>
            </>
          )}
        </Panel>
      </div>
    </div>
  );

  async function handlePreview(explicitReservationId?: number) {
    const reservationId = explicitReservationId ?? Number(reservationIdInput);

    if (!reservationId) {
      setError('Can nhap reservation ID hop le de lay refund preview.');
      return;
    }

    setBusyKey('preview');
    setError(null);

    try {
      const nextPreview = await loadRefundPreview(reservationId, {
        refund_scope: refundScope,
        refund_amount: refundAmount.trim() ? Number(refundAmount) : undefined,
        cancel_after_payment: cancelAfterPayment,
      });
      const previewAmount = readNumber(nextPreview.data.refund, 'refund_amount');
      const normalizedScope = nextPreview.data.refund.refund_scope as (typeof refundScopes)[number];
      const normalizedAmount = previewAmount === null ? '' : String(previewAmount);

      setPreview(nextPreview);
      setPreviewFormState({
        reservationId: String(reservationId),
        refundScope: normalizedScope,
        refundAmount: normalizedAmount,
        cancelAfterPayment,
      });
      setReservationIdInput(String(reservationId));
      setRefundScope(normalizedScope);
      setRefundAmount(normalizedAmount);
      setNotice(`Da tai refund preview cho reservation #${reservationId}.`);
    } catch (cause) {
      handleError(cause, 'Khong tai duoc refund preview.');
    } finally {
      setBusyKey(null);
    }
  }

  async function handleRefundOnly() {
    const reservationId = preview?.data.reservation.reservation_id;
    const rowVersion = preview?.data.reservation.row_version;

    if (!reservationId || !rowVersion) {
      setError('Can co refund preview voi row_version hop le truoc khi refund.');
      return;
    }

    if (refundMutationGuardReason) {
      setError(refundMutationGuardReason);
      return;
    }

    setBusyKey('refund');
    setError(null);

    try {
      await refundReservation(reservationId, {
        payment_method: paymentMethod,
        payment_provider: paymentProvider,
        refund_scope: preview.data.refund.refund_scope as (typeof refundScopes)[number],
        refund_amount: previewRefundAmount ?? undefined,
        transaction_code: transactionCode.trim() || null,
        notes: notes.trim() || null,
        reason: reason.trim() || null,
        row_version: rowVersion,
      });

      setNotice(`Da thuc hien refund cho reservation #${reservationId}.`);
      await handlePreview(reservationId);
      await Promise.all([refreshBoard(), refreshReservationLookup()]);
    } catch (cause) {
      if (isRowVersionConflict(cause)) {
        setError(rowVersionConflictMessage(`Reservation #${reservationId}`));
      } else {
        handleError(cause, 'Khong the refund reservation.');
      }
    } finally {
      setBusyKey(null);
    }
  }

  async function handleRefundCancel() {
    const reservationId = preview?.data.reservation.reservation_id;
    const rowVersion = preview?.data.reservation.row_version;

    if (!reservationId || !rowVersion) {
      setError('Can co refund preview voi row_version hop le truoc khi refund-cancel.');
      return;
    }

    if (refundMutationGuardReason) {
      setError(refundMutationGuardReason);
      return;
    }

    setBusyKey('refund-cancel');
    setError(null);

    try {
      await refundAndCancelReservation(reservationId, {
        payment_method: paymentMethod,
        payment_provider: paymentProvider,
        refund_scope: preview.data.refund.refund_scope as (typeof refundScopes)[number],
        refund_amount: previewRefundAmount ?? undefined,
        transaction_code: transactionCode.trim() || null,
        notes: notes.trim() || null,
        reason: reason.trim() || null,
        cancel_reason: cancelReason.trim() || null,
        row_version: rowVersion,
      });

      setNotice(`Da refund va cancel reservation #${reservationId}.`);
      await handlePreview(reservationId);
      await Promise.all([refreshBoard(), refreshReservationLookup()]);
    } catch (cause) {
      if (isRowVersionConflict(cause)) {
        setError(rowVersionConflictMessage(`Reservation #${reservationId}`));
      } else {
        handleError(cause, 'Khong the refund-cancel reservation.');
      }
    } finally {
      setBusyKey(null);
    }
  }

}
