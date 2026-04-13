import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { BellRing, Clock3, RefreshCcw, ShieldCheck, UsersRound, Waves } from 'lucide-react';
import {
  buildBoardWindow as boardWindow,
  checkInReservation,
  getTableBoard as loadTableBoard,
  getTableBoardChanges as loadTableBoardChanges,
  listWaitingList as loadWaitingList,
  getWaitingListChanges as loadWaitingListChanges,
  notifyWaitingListEntry,
  seatWaitingListEntry,
} from '../../core/api/staff-api';
import { formatApiError, isApiStatus } from '../../core/api/errors';
import { useStaffSession } from '../../app/session-context';
import { hasCapability } from '../../lib/capabilities';
import { isRowVersionConflict, rowVersionConflictMessage } from '../../lib/conflicts';
import { formatDateTime, formatMoney, humanizeCode, readString } from '../../lib/format';
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

type StaffBoardWindow = ReturnType<typeof boardWindow>;
type BoardRow = StaffTableBoardEnvelope['data'][number];
type WaitingRow = StaffWaitingListCollectionEnvelope['data'][number];

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
  const occupiedTableCount = useMemo(
    () => board?.data.filter((row) => row.active_order || row.reservation).length ?? 0,
    [board],
  );
  const actionTableCount = useMemo(
    () => board?.data.filter((row) => row.operational_hints.preferred_action && row.operational_hints.preferred_action !== 'none').length ?? 0,
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
      if (isApiStatus(cause, 401)) {
        expire('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
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
        refreshBoardSlice && canViewBoard && nextWindow
          ? loadTableBoard({
              ...nextWindow,
              include_holds: true,
              group_by: 'zone',
            })
          : Promise.resolve(null),
        refreshWaitingSlice && canManageWaiting
          ? loadWaitingList({
              active_only: true,
              per_page: 12,
              sort: '-priority',
            })
          : Promise.resolve(null),
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
      handlePageError(cause, 'Không thể tải sơ đồ bàn và khách chờ.');
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
            ? 'Đã cập nhật dữ liệu mới.'
            : 'Hiện chưa có thay đổi mới.',
        );
      }
    } catch (cause) {
      if (!background) {
        handlePageError(cause, 'Không thể kiểm tra thay đổi.');
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
      <Panel className="px-6 py-6">
        <div className="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
          <div className="max-w-3xl">
            <p className="eyebrow">Sơ đồ bàn</p>
            <h2 className="workspace-title mt-2 text-3xl font-semibold text-slate-950">Bàn ăn và khách chờ</h2>
            <p className="mt-3 text-sm leading-7 text-slate-600">
              Nhìn nhanh bàn nào đang phục vụ, khách nào sẵn sàng vào bàn và việc nào cần làm tiếp.
            </p>
          </div>
          <div className="flex flex-wrap gap-3">
            <ActionButton onClick={checkRealtimeChanges} busy={busyKey === 'changes'} icon={<Waves className="h-4 w-4" />} variant="secondary">
              Kiểm tra thay đổi
            </ActionButton>
            <ActionButton onClick={() => { void refreshPage(); }} busy={loading || busyKey === 'refresh'} icon={<RefreshCcw className="h-4 w-4" />}>
              Làm mới
            </ActionButton>
          </div>
        </div>

        <div className="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          <MetricCard label="Bàn đang phục vụ" value={String(occupiedTableCount)} />
          <MetricCard label="Đơn đang mở" value={String(board?.summary.active_order_count ?? 0)} />
          <MetricCard label="Khách chờ sẵn sàng" value={String(waiting?.meta?.summary.ready_to_seat_count ?? 0)} />
          <MetricCard label="Tần suất cập nhật" value={`${Math.round(pollDelayMs / 1000)} giây`} />
        </div>

        <div className="mt-4 flex flex-wrap gap-2">
          <StatusPill value={`Từ ${formatDateTime(window.from)}`} />
          <StatusPill value={`Đến ${formatDateTime(window.to)}`} />
          <StatusPill value={`Cần chú ý ${actionTableCount}`} tone={actionTableCount > 0 ? 'warning' : 'success'} />
          <StatusPill value={`Khách chờ ${waiting?.meta?.summary.ready_to_seat_count ?? 0}`} tone="info" />
        </div>
      </Panel>

      {notice ? <Banner tone="success">{notice}</Banner> : null}
      {error ? <Banner tone="error">{error}</Banner> : null}
      <div className="grid gap-6 2xl:grid-cols-[minmax(0,1.25fr)_400px]">
        <div className="space-y-6">
          <Panel className="px-5 py-5">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <p className="eyebrow">Khu bàn</p>
                <h3 className="workspace-title mt-2 text-2xl font-semibold text-slate-950">Tình trạng bàn</h3>
                <p className="mt-3 max-w-2xl text-sm leading-7 text-slate-600">
                  Ưu tiên những bàn đang phục vụ, bàn có khách cần nhận vào và bàn đang cần xử lý tiếp.
                </p>
              </div>
              <div className="grid grid-cols-2 gap-2 text-right">
                <MetricCard label="Đơn đang mở" value={String(board?.summary.active_order_count ?? 0)} />
                <MetricCard label="Khách chưa xếp bàn" value={String(board?.summary.unassigned_reservation_count ?? 0)} />
              </div>
            </div>

            {!canViewBoard ? (
              <div className="mt-5">
                <EmptyState
                  title="Bạn chưa có quyền xem sơ đồ bàn"
                  description="Tài khoản hiện tại vẫn có thể xử lý khách chờ nếu được cấp quyền phù hợp."
                />
              </div>
            ) : null}

            {canViewBoard ? (
              <div className="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {(board?.data ?? []).map((row) => {
                  const styles = boardCardStyles(row, selectedTableId === row.table_id);

                  return (
                    <button
                      key={row.table_id}
                      type="button"
                      onClick={() => {
                        setNextStepJourney(null);
                        setSelectedTableId(row.table_id);
                      }}
                      className={`rounded-[28px] border px-4 py-4 text-left transition ${styles.cardClass}`}
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div className="flex items-start gap-3">
                          <span className={`mt-1.5 h-3 w-3 rounded-full ${styles.dotClass}`} />
                          <div>
                            <p className="eyebrow">{row.zone ?? 'Chưa phân khu'}</p>
                            <p className="workspace-title mt-2 text-2xl font-semibold text-slate-950">{row.table_code}</p>
                          </div>
                        </div>
                        <StatusPill value={translateBoardState(row.board_state)} tone={styles.pillTone} />
                      </div>
                      <div className="mt-4 grid gap-3 sm:grid-cols-2">
                        <MetricCard label="Đặt bàn" value={row.reservation?.reservation_code ?? 'Trống'} />
                        <MetricCard label="Đơn hàng" value={row.active_order ? `#${row.active_order.order_id}` : 'Chưa mở'} />
                      </div>
                      <div className="mt-4 flex flex-wrap gap-2 text-xs font-medium text-slate-500">
                        <span>{row.capacity.seats ? `${row.capacity.seats} chỗ` : 'Chưa có sức chứa'}</span>
                        <span>Tình trạng {translateBoardState(row.realtime_status)}</span>
                        <span>Việc tiếp theo {translateNextAction(row.operational_hints.preferred_action || 'none')}</span>
                      </div>
                    </button>
                  );
                })}
              </div>
            ) : null}
          </Panel>
        </div>

        <div className="space-y-6">
          {nextStepJourney ? (
            <Panel className="px-5 py-5">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p className="eyebrow">Gợi ý tiếp theo</p>
                  <h3 className="workspace-title mt-2 text-xl font-semibold text-slate-950">{nextStepJourney.title}</h3>
                  <p className="mt-2 text-sm leading-7 text-slate-600">{nextStepJourney.description}</p>
                </div>
                {canManageOrders ? (
                  <Link
                    to={`/orders?${nextStepJourney.search}`}
                    className="inline-flex items-center justify-center rounded-[22px] bg-[#c46b2d] px-4 py-3 text-sm font-semibold text-white shadow-[0_20px_40px_-28px_rgba(196,107,45,0.95)]"
                  >
                    {nextStepJourney.actionLabel}
                  </Link>
                ) : (
                  <StatusPill value="Chưa mở mục đơn hàng" tone="warning" />
                )}
              </div>
            </Panel>
          ) : null}

          <Panel className="px-5 py-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="eyebrow">Bàn đang xem</p>
                <h3 className="workspace-title mt-2 text-2xl font-semibold text-slate-950">
                  {selectedTable ? selectedTable.table_code : 'Chưa chọn bàn'}
                </h3>
                <p className="mt-2 text-sm leading-7 text-slate-600">
                  Chọn một bàn để xem khách, đơn hàng và thao tác nhanh.
                </p>
              </div>
              {selectedTable?.actions.check_in?.available && selectedTable.reservation ? (
                <ActionButton
                  onClick={() => handleCheckIn(selectedTable.table_id)}
                  busy={busyKey === `check-in-${selectedTable.table_id}`}
                  icon={<ShieldCheck className="h-4 w-4" />}
                >
                  Nhận khách
                </ActionButton>
              ) : null}
            </div>

            {!selectedTable ? (
              <div className="mt-5">
                <EmptyState
                  title="Chưa chọn bàn"
                  description="Chọn một bàn ở bên trái để xem thông tin chi tiết."
                />
              </div>
            ) : (
              <>
                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                  <MetricCard label="Tình trạng" value={translateBoardState(selectedTable.realtime_status)} />
                  <MetricCard label="Sức chứa" value={selectedTable.capacity.seats ? `${selectedTable.capacity.seats} chỗ` : 'Không rõ'} />
                  <MetricCard
                    label="Mã đặt bàn"
                    value={selectedTable.reservation?.reservation_code ?? 'Không có đặt bàn trong khung giờ này'}
                  />
                  <MetricCard
                    label="Khách"
                    value={
                      readString(selectedTable.reservation?.user, 'full_name') ??
                      readString(selectedTable.reservation?.user, 'phone') ??
                      'Không rõ'
                    }
                  />
                  <MetricCard
                    label="Cọc còn lại"
                    value={formatMoney(
                      selectedTable.reservation?.deposit.outstanding_amount,
                      selectedTable.reservation?.deposit.currency,
                    )}
                  />
                  <MetricCard
                    label="Việc nên làm"
                    value={translateNextAction(selectedTable.operational_hints.preferred_action || 'none')}
                  />
                </div>

                {ordersJourneySearch && (canManageOrders || canManageSettlement) ? (
                  <div className="mt-5 flex flex-wrap gap-3">
                    {canManageOrders ? (
                      <Link
                        to={`/orders?${ordersJourneySearch}`}
                        className="inline-flex items-center justify-center rounded-[22px] bg-slate-950 px-4 py-3 text-sm font-semibold text-white"
                      >
                        Mở đơn cho bàn này
                      </Link>
                    ) : null}
                    {settlementJourneySearch && canManageSettlement ? (
                      <Link
                        to={`/settlement?${settlementJourneySearch}`}
                        className="inline-flex items-center justify-center rounded-[22px] border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                      >
                        Mở thanh toán
                      </Link>
                    ) : null}
                  </div>
                ) : null}
              </>
            )}
          </Panel>

          <Panel className="px-5 py-5">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <p className="eyebrow">Khách chờ</p>
                <h3 className="workspace-title mt-2 text-2xl font-semibold text-slate-950">Danh sách chờ</h3>
                <p className="mt-3 text-sm leading-7 text-slate-600">
                  Theo dõi khách sẵn sàng vào bàn và gọi khách ngay khi có chỗ.
                </p>
              </div>
              <div className="grid grid-cols-2 gap-2 text-right">
                <MetricCard label="Sẵn sàng" value={String(waiting?.meta?.summary.ready_to_seat_count ?? 0)} />
                <MetricCard label="Cần gọi lại" value={String(waiting?.meta?.summary.awaiting_customer_follow_up_count ?? 0)} />
              </div>
            </div>

            {!canManageWaiting ? (
              <div className="mt-5">
                <EmptyState
                  title="Bạn chưa có quyền xử lý khách chờ"
                  description="Bạn vẫn có thể xem sơ đồ bàn, nhưng chưa thể gọi khách hoặc xếp bàn."
                />
              </div>
            ) : null}

            {canManageWaiting ? (
              <>
                <div className="mt-5 space-y-3">
                  {(waiting?.data ?? []).map((entry) => {
                    const styles = waitingCardStyles(entry, selectedWaitingId === entry.waiting_id);

                    return (
                      <button
                        key={entry.waiting_id}
                        type="button"
                        onClick={() => {
                          setNextStepJourney(null);
                          setSelectedWaitingId(entry.waiting_id);
                        }}
                        className={`w-full rounded-[26px] border px-4 py-4 text-left transition ${styles.cardClass}`}
                      >
                        <div className="flex items-start justify-between gap-3">
                          <div className="flex items-start gap-3">
                            <span className={`mt-1.5 h-3 w-3 rounded-full ${styles.dotClass}`} />
                          <div>
                            <p className="font-semibold text-slate-900">
                              {entry.guest_name ?? entry.phone ?? `Khách chờ #${entry.waiting_id}`}
                            </p>
                              <p className="mt-1 text-xs text-slate-500">
                                {entry.guest_count} khách · {translateWaitingStatus(entry.status)} · {translateWaitingResponse(entry.current_response_state)}
                              </p>
                            </div>
                          </div>
                          <StatusPill
                            value={translateSeatReadiness(entry.invite_lifecycle.seat_readiness)}
                            tone={entry.invite_lifecycle.can_staff_seat_now ? 'success' : 'warning'}
                          />
                        </div>
                        <div className="mt-3 flex items-center gap-2 text-xs text-slate-500">
                          <Clock3 className="h-3.5 w-3.5" />
                          <span>Đăng ký lúc {formatDateTime(entry.requested_at)}</span>
                        </div>
                        <p className="mt-2 text-sm text-slate-600">
                          Trạng thái vào bàn: {translateSeatReadiness(entry.invite_lifecycle.seat_readiness)}.
                        </p>
                      </button>
                    );
                  })}
                </div>

                {selectedWaiting ? (
                  <div className="mt-5 rounded-[28px] border border-slate-200/80 bg-white/80 p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                      <div>
                        <p className="eyebrow">Khách đang xem</p>
                        <h4 className="workspace-title mt-2 text-xl font-semibold text-slate-950">
                          {selectedWaiting.guest_name ?? selectedWaiting.phone ?? `Khách chờ #${selectedWaiting.waiting_id}`}
                        </h4>
                      </div>
                      {selectedWaiting.invite_lifecycle.can_staff_seat_now ? (
                        <ActionButton
                          onClick={() => handleSeat(selectedWaiting.waiting_id)}
                          busy={busyKey === `seat-${selectedWaiting.waiting_id}`}
                          icon={<UsersRound className="h-4 w-4" />}
                        >
                          Xếp bàn ngay
                        </ActionButton>
                      ) : null}
                    </div>

                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                      <MetricCard label="Đang giữ chỗ" value={selectedWaiting.invite_window.is_active ? 'Có' : 'Không'} />
                      <MetricCard label="Thời gian còn lại" value={`${selectedWaiting.invite_window.seconds_remaining} giây`} />
                      <MetricCard label="Việc tiếp theo" value={translateNextAction(selectedWaiting.invite_lifecycle.staff_next_step)} />
                      <MetricCard label="Ghi chú" value={selectedWaiting.notes ?? 'Không có'} />
                    </div>

                    <div className="mt-5 rounded-[24px] border border-slate-200 bg-slate-50/80 p-4">
                      <p className="text-sm font-semibold text-slate-900">Mời vào bàn</p>
                      <div className="mt-3 grid gap-3 md:grid-cols-[1fr_140px_auto]">
                        <label className="rounded-[22px] border border-slate-200 bg-white px-4 py-3">
                          <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Bàn</span>
                          <select
                            value={notifyTableId ?? ''}
                            onChange={(event) => setNotifyTableId(event.target.value ? Number(event.target.value) : null)}
                            className="mt-3 w-full bg-transparent text-sm outline-none"
                          >
                            <option value="">Chọn bàn còn trống</option>
                            {notifyTables.map((row) => (
                              <option key={row.table_id} value={row.table_id}>
                                {row.table_code} - {row.zone ?? 'Chưa phân khu'}
                              </option>
                            ))}
                          </select>
                        </label>
                        <label className="rounded-[22px] border border-slate-200 bg-white px-4 py-3">
                          <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Giữ bàn (phút)</span>
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
                            Gọi khách
                          </ActionButton>
                        </div>
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="mt-5">
                    <EmptyState
                      title="Chưa chọn khách"
                      description="Chọn một khách trong danh sách chờ để gọi khách hoặc xếp bàn."
                    />
                  </div>
                )}
              </>
            ) : null}
          </Panel>

          {(boardChanges || waitingChanges) && !error ? (
            <Panel className="px-5 py-5">
              <p className="eyebrow">Đồng bộ dữ liệu</p>
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                <ChangeSummary title="Sơ đồ bàn" payload={boardChanges} />
                <ChangeSummary title="Khách chờ" payload={waitingChanges} />
              </div>
            </Panel>
          ) : null}
        </div>
      </div>
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
          actionLabel: 'Mở đơn cho bàn này',
          description: `${refreshedReservation.reservation_code} tại ${refreshedTable.table_code} đã sẵn sàng. Bạn có thể mở đơn ngay.`,
          search: buildOperatorJourneySearch({
            source: 'board',
            tableId: refreshedTable.table_id,
            reservationId: refreshedReservation.reservation_id,
            reservationRowVersion: refreshedReservation.row_version,
            orderId: refreshedTable.active_order?.order_id,
          }),
          title: 'Có thể tiếp tục mở đơn',
        });
      }

      setNotice(`Đã nhận khách ${reservation.reservation_code}.`);
    } catch (cause) {
      if (isRowVersionConflict(cause)) {
        setError(rowVersionConflictMessage(`Đặt bàn ${reservation.reservation_code}`));
      } else {
        handlePageError(cause, 'Không thể nhận khách vào bàn.');
      }
    } finally {
      setBusyKey(null);
    }
  }

  async function handleNotify(waitingId: number) {
    const entry = waiting?.data.find((item) => item.waiting_id === waitingId);
    const notifyTable = notifyTables.find((table) => table.table_id === notifyTableId);

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
      setNotice(`Đã gọi khách vào ${notifyTable?.table_code ?? `bàn #${notifyTableId}`}.`);
      await refreshPage();
    } catch (cause) {
      if (isRowVersionConflict(cause)) {
        setError(rowVersionConflictMessage(`Khách chờ #${waitingId}`));
      } else {
        handlePageError(cause, 'Không thể gọi khách vào bàn.');
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
        actionLabel: 'Mở đơn cho khách này',
        description: `${seatedReservation.reservation_code} vừa được xếp bàn${refreshedTable ? ` tại ${refreshedTable.table_code}` : ''}. Bạn có thể mở đơn ngay.`,
        search: buildOperatorJourneySearch({
          source: 'board',
          tableId: seatedTableId,
          reservationId: seatedReservation.reservation_id,
          reservationRowVersion: seatedReservation.row_version,
          orderId: refreshedTable?.active_order?.order_id,
        }),
        title: 'Khách đã vào bàn',
      });

      setNotice(`Đã xếp bàn cho khách chờ #${waitingId}.`);
    } catch (cause) {
      if (isRowVersionConflict(cause)) {
        setError(rowVersionConflictMessage(`Khách chờ #${waitingId}`));
      } else {
        handlePageError(cause, 'Không thể xếp bàn cho khách.');
      }
    } finally {
      setBusyKey(null);
    }
  }

}

function boardCardStyles(row: BoardRow, selected: boolean) {
  if (selected) {
    return {
      cardClass: 'border-[#c46b2d] bg-[rgba(196,107,45,0.12)] shadow-[0_22px_54px_-36px_rgba(196,107,45,0.95)]',
      dotClass: 'bg-[#c46b2d]',
      pillTone: 'warning' as const,
    };
  }

  if (row.active_order) {
    return {
      cardClass: 'border-amber-200 bg-amber-50/75 hover:bg-amber-50',
      dotClass: 'bg-amber-500',
      pillTone: 'warning' as const,
    };
  }

  if (row.reservation) {
    return {
      cardClass: 'border-sky-200 bg-sky-50/70 hover:bg-sky-50',
      dotClass: 'bg-sky-500',
      pillTone: 'info' as const,
    };
  }

  return {
    cardClass: 'border-slate-200 bg-white/80 hover:bg-white',
    dotClass: 'bg-emerald-500',
    pillTone: 'success' as const,
  };
}

function waitingCardStyles(entry: WaitingRow, selected: boolean) {
  if (selected) {
    return {
      cardClass: 'border-sky-300 bg-sky-50 shadow-[0_20px_48px_-36px_rgba(14,165,233,0.85)]',
      dotClass: 'bg-sky-500',
    };
  }

  if (entry.invite_lifecycle.can_staff_seat_now) {
    return {
      cardClass: 'border-emerald-200 bg-emerald-50/80 hover:bg-emerald-50',
      dotClass: 'bg-emerald-500',
    };
  }

  return {
    cardClass: 'border-slate-200 bg-white/80 hover:bg-white',
    dotClass: 'bg-amber-500',
  };
}

function translateBoardState(value: string | null | undefined) {
  return translateCode(value, {
    occupied: 'đang phục vụ',
    available: 'còn trống',
    reserved: 'đã giữ bàn',
    pending: 'đang chờ',
    open: 'đang mở',
    checked_in: 'đã nhận khách',
  });
}

function translateNextAction(value: string | null | undefined) {
  return translateCode(value, {
    none: 'chưa có',
    check_in: 'nhận khách',
    seat: 'xếp bàn',
    notify: 'gọi khách',
    order: 'mở đơn',
    settlement: 'thanh toán',
    payment: 'thanh toán',
    follow_up: 'liên hệ lại',
  });
}

function translateWaitingStatus(value: string | null | undefined) {
  return translateCode(value, {
    waiting: 'đang chờ',
    ready: 'sẵn sàng',
    seated: 'đã vào bàn',
    cancelled: 'đã hủy',
  });
}

function translateWaitingResponse(value: string | null | undefined) {
  return translateCode(value, {
    pending: 'chờ phản hồi',
    accepted: 'đã nhận lời',
    declined: 'đã từ chối',
    expired: 'đã quá hạn',
  });
}

function translateSeatReadiness(value: string | null | undefined) {
  return translateCode(value, {
    ready: 'sẵn sàng vào bàn',
    waiting: 'đang chờ',
    invite_active: 'đang giữ chỗ',
    follow_up: 'cần gọi lại',
  });
}

function translateCode(value: string | null | undefined, labels: Record<string, string>) {
  if (!value) {
    return 'không rõ';
  }

  const normalized = value.trim().toLowerCase();
  return labels[normalized] ?? humanizeCode(value).toLowerCase();
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
        title={`${title} chưa được kiểm tra`}
        description="Bấm Kiểm tra thay đổi để xem có dữ liệu mới hay không."
      />
    );
  }

  return (
    <div className="rounded-[24px] border border-slate-200 bg-white/80 p-5">
      <p className="text-sm font-semibold text-slate-900">{title}</p>
      <div className="mt-3 grid gap-3 md:grid-cols-2">
        <MetricCard label="Phiên bản hiện tại" value={String(payload.data.current_version)} />
        <MetricCard label="Có thay đổi" value={payload.data.has_changes ? 'Có' : 'Không'} />
        <MetricCard label="Cần tải lại" value={payload.data.stale_cursor ? 'Có' : 'Không'} />
        <MetricCard label="Số tín hiệu" value={String(payload.data.events.length)} />
      </div>
    </div>
  );
}
