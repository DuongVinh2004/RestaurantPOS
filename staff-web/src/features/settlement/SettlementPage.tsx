import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { CreditCard, FileSpreadsheet, ReceiptText, Search } from 'lucide-react';
import {
  boardWindow,
  createBillSnapshot,
  finalizeSettlement,
  isUnauthorized,
  loadOrderDetail,
  loadReservationOrders,
  loadSettlementPreview,
  loadStaffReservations,
  loadTableBoard,
  type StaffBoardWindow,
} from '../../api/client';
import { useStaffSession } from '../../app/session-context';
import { hasCapability } from '../../lib/capabilities';
import { isRowVersionConflict, rowVersionConflictMessage } from '../../lib/conflicts';
import { normalizeApiError } from '../../lib/api-errors';
import { formatFinanceOperatorError } from '../../lib/financeErrors';
import { formatDateTime, formatMoney, humanizeCode } from '../../lib/format';
import { readOperatorJourneyContext } from '../../lib/operatorJourney';
import type {
  StaffCheckoutSettlementEnvelope,
  StaffOrderReadEnvelope,
  StaffReservationLookupCollectionEnvelope,
  StaffReservationLookupEntry,
  StaffReservationOrderCollectionEnvelope,
  StaffTableBoardEnvelope,
} from '../../api/sdk';
import { ActionButton, Banner, EmptyState, MetricCard, Panel, StatusPill } from '../../components/ui';

const paymentOptions = ['Cash', 'Card', 'BankTransfer', 'Other'] as const;

export function SettlementPage() {
  const [searchParams] = useSearchParams();
  const { expire, session } = useStaffSession();
  const [window] = useState<StaffBoardWindow>(() => boardWindow());
  const [board, setBoard] = useState<StaffTableBoardEnvelope | null>(null);
  const [order, setOrder] = useState<StaffOrderReadEnvelope | null>(null);
  const [preview, setPreview] = useState<StaffCheckoutSettlementEnvelope | null>(null);
  const [reservationLookup, setReservationLookup] = useState<StaffReservationLookupCollectionEnvelope | null>(null);
  const [reservationOrders, setReservationOrders] = useState<StaffReservationOrderCollectionEnvelope | null>(null);
  const [selectedTableId, setSelectedTableId] = useState<number | null>(null);
  const [selectedReservationId, setSelectedReservationId] = useState<number | null>(null);
  const [orderIdInput, setOrderIdInput] = useState('');
  const [reservationLookupQuery, setReservationLookupQuery] = useState('');
  const [lookupNotice, setLookupNotice] = useState<string | null>(null);
  const [reservationOrdersNotice, setReservationOrdersNotice] = useState<string | null>(null);
  const [discountAmount, setDiscountAmount] = useState('0');
  const [snapshotNotes, setSnapshotNotes] = useState('');
  const [currency, setCurrency] = useState('VND');
  const [paymentMethod, setPaymentMethod] = useState<(typeof paymentOptions)[number]>('Cash');
  const [paymentProvider, setPaymentProvider] = useState<(typeof paymentOptions)[number]>('Cash');
  const [paidAmount, setPaidAmount] = useState('');
  const [transactionCode, setTransactionCode] = useState('');
  const [settlementNotes, setSettlementNotes] = useState('');
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const routeContext = useMemo(() => readOperatorJourneyContext(searchParams), [searchParams]);
  const routeContextKey = searchParams.toString();
  const hasRouteContext = routeContext.tableId !== undefined
    || routeContext.reservationId !== undefined
    || routeContext.orderId !== undefined;
  const appliedRouteContextRef = useRef<string | null>(null);
  const autoLoadedRouteOrderRef = useRef<string | null>(null);
  const autoLoadedRouteReservationRef = useRef<string | null>(null);

  const canViewBoard = hasCapability(session, 'table.board.view');
  const canLookupReservations = hasCapability(session, 'reservation.manage');
  const activeOrders = useMemo(() => board?.data.filter((row) => row.active_order) ?? [], [board]);
  const selectedBoardOrderSource = useMemo(
    () => activeOrders.find((row) => row.table_id === selectedTableId) ?? null,
    [activeOrders, selectedTableId],
  );
  const selectedReservation = useMemo(
    () => reservationLookup?.data.find((entry) => entry.reservation_id === selectedReservationId) ?? null,
    [reservationLookup, selectedReservationId],
  );
  const usingBoardSource = selectedBoardOrderSource !== null;
  const usingReservationLookupSource = !usingBoardSource && selectedReservation !== null;
  const reservationOrderCandidates = reservationOrders?.data ?? [];
  const orderId = order?.data.order.order_id ?? null;
  const orderRowVersion = order?.data.order.row_version ?? null;
  const previewRequiresRefreshReason = !order
    ? 'Tai order detail truoc khi preview hoac finalize settlement.'
    : !preview
      ? 'Can refresh settlement preview cho order hien tai truoc khi finalize.'
      : preview.data.order_id !== orderId
        ? 'Settlement preview hien tai thuoc order khac. Refresh preview cho order dang mo truoc khi finalize.'
        : preview.data.row_version !== orderRowVersion
          ? 'Order row_version da thay doi sau lan preview truoc. Refresh preview de khoa dung snapshot moi nhat.'
          : preview.data.currency !== currency
            ? 'Currency da thay doi sau preview. Refresh preview de xac nhan lai outstanding theo currency hien tai.'
            : null;
  const canFinalizeSettlement = previewRequiresRefreshReason === null;
  const settlementNextAction = !preview
    ? 'Preview required'
    : Number(preview.data.outstanding_amount ?? 0) > 0
      ? `Thu ${formatMoney(preview.data.outstanding_amount, preview.data.currency)}`
      : 'Khong con outstanding';

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

  const applyReservationSource = useCallback((reservation: StaffReservationLookupEntry) => {
    setSelectedTableId(null);
    setSelectedReservationId(reservation.reservation_id);
    setReservationOrders(null);
    setReservationOrdersNotice(null);
  }, []);

  const refreshBoard = useCallback(async () => {
    if (!canViewBoard) {
      setBoard(null);
      setSelectedTableId(null);
      return;
    }

    setBusyKey('refresh-board');

    try {
      const nextBoard = await loadTableBoard(window);

      setBoard(nextBoard);
      setSelectedTableId((currentSelectedTableId) => {
        const nextSelectedTableId =
          currentSelectedTableId && nextBoard.data.some((row) => row.table_id === currentSelectedTableId && row.active_order)
            ? currentSelectedTableId
            : nextBoard.data.find((row) => row.active_order)?.table_id ?? null;
        const preferredOrderSource =
          nextBoard.data.find((row) => row.table_id === nextSelectedTableId && row.active_order) ??
          nextBoard.data.find((row) => row.active_order) ??
          null;

        setOrderIdInput((current) =>
          preferredOrderSource?.active_order?.order_id ? String(preferredOrderSource.active_order.order_id) : current,
        );

        return nextSelectedTableId;
      });
    } catch (cause) {
      handleError(cause, 'Khong tai duoc active orders tu board.');
    } finally {
      setBusyKey(null);
    }
  }, [canViewBoard, handleError, window]);

  const loadReservationLookup = useCallback(async (query: string) => {
    if (!canLookupReservations) {
      setReservationLookup(null);
      setReservationOrders(null);
      setReservationOrdersNotice(null);
      setLookupNotice('Session nay khong co reservation.manage. Settlement lookup se dung board active orders hoac manual fallback.');
      return;
    }

    setBusyKey('refresh-reservations');
    setReservationOrdersNotice(null);

    try {
      const nextLookup = await loadStaffReservations({
        bucket: 'all',
        q: query.trim() || undefined,
        per_page: 8,
        sort: '-start_time',
      });

      setReservationLookup(nextLookup);
      setSelectedReservationId((currentSelectedReservationId) => {
        const canKeepSelection = currentSelectedReservationId !== null
          && nextLookup.data.some((entry) => entry.reservation_id === currentSelectedReservationId);

        if (!canKeepSelection) {
          setReservationOrders(null);
          setReservationOrdersNotice(null);
        }

        return canKeepSelection ? currentSelectedReservationId : null;
      });
      setLookupNotice(null);
    } catch (cause) {
      const normalized = normalizeApiError(cause, 'Khong tai duoc reservation lookup.');
      if (normalized.status === 403) {
        setReservationLookup(null);
        setReservationOrders(null);
        setSelectedReservationId(null);
        setReservationOrdersNotice(null);
        setLookupNotice(
          normalized.requiredCapability
            ? `Reservation lookup bi chan boi capability ${normalized.requiredCapability}. Manual order ID van kha dung.`
            : 'Reservation lookup bi tu choi. Manual order ID van kha dung.',
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

  const handleLoadOrder = useCallback(async (orderId: number | null) => {
    if (!orderId) {
      setError('Can nhap order ID hop le.');
      return;
    }

    setBusyKey('load-order');
    setError(null);

    try {
      const nextOrder = await loadOrderDetail(orderId);
      setOrder(nextOrder);
      setPreview((current) => (
        current?.data.order_id === orderId
        && current.data.row_version === nextOrder.data.order.row_version
        && current.data.currency === (nextOrder.data.order.totals?.currency ?? 'VND')
          ? current
          : null
      ));
      setOrderIdInput(String(orderId));
      setPaidAmount(String(nextOrder.data.order.totals?.outstanding ?? '0'));
      setCurrency(nextOrder.data.order.totals?.currency ?? 'VND');
      setNotice(`Da tai order #${orderId} luc ${formatDateTime(nextOrder.data.order.created_at)}.`);
    } catch (cause) {
      if (normalizeApiError(cause, '').status === 404) {
        setOrder(null);
        setPreview(null);
      }
      handleError(cause, 'Khong tai duoc order detail.');
    } finally {
      setBusyKey(null);
    }
  }, [handleError]);

  const refreshReservationOrders = useCallback(async (reservationId: number) => {
    setBusyKey('refresh-reservation-orders');
    setReservationOrdersNotice(null);

    try {
      const nextOrders = await loadReservationOrders(reservationId);
      const orderCandidates = nextOrders.data ?? [];
      setReservationOrders(nextOrders);

      if (orderCandidates.length === 0) {
        setReservationOrdersNotice(
          'Reservation nay hien chua co order trong canonical lookup. Manual order ID van la fallback cho case historical hoac order duoc xu ly o route Orders.',
        );
        return;
      }

      if (orderCandidates.length === 1) {
        const singleOrder = orderCandidates[0];
        setOrderIdInput(String(singleOrder.order_id));
        setReservationOrdersNotice('Reservation nay chi co 1 order. Page se tu nap order nay de giam thao tac nhap manual order ID.');
        await handleLoadOrder(singleOrder.order_id);
        return;
      }

      setOrderIdInput((current) => {
        const parsedCurrent = Number(current);
        return orderCandidates.some((candidate) => candidate.order_id === parsedCurrent)
          ? current
          : String(orderCandidates[0].order_id);
      });
      setReservationOrdersNotice('Reservation nay co nhieu order. Chon mot order current/historical ben duoi; manual order ID van la fallback khi can doi soat order cu.');
    } catch (cause) {
      const normalized = normalizeApiError(cause, 'Khong tai duoc danh sach order theo reservation.');

      if (normalized.status === 403) {
        setReservationOrders(null);
        setReservationOrdersNotice(
          normalized.requiredCapability
            ? `Reservation-order lookup bi chan boi capability ${normalized.requiredCapability}. Manual order ID van kha dung.`
            : 'Reservation-order lookup bi tu choi. Manual order ID van kha dung.',
        );
        return;
      }

      if (normalized.status === 404) {
        setReservationOrders(null);
        setReservationOrdersNotice('Reservation vua chon khong con kha dung cho order lookup. Refresh canonical lookup de lay du lieu moi nhat hoac dung manual order ID neu dang doi soat order lich su.');
        return;
      }

      if (normalized.status === 422) {
        setReservationOrders(null);
        setReservationOrdersNotice('Reservation-order lookup tra validation_error. Chon reservation co the da stale sau settlement hoac branch context da doi. Refresh lookup hoac dung manual order ID de tiep tuc.');
        return;
      }

      handleError(cause, 'Khong tai duoc danh sach order theo reservation.');
    } finally {
      setBusyKey(null);
    }
  }, [handleError, handleLoadOrder]);

  const bootstrapPage = useCallback(async () => {
    await Promise.all([refreshBoard(), loadReservationLookup('')]);
  }, [loadReservationLookup, refreshBoard]);

  useEffect(() => {
    void bootstrapPage();
  }, [bootstrapPage, session?.staff_api_key_id]);

  useEffect(() => {
    if (!hasRouteContext || appliedRouteContextRef.current === routeContextKey) {
      return;
    }

    if (routeContext.tableId !== undefined) {
      setSelectedTableId(routeContext.tableId);
    }

    if (routeContext.reservationId !== undefined) {
      setSelectedReservationId(routeContext.reservationId);
    }

    if (routeContext.orderId !== undefined) {
      setOrderIdInput(String(routeContext.orderId));
    }

    setNotice(
      routeContext.source === 'board'
        ? 'Da nap active order context tu board. Settlement co the tiep tuc ma khong can nhap lai manual IDs.'
        : 'Da nap operator context tu route.',
    );
    appliedRouteContextRef.current = routeContextKey;
  }, [hasRouteContext, routeContext, routeContextKey]);

  useEffect(() => {
    if (routeContext.orderId === undefined || autoLoadedRouteOrderRef.current === routeContextKey) {
      return;
    }

    autoLoadedRouteOrderRef.current = routeContextKey;
    void handleLoadOrder(routeContext.orderId);
  }, [handleLoadOrder, routeContext.orderId, routeContextKey]);

  useEffect(() => {
    if (
      routeContext.reservationId === undefined
      || routeContext.orderId !== undefined
      || autoLoadedRouteReservationRef.current === routeContextKey
    ) {
      return;
    }

    const matchedReservation = reservationLookup?.data.find(
      (reservation) => reservation.reservation_id === routeContext.reservationId,
    );

    if (!matchedReservation) {
      return;
    }

    setSelectedReservationId(matchedReservation.reservation_id);
    autoLoadedRouteReservationRef.current = routeContextKey;
    void refreshReservationOrders(matchedReservation.reservation_id);
  }, [refreshReservationOrders, reservationLookup, routeContext.orderId, routeContext.reservationId, routeContextKey]);

  useEffect(() => {
    if (!selectedBoardOrderSource?.active_order?.order_id) {
      return;
    }

    setOrderIdInput(String(selectedBoardOrderSource.active_order.order_id));
  }, [selectedBoardOrderSource]);

  return (
    <div className="space-y-6">
      <Panel>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p className="eyebrow">Settlement</p>
            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Bill snapshot, preview va finalize</h2>
            <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
              Flow nay dung `GET /settlement-preview`, `POST /bill-snapshot`, `POST /settlement/finalize`, kem `GET /staff/reservations`
              va `GET /staff/reservations/{'{reservation_id}'}/orders` cho canonical order lookup. Board active-order van la fast path; manual
              order ID chi con la fallback khi lookup khong kha dung hoac operator dang xu ly case dac biet.
            </p>
          </div>
          <div className="flex flex-wrap gap-3">
            <ActionButton onClick={refreshBoard} busy={busyKey === 'refresh-board'} icon={<ReceiptText className="h-4 w-4" />}>
              Refresh active orders
            </ActionButton>
            <ActionButton onClick={refreshReservationLookup} busy={busyKey === 'refresh-reservations'} icon={<Search className="h-4 w-4" />}>
              Refresh lookup
            </ActionButton>
          </div>
        </div>

        <div className="mt-4 flex flex-wrap gap-2">
          <StatusPill value={`Board window ${formatDateTime(window.from)} - ${formatDateTime(window.to)}`} />
          {order ? <StatusPill value={`Order #${order.data.order.order_id}`} tone="info" /> : null}
          {preview ? (
            <StatusPill
              value={`Settlement ${humanizeCode(preview.data.payment_status)}`}
              tone={canFinalizeSettlement ? 'success' : 'warning'}
            />
          ) : null}
        </div>
      </Panel>

      {notice ? <Banner tone="success">{notice}</Banner> : null}
      {error ? <Banner tone="error">{error}</Banner> : null}

      <div className="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
        <Panel>
          <p className="eyebrow">Order selector</p>
          <h3 className="text-xl font-semibold text-slate-950">Nguon order can settle</h3>
          {canViewBoard ? (
            <>
              <div className="mt-4 space-y-3">
                {activeOrders.map((row) => (
                  <button
                    key={row.table_id}
                    type="button"
                    onClick={() => {
                      setSelectedReservationId(null);
                      setReservationOrders(null);
                      setReservationOrdersNotice(null);
                      setSelectedTableId(row.table_id);
                      setOrderIdInput(String(row.active_order?.order_id ?? ''));
                      void handleLoadOrder(row.active_order?.order_id ?? null);
                    }}
                    className={`w-full rounded-[24px] border p-4 text-left transition ${
                      selectedTableId === row.table_id ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white hover:bg-slate-50'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="font-semibold text-slate-900">{row.table_code}</p>
                        <p className="mt-1 text-xs text-slate-500">
                          Order #{row.active_order?.order_id} | {row.reservation?.reservation_code ?? 'No reservation'}
                        </p>
                      </div>
                      <StatusPill value={humanizeCode(row.active_order?.status ?? 'active')} tone="warning" />
                    </div>
                  </button>
                ))}
              </div>

              {selectedBoardOrderSource ? (
                <div className="mt-5 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                  <div className="grid gap-3 md:grid-cols-2">
                    <MetricCard label="Table" value={selectedBoardOrderSource.table_code} />
                    <MetricCard label="Order" value={`#${selectedBoardOrderSource.active_order?.order_id ?? 'N/A'}`} />
                    <MetricCard label="Reservation" value={selectedBoardOrderSource.reservation?.reservation_code ?? 'N/A'} />
                    <MetricCard label="Order rv" value={String(selectedBoardOrderSource.active_order?.row_version ?? '-')} />
                  </div>
                </div>
              ) : null}
            </>
          ) : (
            <div className="mt-4">
              <EmptyState
                title="Board active-order fast path dang tat"
                description="Session hien tai khong co `table.board.view`, vi vay settlement se uu tien canonical reservation lookup roi moi xuong manual order ID."
              />
            </div>
          )}

          <div className="mt-5 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-sm font-semibold text-slate-900">Canonical reservation lookup</p>
                <p className="mt-1 text-sm text-slate-600">
                  Dung `GET /staff/reservations` de tim reservation, sau do nap `GET /staff/reservations/{'{reservation_id}'}/orders` de chon
                  current/historical order tu backend thay vi yeu cau operator nho manual `order_id`.
                </p>
              </div>
              <StatusPill
                value={canLookupReservations ? 'Lookup enabled' : 'Manual fallback'}
                tone={canLookupReservations ? 'success' : 'warning'}
              />
            </div>

            {canLookupReservations ? (
              <>
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

                {lookupNotice ? (
                  <div className="mt-4">
                    <EmptyState title="Reservation lookup khong kha dung" description={lookupNotice} />
                  </div>
                ) : reservationLookup?.data.length ? (
                  <div className="mt-4 grid gap-3">
                    {reservationLookup.data.map((reservation) => (
                      <button
                        key={reservation.reservation_id}
                        type="button"
                      onClick={() => {
                          applyReservationSource(reservation);
                          void refreshReservationOrders(reservation.reservation_id);
                        }}
                        className={`rounded-[20px] border px-4 py-3 text-left transition ${
                          selectedReservationId === reservation.reservation_id
                            ? 'border-sky-300 bg-sky-50'
                            : 'border-slate-200 bg-white hover:bg-slate-50'
                        }`}
                      >
                        <div className="flex items-start justify-between gap-3">
                          <div>
                            <p className="font-semibold text-slate-900">{reservation.reservation_code}</p>
                            <p className="mt-1 text-xs text-slate-500">
                              {reservation.user?.full_name ?? reservation.user?.phone ?? 'Unknown guest'} | tables {reservation.tables.map((table) => table.table_code).join(', ') || 'N/A'}
                            </p>
                          </div>
                          <StatusPill value={humanizeCode(reservation.status)} tone="info" />
                        </div>
                      </button>
                    ))}
                  </div>
                ) : (
                  <div className="mt-4">
                    <EmptyState
                      title="Chua co ket qua reservation lookup"
                      description="Nhap reservation code, guest, phone hoac table roi refresh lookup. Manual order ID van duoc giu cho case ngoai lookup."
                    />
                  </div>
                )}
              </>
            ) : (
              <div className="mt-4">
                <EmptyState
                  title="Reservation lookup khong duoc cap quyen"
                  description="Page nay van hoat dong voi board active-order fast path neu co, hoac manual order ID khi session khong co `reservation.manage`."
                />
              </div>
            )}
          </div>

          {selectedReservation ? (
            <div className="mt-5 rounded-[24px] border border-slate-200 bg-white p-5">
              <div className="grid gap-3 md:grid-cols-3">
                <MetricCard label="Reservation" value={selectedReservation.reservation_code} />
                <MetricCard label="Table" value={selectedReservation.tables[0]?.table_code ?? 'Nhap tay'} />
                <MetricCard label="Row version" value={String(selectedReservation.row_version)} />
              </div>

              {reservationOrdersNotice ? (
                <div className="mt-4 rounded-[20px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800">
                  {reservationOrdersNotice}
                </div>
              ) : null}

              {reservationOrderCandidates.length > 0 ? (
                <div className="mt-4 grid gap-3">
                  {reservationOrderCandidates.map((candidate) => (
                    <button
                      key={candidate.order_id}
                      type="button"
                      onClick={() => void handleLoadOrder(candidate.order_id)}
                      className="rounded-[20px] border border-slate-200 bg-slate-50 px-4 py-3 text-left transition hover:bg-white"
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className="font-semibold text-slate-900">Order #{candidate.order_id}</p>
                          <p className="mt-1 text-xs text-slate-500">
                            {humanizeCode(candidate.status)} | {humanizeCode(candidate.order_type)}
                          </p>
                        </div>
                        <StatusPill value={`rv ${candidate.row_version ?? '-'}`} tone="info" />
                      </div>
                    </button>
                  ))}
                </div>
              ) : selectedReservationId ? (
                <div className="mt-4">
                  <EmptyState
                    title="Reservation nay chua co order"
                    description="Neu can settle order khac thi tiep tuc manual fallback. Neu can order moi, mo route Orders de tao order theo reservation nay."
                  />
                </div>
              ) : null}
            </div>
          ) : null}

          <div className="mt-5 rounded-[24px] bg-slate-50 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-sm font-semibold text-slate-900">Load order / preview</p>
                <p className="mt-1 text-sm text-slate-600">
                  {usingBoardSource
                    ? 'Dang dung board active-order fast path. Sua Order ID se chuyen sang manual fallback.'
                    : usingReservationLookupSource
                      ? 'Dang dung canonical reservation lookup. Chon order candidate hoac sua Order ID de xuong manual fallback.'
                      : 'Dang dung manual fallback. Co the chon lai active order tu board hoac reservation lookup de lay canonical source.'}
                </p>
              </div>
              <StatusPill
                value={usingBoardSource ? 'Source board' : usingReservationLookupSource ? 'Source lookup' : 'Source manual'}
                tone={usingBoardSource ? 'success' : usingReservationLookupSource ? 'info' : 'warning'}
              />
            </div>

            <label className="mt-4 block rounded-2xl border border-slate-200 bg-white px-4 py-3">
              <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Order ID</span>
              <input
                value={orderIdInput}
                onChange={(event) => {
                  setSelectedTableId(null);
                  setSelectedReservationId(null);
                  setReservationOrders(null);
                  setReservationOrdersNotice(null);
                  setOrderIdInput(event.target.value);
                }}
                className="mt-3 w-full bg-transparent text-sm outline-none"
                inputMode="numeric"
              />
            </label>
            <div className="mt-4 flex flex-wrap gap-3">
              <ActionButton onClick={() => handleLoadOrder(Number(orderIdInput) || null)} busy={busyKey === 'load-order'} icon={<ReceiptText className="h-4 w-4" />}>
                Tai order
              </ActionButton>
              <ActionButton onClick={handlePreview} busy={busyKey === 'preview'} icon={<CreditCard className="h-4 w-4" />}>
                Settlement preview
              </ActionButton>
            </div>
          </div>

          {order ? (
            <div className="mt-5 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
              <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <MetricCard label="Order" value={`#${order.data.order.order_id}`} />
                <MetricCard label="Row version" value={String(order.data.order.row_version ?? '-')} />
                <MetricCard label="Reservation" value={order.data.reservation?.reservation_code ?? 'N/A'} />
                <MetricCard label="Outstanding" value={formatMoney(order.data.order.totals?.outstanding, order.data.order.totals?.currency ?? 'VND')} />
              </div>

              <div className="mt-4 rounded-[20px] border border-slate-200 bg-white p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-slate-900">Finalize guard</p>
                    <p className="mt-1 text-sm text-slate-600">
                      Bill snapshot co the dung `order detail`. Finalize settlement chi mo khi preview van khop `order_id`, `row_version`, va `currency` hien tai.
                    </p>
                  </div>
                  <StatusPill value={canFinalizeSettlement ? 'Preview ready' : 'Preview stale'} tone={canFinalizeSettlement ? 'success' : 'warning'} />
                </div>
                <div className="mt-4 grid gap-3 md:grid-cols-3">
                  <MetricCard label="Mutation rv source" value={`Order detail rv ${orderRowVersion ?? '-'}`} />
                  <MetricCard label="Preview rv" value={preview ? `Preview rv ${preview.data.row_version}` : 'Chua preview'} />
                  <MetricCard label="Next action" value={settlementNextAction} />
                </div>
                {previewRequiresRefreshReason ? (
                  <div className="mt-4 rounded-[18px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800">
                    {previewRequiresRefreshReason}
                  </div>
                ) : null}
              </div>
            </div>
          ) : null}
        </Panel>

        <Panel>
          <p className="eyebrow">Settlement actions</p>
          <h3 className="text-xl font-semibold text-slate-950">Snapshot truoc, finalize sau</h3>

          {!order ? (
            <div className="mt-4">
              <EmptyState
                title="Chua chon order"
                description="Tai order detail truoc khi bill snapshot hoac finalize settlement."
              />
            </div>
          ) : (
            <>
              <div className="mt-4 grid gap-3 md:grid-cols-2">
                <label className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                  <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Discount amount</span>
                  <input
                    value={discountAmount}
                    onChange={(event) => setDiscountAmount(event.target.value)}
                    className="mt-3 w-full bg-transparent text-sm outline-none"
                    inputMode="decimal"
                  />
                </label>
                <label className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                  <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Currency</span>
                  <input
                    value={currency}
                    onChange={(event) => setCurrency(event.target.value.toUpperCase())}
                    className="mt-3 w-full bg-transparent text-sm outline-none"
                  />
                </label>
              </div>

              <label className="mt-3 block rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Bill snapshot notes</span>
                <textarea
                  value={snapshotNotes}
                  onChange={(event) => setSnapshotNotes(event.target.value)}
                  rows={3}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                />
              </label>

              <div className="mt-4">
                <ActionButton onClick={handleBillSnapshot} busy={busyKey === 'snapshot'} icon={<FileSpreadsheet className="h-4 w-4" />}>
                  Tao canonical bill snapshot
                </ActionButton>
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
                    <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Paid amount</span>
                    <input
                      value={paidAmount}
                      onChange={(event) => setPaidAmount(event.target.value)}
                      className="mt-3 w-full bg-transparent text-sm outline-none"
                      inputMode="decimal"
                    />
                  </label>
                  <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                    <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Transaction code</span>
                    <input
                      value={transactionCode}
                      onChange={(event) => setTransactionCode(event.target.value)}
                      className="mt-3 w-full bg-transparent text-sm outline-none"
                    />
                  </label>
                </div>
                <label className="mt-3 block rounded-2xl border border-slate-200 bg-white px-4 py-3">
                  <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Settlement notes</span>
                  <textarea
                    value={settlementNotes}
                    onChange={(event) => setSettlementNotes(event.target.value)}
                    rows={3}
                    className="mt-3 w-full bg-transparent text-sm outline-none"
                  />
                </label>
                <div className="mt-4 flex flex-wrap gap-3">
                  <ActionButton onClick={handlePreview} busy={busyKey === 'preview'} icon={<CreditCard className="h-4 w-4" />}>
                    Refresh preview
                  </ActionButton>
                  <ActionButton onClick={handleFinalize} busy={busyKey === 'finalize'} disabled={!canFinalizeSettlement} icon={<CreditCard className="h-4 w-4" />}>
                    Finalize settlement
                  </ActionButton>
                </div>
              </div>
            </>
          )}
        </Panel>
      </div>

      <Panel>
        <p className="eyebrow">Settlement response</p>
        <h3 className="text-xl font-semibold text-slate-950">Preview / finalize envelope</h3>
        {!preview ? (
          <div className="mt-4">
            <EmptyState
              title="Chua co settlement preview"
              description="Dung nut Settlement preview de doi soat tong tien, deposit da ap dung, outstanding va payment_status."
            />
          </div>
        ) : (
          <div className="mt-5 grid gap-4 md:grid-cols-3 xl:grid-cols-4">
            <MetricCard label="Row version" value={String(preview.data.row_version)} />
            <MetricCard label="Total" value={formatMoney(preview.data.total_amount, preview.data.currency)} />
            <MetricCard label="Paid" value={formatMoney(preview.data.paid_amount, preview.data.currency)} />
            <MetricCard label="Outstanding" value={formatMoney(preview.data.outstanding_amount, preview.data.currency)} />
            <MetricCard label="Deposit applied" value={formatMoney(preview.data.deposit_applied_amount, preview.data.currency)} />
            <MetricCard label="Final paid" value={formatMoney(preview.data.final_paid_amount, preview.data.currency)} />
            <MetricCard label="Status" value={`${humanizeCode(preview.data.payment_status)} / ${humanizeCode(preview.data.order_status)}`} />
          </div>
        )}
      </Panel>
    </div>
  );

  async function handleBillSnapshot() {
    const orderId = order?.data.order.order_id;
    const rowVersion = order?.data.order.row_version;

    if (!orderId || !rowVersion) {
      setError('Can tai order co row_version truoc khi bill snapshot.');
      return;
    }

    setBusyKey('snapshot');
    setError(null);

    try {
      await createBillSnapshot(orderId, {
        row_version: rowVersion,
        discount_amount: Number(discountAmount) || 0,
        notes: snapshotNotes.trim() || null,
      });
      setNotice(`Da tao bill snapshot cho order #${orderId}.`);
      await handleLoadOrder(orderId);
      await handlePreview(orderId);
      await Promise.all([
        refreshBoard(),
        selectedReservationId ? refreshReservationOrders(selectedReservationId) : Promise.resolve(),
      ]);
    } catch (cause) {
      if (isRowVersionConflict(cause)) {
        setError(rowVersionConflictMessage(`Order #${orderId}`));
      } else {
        handleError(cause, 'Khong the tao bill snapshot.');
      }
    } finally {
      setBusyKey(null);
    }
  }

  async function handlePreview(explicitOrderId?: number) {
    const orderId = explicitOrderId ?? order?.data.order.order_id ?? Number(orderIdInput);

    if (!orderId) {
      setError('Can nhap order ID hop le de xem settlement preview.');
      return;
    }

    setBusyKey('preview');
    setError(null);

    try {
      const nextPreview = await loadSettlementPreview(orderId, {
        currency,
      });
      setPreview(nextPreview);
      setPaidAmount(String(nextPreview.data.outstanding_amount));
      setNotice(`Da doi soat settlement preview cho order #${orderId}.`);
    } catch (cause) {
      handleError(cause, 'Khong tai duoc settlement preview.');
    } finally {
      setBusyKey(null);
    }
  }

  async function handleFinalize() {
    const orderId = order?.data.order.order_id;
    const rowVersion = order?.data.order.row_version;

    if (!orderId || !rowVersion) {
      setError('Can tai order co row_version truoc khi finalize settlement.');
      return;
    }

    if (previewRequiresRefreshReason) {
      setError(previewRequiresRefreshReason);
      return;
    }

    setBusyKey('finalize');
    setError(null);

    try {
      const nextPreview = await finalizeSettlement(orderId, {
        payment_method: paymentMethod,
        payment_provider: paymentProvider,
        paid_amount: Number(paidAmount) || 0,
        currency,
        transaction_code: transactionCode.trim() || null,
        notes: settlementNotes.trim() || null,
        row_version: rowVersion,
      });

      setPreview(nextPreview);
      setNotice(`Da finalize settlement cho order #${orderId}.`);
      await handleLoadOrder(orderId);
      await Promise.all([
        refreshBoard(),
        refreshReservationLookup(),
        selectedReservationId ? refreshReservationOrders(selectedReservationId) : Promise.resolve(),
      ]);
    } catch (cause) {
      if (isRowVersionConflict(cause)) {
        setError(rowVersionConflictMessage(`Order #${orderId}`));
      } else {
        handleError(cause, 'Khong the finalize settlement.');
      }
    } finally {
      setBusyKey(null);
    }
  }
}
