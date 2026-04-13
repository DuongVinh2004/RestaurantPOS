import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { CheckCircle2, Flame, RefreshCcw, RotateCcw, SendHorizontal, Waves } from 'lucide-react';
import {
  bumpKitchenTicket,
  dispatchKitchenOrder,
  fireKitchenTicket,
  getKitchenChanges as loadKitchenChanges,
  listKitchenStations as loadKitchenStations,
  getKitchenStationTickets as loadKitchenStationTickets,
  recallKitchenTicket,
} from '../../core/api/staff-api';
import { useStaffSession } from '../../app/session-context';
import { formatApiError, isApiStatus, normalizeApiError } from '../../core/api/errors';
import { isRowVersionConflict, rowVersionConflictMessage } from '../../lib/conflicts';
import { formatDateTime, humanizeCode } from '../../lib/format';
import { readOperatorJourneyContext } from '../../lib/operatorJourney';
import type {
  KitchenOrderItemTicket,
  KitchenStation,
  StaffKitchenDispatchEnvelope,
  StaffKitchenStationCollectionEnvelope,
  StaffKitchenTicketCollectionEnvelope,
  StaffOperationalRealtimeEnvelope,
} from '../../api/sdk';
import { ActionButton, Banner, EmptyState, MetricCard, Panel, StatusPill } from '../../components/ui';

const ticketStatusOptions = ['all', 'Queued', 'Fired', 'Ready', 'Completed', 'Cancelled'] as const;

type TicketActionKey = 'fire' | 'bump' | 'recall';

export function KitchenPage() {
  const [searchParams] = useSearchParams();
  const { expire, session } = useStaffSession();
  const [stations, setStations] = useState<StaffKitchenStationCollectionEnvelope | null>(null);
  const [tickets, setTickets] = useState<StaffKitchenTicketCollectionEnvelope | null>(null);
  const [changes, setChanges] = useState<StaffOperationalRealtimeEnvelope | null>(null);
  const [selectedStationId, setSelectedStationId] = useState<number | null>(null);
  const [selectedTicketId, setSelectedTicketId] = useState<number | null>(null);
  const [ticketStatusFilter, setTicketStatusFilter] = useState<(typeof ticketStatusOptions)[number]>('all');
  const [includeTerminal, setIncludeTerminal] = useState(false);
  const [dispatchOrderIdInput, setDispatchOrderIdInput] = useState('');
  const [dispatchRowVersionInput, setDispatchRowVersionInput] = useState('');
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const routeContext = useMemo(() => readOperatorJourneyContext(searchParams), [searchParams]);
  const routeContextKey = searchParams.toString();
  const appliedRouteContextRef = useRef<string | null>(null);

  const selectedStation = useMemo(
    () => stations?.data.find((station) => station.station_id === selectedStationId) ?? null,
    [selectedStationId, stations],
  );
  const selectedTicket = useMemo(
    () => tickets?.data.find((ticket) => ticket.ticket_id === selectedTicketId) ?? null,
    [selectedTicketId, tickets],
  );
  const stationRealtimeVersion = stations?.meta?.realtime.current_version;
  const dispatchHint = routeContext.orderId
    ? 'Da nap order context tu route. Co the dispatch ngay vao kitchen ma khong can reconstruct ID bang Postman.'
    : 'Dispatch nam trong staff-web nay. `row_version` la optional, nhung nen gui khi operator vua load order de tranh stale write.';

  const handlePageError = useCallback((cause: unknown, fallback: string) => {
    if (isApiStatus(cause, 401)) {
      expire('Phien staff da het han. Dang nhap lai de tiep tuc.');
      return;
    }

    setError(formatKitchenOperatorError(cause, fallback));
  }, [expire]);

  const refreshStations = useCallback(async (
    options: { preferredStationId?: number | null; silent?: boolean } = {},
  ) => {
    const silent = options.silent ?? false;

    if (!silent) {
      setBusyKey('refresh-stations');
      setError(null);
    }

    try {
      const nextStations = await loadKitchenStations();
      const nextSelectedStationId =
        options.preferredStationId && nextStations.data.some((station) => station.station_id === options.preferredStationId)
          ? options.preferredStationId
          : selectedStationId && nextStations.data.some((station) => station.station_id === selectedStationId)
            ? selectedStationId
            : nextStations.data[0]?.station_id ?? null;

      setStations(nextStations);
      setSelectedStationId(nextSelectedStationId);

      return { stations: nextStations, selectedStationId: nextSelectedStationId };
    } catch (cause) {
      handlePageError(cause, 'Khong tai duoc danh sach kitchen stations.');
      return { stations: null, selectedStationId: null };
    } finally {
      if (!silent) {
        setBusyKey(null);
      }
    }
  }, [handlePageError, selectedStationId]);

  const refreshTickets = useCallback(async (
    stationId: number | null,
    options: { preferredTicketId?: number | null; silent?: boolean } = {},
  ) => {
    if (!stationId) {
      setTickets(null);
      setSelectedTicketId(null);
      return null;
    }

    const silent = options.silent ?? false;

    if (!silent) {
      setBusyKey('refresh-tickets');
      setError(null);
    }

    try {
      const nextTickets = await loadKitchenStationTickets(stationId, {
        status: ticketStatusFilter === 'all' ? undefined : ticketStatusFilter,
        include_terminal: includeTerminal,
      });
      const nextSelectedTicketId =
        options.preferredTicketId && nextTickets.data.some((ticket) => ticket.ticket_id === options.preferredTicketId)
          ? options.preferredTicketId
          : selectedTicketId && nextTickets.data.some((ticket) => ticket.ticket_id === selectedTicketId)
            ? selectedTicketId
            : nextTickets.data[0]?.ticket_id ?? null;

      setTickets(nextTickets);
      setSelectedTicketId(nextSelectedTicketId);

      return nextTickets;
    } catch (cause) {
      handlePageError(cause, 'Khong tai duoc ticket list cho station nay.');
      return null;
    } finally {
      if (!silent) {
        setBusyKey(null);
      }
    }
  }, [handlePageError, includeTerminal, selectedTicketId, ticketStatusFilter]);

  const refreshKitchenSlices = useCallback(async (
    options: { preferredStationId?: number | null; preferredTicketId?: number | null; silent?: boolean } = {},
  ) => {
    const stationState = await refreshStations({
      preferredStationId: options.preferredStationId,
      silent: options.silent,
    });
    const stationIdForTickets = stationState.selectedStationId ?? options.preferredStationId ?? selectedStationId ?? null;

    await refreshTickets(stationIdForTickets, {
      preferredTicketId: options.preferredTicketId,
      silent: options.silent,
    });
  }, [refreshStations, refreshTickets, selectedStationId]);

  const checkChanges = useCallback(async () => {
    setBusyKey('changes');
    setError(null);

    try {
      const nextChanges = await loadKitchenChanges(changes?.data.current_version ?? stationRealtimeVersion);

      setChanges(nextChanges);

      if (nextChanges.data.has_changes || nextChanges.data.stale_cursor) {
        await refreshKitchenSlices({
          preferredStationId: selectedStationId,
          preferredTicketId: selectedTicketId,
          silent: true,
        });
        setNotice(
          nextChanges.data.stale_cursor
            ? 'Kitchen cursor da stale. Da reload station va ticket list de lay state moi nhat.'
            : 'Kitchen co thay doi moi. Da reload station va ticket list.',
        );
        return;
      }

      setNotice('Da doi soat kitchen change feed. Chua co thay doi moi.');
    } catch (cause) {
      handlePageError(cause, 'Khong doi soat duoc kitchen change feed.');
    } finally {
      setBusyKey(null);
    }
  }, [changes?.data.current_version, handlePageError, refreshKitchenSlices, selectedStationId, selectedTicketId, stationRealtimeVersion]);

  useEffect(() => {
    void refreshStations();
  }, [refreshStations, session?.staff_api_key_id]);

  useEffect(() => {
    if (!selectedStationId) {
      setTickets(null);
      setSelectedTicketId(null);
      return;
    }

    void refreshTickets(selectedStationId);
  }, [includeTerminal, refreshTickets, selectedStationId, ticketStatusFilter]);

  useEffect(() => {
    if (appliedRouteContextRef.current === routeContextKey) {
      return;
    }

    if (routeContext.orderId !== undefined) {
      setDispatchOrderIdInput(String(routeContext.orderId));
    }

    if (routeContext.orderRowVersion !== undefined) {
      setDispatchRowVersionInput(String(routeContext.orderRowVersion));
    }

    if (routeContext.orderId !== undefined) {
      setNotice('Da nap order context tu route. Kitchen dispatch co the tiep tuc ngay trong staff-web.');
    }

    appliedRouteContextRef.current = routeContextKey;
  }, [routeContext, routeContextKey]);

  return (
    <div className="space-y-6">
      <Panel>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p className="eyebrow">Kitchen / KDS</p>
            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Station scan, ticket state, safe actions</h2>
            <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
              Surface nay giu scope gon cho day-1 kitchen ops: doc station list, doc ticket list or detail, dispatch order vao kitchen, va chi
              mo cac transition an toan `Queued -&gt; Fired -&gt; Ready -&gt; Fired`. Neu backend bao stale state hoac transition khong hop le, page se
              reload lai kitchen state thay vi de operator doan ticket dang o trang thai nao.
            </p>
          </div>
          <div className="flex flex-wrap gap-3">
            <ActionButton onClick={checkChanges} busy={busyKey === 'changes'} icon={<Waves className="h-4 w-4" />}>
              Check changes
            </ActionButton>
            <ActionButton
              onClick={() => { void refreshKitchenSlices({ preferredStationId: selectedStationId, preferredTicketId: selectedTicketId }); }}
              busy={busyKey === 'refresh-stations' || busyKey === 'refresh-tickets'}
              icon={<RefreshCcw className="h-4 w-4" />}
            >
              Lam moi
            </ActionButton>
          </div>
        </div>

        <div className="mt-4 flex flex-wrap gap-2">
          <StatusPill value={`Stations ${stations?.data.length ?? 0}`} tone="success" />
          <StatusPill value={`Filter ${ticketStatusFilter}`} />
          {selectedStation ? <StatusPill value={`Selected ${selectedStation.code}`} tone="info" /> : null}
          {stationRealtimeVersion ? <StatusPill value={`Kitchen v${stationRealtimeVersion}`} tone="info" /> : null}
        </div>
      </Panel>

      {notice ? <Banner tone="success">{notice}</Banner> : null}
      {error ? <Banner tone="error">{error}</Banner> : null}

      <div className="grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
        <Panel>
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Station list</p>
              <h3 className="text-xl font-semibold text-slate-950">Kitchen lanes and load</h3>
            </div>
            <div className="grid grid-cols-2 gap-2 text-right">
              <MetricCard label="Queued" value={String(sumStationCount(stations, 'queued'))} />
              <MetricCard label="Ready" value={String(sumStationCount(stations, 'ready'))} />
            </div>
          </div>

          {(stations?.data ?? []).length === 0 ? (
            <div className="mt-5">
              <EmptyState
                title="Chua co kitchen station"
                description="Khi backend tra ve station, page nay se hien route_count, output_mode va ticket_counts de operator scan lane nhanh hon."
              />
            </div>
          ) : (
            <div className="mt-5 space-y-3">
              {(stations?.data ?? []).map((station) => (
                <button
                  key={station.station_id}
                  type="button"
                  onClick={() => setSelectedStationId(station.station_id)}
                  className={`w-full rounded-[24px] border p-4 text-left transition ${
                    selectedStationId === station.station_id
                      ? 'border-amber-300 bg-amber-50'
                      : 'border-slate-200 bg-white hover:bg-slate-50'
                  }`}
                >
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-slate-900">{station.name}</p>
                      <p className="mt-1 text-xs text-slate-500">
                        {station.code} | {humanizeCode(station.output_mode)} | routes {station.route_count}
                      </p>
                    </div>
                    <StatusPill value={station.is_active ? 'Active' : 'Inactive'} tone={station.is_active ? 'success' : 'danger'} />
                  </div>
                  <div className="mt-4 grid gap-3 md:grid-cols-3">
                    <MetricCard label="Queued" value={String(station.ticket_counts.queued)} />
                    <MetricCard label="Fired" value={String(station.ticket_counts.fired)} />
                    <MetricCard label="Ready" value={String(station.ticket_counts.ready)} />
                  </div>
                </button>
              ))}
            </div>
          )}

          {selectedStation ? (
            <div className="mt-5 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p className="eyebrow">Selected station</p>
                  <h4 className="text-xl font-semibold text-slate-950">{selectedStation.name}</h4>
                </div>
                <div className="flex flex-wrap gap-2">
                  <StatusPill value={humanizeCode(selectedStation.output_mode)} tone="info" />
                  {selectedStation.printer_target ? <StatusPill value={`Printer ${selectedStation.printer_target}`} /> : null}
                </div>
              </div>
              <div className="mt-4 grid gap-3 md:grid-cols-2">
                <MetricCard label="Routes" value={String(selectedStation.route_count)} />
                <MetricCard label="Updated" value={formatDateTime(selectedStation.updated_at)} />
                <MetricCard label="Queued" value={String(selectedStation.ticket_counts.queued)} />
                <MetricCard label="Ready" value={String(selectedStation.ticket_counts.ready)} />
              </div>
            </div>
          ) : null}

          <div className="mt-5 rounded-[24px] bg-slate-50 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-sm font-semibold text-slate-900">Dispatch order vao kitchen</p>
                <p className="mt-1 text-sm text-slate-600">{dispatchHint}</p>
              </div>
              <StatusPill value={routeContext.orderId ? 'Route handoff' : 'Manual fallback'} tone={routeContext.orderId ? 'info' : 'warning'} />
            </div>
            <div className="mt-4 grid gap-3 md:grid-cols-2">
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Dispatch order ID</span>
                <input
                  value={dispatchOrderIdInput}
                  onChange={(event) => setDispatchOrderIdInput(event.target.value)}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                  inputMode="numeric"
                />
              </label>
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Order row version</span>
                <input
                  value={dispatchRowVersionInput}
                  onChange={(event) => setDispatchRowVersionInput(event.target.value)}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                  inputMode="numeric"
                  placeholder="Optional but recommended"
                />
              </label>
            </div>
            <div className="mt-4">
              <ActionButton onClick={handleDispatch} busy={busyKey === 'dispatch'} icon={<SendHorizontal className="h-4 w-4" />}>
                Dispatch order
              </ActionButton>
            </div>
          </div>
        </Panel>

        <Panel>
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Ticket list</p>
              <h3 className="text-xl font-semibold text-slate-950">Selected station work queue</h3>
            </div>
            <div className="grid grid-cols-2 gap-2 text-right">
              <MetricCard label="Tickets" value={String(tickets?.data.length ?? 0)} />
              <MetricCard label="Cursor events" value={String(changes?.data.events.length ?? 0)} />
            </div>
          </div>

          <div className="mt-5 rounded-[24px] bg-slate-50 p-5">
            <div className="grid gap-3 md:grid-cols-[1fr_auto_auto]">
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Ticket status</span>
                <select
                  value={ticketStatusFilter}
                  onChange={(event) => setTicketStatusFilter(event.target.value as (typeof ticketStatusOptions)[number])}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                >
                  {ticketStatusOptions.map((option) => (
                    <option key={option} value={option}>
                      {option}
                    </option>
                  ))}
                </select>
              </label>
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Terminal</span>
                <select
                  value={includeTerminal ? 'include' : 'active'}
                  onChange={(event) => setIncludeTerminal(event.target.value === 'include')}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                >
                  <option value="active">Active only</option>
                  <option value="include">Include terminal</option>
                </select>
              </label>
              <div className="flex items-end">
                <ActionButton
                  onClick={() => { void refreshTickets(selectedStationId); }}
                  busy={busyKey === 'refresh-tickets'}
                  icon={<RefreshCcw className="h-4 w-4" />}
                >
                  Reload tickets
                </ActionButton>
              </div>
            </div>
          </div>

          {!selectedStation ? (
            <div className="mt-5">
              <EmptyState
                title="Chua chon station"
                description="Chon mot kitchen station ben trai de tai ticket list va KDS detail."
              />
            </div>
          ) : (tickets?.data ?? []).length === 0 ? (
            <div className="mt-5">
              <EmptyState
                title="Station nay chua co ticket"
                description="Co the dispatch order vao kitchen o panel ben trai hoac doi soat change feed de refresh lane nay."
              />
            </div>
          ) : (
            <>
              <div className="mt-5 space-y-3">
                {(tickets?.data ?? []).map((ticket) => (
                  <button
                    key={ticket.ticket_id}
                    type="button"
                    onClick={() => setSelectedTicketId(ticket.ticket_id)}
                    className={`w-full rounded-[24px] border p-4 text-left transition ${
                      selectedTicketId === ticket.ticket_id
                        ? 'border-sky-300 bg-sky-50'
                        : 'border-slate-200 bg-white hover:bg-slate-50'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="font-semibold text-slate-900">{ticketTitle(ticket)}</p>
                        <p className="mt-1 text-xs text-slate-500">
                          Order #{ticket.order.order_id} | item status {humanizeCode(ticket.order_item?.status ?? 'unknown')} | route {ticket.route?.route_id ?? 'N/A'}
                        </p>
                      </div>
                      <StatusPill value={humanizeCode(ticket.ticket_status)} tone={ticketTone(ticket.ticket_status)} />
                    </div>
                  </button>
                ))}
              </div>

              {selectedTicket ? (
                <div className="mt-5 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <p className="eyebrow">Selected ticket</p>
                      <h4 className="text-xl font-semibold text-slate-950">{ticketTitle(selectedTicket)}</h4>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <StatusPill value={humanizeCode(selectedTicket.ticket_status)} tone={ticketTone(selectedTicket.ticket_status)} />
                      <StatusPill value={`Dispatch ${selectedTicket.dispatch_count}`} />
                      {selectedTicket.recall_count > 0 ? <StatusPill value={`Recall ${selectedTicket.recall_count}`} tone="warning" /> : null}
                    </div>
                  </div>

                  <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard label="Order" value={`#${selectedTicket.order.order_id}`} />
                    <MetricCard label="Reservation" value={`#${selectedTicket.order.reservation_id}`} />
                    <MetricCard label="Output" value={humanizeCode(selectedTicket.output_mode)} />
                    <MetricCard label="Printer" value={selectedTicket.printer_target ?? 'N/A'} />
                    <MetricCard label="Route source" value={selectedTicket.route_source ?? 'N/A'} />
                    <MetricCard label="Route active" value={selectedTicket.routing.route_active === null ? 'N/A' : selectedTicket.routing.route_active ? 'Yes' : 'No'} />
                    <MetricCard label="Station match" value={selectedTicket.routing.station_matches_route === null ? 'N/A' : selectedTicket.routing.station_matches_route ? 'Yes' : 'No'} />
                    <MetricCard label="Order item" value={selectedTicket.order_item ? `#${selectedTicket.order_item.order_item_id}` : 'N/A'} />
                  </div>

                  {selectedTicket.ticket_notes ? (
                    <div className="mt-4 rounded-[18px] border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-600">
                      {selectedTicket.ticket_notes}
                    </div>
                  ) : null}

                  {!selectedTicket.routing.route_present ? (
                    <div className="mt-4 rounded-[18px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800">
                      Ticket nay dang khong con route snapshot active. Co the can redispatch order sau khi route duoc cap nhat.
                    </div>
                  ) : null}

                  <div className="mt-4 grid gap-3 md:grid-cols-2">
                    <MetricCard label="First dispatched" value={formatDateTime(selectedTicket.first_dispatched_at)} />
                    <MetricCard label="Fired at" value={formatDateTime(selectedTicket.fired_at)} />
                    <MetricCard label="Ready at" value={formatDateTime(selectedTicket.ready_at)} />
                    <MetricCard label="Last recalled" value={formatDateTime(selectedTicket.last_recalled_at)} />
                  </div>

                  <div className="mt-5 flex flex-wrap gap-3">
                    {renderTicketActionButton(selectedTicket, busyKey, handleTicketAction)}
                  </div>
                </div>
              ) : null}
            </>
          )}
        </Panel>
      </div>
    </div>
  );

  async function handleDispatch() {
    const orderId = parsePositiveInteger(dispatchOrderIdInput);
    const rowVersion = parsePositiveInteger(dispatchRowVersionInput);

    if (!orderId) {
      setError('Can nhap order ID hop le de dispatch vao kitchen.');
      return;
    }

    setBusyKey('dispatch');
    setError(null);

    try {
      const dispatched = await dispatchKitchenOrder(orderId, rowVersion ? { row_version: rowVersion } : {});
      await applyDispatchResult(orderId, dispatched);
    } catch (cause) {
      await handleMutationFailure(cause, `Khong the dispatch order #${orderId} vao kitchen.`);
    } finally {
      setBusyKey(null);
    }
  }

  async function handleTicketAction(action: TicketActionKey) {
    if (!selectedTicket) {
      return;
    }

    const actionKey = `${action}-${selectedTicket.ticket_id}`;
    setBusyKey(actionKey);
    setError(null);

    try {
      if (action === 'fire') {
        await fireKitchenTicket(selectedTicket.ticket_id);
      } else if (action === 'bump') {
        await bumpKitchenTicket(selectedTicket.ticket_id);
      } else {
        await recallKitchenTicket(selectedTicket.ticket_id);
      }

      await refreshKitchenSlices({
        preferredStationId: selectedStationId,
        preferredTicketId: selectedTicket.ticket_id,
        silent: true,
      });
      setNotice(`Da ${humanizeCode(action)} ticket #${selectedTicket.ticket_id}.`);
    } catch (cause) {
      await handleMutationFailure(cause, `Khong the ${humanizeCode(action).toLowerCase()} ticket #${selectedTicket.ticket_id}.`);
    } finally {
      setBusyKey(null);
    }
  }

  async function applyDispatchResult(orderId: number, dispatched: StaffKitchenDispatchEnvelope) {
    const preferredStationId = dispatched.data[0]?.station?.station_id ?? selectedStationId;
    const preferredTicketId = dispatched.data[0]?.ticket_id ?? null;
    await refreshKitchenSlices({
      preferredStationId,
      preferredTicketId,
      silent: true,
    });

    const summaryParts = [
      `created ${dispatched.meta?.created_count ?? 0}`,
      `reused ${dispatched.meta?.reused_count ?? 0}`,
    ];

    if ((dispatched.meta?.unrouted_count ?? 0) > 0) {
      summaryParts.push(`unrouted ${dispatched.meta?.unrouted_count ?? 0}`);
    }

    if ((dispatched.meta?.pinned_route_count ?? 0) > 0) {
      summaryParts.push(`pinned ${dispatched.meta?.pinned_route_count ?? 0}`);
    }

    setNotice(`Da dispatch order #${orderId} vao kitchen (${summaryParts.join(', ')}).`);
  }

  async function handleMutationFailure(cause: unknown, fallback: string) {
    if (isRowVersionConflict(cause)) {
      const orderId = parsePositiveInteger(dispatchOrderIdInput);
      setError(orderId ? rowVersionConflictMessage(`Order #${orderId}`) : rowVersionConflictMessage('Kitchen state'));
    } else {
      setError(formatKitchenOperatorError(cause, fallback));
    }

    if (!shouldReloadKitchenState(cause)) {
      if (isApiStatus(cause, 401)) {
        expire('Phien staff da het han. Dang nhap lai de tiep tuc.');
      }
      return;
    }

    await refreshKitchenSlices({
      preferredStationId: selectedStationId,
      preferredTicketId: selectedTicketId,
      silent: true,
    });
    setNotice('Kitchen state da doi tren backend. Da reload station va ticket list de operator tiep tuc tren du lieu moi nhat.');
  }
}

function renderTicketActionButton(
  ticket: KitchenOrderItemTicket,
  busyKey: string | null,
  onAction: (action: TicketActionKey) => void | Promise<void>,
) {
  const action = nextTicketAction(ticket);

  if (!action) {
    return (
      <StatusPill
        value={ticket.ticket_status === 'Cancelled' ? 'Ticket cancelled' : 'No safe action'}
        tone={ticket.ticket_status === 'Cancelled' ? 'danger' : 'neutral'}
      />
    );
  }

  return (
    <ActionButton
      onClick={() => onAction(action.key)}
      busy={busyKey === `${action.key}-${ticket.ticket_id}`}
      icon={action.icon}
    >
      {action.label}
    </ActionButton>
  );
}

function nextTicketAction(ticket: KitchenOrderItemTicket): { key: TicketActionKey; label: string; icon: JSX.Element } | null {
  if (ticket.ticket_status === 'Queued') {
    return { key: 'fire', label: 'Fire ticket', icon: <Flame className="h-4 w-4" /> };
  }

  if (ticket.ticket_status === 'Fired') {
    return { key: 'bump', label: 'Bump to ready', icon: <CheckCircle2 className="h-4 w-4" /> };
  }

  if (ticket.ticket_status === 'Ready') {
    return { key: 'recall', label: 'Recall ticket', icon: <RotateCcw className="h-4 w-4" /> };
  }

  return null;
}

function ticketTitle(ticket: KitchenOrderItemTicket): string {
  return ticket.item?.name ?? ticket.order_item?.item_name_snapshot ?? `Ticket #${ticket.ticket_id}`;
}

function ticketTone(status: string): 'neutral' | 'success' | 'warning' | 'danger' | 'info' {
  switch (status) {
    case 'Queued':
      return 'warning';
    case 'Fired':
      return 'info';
    case 'Ready':
      return 'success';
    case 'Cancelled':
      return 'danger';
    default:
      return 'neutral';
  }
}

function sumStationCount(
  stations: StaffKitchenStationCollectionEnvelope | null,
  key: keyof KitchenStation['ticket_counts'],
): number {
  return (stations?.data ?? []).reduce((sum, station) => sum + station.ticket_counts[key], 0);
}

function parsePositiveInteger(value: string): number | undefined {
  const parsed = Number(value);

  if (!Number.isInteger(parsed) || parsed <= 0) {
    return undefined;
  }

  return parsed;
}

function formatKitchenOperatorError(cause: unknown, fallback: string): string {
  const normalized = normalizeApiError(cause, fallback);
  const featureFlagMessage = normalized.validation.feature_flag?.find((message) => message.trim() !== '');

  if (featureFlagMessage) {
    return `${featureFlagMessage} Kitchen mutation dang bi khoa boi feature flag branch nay.`;
  }

  return formatApiError(cause, fallback);
}

function shouldReloadKitchenState(cause: unknown): boolean {
  if (isRowVersionConflict(cause)) {
    return true;
  }

  const normalized = normalizeApiError(cause, '');

  if (normalized.kind === 'conflict') {
    return true;
  }

  if (normalized.kind !== 'validation') {
    return false;
  }

  if ((normalized.validation.feature_flag ?? []).length > 0) {
    return false;
  }

  if (['ticket_id', 'order_id', 'row_version'].some((field) => field in normalized.validation)) {
    return true;
  }

  return [normalized.message, ...Object.values(normalized.validation).flat()].some((value) => {
    const signal = value
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase();

    return (
      signal.includes('queued') ||
      signal.includes('fired') ||
      signal.includes('ready') ||
      signal.includes('row_version mismatch') ||
      signal.includes('state conflict') ||
      signal.includes('du lieu da thay doi')
    );
  });
}
