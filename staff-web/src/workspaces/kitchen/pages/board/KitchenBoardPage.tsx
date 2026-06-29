import { useCallback, useEffect, useMemo, useRef, useState, type ChangeEvent } from 'react';
import { Button, Drawer } from 'antd';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate, useSearchParams } from 'react-router-dom';
import type { KitchenOrderItemTicket, KitchenStation } from '../../../../shared/api/sdk';
import {
  bumpKitchenTicket,
  dispatchKitchenOrder,
  fireKitchenTicket,
  recallKitchenTicket,
} from '../../../../shared/api/staff-api';
import { formatApiError, normalizeApiError } from '../../../../shared/api/errors';
import { formatDateTime, formatRelativeAge } from '../../../../shared/utils/format';
import { mergeJourneySearch } from '../../../../app/router/journey';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { kitchenTone } from '../../../../shared/status/status';
import { translateUiCode } from '../../../../shared/utils/translation';
import { toast } from '../../../../shared/ui/feedback/toast';
import { MutationStatusNotice } from '../../../../shared/ui/feedback/MutationStatusNotice';
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
  kitchenStationDisplayName,
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
import {
  createIdleMutationFeedback,
  mapMutationErrorToFeedback,
} from '../../../../shared/mutations/mutation-ux';
import { useSmartETA } from '../../../../domains/kitchen/hooks/useSmartETA';
import { ClockCircleOutlined } from '@ant-design/icons';

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
  const [lockedTicketAction, setLockedTicketAction] = useState<KitchenTicketAction | null>(null);
  const [detailDrawerOpen, setDetailDrawerOpen] = useState(false);
  const [mutationFeedback, setMutationFeedback] = useState(createIdleMutationFeedback);
  const ticketActionLockRef = useRef(false);
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

  useEffect(() => {
    if (selectedTicketId || journey.orderId) {
      setDetailDrawerOpen(true);
    }
  }, [selectedTicketId, journey.orderId]);

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
      label: kitchenStationDisplayName(selectedStation),
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

    if (!ticketsQuery.isFetched || ticketsQuery.isFetching || ticketsQuery.error) {
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
  }, [
    canLoadTickets,
    journey.orderId,
    selectedTicketId,
    stationId,
    tickets,
    ticketsQuery.error,
    ticketsQuery.isFetched,
    ticketsQuery.isFetching,
    updateKitchenSearch,
  ]);

  async function refreshKitchenWorkspaceSlices(orderId?: number | null) {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: kitchenQueryKeys.stationsRoot(), refetchType: 'active' }),
      queryClient.invalidateQueries({ queryKey: kitchenQueryKeys.ticketsRoot(), refetchType: 'active' }),
      queryClient.invalidateQueries({ queryKey: ['kitchen-changes'], refetchType: 'active' }),
      orderId
        ? queryClient.invalidateQueries({ queryKey: ['order-detail', orderId], refetchType: 'active' })
        : Promise.resolve(),
      orderId
        ? queryClient.invalidateQueries({ queryKey: ['checkout-order-detail', orderId], refetchType: 'active' })
        : Promise.resolve(),
    ]);
  }

  async function handleKitchenMutationError(
    error: unknown,
    context: {
      actionLabel: string;
      fallbackMessage: string;
    },
    orderId?: number | null,
  ) {
    const feedback = mapMutationErrorToFeedback(error, context);
    const normalized = normalizeApiError(error, context.fallbackMessage);
    const staleWrite = normalized.code === 'stale_row_version'
      || normalized.categoryCode === 'stale_write'
      || normalized.conflictType === 'stale_write';

    setMutationFeedback(feedback);

    if (feedback.phase === 'conflict') {
      await refreshKitchenWorkspaceSlices(orderId);

      if (staleWrite) {
        toast.warning(`${normalized.message} Bảng phiếu bếp đã được tải lại trước khi thao tác tiếp.`);
      }

      return;
    }

    if (feedback.phase === 'retriable_failure') {
      toast.error(formatApiError(error, context.fallbackMessage));
    }
  }

  const dispatchMutation = useMutation({
    mutationFn: async () => {
      if (!journey.orderId) {
        throw new Error('Chưa có ngữ cảnh đơn hàng để gửi xuống bếp.');
      }

      if (journey.orderRowVersion === undefined) {
        throw new Error('Hãy tải lại đơn hàng trước khi gửi xuống bếp.');
      }

      return dispatchKitchenOrder(journey.orderId, {
        row_version: journey.orderRowVersion,
      });
    },
    onMutate: () => {
      setMutationFeedback(createIdleMutationFeedback());
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

      setMutationFeedback(createIdleMutationFeedback());

      if (!dispatchedTicket && unroutedCount > 0) {
        toast.warning(`Đơn #${journey.orderId} chưa tạo phiếu bếp vì ${unroutedCount} món chưa có tuyến bếp.`);
        return;
      }

      toast.success(`Đã gửi đơn #${journey.orderId} xuống bếp.`);

      if (unroutedCount > 0) {
        toast.warning(`${unroutedCount} món chưa tạo phiếu vì chưa được gán tuyến bếp.`);
      }
    },
    onError: (error) => {
      void handleKitchenMutationError(error, {
        actionLabel: 'Gửi đơn xuống bếp',
        fallbackMessage: 'Không thể gửi đơn xuống bếp.',
      }, journey.orderId ?? null);
    },
  });

  const ticketActionMutation = useMutation({
    mutationFn: async (input: {
      action: KitchenTicketAction;
      ticketId: number;
      rowVersion: number;
    }) => {
      if (input.action === 'fire') {
        return fireKitchenTicket(input.ticketId, input.rowVersion);
      }
      if (input.action === 'bump') {
        return bumpKitchenTicket(input.ticketId, input.rowVersion);
      }

      return recallKitchenTicket(input.ticketId, input.rowVersion);
    },
    onMutate: () => {
      setMutationFeedback(createIdleMutationFeedback());
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
      setMutationFeedback(createIdleMutationFeedback());
      toast.success('Đã cập nhật phiếu bếp.');
    },
    onError: (error, variables) => {
      void handleKitchenMutationError(error, {
        actionLabel: labelForTicketAction(variables.action),
        fallbackMessage: 'Không thể cập nhật phiếu bếp.',
      }, selectedTicket?.order.order_id ?? journey.orderId ?? null);
    },
  });

  async function handleTicketAction(action: KitchenTicketAction) {
    if (
      !selectedTicket
      || selectedTicket.row_version === null
      || !ticketAllowedActions(selectedTicket).includes(action)
      || ticketActionMutation.isPending
      || ticketActionLockRef.current
      || !isOnline
    ) {
      return;
    }

    ticketActionLockRef.current = true;
    setLockedTicketAction(action);
    const ticketId = selectedTicket.ticket_id;
    const rowVersion = selectedTicket.row_version;

    try {
      const confirmed = await confirmAction({
        title: `${labelForTicketAction(action)} phiếu #${ticketId}`,
        content: 'Hệ thống chỉ gửi bước chuyển trạng thái hợp lệ tiếp theo cho phiếu bếp đang chọn.',
        okText: labelForTicketAction(action),
        danger: action === 'recall',
      });

      if (confirmed) {
        await ticketActionMutation.mutateAsync({
          action,
          ticketId,
          rowVersion,
        });
      }
    } catch {
      // Mutation onError already surfaces the operator-facing state.
    } finally {
      ticketActionLockRef.current = false;
      setLockedTicketAction(null);
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
    <div className="staff-workspace-fluid staff-workspace-flex-column" data-testid="kitchen-board-page" style={{ padding: '16px', background: '#f5f7fa', minHeight: '100%', width: '100%', display: 'flex', flexDirection: 'column', gap: '16px' }}>
      
      {/* Top Toolbar */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '12px', background: '#fff', padding: '12px 16px', borderRadius: '8px', border: '1px solid #f0f0f0' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
          <span className="staff-eyebrow" style={{ fontSize: '14px', fontWeight: 600 }}>Line bếp</span>
          <StatusChip label={branchId ? `Chi nhánh #${branchId}` : 'Chưa chọn chi nhánh'} tone={branchId ? 'processing' : 'warning'} />
          <StatusChip label={selectedStation ? kitchenStationDisplayName(selectedStation) : 'Chưa chọn trạm'} tone={selectedStation ? 'processing' : 'warning'} />
          <StatusChip label={`${ticketSummary.all} phiếu`} tone="default" />
          <StatusChip label={realtimeSummary.label} tone={realtimeSummary.tone} />
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
          <select
            aria-label="Lọc phiếu bếp theo trạng thái"
            className="staff-toolbar-select"
            value={ticketStatus}
            onChange={handleTicketStatusChange}
            style={{ padding: '4px 8px', borderRadius: '4px', border: '1px solid #d9d9d9' }}
          >
            {kitchenTicketStatusOptions.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
          <Button onClick={() => ticketsQuery.refetch()} disabled={!stationId || !isOnline} loading={ticketsQuery.isFetching}>
            Tải lại phiếu
          </Button>
          <Button onClick={() => navigate(staffRoutePaths.kitchen.landing)}>Đổi trạm</Button>
        </div>
      </div>

      {!isOnline ? (
        <InlineWarning
          title="Kết nối bếp đang ngoại tuyến"
          description="Tạm dừng thao tác nhanh. Bảng phiếu sẽ tự tải lại khi trình duyệt kết nối lại."
        />
      ) : null}

      {blockingState ?? (
        <>
          {/* Horizontal Station List */}
          <div style={{ background: '#fff', padding: '16px', borderRadius: '8px', border: '1px solid #f0f0f0' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '12px' }}>
               <h3 style={{ margin: 0, fontSize: '16px' }}>Trạm bếp</h3>
               <div className="staff-kitchen-lane-summary" aria-label="Tóm tắt phiếu bếp" style={{ display: 'flex', gap: '8px' }}>
                 <StatusChip label={`${ticketSummary.queued} chờ làm`} tone="warning" />
                 <StatusChip label={`${ticketSummary.fired} đang làm`} tone="processing" />
                 <StatusChip label={`${ticketSummary.ready} sẵn sàng phục vụ`} tone="success" />
               </div>
            </div>
            
            {stationsQuery.isLoading ? <InlineLoading tip="Đang tải trạm bếp..." /> : null}
            {stationsQuery.error ? (
              <TransientFailureState
                title="Chưa tải được trạm bếp"
                description={formatApiError(stationsQuery.error, 'Không thể tải danh sách trạm bếp.')}
                primaryAction={<Button onClick={() => stationsQuery.refetch()}>Tải lại</Button>}
              />
            ) : null}
            {!stationsQuery.isLoading && !stationsQuery.error && stations.length === 0 ? (
              <EmptyBlock
                title="Chưa có trạm bếp"
                description="Chi nhánh đang chọn chưa có trạm bếp khả dụng."
              />
            ) : null}
            
            {stationContext.selectableStations.length > 0 ? (
              <div className="staff-kitchen-station-list" style={{ display: 'flex', gap: '16px', overflowX: 'auto', paddingBottom: '8px' }} role="list" aria-label="Danh sách trạm bếp">
                {stationContext.selectableStations.map((station) => (
                  <button
                    key={station.station_id}
                    type="button"
                    className={`staff-kitchen-station-card${station.station_id === stationId ? ' staff-card-selected' : ''}`}
                    aria-pressed={station.station_id === stationId}
                    onClick={() => selectStation(station)}
                    style={{ flexShrink: 0, width: '280px', textAlign: 'left', padding: '12px', border: station.station_id === stationId ? '2px solid #1890ff' : '1px solid #d9d9d9', borderRadius: '8px', cursor: 'pointer', background: station.station_id === stationId ? '#e6f7ff' : '#fff' }}
                  >
                    <div className="staff-kitchen-station-card-main">
                      <div className="staff-kitchen-station-card-head" style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px' }}>
                        <div className="staff-kitchen-station-copy">
                          <strong style={{ display: 'block', fontSize: '14px' }}>{kitchenStationDisplayName(station)}</strong>
                          <span className="staff-kitchen-station-code" style={{ fontSize: '12px', color: '#888' }}>{station.code}</span>
                        </div>
                      </div>
                      <div className="staff-kitchen-station-metrics" style={{ display: 'flex', justifyContent: 'space-between', fontSize: '12px', color: '#555' }}>
                        <span style={{ display: 'flex', flexDirection: 'column' }}>
                          <strong>{translateUiCode(station.output_mode)}</strong>
                          <small>Chế độ</small>
                        </span>
                        <span style={{ display: 'flex', flexDirection: 'column' }}>
                          <strong>{station.ticket_counts.queued}</strong>
                          <small>Chờ làm</small>
                        </span>
                        <span style={{ display: 'flex', flexDirection: 'column' }}>
                          <strong>{station.ticket_counts.ready}</strong>
                          <small>Sẵn sàng</small>
                        </span>
                      </div>
                    </div>
                  </button>
                ))}
              </div>
            ) : null}
          </div>

          {/* Ticket Lanes */}
          <div style={{ flex: 1, minHeight: 0, display: 'flex', flexDirection: 'column', background: '#fff', borderRadius: '8px', border: '1px solid #f0f0f0', overflow: 'hidden' }}>
            {!stationId && !stationsQuery.isLoading ? (
              <EmptyBlock
                title="Cần chọn trạm bếp"
                description="Chọn một trạm được gán trước khi tải danh sách phiếu."
              />
            ) : null}
            {stationId && ticketsQuery.isLoading ? <InlineLoading tip="Đang tải phiếu bếp..." /> : null}
            {stationId && ticketsQuery.error ? (
              <TransientFailureState
                title="Chưa tải được phiếu bếp"
                description={formatApiError(ticketsQuery.error, 'Không thể tải danh sách phiếu bếp.')}
                primaryAction={<Button onClick={() => ticketsQuery.refetch()}>Tải lại</Button>}
              />
            ) : null}
            {stationId && !ticketsQuery.isLoading && !ticketsQuery.error && tickets.length === 0 ? (
              <EmptyBlock
                title="Chưa có phiếu trong bộ lọc này"
                description="Trạm đang chọn chưa có phiếu bếp phù hợp với trạng thái hiện tại."
              />
            ) : null}
            {stationId && !ticketsQuery.isLoading && !ticketsQuery.error && tickets.length > 0 ? (
              <TicketLanes
                lanes={activeLanes}
                selectedTicketId={selectedTicketId}
                onSelectTicket={(ticket) => { updateKitchenSearch({ ticketId: ticket.ticket_id }, stationId); setDetailDrawerOpen(true); }}
              />
            ) : null}
          </div>

          {/* Ticket Detail Drawer */}
          <Drawer
            title="Chi tiết phiếu bếp"
            placement="right"
            width={450}
            onClose={() => setDetailDrawerOpen(false)}
            open={detailDrawerOpen}
            destroyOnClose={false}
          >
            <div className="staff-kitchen-board-detail" aria-label="Phiếu bếp đang chọn" style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
              <MutationStatusNotice
                feedback={mutationFeedback}
                onDismiss={() => setMutationFeedback(createIdleMutationFeedback())}
                onRetry={() => {
                  void refreshKitchenWorkspaceSlices(selectedTicket?.order.order_id ?? journey.orderId ?? null);
                }}
              />
              <TicketDetailPanel
                isOnline={isOnline}
                isPending={ticketActionMutation.isPending || lockedTicketAction !== null}
                selectedTicket={selectedTicket}
                stationId={stationId}
                onTicketAction={(action) => void handleTicketAction(action)}
              />

              {journey.orderId && canDispatchOrder ? (
                <section className="staff-kitchen-subpanel" aria-label="Gửi đơn xuống bếp">
                  <div className="staff-kitchen-section-head">
                    <div>
                      <span className="staff-eyebrow">Chuyển món</span>
                      <h3>Gửi đơn #{journey.orderId}</h3>
                    </div>
                  </div>
                  <p className="staff-kitchen-muted">Dùng khi luồng gọi món đã chuyển đúng đơn sang màn bếp.</p>
                  <Button
                    type="primary"
                    onClick={() => dispatchMutation.mutate()}
                    disabled={!journey.orderId || journey.orderRowVersion === undefined || !isOnline}
                    loading={dispatchMutation.isPending}
                  >
                    Gửi đơn xuống bếp
                  </Button>
                </section>
              ) : null}

              <section className="staff-kitchen-subpanel" aria-label="Đồng bộ bếp">
                <div className="staff-kitchen-section-head">
                  <div>
                    <span className="staff-eyebrow">Đồng bộ</span>
                    <h3>Đang theo dõi thay đổi</h3>
                  </div>
                  <StatusChip label={realtimeSummary.label} tone={realtimeSummary.tone} />
                </div>
                {changesQuery.isLoading ? (
                  <InlineLoading tip="Đang đọc thay đổi trong bếp..." />
                ) : changesQuery.error ? (
                  <TransientFailureState
                    title="Chưa đọc được đồng bộ bếp"
                    description={formatApiError(changesQuery.error, 'Không thể đọc luồng thay đổi của bếp.')}
                    primaryAction={<Button onClick={() => changesQuery.refetch()}>Tải lại</Button>}
                  />
                ) : (
                  <div className="staff-kitchen-sync-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '12px' }}>
                    <Metric label="Sự kiện" value={realtimeSummary.eventCount} />
                    <Metric label="Chu kỳ giây" value={realtimeSummary.pollHintSeconds ?? 0} />
                    <Metric label="Lệch dữ liệu" value={ticketSummary.drift} />
                  </div>
                )}
              </section>
            </div>
          </Drawer>
        </>
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
            <p className="staff-kitchen-muted">Chưa có phiếu.</p>
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
                    Đơn #{ticket.order.order_id} / {formatRelativeAge(ticket.updated_at)}
                  </span>
                  <StatusChip label={translateUiCode(ticket.ticket_status)} tone={kitchenTone(ticket.ticket_status)} />
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
      <section className="staff-kitchen-subpanel" aria-label="Chưa chọn phiếu bếp">
        <EmptyBlock
          title="Chọn một phiếu bếp"
          description="Chọn phiếu trong các cột trạng thái để xem tiến trình và thao tác nhanh."
        />
      </section>
    );
  }

  const allowedActions = ticketAllowedActions(selectedTicket);
  const disableActions = !isOnline || isPending || stationId === null || selectedTicket.row_version === null;
  const orderItemQuantity = selectedTicket.order_item?.quantity ?? null;
  const orderItemStatus = selectedTicket.order_item?.status ?? selectedTicket.reconciliation.order_item_expected_status ?? null;
  const ticketNotes = readTicketNote(selectedTicket.ticket_notes);
  const orderItemNotes = readTicketNote(selectedTicket.order_item?.notes);
  const reconciliationLabel = selectedTicket.reconciliation.order_item_matches_ticket === false
    ? 'Đang lệch'
    : selectedTicket.reconciliation.order_item_matches_ticket === true
      ? 'Đang khớp'
      : 'Chưa rõ';

  const eta = useSmartETA(selectedTicket);

  return (
    <section className="staff-kitchen-subpanel" aria-label="Chi tiết phiếu bếp">
      <div className="staff-kitchen-section-head">
        <div>
          <span className="staff-eyebrow">Phiếu #{selectedTicket.ticket_id}</span>
          <h3>{ticketDisplayName(selectedTicket)}</h3>
          {eta ? (
            <div style={{ marginTop: 4, display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, color: '#595959' }}>
              <ClockCircleOutlined /> 
              <span><strong>ETA: {eta.estimatedMinutes} phút</strong> ({eta.confidence === 'high' ? 'Tin cậy cao' : eta.confidence === 'medium' ? 'Tin cậy TB' : 'Tin cậy thấp'} - {eta.reason})</span>
            </div>
          ) : null}
        </div>
        <StatusChip label={translateUiCode(selectedTicket.ticket_status)} tone={kitchenTone(selectedTicket.ticket_status)} />
      </div>

      <div className="staff-kitchen-ticket-facts">
        <span>Đơn #{selectedTicket.order.order_id}</span>
        <span>Phiếu v{selectedTicket.row_version ?? 'chưa có'}</span>
        <span>Gửi bếp {selectedTicket.dispatch_count}</span>
        <span>Gọi lại {selectedTicket.recall_count}</span>
        <span>{formatRelativeAge(selectedTicket.updated_at)}</span>
      </div>

      <div className="staff-kitchen-ticket-stack" aria-label="Ngữ cảnh món trên phiếu bếp">
        <div className="staff-kitchen-ticket-card">
          <span>Số lượng</span>
          <strong>{orderItemQuantity === null ? 'Chưa có' : `x${orderItemQuantity}`}</strong>
        </div>
        <div className="staff-kitchen-ticket-card">
          <span>Trạng thái món trên đơn</span>
          <strong>{orderItemStatus ? translateUiCode(orderItemStatus) : 'Chưa có'}</strong>
        </div>
        <div className="staff-kitchen-ticket-card">
          <span>Đối soát phiếu</span>
          <strong>{reconciliationLabel}</strong>
        </div>
      </div>

      {ticketNotes ? (
        <div className="staff-kitchen-ticket-card" aria-label="Ghi chú phiếu bếp">
          <span>Ghi chú phiếu bếp</span>
          <p className="staff-kitchen-ticket-copy">{ticketNotes}</p>
        </div>
      ) : null}

      {orderItemNotes ? (
        <div className="staff-kitchen-ticket-card" aria-label="Ghi chú món">
          <span>Ghi chú món</span>
          <p className="staff-kitchen-ticket-copy">{orderItemNotes}</p>
        </div>
      ) : null}

      <div className="staff-kitchen-ticket-timeline" aria-label="Tiến trình phiếu bếp">
        {ticketTimeline(selectedTicket).map((entry) => (
          <div key={entry.key} className={`staff-kitchen-timeline-item${entry.active ? ' staff-kitchen-timeline-item-active' : ''}`}>
            <span>{entry.label}</span>
            <strong>{entry.value ? formatDateTime(entry.value) : 'Chưa có'}</strong>
          </div>
        ))}
      </div>

      <div className="staff-kitchen-fast-actions">
        <Button
          type="primary"
          size="large"
          onClick={() => onTicketAction('fire')}
          disabled={disableActions || !allowedActions.includes('fire')}
          loading={isPending}
        >
          Bắt đầu làm
        </Button>
        <Button
          type="primary"
          size="large"
          onClick={() => onTicketAction('bump')}
          disabled={disableActions || !allowedActions.includes('bump')}
          loading={isPending}
        >
          Báo đã xong
        </Button>
        <Button
          size="large"
          danger
          onClick={() => onTicketAction('recall')}
          disabled={disableActions || !allowedActions.includes('recall')}
          loading={isPending}
        >
          Gọi lại bếp
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
        title="Bảng phiếu bếp đang ngoại tuyến"
        description="Kết nối lại trước khi tải hàng đợi hoặc gửi thao tác nhanh."
        primaryAction={<Button onClick={onRetry}>Đồng bộ lại</Button>}
      />
    );
  }

  return null;
}

function labelForTicketAction(action: KitchenTicketAction): string {
  if (action === 'fire') {
    return 'Bắt đầu làm';
  }

  if (action === 'bump') {
    return 'Báo đã xong';
  }

  return 'Gọi lại bếp';
}

function readTicketNote(value: string | null | undefined): string | null {
  if (typeof value !== 'string') {
    return null;
  }

  const normalized = value.trim();
  return normalized === '' ? null : normalized;
}
