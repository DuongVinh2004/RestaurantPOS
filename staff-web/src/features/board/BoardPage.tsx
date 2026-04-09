import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { BellRing, RefreshCcw, ShieldCheck, UsersRound, Waves } from 'lucide-react';
import {
  boardWindow,
  checkInReservation,
  isUnauthorized,
  loadTableBoard,
  loadTableBoardChanges,
  loadWaitingList,
  loadWaitingListChanges,
  notifyWaitingListEntry,
  seatWaitingListEntry,
  type StaffBoardWindow,
} from '../../api/client';
import { useStaffSession } from '../../app/session-context';
import { hasCapability } from '../../lib/capabilities';
import { isRowVersionConflict, rowVersionConflictMessage } from '../../lib/conflicts';
import { formatDateTime, formatMoney, humanizeCode, readString } from '../../lib/format';
import { formatApiError } from '../../lib/api-errors';
import { buildOperatorJourneySearch } from '../../lib/operatorJourney';
import type { StaffOperationalRealtimeEnvelope, StaffTableBoardEnvelope, StaffWaitingListCollectionEnvelope } from '../../api/sdk';
import { ActionButton, Banner, EmptyState, MetricCard, Panel, StatusPill } from '../../components/ui';
import { resolveOperationalPollDelay, shouldRefetchOperationalSlice } from './boardPolling';

type NextStepJourney = {
  actionLabel: string;
  description: string;
  search: string;
  title: string;
};

export function BoardPage({ enableBackgroundPolling = true }: { enableBackgroundPolling?: boolean }) {
  const { session, expire } = useStaffSession();
  const [window, setWindow] = useState<StaffBoardWindow>(() => boardWindow());
  const [board, setBoard] = useState<StaffTableBoardEnvelope | null>(null);
  const [waiting, setWaiting] = useState<StaffWaitingListCollectionEnvelope | null>(null);
  const [boardChanges, setBoardChanges] = useState<StaffOperationalRealtimeEnvelope | null>(null);
  const [waitingChanges, setWaitingChanges] = useState<StaffOperationalRealtimeEnvelope | null>(null);
  const [selectedTableId, setSelectedTableId] = useState<number | null>(null);
  const [selectedWaitingId, setSelectedWaitingId] = useState<number | null>(null);
  const [notifyTableId, setNotifyTableId] = useState<number | null>(null);
  const [holdMinutes, setHoldMinutes] = useState('10');
  const [loading, setLoading] = useState(true);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [nextStepJourney, setNextStepJourney] = useState<NextStepJourney | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isDocumentVisible, setIsDocumentVisible] = useState(
    () => typeof document === 'undefined' || document.visibilityState === 'visible',
  );
  const pollingInFlightRef = useRef(false);

  const canViewBoard = hasCapability(session, 'table.board.view');
  const canManageWaiting = hasCapability(session, 'waiting_list.manage');
  const canManageOrders = hasCapability(session, 'order.manage');
  const canManageSettlement = hasCapability(session, 'settlement.manage');

  const selectedTable = useMemo(
    () => board?.data.find((row) => row.table_id === selectedTableId) ?? null,
    [board, selectedTableId],
  );
  const selectedWaiting = useMemo(
    () => waiting?.data.find((row) => row.waiting_id === selectedWaitingId) ?? null,
    [selectedWaitingId, waiting],
  );
  const notifyTables = useMemo(
    () => board?.data.filter((row) => row.availability.accepts_new_assignment) ?? [],
    [board],
  );
  const ordersJourneySearch = useMemo(() => {
    if (!selectedTable) {
      return '';
    }

    return buildOperatorJourneySearch({
      source: 'board',
      tableId: selectedTable.table_id,
      reservationId: selectedTable.reservation?.reservation_id,
      reservationRowVersion: selectedTable.reservation?.row_version,
      orderId: selectedTable.active_order?.order_id,
    });
  }, [selectedTable]);
  const settlementJourneySearch = useMemo(() => {
    if (!selectedTable?.active_order?.order_id) {
      return '';
    }

    return buildOperatorJourneySearch({
      source: 'board',
      tableId: selectedTable.table_id,
      reservationId: selectedTable.reservation?.reservation_id,
      reservationRowVersion: selectedTable.reservation?.row_version,
      orderId: selectedTable.active_order.order_id,
    });
  }, [selectedTable]);

  const handlePageError = useCallback(
    (cause: unknown, fallback: string) => {
      if (isUnauthorized(cause)) {
        expire('Phien staff da het han. Dang nhap lai de tiep tuc.');
        return;
      }

      setError(formatApiError(cause, fallback));
    },
    [expire],
  );

  const refreshPage = useCallback(async (
    options: {
      board?: boolean;
      waiting?: boolean;
      silent?: boolean;
    } = {},
  ) => {
    const refreshBoardSlice = options.board ?? canViewBoard;
    const refreshWaitingSlice = options.waiting ?? canManageWaiting;
    const silent = options.silent ?? false;

    if (!silent) {
      setBusyKey('refresh');
      setError(null);
      setNotice(null);
    }

    try {
      const nextWindow = refreshBoardSlice ? boardWindow() : null;
      const [nextBoard, nextWaiting] = await Promise.all([
        refreshBoardSlice && canViewBoard && nextWindow ? loadTableBoard(nextWindow) : Promise.resolve(null),
        refreshWaitingSlice && canManageWaiting ? loadWaitingList() : Promise.resolve(null),
      ]);

      if (refreshBoardSlice && nextWindow) {
        setWindow(nextWindow);
        setBoard(nextBoard);
        setSelectedTableId((current) => (current && nextBoard?.data.some((row) => row.table_id === current) ? current : nextBoard?.data[0]?.table_id ?? null));
        setNotifyTableId((current) =>
          current && nextBoard?.data.some((row) => row.table_id === current)
            ? current
            : nextBoard?.data.find((row) => row.availability.accepts_new_assignment)?.table_id ?? null,
        );
      }

      if (refreshWaitingSlice) {
        setWaiting(nextWaiting);
        setSelectedWaitingId((current) =>
          current && nextWaiting?.data.some((row) => row.waiting_id === current) ? current : nextWaiting?.data[0]?.waiting_id ?? null,
        );
      }

      return {
        board: nextBoard,
        waiting: nextWaiting,
      };
    } catch (cause) {
      handlePageError(cause, 'Khong tai duoc board/waiting list.');
      return {
        board: null,
        waiting: null,
      };
    } finally {
      if (!silent) {
        setBusyKey(null);
        setLoading(false);
      }
    }
  }, [canManageWaiting, canViewBoard, handlePageError]);

  const pollDelayMs = useMemo(
    () => resolveOperationalPollDelay([boardChanges?.data.poll_hint_ms, waitingChanges?.data.poll_hint_ms], isDocumentVisible),
    [boardChanges?.data.poll_hint_ms, isDocumentVisible, waitingChanges?.data.poll_hint_ms],
  );

  const checkRealtimeChanges = useCallback(async (background = false) => {
    if (!background) {
      setBusyKey('changes');
      setError(null);
    }

    try {
      const [nextBoardChanges, nextWaitingChanges] = await Promise.all([
        canViewBoard ? loadTableBoardChanges(board?.meta.realtime.current_version) : Promise.resolve(null),
        canManageWaiting ? loadWaitingListChanges(waiting?.meta?.realtime.current_version) : Promise.resolve(null),
      ]);

      setBoardChanges(nextBoardChanges);
      setWaitingChanges(nextWaitingChanges);

      const shouldRefreshBoard = shouldRefetchOperationalSlice(nextBoardChanges);
      const shouldRefreshWaiting = shouldRefetchOperationalSlice(nextWaitingChanges);

      if (shouldRefreshBoard || shouldRefreshWaiting) {
        await refreshPage({
          board: shouldRefreshBoard,
          waiting: shouldRefreshWaiting,
          silent: true,
        });
      }

      if (!background) {
        setNotice(
          shouldRefreshBoard || shouldRefreshWaiting
            ? 'Da doi soat realtime cursors va lam moi du lieu co thay doi.'
            : 'Da doi soat realtime cursors cho board va waiting list.',
        );
      }
    } catch (cause) {
      if (!background) {
        handlePageError(cause, 'Khong doi soat duoc realtime changes.');
      }
    } finally {
      if (!background) {
        setBusyKey(null);
      }
    }
  }, [board?.meta.realtime.current_version, canManageWaiting, canViewBoard, handlePageError, refreshPage, waiting?.meta?.realtime.current_version]);

  useEffect(() => {
    void refreshPage();
  }, [refreshPage, session?.staff_api_key_id]);

  useEffect(() => {
    setNextStepJourney(null);
  }, [selectedTableId]);

  useEffect(() => {
    if (typeof document === 'undefined') {
      return;
    }

    const handleVisibilityChange = () => {
      setIsDocumentVisible(document.visibilityState === 'visible');
    };

    handleVisibilityChange();
    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, []);

  useEffect(() => {
    if (!enableBackgroundPolling) {
      return;
    }

    if (!canViewBoard && !canManageWaiting) {
      return;
    }

    const intervalId = globalThis.setInterval(() => {
      if (pollingInFlightRef.current || busyKey !== null) {
        return;
      }

      pollingInFlightRef.current = true;
      void checkRealtimeChanges(true).finally(() => {
        pollingInFlightRef.current = false;
      });
    }, pollDelayMs);

    return () => {
      globalThis.clearInterval(intervalId);
    };
  }, [busyKey, canManageWaiting, canViewBoard, checkRealtimeChanges, enableBackgroundPolling, pollDelayMs]);

  return (
    <div className="space-y-6">
      <Panel>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p className="eyebrow">Board + Waiting</p>
            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Realtime floor state cho host va FOH</h2>
            <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
              Route nay dung canonical board contract, waiting list collection, waiting notify/seat va check-in action metadata. Tat
              ca mutation deu di kem row_version va Idempotency-Key. Change cursors nay duoc poll nen chi lam moi full board/waiting khi backend
              bao co thay doi.
            </p>
          </div>
          <div className="flex flex-wrap gap-3">
            <ActionButton onClick={checkRealtimeChanges} busy={busyKey === 'changes'} icon={<Waves className="h-4 w-4" />}>
              Check changes
            </ActionButton>
            <ActionButton onClick={() => { void refreshPage(); }} busy={loading || busyKey === 'refresh'} icon={<RefreshCcw className="h-4 w-4" />}>
              Lam moi
            </ActionButton>
          </div>
        </div>

        <div className="mt-4 flex flex-wrap gap-2">
          <StatusPill value={`Board from ${formatDateTime(window.from)}`} />
          <StatusPill value={`Board to ${formatDateTime(window.to)}`} />
          {board?.meta.realtime ? (
            <StatusPill value={`Board v${board.meta.realtime.current_version}`} tone="info" />
          ) : null}
          {waiting?.meta?.realtime ? (
            <StatusPill value={`Waiting v${waiting.meta.realtime.current_version}`} tone="info" />
          ) : null}
        </div>
      </Panel>

      {notice ? <Banner tone="success">{notice}</Banner> : null}
      {error ? <Banner tone="error">{error}</Banner> : null}
      {nextStepJourney ? (
        <Panel>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Next step</p>
              <h3 className="text-xl font-semibold text-slate-950">{nextStepJourney.title}</h3>
              <p className="mt-2 text-sm leading-7 text-slate-600">{nextStepJourney.description}</p>
            </div>
            {canManageOrders ? (
              <Link
                to={`/orders?${nextStepJourney.search}`}
                className="inline-flex items-center justify-center rounded-[22px] bg-slate-950 px-4 py-3 text-sm font-semibold text-white"
              >
                {nextStepJourney.actionLabel}
              </Link>
            ) : (
              <StatusPill value="Orders locked" tone="warning" />
            )}
          </div>
        </Panel>
      ) : null}

      <div className="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <Panel>
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Table board</p>
              <h3 className="text-xl font-semibold text-slate-950">Operational floor map</h3>
            </div>
            <div className="grid grid-cols-2 gap-2 text-right">
              <MetricCard label="Active orders" value={String(board?.summary.active_order_count ?? 0)} />
              <MetricCard label="Unassigned" value={String(board?.summary.unassigned_reservation_count ?? 0)} />
            </div>
          </div>

          {!canViewBoard ? (
            <div className="mt-5">
              <EmptyState
                title="Khong du capability table.board.view"
                description="Session hien tai van co the vao route nay de xu ly waiting list, nhung khong du capability de doc board."
              />
            </div>
          ) : null}

          {canViewBoard ? (
            <>
              <div className="mt-5 grid gap-3 md:grid-cols-2">
                {(board?.data ?? []).map((row) => (
                  <button
                    key={row.table_id}
                    type="button"
                    onClick={() => {
                      setNextStepJourney(null);
                      setSelectedTableId(row.table_id);
                    }}
                    className={`rounded-[24px] border p-4 text-left transition ${
                      selectedTableId === row.table_id ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white hover:bg-slate-50'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="eyebrow">{row.zone ?? 'No zone'}</p>
                        <p className="mt-2 text-xl font-semibold text-slate-950">{row.table_code}</p>
                      </div>
                      <StatusPill value={humanizeCode(row.board_state)} tone={row.active_order ? 'warning' : 'success'} />
                    </div>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                      <MetricCard label="Reservation" value={row.reservation?.reservation_code ?? 'Trong'} />
                      <MetricCard label="Order" value={row.active_order ? `#${row.active_order.order_id}` : 'Chua mo'} />
                    </div>
                  </button>
                ))}
              </div>

              {selectedTable ? (
                <div className="mt-5 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <p className="eyebrow">Selected table</p>
                      <h4 className="text-xl font-semibold text-slate-950">{selectedTable.table_code}</h4>
                    </div>
                    {selectedTable.actions.check_in?.available && selectedTable.reservation ? (
                      <ActionButton
                        onClick={() => handleCheckIn(selectedTable.table_id)}
                        busy={busyKey === `check-in-${selectedTable.table_id}`}
                        icon={<ShieldCheck className="h-4 w-4" />}
                      >
                        Check-in
                      </ActionButton>
                    ) : null}
                  </div>

                  <div className="mt-4 grid gap-3 md:grid-cols-2">
                    <MetricCard label="Realtime" value={humanizeCode(selectedTable.realtime_status)} />
                    <MetricCard label="Capacity" value={selectedTable.capacity.seats ? `${selectedTable.capacity.seats} seats` : 'N/A'} />
                    <MetricCard
                      label="Reservation"
                      value={selectedTable.reservation?.reservation_code ?? 'Khong co reservation trong cua so nay'}
                    />
                    <MetricCard
                      label="Guest"
                      value={
                        readString(selectedTable.reservation?.user, 'full_name') ??
                        readString(selectedTable.reservation?.user, 'phone') ??
                        'N/A'
                      }
                    />
                    <MetricCard
                      label="Deposit outstanding"
                      value={formatMoney(
                        selectedTable.reservation?.deposit.outstanding_amount,
                        selectedTable.reservation?.deposit.currency,
                      )}
                    />
                    <MetricCard
                      label="Preferred action"
                      value={humanizeCode(selectedTable.operational_hints.preferred_action || 'none')}
                    />
                  </div>
                  {ordersJourneySearch && (canManageOrders || canManageSettlement) ? (
                    <div className="mt-4 flex flex-wrap gap-3">
                      {canManageOrders ? (
                        <Link
                          to={`/orders?${ordersJourneySearch}`}
                          className="inline-flex items-center justify-center rounded-[22px] bg-slate-950 px-4 py-3 text-sm font-semibold text-white"
                        >
                          Mo Orders voi context nay
                        </Link>
                      ) : null}
                      {settlementJourneySearch && canManageSettlement ? (
                        <Link
                          to={`/settlement?${settlementJourneySearch}`}
                          className="inline-flex items-center justify-center rounded-[22px] border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                        >
                          Mo Settlement cho order nay
                        </Link>
                      ) : null}
                    </div>
                  ) : null}
                </div>
              ) : null}
            </>
          ) : null}
        </Panel>

        <Panel>
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Waiting list</p>
              <h3 className="text-xl font-semibold text-slate-950">Notify + seat theo queue state</h3>
            </div>
            <div className="grid grid-cols-2 gap-2 text-right">
              <MetricCard label="Ready" value={String(waiting?.meta?.summary.ready_to_seat_count ?? 0)} />
              <MetricCard label="Follow-up" value={String(waiting?.meta?.summary.awaiting_customer_follow_up_count ?? 0)} />
            </div>
          </div>

          {!canManageWaiting ? (
            <div className="mt-5">
              <EmptyState
                title="Khong du capability waiting_list.manage"
                description="Board van doc duoc, nhung notify/seat waiting list bi khoa boi capability boundary."
              />
            </div>
          ) : null}

          {canManageWaiting ? (
            <>
              <div className="mt-5 space-y-3">
                {(waiting?.data ?? []).map((entry) => (
                  <button
                    key={entry.waiting_id}
                    type="button"
                    onClick={() => {
                      setNextStepJourney(null);
                      setSelectedWaitingId(entry.waiting_id);
                    }}
                    className={`w-full rounded-[24px] border p-4 text-left transition ${
                      selectedWaitingId === entry.waiting_id ? 'border-sky-300 bg-sky-50' : 'border-slate-200 bg-white hover:bg-slate-50'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="font-semibold text-slate-900">
                          {entry.guest_name ?? entry.phone ?? `Waiting #${entry.waiting_id}`}
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                          {entry.guest_count} khach | {humanizeCode(entry.status)} | {humanizeCode(entry.current_response_state)}
                        </p>
                      </div>
                      <StatusPill value={`rv ${entry.row_version}`} tone="info" />
                    </div>
                    <p className="mt-2 text-sm text-slate-600">
                      Requested {formatDateTime(entry.requested_at)}. Seat readiness: {humanizeCode(entry.invite_lifecycle.seat_readiness)}.
                    </p>
                  </button>
                ))}
              </div>

              {selectedWaiting ? (
                <div className="mt-5 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <p className="eyebrow">Selected guest</p>
                      <h4 className="text-xl font-semibold text-slate-950">
                        {selectedWaiting.guest_name ?? selectedWaiting.phone ?? `Waiting #${selectedWaiting.waiting_id}`}
                      </h4>
                    </div>
                    {selectedWaiting.invite_lifecycle.can_staff_seat_now ? (
                      <ActionButton
                        onClick={() => handleSeat(selectedWaiting.waiting_id)}
                        busy={busyKey === `seat-${selectedWaiting.waiting_id}`}
                        icon={<UsersRound className="h-4 w-4" />}
                      >
                        Seat ngay
                      </ActionButton>
                    ) : null}
                  </div>

                  <div className="mt-4 grid gap-3 md:grid-cols-2">
                    <MetricCard label="Invite active" value={selectedWaiting.invite_window.is_active ? 'Yes' : 'No'} />
                    <MetricCard label="Seconds left" value={String(selectedWaiting.invite_window.seconds_remaining)} />
                    <MetricCard label="Next step" value={humanizeCode(selectedWaiting.invite_lifecycle.staff_next_step)} />
                    <MetricCard label="Notes" value={selectedWaiting.notes ?? 'N/A'} />
                  </div>

                  <div className="mt-5 rounded-[22px] bg-white p-4">
                    <p className="text-sm font-semibold text-slate-900">Notify vao table</p>
                    <div className="mt-3 grid gap-3 md:grid-cols-[1fr_140px_auto]">
                      <label className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Table</span>
                        <select
                          value={notifyTableId ?? ''}
                          onChange={(event) => setNotifyTableId(event.target.value ? Number(event.target.value) : null)}
                          className="mt-3 w-full bg-transparent text-sm outline-none"
                        >
                          <option value="">Chon table available</option>
                          {notifyTables.map((row) => (
                            <option key={row.table_id} value={row.table_id}>
                              {row.table_code} - {row.zone ?? 'No zone'}
                            </option>
                          ))}
                        </select>
                      </label>
                      <label className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Hold mins</span>
                        <input
                          value={holdMinutes}
                          onChange={(event) => setHoldMinutes(event.target.value)}
                          className="mt-3 w-full bg-transparent text-sm outline-none"
                          inputMode="numeric"
                        />
                      </label>
                      <div className="flex items-end">
                        <ActionButton
                          onClick={() => handleNotify(selectedWaiting.waiting_id)}
                          busy={busyKey === `notify-${selectedWaiting.waiting_id}`}
                          disabled={notifyTableId === null}
                          icon={<BellRing className="h-4 w-4" />}
                          className="w-full justify-center"
                        >
                          Notify
                        </ActionButton>
                      </div>
                    </div>
                  </div>
                </div>
              ) : null}
            </>
          ) : null}
        </Panel>
      </div>

      {(boardChanges || waitingChanges) && !error ? (
        <Panel>
          <p className="eyebrow">Change cursors</p>
          <div className="mt-4 grid gap-4 md:grid-cols-2">
            <ChangeSummary title="Board changes" payload={boardChanges} />
            <ChangeSummary title="Waiting changes" payload={waitingChanges} />
          </div>
        </Panel>
      ) : null}
    </div>
  );

  async function handleCheckIn(tableId: number) {
    const row = board?.data.find((item) => item.table_id === tableId);
    const action = row?.actions.check_in;
    const reservation = row?.reservation;

    if (!row || !reservation || !action?.available) {
      return;
    }

    setBusyKey(`check-in-${tableId}`);
    setError(null);
    setNextStepJourney(null);

    try {
      await checkInReservation(reservation.reservation_id, action.preferred_payload);
      const refreshed = await refreshPage();
      const refreshedTable = refreshed.board?.data.find((item) => item.table_id === tableId);
      const refreshedReservation = refreshedTable?.reservation;

      if (refreshedTable && refreshedReservation) {
        setNextStepJourney({
          actionLabel: 'Tiep tuc sang Orders',
          description: `${refreshedReservation.reservation_code} tai ${refreshedTable.table_code} da duoc refresh context. Operator co the mo Orders ngay ma khong can reconstruct lai \`table_id\`, \`reservation_id\`, hay \`reservation_row_version\`.`,
          search: buildOperatorJourneySearch({
            source: 'board',
            tableId: refreshedTable.table_id,
            reservationId: refreshedReservation.reservation_id,
            reservationRowVersion: refreshedReservation.row_version,
            orderId: refreshedTable.active_order?.order_id,
          }),
          title: 'Check-in da xong, co the tiep tuc order flow',
        });
      }

      setNotice(`Da check-in ${reservation.reservation_code}.`);
    } catch (cause) {
      if (isRowVersionConflict(cause)) {
        setError(rowVersionConflictMessage(`Reservation ${reservation.reservation_code}`));
      } else {
        handlePageError(cause, 'Khong the check-in reservation.');
      }
    } finally {
      setBusyKey(null);
    }
  }

  async function handleNotify(waitingId: number) {
    const entry = waiting?.data.find((item) => item.waiting_id === waitingId);

    if (!entry || notifyTableId === null) {
      return;
    }

    setBusyKey(`notify-${waitingId}`);
    setError(null);

    try {
      await notifyWaitingListEntry(waitingId, {
        table_id: notifyTableId,
        hold_minutes: Number(holdMinutes) || 10,
        row_version: entry.row_version,
      });
      setNotice(`Da notify waiting #${waitingId} vao table #${notifyTableId}.`);
      await refreshPage();
    } catch (cause) {
      if (isRowVersionConflict(cause)) {
        setError(rowVersionConflictMessage(`Waiting entry #${waitingId}`));
      } else {
        handlePageError(cause, 'Khong the notify waiting list.');
      }
    } finally {
      setBusyKey(null);
    }
  }

  async function handleSeat(waitingId: number) {
    const entry = waiting?.data.find((item) => item.waiting_id === waitingId);

    if (!entry) {
      return;
    }

    setBusyKey(`seat-${waitingId}`);
    setError(null);
    setNextStepJourney(null);

    try {
      const seated = await seatWaitingListEntry(waitingId, {
        row_version: entry.row_version,
        service_minutes: 120,
        user_id: entry.user_id ?? undefined,
      });
      const refreshed = await refreshPage();
      const seatedReservation = seated.data.reservation;
      const seatedTableId = seatedReservation.table_ids?.[0];
      const refreshedTable = seatedTableId
        ? refreshed.board?.data.find((item) => item.table_id === seatedTableId) ?? null
        : null;

      if (seatedTableId) {
        setSelectedTableId(seatedTableId);
      }

      setNextStepJourney({
        actionLabel: 'Mo Orders cho reservation nay',
        description: `${seatedReservation.reservation_code} vua duoc seat${refreshedTable ? ` vao ${refreshedTable.table_code}` : ''}. Operator co the mo Orders bang reservation context moi ma khong can nhap tay ID.`,
        search: buildOperatorJourneySearch({
          source: 'board',
          tableId: seatedTableId,
          reservationId: seatedReservation.reservation_id,
          reservationRowVersion: seatedReservation.row_version,
          orderId: refreshedTable?.active_order?.order_id,
        }),
        title: 'Seat da xong, co the mo order ngay',
      });

      setNotice(`Da seat waiting #${waitingId}.`);
    } catch (cause) {
      if (isRowVersionConflict(cause)) {
        setError(rowVersionConflictMessage(`Waiting entry #${waitingId}`));
      } else {
        handlePageError(cause, 'Khong the seat waiting list.');
      }
    } finally {
      setBusyKey(null);
    }
  }

}

function ChangeSummary({
  title,
  payload,
}: {
  title: string;
  payload: StaffOperationalRealtimeEnvelope | null;
}) {
  if (!payload) {
    return (
      <EmptyState
        title={`${title} chua duoc truy van`}
        description="Dung nut Check changes de doi soat current_version voi backend realtime feed."
      />
    );
  }

  return (
    <div className="rounded-[24px] border border-slate-200 bg-slate-50 p-5">
      <p className="text-sm font-semibold text-slate-900">{title}</p>
      <div className="mt-3 grid gap-3 md:grid-cols-2">
        <MetricCard label="Current version" value={String(payload.data.current_version)} />
        <MetricCard label="Has changes" value={payload.data.has_changes ? 'Yes' : 'No'} />
        <MetricCard label="Stale cursor" value={payload.data.stale_cursor ? 'Yes' : 'No'} />
        <MetricCard label="Events" value={String(payload.data.events.length)} />
      </div>
    </div>
  );
}
