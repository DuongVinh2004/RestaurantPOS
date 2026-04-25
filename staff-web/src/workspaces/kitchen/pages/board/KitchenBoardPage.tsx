import { useCallback, useEffect, useMemo, useRef, useState, type ChangeEvent } from 'react';
import { Button } from 'antd';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate, useSearchParams } from 'react-router-dom';
import type { KitchenOrderItemTicket, KitchenStation } from '../../../../shared/api/sdk';
import {
  bumpKitchenTicket,
  dispatchKitchenOrder,
  fireKitchenTicket,
  recallKitchenTicket,
} from '../../../../shared/api/staff-api';
import { formatApiError } from '../../../../shared/api/errors';
import { formatDateTime, formatRelativeAge } from '../../../../shared/utils/format';
import { mergeJourneySearch } from '../../../../app/router/journey';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { kitchenTone } from '../../../../shared/status/status';
import { translateUiCode } from '../../../../shared/utils/translation';
import { toast } from '../../../../shared/ui/feedback/toast';
import {
  BranchPolicyState,
  EmptyBlock,
  InlineLoading,
  InlineWarning,
  PermissionDeniedState,
  TransientFailureState,
} from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { useJourneyContext } from '../../../../app/router/useJourneyContext';
import { useConfirmAction } from '../../../../shared/hooks/useConfirmAction';
import { useOnlineStatus } from '../../../../shared/hooks/useOnlineStatus';
import { buildKitchenBoardSearch, readKitchenBoardUrlState } from '../../../../domains/kitchen/kitchen-board-url';
import {
  canDispatchKitchenOrder,
  groupKitchenTicketsByLane,
  isKitchenTicketStatusFilter,
  kitchenTicketStatusOptions,
  resolveKitchenBranchGuard,
  resolveKitchenStationContext,
  resolveKitchenWorkspaceGuard,
  stationWorkloadLabel,
  summarizeKitchenRealtime,
  summarizeKitchenTickets,
  ticketAllowedActions,
  ticketDisplayName,
  ticketTimeline,
  type KitchenGuard,
  type KitchenTicketAction,
} from '../../../../domains/kitchen/kitchen-workspace';
import {
  kitchenQueryKeys,
  useKitchenChangesQuery,
  useKitchenStationsQuery,
  useKitchenTicketsQuery,
} from '../../../../domains/kitchen/useKitchenWorkspace';

export function KitchenBoardPage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const confirmAction = useConfirmAction();
  const journey = useJourneyContext();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const storedStationId = useFlowStore((state) => state.selectedStationId);
  const setStationContext = useFlowStore((state) => state.setStationContext);
  const isOnline = useOnlineStatus();
  const [showChangeFeed, setShowChangeFeed] = useState(true);
  const lastAppliedKitchenChangeVersionRef = useRef<number | null>(null);
  const kitchenUrlState = useMemo(() => readKitchenBoardUrlState(searchParams), [searchParams]);
  const ticketStatus = kitchenUrlState.status;
  const selectedTicketId = kitchenUrlState.ticketId;
  const requestedStationId = journey.stationId ?? storedStationId ?? null;
  const workspaceGuard = resolveKitchenWorkspaceGuard(session);
  const branchGuard = resolveKitchenBranchGuard(session, branchId);
  const canLoadStations = !workspaceGuard && !branchGuard && isOnline;
  const canDispatchOrder = canDispatchKitchenOrder(session);

  const stationsQuery = useKitchenStationsQuery({
    branchId,
    enabled: canLoadStations,
  });
  const stations = useMemo(() => stationsQuery.data?.data ?? [], [stationsQuery.data?.data]);
  const stationContext = useMemo(
    () => resolveKitchenStationContext({
      session,
      stations,
      requestedStationId,
    }),
    [requestedStationId, session, stations],
  );
  const stationId = stationContext.selectedStationId;
  const selectedStation = stationContext.selectedStation;
  const canLoadTickets = canLoadStations && !stationContext.guard && stationId !== null;

  const ticketsQuery = useKitchenTicketsQuery({
    branchId,
    stationId,
    status: ticketStatus,
    enabled: canLoadTickets,
  });
  const tickets = useMemo(() => ticketsQuery.data?.data ?? [], [ticketsQuery.data?.data]);
  const ticketSummary = useMemo(() => summarizeKitchenTickets(tickets), [tickets]);
  const activeLanes = useMemo(() => groupKitchenTicketsByLane(tickets), [tickets]);
  const selectedTicket = useMemo(
    () => tickets.find((ticket) => ticket.ticket_id === selectedTicketId) ?? null,
    [selectedTicketId, tickets],
  );
  const realtimeVersion = stationsQuery.data?.meta?.realtime.current_version ?? null;
  const changesQuery = useKitchenChangesQuery({
    branchId,
    currentVersion: realtimeVersion,
    enabled: canLoadStations,
  });
  const realtimeSummary = summarizeKitchenRealtime(changesQuery.data?.data);

  useEffect(() => {
    lastAppliedKitchenChangeVersionRef.current = null;
  }, [branchId]);

  const updateKitchenSearch = useCallback((
    patch: Partial<typeof kitchenUrlState>,
    stationOverride?: number | null,
    options?: { replace?: boolean },
  ) => {
    const nextLocalSearch = buildKitchenBoardSearch(searchParams, patch);
    const nextSearch = mergeJourneySearch(nextLocalSearch, {
      source: 'kitchen',
      tableId: journey.tableId,
      tableIds: journey.tableIds,
      reservationId: journey.reservationId,
      reservationRowVersion: journey.reservationRowVersion,
      orderId: journey.orderId,
      orderRowVersion: journey.orderRowVersion,
      stationId: stationOverride ?? journey.stationId ?? undefined,
    });
    setSearchParams(new URLSearchParams(nextSearch), { replace: options?.replace });
  }, [
    journey.orderId,
    journey.orderRowVersion,
    journey.reservationId,
    journey.reservationRowVersion,
    journey.stationId,
    journey.tableId,
    journey.tableIds,
    searchParams,
    setSearchParams,
  ]);

  useEffect(() => {
    if (!stationId || journey.stationId === stationId || journey.orderId || selectedTicketId !== null) {
      return;
    }

    updateKitchenSearch({ ticketId: null }, stationId, { replace: true });
  }, [journey.orderId, journey.stationId, selectedTicketId, stationId, updateKitchenSearch]);

  useEffect(() => {
    if (!selectedStation || storedStationId === selectedStation.station_id) {
      return;
    }

    setStationContext({
      stationId: selectedStation.station_id,
      label: selectedStation.name,
      source: 'kitchen',
    });
  }, [selectedStation, setStationContext, storedStationId]);

  useEffect(() => {
    if (!isOnline) {
      return;
    }

    void queryClient.invalidateQueries({ queryKey: kitchenQueryKeys.stationsRoot(), refetchType: 'active' });
    void queryClient.invalidateQueries({ queryKey: kitchenQueryKeys.ticketsRoot(), refetchType: 'active' });
  }, [isOnline, queryClient]);

  useEffect(() => {
    const currentVersion = stationsQuery.data?.meta?.realtime.current_version ?? null;
    const latestVersion = changesQuery.data?.data.current_version ?? null;
    const eventCount = changesQuery.data?.data.events.length ?? 0;

    if (currentVersion === null || latestVersion === null) {
      return;
    }

    if (latestVersion === currentVersion) {
      lastAppliedKitchenChangeVersionRef.current = latestVersion;
      return;
    }

    if (
      (latestVersion > currentVersion || eventCount > 0)
      && lastAppliedKitchenChangeVersionRef.current !== latestVersion
    ) {
      lastAppliedKitchenChangeVersionRef.current = latestVersion;
      void Promise.all([
        queryClient.invalidateQueries({ queryKey: kitchenQueryKeys.stationsRoot(), refetchType: 'active' }),
        queryClient.invalidateQueries({ queryKey: kitchenQueryKeys.ticketsRoot(), refetchType: 'active' }),
      ]);
    }
  }, [
    changesQuery.data?.data.current_version,
    changesQuery.data?.data.events.length,
    queryClient,
    stationsQuery.data?.meta?.realtime.current_version,
  ]);

  useEffect(() => {
    if (!canLoadTickets) {
      return;
    }

    if (tickets.length === 0) {
      if (selectedTicketId !== null) {
        updateKitchenSearch({ ticketId: null }, stationId, { replace: true });
      }
      return;
    }

    if (selectedTicketId && tickets.some((ticket) => ticket.ticket_id === selectedTicketId)) {
      return;
    }

    const focusedTicket = journey.orderId
      ? tickets.find((ticket) => ticket.order.order_id === journey.orderId)
      : null;

    if (focusedTicket) {
      updateKitchenSearch({ ticketId: focusedTicket.ticket_id }, stationId, { replace: true });
      return;
    }

    if (selectedTicketId !== null) {
      updateKitchenSearch({ ticketId: null }, stationId, { replace: true });
    }
  }, [canLoadTickets, journey.orderId, selectedTicketId, stationId, tickets, updateKitchenSearch]);

  const dispatchMutation = useMutation({
    mutationFn: async () => {
      if (!journey.orderId) {
        throw new Error('No order context is available for kitchen dispatch.');
      }

      return dispatchKitchenOrder(journey.orderId, {
        row_version: journey.orderRowVersion ?? undefined,
      });
    },
    onSuccess: async (dispatchEnvelope) => {
      const dispatchedTicket = dispatchEnvelope.data[0] ?? null;
      const dispatchedStationId = dispatchedTicket?.station?.station_id ?? null;
      const unroutedCount = dispatchEnvelope.meta?.unrouted_count ?? 0;

      await Promise.all([
        queryClient.invalidateQueries({ queryKey: kitchenQueryKeys.stationsRoot() }),
        queryClient.invalidateQueries({ queryKey: kitchenQueryKeys.ticketsRoot() }),
        queryClient.invalidateQueries({ queryKey: ['order-detail', journey.orderId] }),
        queryClient.invalidateQueries({ queryKey: ['checkout-order-detail', journey.orderId] }),
      ]);

      if (dispatchedTicket) {
        updateKitchenSearch({ ticketId: dispatchedTicket.ticket_id }, dispatchedStationId, { replace: true });
      } else if (dispatchedStationId) {
        updateKitchenSearch({ ticketId: null }, dispatchedStationId, { replace: true });
      }

      if (!dispatchedTicket && unroutedCount > 0) {
        toast.warning(`Order #${journey.orderId} did not create kitchen tickets because ${unroutedCount} items are unrouted.`);
        return;
      }

      toast.success(`Order #${journey.orderId} was dispatched to kitchen.`);

      if (unroutedCount > 0) {
        toast.warning(`${unroutedCount} items did not create tickets because they are unrouted.`);
      }
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Could not dispatch the order to kitchen.'));
    },
  });

  const ticketActionMutation = useMutation({
    mutationFn: async (action: KitchenTicketAction) => {
      if (!selectedTicket) {
        throw new Error('Select a kitchen ticket first.');
      }

      if (action === 'fire') {
        return fireKitchenTicket(selectedTicket.ticket_id);
      }
      if (action === 'bump') {
        return bumpKitchenTicket(selectedTicket.ticket_id);
      }

      return recallKitchenTicket(selectedTicket.ticket_id);
    },
    onSuccess: async (ticketEnvelope) => {
      const orderId = ticketEnvelope.data.order.order_id;
      updateKitchenSearch({ ticketId: ticketEnvelope.data.ticket_id }, ticketEnvelope.data.station?.station_id ?? stationId, { replace: true });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: kitchenQueryKeys.ticketsRoot() }),
        queryClient.invalidateQueries({ queryKey: kitchenQueryKeys.stationsRoot() }),
        queryClient.invalidateQueries({ queryKey: ['order-detail', orderId] }),
        queryClient.invalidateQueries({ queryKey: ['checkout-order-detail', orderId] }),
      ]);
      toast.success('Kitchen ticket was updated.');
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Could not update the kitchen ticket.'));
    },
  });

  async function handleTicketAction(action: KitchenTicketAction) {
    if (!selectedTicket || !ticketAllowedActions(selectedTicket).includes(action) || !isOnline) {
      return;
    }

    const confirmed = await confirmAction({
      title: `${labelForTicketAction(action)} ticket #${selectedTicket.ticket_id}`,
      content: 'Only the next safe lifecycle transition will be sent for the selected kitchen ticket.',
      okText: labelForTicketAction(action),
      danger: action === 'recall',
    });

    if (confirmed) {
      await ticketActionMutation.mutateAsync(action);
    }
  }

  function handleTicketStatusChange(event: ChangeEvent<HTMLSelectElement>) {
    const nextStatus = event.target.value;

    if (!isKitchenTicketStatusFilter(nextStatus)) {
      return;
    }

    updateKitchenSearch({ status: nextStatus, ticketId: null }, stationId, { replace: true });
  }

  function selectStation(station: KitchenStation) {
    setStationContext({
      stationId: station.station_id,
      label: station.name,
      source: 'kitchen',
    });
    updateKitchenSearch({ ticketId: null }, station.station_id);
  }

  const blockingState = renderKitchenBoardGuard({
    workspaceGuard,
    branchGuard,
    stationGuard: stationContext.guard?.kind === 'missing-assigned-station' ? stationContext.guard : null,
    isOnline,
    onRetry: () => {
      void stationsQuery.refetch();
      void ticketsQuery.refetch();
      void changesQuery.refetch();
    },
  });

  return (
    <div className="staff-kitchen-board-page" data-testid="kitchen-board-page">
      <section className="staff-kitchen-board-head" aria-label="Kitchen board context">
        <div className="staff-kitchen-board-title">
          <span className="staff-eyebrow">Kitchen line</span>
          <h2>Ticket queue</h2>
          <p>Run the selected station through queued, in-prep, and ready lanes.</p>
        </div>

        <div className="staff-kitchen-board-toolbar">
          <StatusChip label={branchId ? `Branch #${branchId}` : 'No branch'} tone={branchId ? 'processing' : 'warning'} />
          <StatusChip label={selectedStation ? selectedStation.name : 'No station'} tone={selectedStation ? 'processing' : 'warning'} />
          <StatusChip label={`${ticketSummary.all} tickets`} tone="default" />
          <StatusChip label={realtimeSummary.label} tone={realtimeSummary.tone} />
          <div className="staff-toolbar-select-wrap">
            <select
              aria-label="Filter kitchen tickets by status"
              className="staff-toolbar-select"
              value={ticketStatus}
              onChange={handleTicketStatusChange}
            >
              {kitchenTicketStatusOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
          <Button onClick={() => ticketsQuery.refetch()} disabled={!stationId || !isOnline} loading={ticketsQuery.isFetching}>
            Refresh queue
          </Button>
          <Button onClick={() => setShowChangeFeed((value) => !value)}>
            {showChangeFeed ? 'Hide live sync' : 'Show live sync'}
          </Button>
        </div>
      </section>

      {!isOnline ? (
        <InlineWarning
          title="Kitchen connection is offline"
          description="Fast actions are paused. The queue will refetch when the browser reconnects."
        />
      ) : null}

      {blockingState ?? (
        <div className="staff-kitchen-board-grid">
          <section className="staff-kitchen-panel staff-kitchen-board-stations" aria-label="Station context">
            <div className="staff-kitchen-section-head">
              <div>
                <span className="staff-eyebrow">Station</span>
                <h3>Assigned lane</h3>
              </div>
              <Button
                onClick={() => navigate(staffRoutePaths.kitchen.landing)}
                type="text"
              >
                Change
              </Button>
            </div>

            {stationsQuery.isLoading ? <InlineLoading tip="Loading kitchen stations..." /> : null}
            {stationsQuery.error ? (
              <TransientFailureState
                title="Kitchen stations are not available"
                description={formatApiError(stationsQuery.error, 'Could not load kitchen stations.')}
                primaryAction={<Button onClick={() => stationsQuery.refetch()}>Retry</Button>}
              />
            ) : null}
            {!stationsQuery.isLoading && !stationsQuery.error && stations.length === 0 ? (
              <EmptyBlock
                title="No station configured"
                description="The selected branch has no station available to the kitchen workspace."
              />
            ) : null}
            {stationContext.guard && stationContext.guard.kind !== 'missing-assigned-station' ? (
              <BranchPolicyState
                title={stationContext.guard.title}
                description={stationContext.guard.description}
                meta={stationContext.guard.meta}
              />
            ) : null}
            {stationContext.selectableStations.length > 0 ? (
              <div className="staff-kitchen-station-list" role="list" aria-label="Kitchen stations">
                {stationContext.selectableStations.map((station) => (
                  <button
                    key={station.station_id}
                    type="button"
                    className={`staff-kitchen-station-card${station.station_id === stationId ? ' staff-card-selected' : ''}`}
                    aria-pressed={station.station_id === stationId}
                    onClick={() => selectStation(station)}
                  >
                    <div className="staff-kitchen-station-card-main">
                      <div className="staff-kitchen-station-card-head">
                        <div className="staff-kitchen-station-copy">
                          <span className="staff-kitchen-station-code">{station.code}</span>
                          <strong>{station.name}</strong>
                        </div>
                        <span className={`staff-kitchen-station-state${station.station_id === stationId ? ' staff-kitchen-station-state-active' : ''}`}>
                          {station.station_id === stationId ? 'Locked' : 'Select'}
                        </span>
                      </div>
                      <div className="staff-kitchen-station-metrics">
                        <span>
                          <strong>{translateUiCode(station.output_mode)}</strong>
                          <small>Mode</small>
                        </span>
                        <span>
                          <strong>{station.ticket_counts.queued}</strong>
                          <small>Queued</small>
                        </span>
                        <span>
                          <strong>{station.ticket_counts.ready}</strong>
                          <small>Ready</small>
                        </span>
                      </div>
                      <p className="staff-kitchen-station-summary">{stationWorkloadLabel(station)}</p>
                    </div>
                  </button>
                ))}
              </div>
            ) : null}
          </section>

          <section className="staff-kitchen-panel staff-kitchen-board-lanes" aria-label="Ticket status lanes">
            <div className="staff-kitchen-section-head">
              <div>
                <span className="staff-eyebrow">Queue</span>
                <h3>{selectedStation ? selectedStation.name : 'Select station'}</h3>
              </div>
              <div className="staff-kitchen-lane-summary" aria-label="Ticket summary">
                <StatusChip label={`${ticketSummary.queued} queued`} tone="warning" />
                <StatusChip label={`${ticketSummary.fired} in prep`} tone="processing" />
                <StatusChip label={`${ticketSummary.ready} ready`} tone="success" />
              </div>
            </div>

            {!stationId && !stationsQuery.isLoading ? (
              <EmptyBlock
                title="Station selection required"
                description="Choose one assigned station before loading tickets."
              />
            ) : null}
            {stationId && ticketsQuery.isLoading ? <InlineLoading tip="Loading kitchen tickets..." /> : null}
            {stationId && ticketsQuery.error ? (
              <TransientFailureState
                title="Kitchen tickets are not available"
                description={formatApiError(ticketsQuery.error, 'Could not load kitchen tickets.')}
                primaryAction={<Button onClick={() => ticketsQuery.refetch()}>Retry</Button>}
              />
            ) : null}
            {stationId && !ticketsQuery.isLoading && !ticketsQuery.error && tickets.length === 0 ? (
              <EmptyBlock
                title="No tickets in this lane"
                description="The selected station has no tickets for the current status filter."
              />
            ) : null}
            {stationId && !ticketsQuery.isLoading && !ticketsQuery.error && tickets.length > 0 ? (
              <TicketLanes
                lanes={activeLanes}
                selectedTicketId={selectedTicketId}
                onSelectTicket={(ticket) => updateKitchenSearch({ ticketId: ticket.ticket_id }, stationId)}
              />
            ) : null}
          </section>

          <aside className="staff-kitchen-panel staff-kitchen-board-detail" aria-label="Selected ticket">
            <TicketDetailPanel
              isOnline={isOnline}
              isPending={ticketActionMutation.isPending}
              selectedTicket={selectedTicket}
              stationId={stationId}
              onTicketAction={(action) => void handleTicketAction(action)}
            />

            {journey.orderId && canDispatchOrder ? (
              <section className="staff-kitchen-subpanel" aria-label="Order handoff">
                <div className="staff-kitchen-section-head">
                  <div>
                    <span className="staff-eyebrow">Order handoff</span>
                    <h3>Dispatch order #{journey.orderId}</h3>
                  </div>
                </div>
                <p className="staff-kitchen-muted">Use this only when the order flow has handed context into the kitchen workspace.</p>
                <Button
                  type="primary"
                  onClick={() => dispatchMutation.mutate()}
                  disabled={!journey.orderId || !isOnline}
                  loading={dispatchMutation.isPending}
                >
                  Dispatch order to kitchen
                </Button>
              </section>
            ) : null}

            <section className="staff-kitchen-subpanel" aria-label="Live kitchen sync">
              <div className="staff-kitchen-section-head">
                <div>
                  <span className="staff-eyebrow">Live sync</span>
                  <h3>{showChangeFeed ? 'Watching changes' : 'Collapsed'}</h3>
                </div>
                <StatusChip label={realtimeSummary.label} tone={realtimeSummary.tone} />
              </div>
              {!showChangeFeed ? (
                <p className="staff-kitchen-muted">Open live sync when you need to confirm realtime cursor health.</p>
              ) : changesQuery.isLoading ? (
                <InlineLoading tip="Reading kitchen changes..." />
              ) : changesQuery.error ? (
                <TransientFailureState
                  title="Kitchen sync is not available"
                  description={formatApiError(changesQuery.error, 'Could not read the kitchen change feed.')}
                  primaryAction={<Button onClick={() => changesQuery.refetch()}>Retry</Button>}
                />
              ) : (
                <div className="staff-kitchen-sync-grid">
                  <Metric label="Events" value={realtimeSummary.eventCount} />
                  <Metric label="Poll sec" value={realtimeSummary.pollHintSeconds ?? 0} />
                  <Metric label="Drift" value={ticketSummary.drift} />
                </div>
              )}
            </section>
          </aside>
        </div>
      )}
    </div>
  );
}

function TicketLanes({
  lanes,
  selectedTicketId,
  onSelectTicket,
}: {
  lanes: ReturnType<typeof groupKitchenTicketsByLane>;
  selectedTicketId: number | null;
  onSelectTicket: (ticket: KitchenOrderItemTicket) => void;
}) {
  return (
    <div className="staff-kitchen-ticket-lanes">
      {lanes.map((lane) => (
        <section key={lane.status} className={`staff-kitchen-ticket-lane staff-kitchen-ticket-lane-${lane.status.toLowerCase()}`}>
          <div className="staff-kitchen-lane-head">
            <div>
              <h4>{lane.label}</h4>
              <p>{lane.description}</p>
            </div>
            <strong>{lane.tickets.length}</strong>
          </div>

          {lane.tickets.length === 0 ? (
            <p className="staff-kitchen-muted">No tickets.</p>
          ) : (
            <div className="staff-kitchen-ticket-stack">
              {lane.tickets.map((ticket) => (
                <button
                  key={ticket.ticket_id}
                  type="button"
                  className={`staff-kitchen-ticket-card${ticket.ticket_id === selectedTicketId ? ' staff-card-selected' : ''}`}
                  aria-pressed={ticket.ticket_id === selectedTicketId}
                  onClick={() => onSelectTicket(ticket)}
                >
                  <span className="staff-kitchen-ticket-card-title">{ticketDisplayName(ticket)}</span>
                  <span className="staff-kitchen-ticket-card-meta">
                    Order #{ticket.order.order_id} / {formatRelativeAge(ticket.updated_at)}
                  </span>
                  <StatusChip label={ticket.ticket_status} tone={kitchenTone(ticket.ticket_status)} />
                </button>
              ))}
            </div>
          )}
        </section>
      ))}
    </div>
  );
}

function TicketDetailPanel({
  isOnline,
  isPending,
  selectedTicket,
  stationId,
  onTicketAction,
}: {
  isOnline: boolean;
  isPending: boolean;
  selectedTicket: KitchenOrderItemTicket | null;
  stationId: number | null;
  onTicketAction: (action: KitchenTicketAction) => void;
}) {
  if (!selectedTicket) {
    return (
      <section className="staff-kitchen-subpanel" aria-label="No ticket selected">
        <EmptyBlock
          title="Select a ticket"
          description="Choose a ticket from a status lane to inspect its timeline and fast actions."
        />
      </section>
    );
  }

  const allowedActions = ticketAllowedActions(selectedTicket);
  const disableActions = !isOnline || isPending || stationId === null;

  return (
    <section className="staff-kitchen-subpanel" aria-label="Selected ticket details">
      <div className="staff-kitchen-section-head">
        <div>
          <span className="staff-eyebrow">Ticket #{selectedTicket.ticket_id}</span>
          <h3>{ticketDisplayName(selectedTicket)}</h3>
        </div>
        <StatusChip label={selectedTicket.ticket_status} tone={kitchenTone(selectedTicket.ticket_status)} />
      </div>

      <div className="staff-kitchen-ticket-facts">
        <span>Order #{selectedTicket.order.order_id}</span>
        <span>Dispatch {selectedTicket.dispatch_count}</span>
        <span>Recall {selectedTicket.recall_count}</span>
        <span>{formatRelativeAge(selectedTicket.updated_at)}</span>
      </div>

      <div className="staff-kitchen-ticket-timeline" aria-label="Ticket timeline">
        {ticketTimeline(selectedTicket).map((entry) => (
          <div key={entry.key} className={`staff-kitchen-timeline-item${entry.active ? ' staff-kitchen-timeline-item-active' : ''}`}>
            <span>{entry.label}</span>
            <strong>{entry.value ? formatDateTime(entry.value) : 'Pending'}</strong>
          </div>
        ))}
      </div>

      <div className="staff-kitchen-fast-actions">
        <Button
          size="large"
          onClick={() => onTicketAction('fire')}
          disabled={disableActions || !allowedActions.includes('fire')}
          loading={isPending}
        >
          Fire
        </Button>
        <Button
          size="large"
          onClick={() => onTicketAction('bump')}
          disabled={disableActions || !allowedActions.includes('bump')}
          loading={isPending}
        >
          Bump ready
        </Button>
        <Button
          size="large"
          danger
          onClick={() => onTicketAction('recall')}
          disabled={disableActions || !allowedActions.includes('recall')}
          loading={isPending}
        >
          Recall
        </Button>
      </div>
    </section>
  );
}

function Metric({ label, value }: { label: string; value: number }) {
  return (
    <div className="staff-kitchen-sync-metric">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

function renderKitchenBoardGuard({
  workspaceGuard,
  branchGuard,
  stationGuard,
  isOnline,
  onRetry,
}: {
  workspaceGuard: KitchenGuard | null;
  branchGuard: KitchenGuard | null;
  stationGuard: KitchenGuard | null;
  isOnline: boolean;
  onRetry: () => void;
}) {
  if (workspaceGuard) {
    return (
      <PermissionDeniedState
        variant="page"
        title={workspaceGuard.title}
        description={workspaceGuard.description}
      />
    );
  }

  if (branchGuard) {
    return (
      <BranchPolicyState
        variant="page"
        title={branchGuard.title}
        description={branchGuard.description}
        meta={branchGuard.meta}
      />
    );
  }

  if (stationGuard) {
    return (
      <BranchPolicyState
        variant="page"
        title={stationGuard.title}
        description={stationGuard.description}
        meta={stationGuard.meta}
      />
    );
  }

  if (!isOnline) {
    return (
      <TransientFailureState
        variant="page"
        title="Kitchen board is offline"
        description="Reconnect before loading ticket queues or sending fast actions."
        primaryAction={<Button onClick={onRetry}>Retry sync</Button>}
      />
    );
  }

  return null;
}

function labelForTicketAction(action: KitchenTicketAction): string {
  if (action === 'fire') {
    return 'Fire';
  }

  if (action === 'bump') {
    return 'Bump ready';
  }

  return 'Recall';
}
