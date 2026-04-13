import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { ChefHat, CreditCard, Plus, ReceiptText, Search } from 'lucide-react';
import {
  addOrderItems,
  buildBoardWindow as boardWindow,
  createTableOrder,
  getOrderDetail as loadOrderDetail,
  listMenuItems as loadMenuItems,
  listReservationOrders as loadReservationOrders,
  listReservations as loadStaffReservations,
  getTableBoard as loadTableBoard,
} from '../../core/api/staff-api';
import { canAccessStaffSection, staffSections } from '../../app/sections';
import { useStaffSession } from '../../app/session-context';
import { formatApiError, isApiStatus, normalizeApiError } from '../../core/api/errors';
import { hasCapability } from '../../lib/capabilities';
import { isRowVersionConflict, rowVersionConflictMessage } from '../../lib/conflicts';
import { formatDateTime, formatMoney, humanizeCode } from '../../lib/format';
import { buildOperatorJourneySearch, readOperatorJourneyContext } from '../../lib/operatorJourney';
import type {
  CustomerMenuItemsCollectionEnvelope,
  StaffOrderReadEnvelope,
  StaffReservationLookupCollectionEnvelope,
  StaffReservationLookupEntry,
  StaffReservationOrderCollectionEnvelope,
  StaffTableBoardEnvelope,
} from '../../api/sdk';
import { ActionButton, Banner, EmptyState, MetricCard, Panel, StatusPill } from '../../components/ui';

const settlementSection = staffSections.find((section) => section.path === '/settlement') ?? null;
const kitchenSection = staffSections.find((section) => section.path === '/kitchen') ?? null;
type StaffBoardWindow = ReturnType<typeof boardWindow>;

export function OrdersPage() {
  const [searchParams] = useSearchParams();
  const { expire, session } = useStaffSession();
  const [window] = useState<StaffBoardWindow>(() => boardWindow());
  const [board, setBoard] = useState<StaffTableBoardEnvelope | null>(null);
  const [menu, setMenu] = useState<CustomerMenuItemsCollectionEnvelope | null>(null);
  const [order, setOrder] = useState<StaffOrderReadEnvelope | null>(null);
  const [reservationLookup, setReservationLookup] = useState<StaffReservationLookupCollectionEnvelope | null>(null);
  const [reservationOrders, setReservationOrders] = useState<StaffReservationOrderCollectionEnvelope | null>(null);
  const [selectedTableId, setSelectedTableId] = useState<number | null>(null);
  const [selectedReservationId, setSelectedReservationId] = useState<number | null>(null);
  const [manualTableId, setManualTableId] = useState('');
  const [manualReservationId, setManualReservationId] = useState('');
  const [manualRowVersion, setManualRowVersion] = useState('');
  const [createNotes, setCreateNotes] = useState('');
  const [orderIdInput, setOrderIdInput] = useState('');
  const [menuQuery, setMenuQuery] = useState('');
  const [reservationLookupQuery, setReservationLookupQuery] = useState('');
  const [selectedMenuItemId, setSelectedMenuItemId] = useState<number | null>(null);
  const [itemQty, setItemQty] = useState('1');
  const [itemNote, setItemNote] = useState('');
  const [lookupNotice, setLookupNotice] = useState<string | null>(null);
  const [reservationOrdersNotice, setReservationOrdersNotice] = useState<string | null>(null);
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
  const hasSettlementCapability = hasCapability(session, 'settlement.manage');
  const canOpenSettlement = settlementSection ? canAccessStaffSection(session, settlementSection) : false;
  const canOpenKitchen = kitchenSection ? canAccessStaffSection(session, kitchenSection) : false;
  const selectedTable = useMemo(
    () => board?.data.find((row) => row.table_id === selectedTableId) ?? null,
    [board, selectedTableId],
  );
  const selectedReservation = useMemo(
    () => reservationLookup?.data.find((entry) => entry.reservation_id === selectedReservationId) ?? null,
    [reservationLookup, selectedReservationId],
  );
  const usingBoardSource = selectedTable !== null;
  const usingReservationLookupSource = !usingBoardSource && selectedReservation !== null;
  const activeOrderSources = useMemo(
    () => (board?.data ?? []).filter((row) => row.active_order),
    [board],
  );
  const menuOptions = menu?.data.filter((item) => item.is_available) ?? [];
  const reservationOrderCandidates = reservationOrders?.data ?? [];
  const settlementBlockedMessage = !hasSettlementCapability
    ? 'Session nay khong co settlement.manage nen khong mo duoc bill snapshot tu Orders.'
    : session?.startup.readiness.requires_cashier_shift && session.startup.readiness.cashier_shift === 'action_required'
      ? 'Can active cashier shift truoc khi mo bill snapshot tu Orders.'
      : settlementSection && !canOpenSettlement
        ? 'Settlement dang bi khoa cho session nay.'
        : null;
  const settlementHandoffSearch = order
    ? buildOperatorJourneySearch({
        source: usingBoardSource || routeContext.source === 'board' ? 'board' : undefined,
        tableId:
          selectedTable?.table_id
          ?? selectedReservation?.table_ids[0]
          ?? routeContext.tableId
          ?? parsePositiveInteger(manualTableId),
        reservationId:
          selectedTable?.reservation?.reservation_id
          ?? selectedReservation?.reservation_id
          ?? routeContext.reservationId
          ?? parsePositiveInteger(manualReservationId),
        reservationRowVersion:
          selectedTable?.reservation?.row_version
          ?? selectedReservation?.row_version
          ?? routeContext.reservationRowVersion
          ?? parsePositiveInteger(manualRowVersion),
        orderId: order.data.order.order_id,
      })
    : '';
  const kitchenHandoffSearch = order
    ? buildOperatorJourneySearch({
        source: usingBoardSource || routeContext.source === 'board' ? 'board' : undefined,
        tableId:
          selectedTable?.table_id
          ?? selectedReservation?.table_ids[0]
          ?? routeContext.tableId
          ?? parsePositiveInteger(manualTableId),
        reservationId:
          selectedTable?.reservation?.reservation_id
          ?? selectedReservation?.reservation_id
          ?? routeContext.reservationId
          ?? parsePositiveInteger(manualReservationId),
        reservationRowVersion:
          selectedTable?.reservation?.row_version
          ?? selectedReservation?.row_version
          ?? routeContext.reservationRowVersion
          ?? parsePositiveInteger(manualRowVersion),
        orderId: order.data.order.order_id,
        orderRowVersion: order.data.order.row_version ?? undefined,
      })
    : '';

  const handleError = useCallback(
    (cause: unknown, fallback: string) => {
      if (isApiStatus(cause, 401)) {
        expire('Phien staff da het han. Dang nhap lai de tiep tuc.');
        return;
      }

      setError(formatApiError(cause, fallback));
    },
    [expire],
  );

  const applyReservationSource = useCallback((reservation: StaffReservationLookupEntry) => {
    setSelectedTableId(null);
    setSelectedReservationId(reservation.reservation_id);
    setReservationOrders(null);
    setReservationOrdersNotice(null);
    setManualReservationId(String(reservation.reservation_id));
    setManualRowVersion(String(reservation.row_version));
    setManualTableId(reservation.table_ids[0] ? String(reservation.table_ids[0]) : '');
  }, []);

  const refreshBoard = useCallback(async () => {
    if (!canViewBoard) {
      setBoard(null);
      setSelectedTableId(null);
      return;
    }

    setBusyKey('refresh-board');

    try {
      const nextBoard = await loadTableBoard({
        ...window,
        include_holds: true,
        group_by: 'zone',
      });

      setBoard(nextBoard);
      setSelectedTableId((currentSelectedTableId) => {
        const nextSelectedTableId =
          currentSelectedTableId && nextBoard.data.some((row) => row.table_id === currentSelectedTableId)
            ? currentSelectedTableId
            : nextBoard.data.find((row) => row.reservation || row.active_order)?.table_id ?? null;
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
      handleError(cause, 'Khong tai duoc order board suggestions.');
    } finally {
      setBusyKey(null);
    }
  }, [canViewBoard, handleError, window]);

  const refreshMenu = useCallback(async () => {
    setBusyKey('refresh-menu');

    try {
      const nextMenu = await loadMenuItems({
        per_page: 12,
        q: menuQuery.trim() || undefined,
        service_time: new Date().toISOString(),
      });

      setMenu(nextMenu);
      setSelectedMenuItemId((current) => (current && nextMenu.data.some((item) => item.item_id === current) ? current : nextMenu.data[0]?.item_id ?? null));
    } catch (cause) {
      handleError(cause, 'Khong tai duoc menu items.');
    } finally {
      setBusyKey(null);
    }
  }, [handleError, menuQuery]);

  const loadReservationLookup = useCallback(async (query: string) => {
    if (!canLookupReservations) {
      setReservationLookup(null);
      setReservationOrders(null);
      setReservationOrdersNotice(null);
      setLookupNotice('Session nay khong co reservation.manage. Reservation lookup se dung manual fallback.');
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
            ? `Reservation lookup bi chan boi capability ${normalized.requiredCapability}. Manual IDs van kha dung.`
            : 'Reservation lookup bi tu choi. Manual IDs van kha dung.',
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
      setOrderIdInput(String(orderId));
      setNotice(`Da tai order #${orderId}.`);
    } catch (cause) {
      if (normalizeApiError(cause, '').status === 404) {
        setOrder(null);
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
          'Reservation nay hien chua co order trong canonical lookup. Neu can mo order moi, giu reservation source nay de tao order. Manual order ID van la fallback cho case historical.',
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
      setReservationOrdersNotice('Reservation nay co nhieu order. Chon mot order ben duoi hoac tiep tuc manual order ID neu can doi soat case cu.');
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
        setReservationOrdersNotice('Reservation-order lookup tra validation_error. Reservation co the da stale sau mutation. Refresh lookup hoac dung manual order ID de tiep tuc.');
        return;
      }

      handleError(cause, 'Khong tai duoc danh sach order theo reservation.');
    } finally {
      setBusyKey(null);
    }
  }, [handleError, handleLoadOrder]);

  const bootstrapPage = useCallback(async () => {
    await Promise.all([
      refreshBoard(),
      loadMenuItems({
        per_page: 12,
        service_time: new Date().toISOString(),
      })
        .then((nextMenu) => {
          setMenu(nextMenu);
          setSelectedMenuItemId(nextMenu.data[0]?.item_id ?? null);
        })
        .catch((cause) => {
          handleError(cause, 'Khong tai duoc menu items.');
        }),
      loadReservationLookup(''),
    ]);
  }, [handleError, loadReservationLookup, refreshBoard]);

  useEffect(() => {
    void bootstrapPage();
  }, [bootstrapPage, session?.staff_api_key_id]);

  useEffect(() => {
    if (!hasRouteContext || appliedRouteContextRef.current === routeContextKey) {
      return;
    }

    if (routeContext.tableId !== undefined) {
      setSelectedTableId(routeContext.tableId);
      setManualTableId(String(routeContext.tableId));
    }

    if (routeContext.reservationId !== undefined) {
      setSelectedReservationId(routeContext.reservationId);
      setManualReservationId(String(routeContext.reservationId));
    }

    if (routeContext.reservationRowVersion !== undefined) {
      setManualRowVersion(String(routeContext.reservationRowVersion));
    }

    if (routeContext.orderId !== undefined) {
      setOrderIdInput(String(routeContext.orderId));
    }

    setNotice(
      routeContext.source === 'board'
        ? 'Da nap table/reservation context tu board. Operator khong can reconstruct IDs bang tay de tiep tuc.'
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
    if (!selectedTable) {
      return;
    }

    setManualTableId(String(selectedTable.table_id));
    setManualReservationId(selectedTable.reservation?.reservation_id ? String(selectedTable.reservation.reservation_id) : '');
    setManualRowVersion(selectedTable.reservation?.row_version ? String(selectedTable.reservation.row_version) : '');
    setOrderIdInput((current) =>
      selectedTable.active_order?.order_id ? String(selectedTable.active_order.order_id) : current,
    );
  }, [selectedTable]);

  useEffect(() => {
    if (!selectedReservation || usingBoardSource) {
      return;
    }

    setManualReservationId(String(selectedReservation.reservation_id));
    setManualRowVersion(String(selectedReservation.row_version));
    setManualTableId(selectedReservation.table_ids[0] ? String(selectedReservation.table_ids[0]) : '');
  }, [selectedReservation, usingBoardSource]);

  return (
    <div className="space-y-6">
      <Panel>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p className="eyebrow">Order lifecycle</p>
            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Create order, add items, read detail</h2>
            <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
              Page nay bind truc tiep vao `POST /staff/tables/{'{table_id}'}/orders`, `POST /staff/orders/{'{order_id}'}/items`,
              `GET /staff/orders/{'{order_id}'}`, `GET /staff/reservations` va `GET /staff/reservations/{'{reservation_id}'}/orders`.
              Board van la source uu tien cho floor state, nhung historical/non-board cases nay da co canonical reservation lookup va reservation-order lookup,
              manual IDs chi con la fallback ro rang.
            </p>
          </div>
          <div className="flex flex-wrap gap-3">
            <ActionButton onClick={refreshBoard} busy={busyKey === 'refresh-board'} icon={<ReceiptText className="h-4 w-4" />}>
              Refresh board
            </ActionButton>
            <ActionButton onClick={refreshReservationLookup} busy={busyKey === 'refresh-reservations'} icon={<Search className="h-4 w-4" />}>
              Refresh lookup
            </ActionButton>
            <ActionButton onClick={refreshMenu} busy={busyKey === 'refresh-menu'} icon={<Search className="h-4 w-4" />}>
              Refresh menu
            </ActionButton>
          </div>
        </div>

        <div className="mt-4 flex flex-wrap gap-2">
          <StatusPill value={`Menu items ${menu?.meta.total ?? menuOptions.length}`} tone="success" />
          <StatusPill value={`Board window ${formatDateTime(window.from)} - ${formatDateTime(window.to)}`} />
          {order ? <StatusPill value={`Order #${order.data.order.order_id}`} tone="info" /> : null}
        </div>
      </Panel>

      {notice ? <Banner tone="success">{notice}</Banner> : null}
      {error ? <Banner tone="error">{error}</Banner> : null}

      <div className="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
        <Panel>
          <p className="eyebrow">Create order</p>
          <h3 className="text-xl font-semibold text-slate-950">Nguon row_version</h3>

          {canViewBoard ? (
            <>
              <div className="mt-4 grid gap-3 md:grid-cols-2">
                {(board?.data ?? [])
                  .filter((row) => row.reservation || row.active_order)
                  .map((row) => (
                    <button
                      key={row.table_id}
                      type="button"
                      onClick={() => {
                        setSelectedReservationId(null);
                        setReservationOrders(null);
                        setReservationOrdersNotice(null);
                        setSelectedTableId(row.table_id);
                      }}
                      className={`rounded-[24px] border p-4 text-left transition ${
                        selectedTableId === row.table_id ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white hover:bg-slate-50'
                      }`}
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className="eyebrow">{row.zone ?? 'No zone'}</p>
                          <p className="mt-2 text-lg font-semibold text-slate-950">{row.table_code}</p>
                        </div>
                        <StatusPill value={row.active_order ? `Order #${row.active_order.order_id}` : humanizeCode(row.board_state)} />
                      </div>
                      <p className="mt-3 text-sm text-slate-600">
                        Reservation: {row.reservation?.reservation_code ?? 'Khong co'} | row_version: {row.reservation?.row_version ?? '-'}
                      </p>
                    </button>
                  ))}
              </div>

              {selectedTable ? (
                <div className="mt-5 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                  <div className="grid gap-3 md:grid-cols-2">
                    <MetricCard label="Table" value={selectedTable.table_code} />
                    <MetricCard label="Reservation" value={selectedTable.reservation?.reservation_code ?? 'Khong co'} />
                    <MetricCard label="Guest" value={String(selectedTable.reservation?.guest_count ?? 0)} />
                    <MetricCard label="Row version" value={String(selectedTable.reservation?.row_version ?? '-')} />
                  </div>
                  {selectedTable.active_order ? (
                    <div className="mt-4">
                      <ActionButton
                        onClick={() => handleLoadOrder(selectedTable.active_order?.order_id ?? null)}
                        busy={busyKey === 'load-order'}
                        icon={<ReceiptText className="h-4 w-4" />}
                      >
                        Tai active order
                      </ActionButton>
                    </div>
                  ) : null}
                </div>
              ) : null}
            </>
          ) : (
            <div className="mt-4">
              <EmptyState
                title="Board suggestions dang tat"
                description="Session nay khong co `table.board.view`, vi vay create-order se uu tien canonical reservation lookup roi moi xuong manual fallback."
              />
            </div>
          )}

          <div className="mt-5 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-sm font-semibold text-slate-900">Canonical reservation lookup</p>
                <p className="mt-1 text-sm text-slate-600">
                  Dung `GET /staff/reservations` de tim reservation theo code, guest, phone hoac table, sau do nap
                  `GET /staff/reservations/{'{reservation_id}'}/orders` de chon order lich su ma khong can nho tay IDs.
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
                          <StatusPill value={`rv ${reservation.row_version}`} tone="info" />
                        </div>
                      </button>
                    ))}
                  </div>
                ) : (
                  <div className="mt-4">
                    <EmptyState
                      title="Chua co ket qua reservation lookup"
                      description="Nhap reservation code, guest, phone hoac table roi refresh lookup. Manual IDs van duoc giu neu operator dang xu ly case ngoai lookup."
                    />
                  </div>
                )}

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
                          description="Neu can mo order moi, giu reservation source nay va tao order. Neu can case dac biet, manual order ID van la fallback."
                        />
                      </div>
                    ) : null}
                  </div>
                ) : null}
              </>
            ) : (
              <div className="mt-4">
                <EmptyState
                  title="Reservation lookup khong duoc cap quyen"
                  description="Page nay van hoat dong voi board suggestions neu co, hoac manual table/reservation/order IDs khi session khong co `reservation.manage`."
                />
              </div>
            )}
          </div>

          <div className="mt-5 rounded-[24px] bg-slate-50 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-sm font-semibold text-slate-900">Create order request</p>
                <p className="mt-1 text-sm text-slate-600">
                  {usingBoardSource
                    ? 'Dang dung reservation source tu board. Sua bat ky ID nao se chuyen sang manual fallback.'
                    : usingReservationLookupSource
                      ? 'Dang dung canonical reservation lookup. Table ID van co the override neu reservation co nhieu ban hoac can fallback.'
                      : 'Dang dung manual fallback. Co the chon lai board suggestion hoac reservation lookup de lay source row_version moi nhat.'}
                </p>
              </div>
              <StatusPill
                value={usingBoardSource ? 'Source board' : usingReservationLookupSource ? 'Source lookup' : 'Source manual'}
                tone={usingBoardSource ? 'success' : usingReservationLookupSource ? 'info' : 'warning'}
              />
            </div>
            <div className="mt-4 grid gap-3 md:grid-cols-3">
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Table ID</span>
                <input
                  value={manualTableId}
                  onChange={(event) => {
                    setSelectedTableId(null);
                    setSelectedReservationId(null);
                    setReservationOrders(null);
                    setReservationOrdersNotice(null);
                    setManualTableId(event.target.value);
                  }}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                  inputMode="numeric"
                />
              </label>
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Reservation ID</span>
                <input
                  value={manualReservationId}
                  onChange={(event) => {
                    setSelectedTableId(null);
                    setSelectedReservationId(null);
                    setReservationOrders(null);
                    setReservationOrdersNotice(null);
                    setManualReservationId(event.target.value);
                  }}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                  inputMode="numeric"
                />
              </label>
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Row version</span>
                <input
                  value={manualRowVersion}
                  onChange={(event) => {
                    setSelectedTableId(null);
                    setSelectedReservationId(null);
                    setReservationOrders(null);
                    setReservationOrdersNotice(null);
                    setManualRowVersion(event.target.value);
                  }}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                  inputMode="numeric"
                />
              </label>
            </div>
            <label className="mt-3 block rounded-2xl border border-slate-200 bg-white px-4 py-3">
              <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Notes</span>
              <textarea
                value={createNotes}
                onChange={(event) => setCreateNotes(event.target.value)}
                rows={3}
                className="mt-3 w-full bg-transparent text-sm outline-none"
              />
            </label>
            <div className="mt-4">
              <div className="flex flex-wrap gap-3">
                <ActionButton onClick={handleCreateOrder} busy={busyKey === 'create-order'} icon={<Plus className="h-4 w-4" />}>
                  Tao order
                </ActionButton>
                {(usingBoardSource || usingReservationLookupSource) ? (
                  <ActionButton
                    onClick={() => {
                      setSelectedTableId(null);
                      setSelectedReservationId(null);
                      setReservationOrders(null);
                      setReservationOrdersNotice(null);
                    }}
                    busy={false}
                    icon={<Search className="h-4 w-4" />}
                    className="border border-slate-200 bg-white text-slate-900"
                  >
                    Dung manual IDs
                  </ActionButton>
                ) : null}
              </div>
            </div>
          </div>
        </Panel>

        <Panel>
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Menu catalog</p>
              <h3 className="text-xl font-semibold text-slate-950">Add items vao order hien tai</h3>
            </div>
            <ActionButton onClick={refreshMenu} busy={busyKey === 'refresh-menu'} icon={<Search className="h-4 w-4" />}>
              Search
            </ActionButton>
          </div>

          <label className="mt-4 block rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Search menu</span>
            <input
              value={menuQuery}
              onChange={(event) => setMenuQuery(event.target.value)}
              className="mt-3 w-full bg-transparent text-sm outline-none"
              placeholder="Pho, coffee, combo..."
            />
          </label>

          <div className="mt-4 space-y-3">
            {menuOptions.map((item) => (
              <button
                key={item.item_id}
                type="button"
                onClick={() => setSelectedMenuItemId(item.item_id)}
                className={`w-full rounded-[24px] border p-4 text-left transition ${
                  selectedMenuItemId === item.item_id ? 'border-sky-300 bg-sky-50' : 'border-slate-200 bg-white hover:bg-slate-50'
                }`}
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-semibold text-slate-900">{item.name}</p>
                    <p className="mt-1 text-xs text-slate-500">{item.code} | {item.category_name ?? 'Uncategorized'}</p>
                  </div>
                  <StatusPill value={formatMoney(item.price.amount, item.price.currency ?? 'VND')} tone="success" />
                </div>
                {item.description ? <p className="mt-3 text-sm text-slate-600">{item.description}</p> : null}
              </button>
            ))}
          </div>

            <div className="mt-5 rounded-[24px] bg-slate-50 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-sm font-semibold text-slate-900">Load order / add items</p>
                <p className="mt-1 text-sm text-slate-600">
                  Active order tu board van la fast path. Reservation lookup da them danh sach order theo reservation cho case lich su; manual Order ID chi con la fallback.
                </p>
              </div>
              <StatusPill value={order ? `rv ${order.data.order.row_version ?? '-'}` : 'Chua load order'} tone="info" />
            </div>

            {activeOrderSources.length > 0 ? (
              <div className="mt-4 grid gap-3">
                {activeOrderSources.map((row) => (
                  <button
                    key={`${row.table_id}-${row.active_order?.order_id ?? 'order'}`}
                    type="button"
                    onClick={() => {
                      setSelectedReservationId(null);
                      setReservationOrders(null);
                      setReservationOrdersNotice(null);
                      setSelectedTableId(row.table_id);
                      setOrderIdInput(String(row.active_order?.order_id ?? ''));
                      void handleLoadOrder(row.active_order?.order_id ?? null);
                    }}
                    className="rounded-[20px] border border-slate-200 bg-white px-4 py-3 text-left transition hover:bg-slate-50"
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="font-semibold text-slate-900">
                          Order #{row.active_order?.order_id} | {row.table_code}
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                          Reservation {row.reservation?.reservation_code ?? 'N/A'} | board row_version {row.reservation?.row_version ?? '-'}
                        </p>
                      </div>
                      <StatusPill value={`rv ${row.active_order?.row_version ?? '-'}`} tone="info" />
                    </div>
                  </button>
                ))}
              </div>
            ) : null}

            <div className="mt-4 grid gap-3 md:grid-cols-[1fr_auto]">
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Order ID</span>
                <input
                  value={orderIdInput}
                  onChange={(event) => {
                    setSelectedReservationId(null);
                    setReservationOrders(null);
                    setReservationOrdersNotice(null);
                    setOrderIdInput(event.target.value);
                  }}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                  inputMode="numeric"
                />
              </label>
              <div className="flex items-end">
                <ActionButton onClick={() => handleLoadOrder(Number(orderIdInput) || null)} busy={busyKey === 'load-order'} icon={<ReceiptText className="h-4 w-4" />}>
                  Tai order
                </ActionButton>
              </div>
            </div>

            <div className="mt-4 grid gap-3 md:grid-cols-3">
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Menu item</span>
                <select
                  value={selectedMenuItemId ?? ''}
                  onChange={(event) => setSelectedMenuItemId(event.target.value ? Number(event.target.value) : null)}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                >
                  <option value="">Chon menu item</option>
                  {menuOptions.map((item) => (
                    <option key={item.item_id} value={item.item_id}>
                      {item.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Qty</span>
                <input
                  value={itemQty}
                  onChange={(event) => setItemQty(event.target.value)}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                  inputMode="numeric"
                />
              </label>
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Note</span>
                <input
                  value={itemNote}
                  onChange={(event) => setItemNote(event.target.value)}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                />
              </label>
            </div>
            <div className="mt-4">
              <ActionButton onClick={handleAddItem} busy={busyKey === 'add-item'} icon={<Plus className="h-4 w-4" />}>
                Them item vao order
              </ActionButton>
            </div>
          </div>
        </Panel>
      </div>

      <Panel>
        <p className="eyebrow">Order detail</p>
        <h3 className="text-xl font-semibold text-slate-950">Backend source of truth</h3>
        {!order ? (
          <div className="mt-4">
            <EmptyState
              title="Chua co order detail"
              description="Chon order tu board, reservation lookup, hoac nhap Order ID de tai `GET /api/v1/staff/orders/{order_id}`."
            />
          </div>
        ) : (
          <div className="mt-5 grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
            <div className="rounded-[24px] border border-slate-200 bg-slate-50 p-5">
              <div className="grid gap-3">
                <MetricCard label="Order" value={`#${order.data.order.order_id}`} />
                <MetricCard label="Status" value={humanizeCode(order.data.order.status)} />
                <MetricCard label="Payment" value={humanizeCode(order.data.order.payment_status ?? 'pending')} />
                <MetricCard label="Reservation" value={order.data.reservation?.reservation_code ?? 'N/A'} />
                <MetricCard label="Customer" value={order.data.customer?.full_name ?? order.data.customer?.phone ?? 'Walk-in / N/A'} />
                <MetricCard label="Created" value={formatDateTime(order.data.order.created_at)} />
              </div>
            </div>

            <div className="space-y-3">
              {order.data.items.map((item) => (
                <div key={item.order_item_id} className="rounded-[24px] border border-slate-200 bg-white p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-slate-900">
                        {item.item?.name ?? item.item_name_snapshot ?? `Item #${item.item_id}`}
                      </p>
                      <p className="mt-1 text-xs text-slate-500">
                        x{item.quantity} | {humanizeCode(item.status)} | {item.notes ?? 'No note'}
                      </p>
                    </div>
                    <StatusPill value={formatMoney(item.line_total, item.currency)} tone="success" />
                  </div>
                </div>
              ))}

              <div className="rounded-[24px] bg-slate-50 p-5">
                <div className="grid gap-3 md:grid-cols-2">
                  <MetricCard label="Subtotal" value={formatMoney(order.data.order.totals?.subtotal, order.data.order.totals?.currency ?? 'VND')} />
                  <MetricCard label="Total due" value={formatMoney(order.data.order.totals?.total_due, order.data.order.totals?.currency ?? 'VND')} />
                  <MetricCard label="Outstanding" value={formatMoney(order.data.order.totals?.outstanding, order.data.order.totals?.currency ?? 'VND')} />
                  <MetricCard label="Items" value={String(order.data.item_summary.quantity_total)} />
                </div>
              </div>

              <div className="rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-slate-900">Next step</p>
                    <p className="mt-1 text-sm text-slate-600">
                      Bill snapshot co the nhan tiep order context nay de operator khong phai nhap lai `order_id` va reservation source.
                    </p>
                  </div>
                  {canOpenSettlement ? (
                    <Link
                      to={`/settlement?${settlementHandoffSearch}`}
                      className="inline-flex items-center gap-2 rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white"
                    >
                      <CreditCard className="h-4 w-4" />
                      Mo bill snapshot
                    </Link>
                  ) : (
                    <StatusPill value="Settlement locked" tone="warning" />
                  )}
                  {canOpenKitchen ? (
                    <Link
                      to={`/kitchen?${kitchenHandoffSearch}`}
                      className="inline-flex items-center gap-2 rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                    >
                      <ChefHat className="h-4 w-4" />
                      Mo Kitchen dispatch
                    </Link>
                  ) : null}
                </div>
                {settlementBlockedMessage ? <p className="mt-3 text-sm leading-6 text-slate-600">{settlementBlockedMessage}</p> : null}
              </div>
            </div>
          </div>
        )}
      </Panel>
    </div>
  );

  async function handleCreateOrder() {
    const tableId = usingBoardSource ? selectedTable.table_id : Number(manualTableId);
    const reservationId = usingBoardSource
      ? (selectedTable.reservation?.reservation_id ?? 0)
      : usingReservationLookupSource
        ? (selectedReservation?.reservation_id ?? 0)
        : Number(manualReservationId);
    const rowVersion = usingBoardSource
      ? (selectedTable.reservation?.row_version ?? 0)
      : usingReservationLookupSource
        ? (selectedReservation?.row_version ?? 0)
        : Number(manualRowVersion);

    if (!tableId || !reservationId || !rowVersion) {
      setError('Can co table_id, reservation_id va row_version hop le de tao order.');
      return;
    }

    setBusyKey('create-order');
    setError(null);

    try {
      const created = await createTableOrder(tableId, {
        reservation_id: reservationId,
        row_version: rowVersion,
        notes: createNotes.trim() || null,
      });

      setOrderIdInput(String(created.data.order_id));
      setNotice(`Da tao order #${created.data.order_id} cho reservation #${reservationId}.`);
      await handleLoadOrder(created.data.order_id);
      await Promise.all([
        refreshBoard(),
        refreshReservationLookup(),
        usingReservationLookupSource ? refreshReservationOrders(reservationId) : Promise.resolve(),
      ]);
    } catch (cause) {
      if (isRowVersionConflict(cause)) {
        setError(rowVersionConflictMessage(`Reservation #${reservationId}`));
      } else {
        handleError(cause, 'Khong the tao order.');
      }
    } finally {
      setBusyKey(null);
    }
  }

  async function handleAddItem() {
    const orderId = order?.data.order.order_id ?? Number(orderIdInput);
    const rowVersion = order?.data.order.row_version;

    if (!orderId || !rowVersion || !selectedMenuItemId || !Number(itemQty)) {
      setError('Can co order da load, row_version moi, menu item va qty hop le de them mon.');
      return;
    }

    setBusyKey('add-item');
    setError(null);

    try {
      await addOrderItems(orderId, {
        row_version: rowVersion,
        items: [
          {
            menu_item_id: selectedMenuItemId,
            qty: Number(itemQty),
            note: itemNote.trim() || null,
          },
        ],
      });

      setNotice(`Da them item vao order #${orderId}.`);
      setItemNote('');
      setItemQty('1');
      await handleLoadOrder(orderId);
    } catch (cause) {
      if (isRowVersionConflict(cause)) {
        setError(rowVersionConflictMessage(`Order #${orderId}`));
      } else {
        handleError(cause, 'Khong the them item vao order.');
      }
    } finally {
      setBusyKey(null);
    }
  }

}

function parsePositiveInteger(value: string): number | undefined {
  const parsed = Number(value);

  if (!Number.isInteger(parsed) || parsed <= 0) {
    return undefined;
  }

  return parsed;
}
