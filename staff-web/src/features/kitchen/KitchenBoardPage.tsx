import { useEffect, useMemo, useState } from 'react';
import { useCallback, useRef } from 'react';
import type { ChangeEvent } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import {
  Button,
  Card,
  Descriptions,
  Empty,
  Space,
  Typography,
} from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  bumpKitchenTicket,
  dispatchKitchenOrder,
  fireKitchenTicket,
  getKitchenChanges,
  getKitchenStationTickets,
  listKitchenStations,
  recallKitchenTicket,
} from '../../core/api/staff-api';
import { formatApiError } from '../../core/api/errors';
import { formatDateTime, formatRelativeAge } from '../../core/utils/format';
import { buildJourneySearch, mergeJourneySearch } from '../../core/utils/journey';
import { kitchenTone } from '../../core/utils/status';
import { translateUiCode } from '../../core/utils/translation';
import { PageHeader } from '../../components/layout/PageHeader';
import { SplitWorkspace } from '../../components/layout/SplitWorkspace';
import { toast } from '../../components/feedback/toast';
import { InlineError, InlineLoading } from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { useFlowStore } from '../../app/store/flow-store';
import { useJourneyContext } from '../../hooks/useJourneyContext';
import { useConfirmAction } from '../../hooks/useConfirmAction';
import { buildKitchenBoardSearch, readKitchenBoardUrlState, type KitchenTicketStatusFilter } from './kitchen-board-url';

const ticketStatusOptions = [
  { value: 'all', label: 'Tất cả phiếu' },
  { value: 'Queued', label: 'Chờ chế biến' },
  { value: 'Fired', label: 'Đang chế biến' },
  { value: 'Ready', label: 'Sẵn sàng' },
  { value: 'Completed', label: 'Hoàn tất' },
  { value: 'Cancelled', label: 'Đã hủy' },
];

function isKitchenTicketStatusFilter(value: string): value is KitchenTicketStatusFilter {
  return ticketStatusOptions.some((option) => option.value === value);
}

export function KitchenBoardPage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const message = toast;
  const confirmAction = useConfirmAction();
  const journey = useJourneyContext();
  const branchId = useFlowStore((state) => state.branchId);
  const [showChangeFeed, setShowChangeFeed] = useState(true);
  const lastAppliedKitchenChangeVersionRef = useRef<number | null>(null);
  const kitchenUrlState = useMemo(() => readKitchenBoardUrlState(searchParams), [searchParams]);
  const ticketStatus = kitchenUrlState.status;

  const stationsQuery = useQuery({
    queryKey: ['kitchen-stations', branchId],
    queryFn: () => listKitchenStations(branchId ?? undefined),
    refetchInterval: 20_000,
  });

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

  const stationId = useMemo(() => {
    const stations = stationsQuery.data?.data ?? [];
    if (stations.length === 0) {
      return null;
    }

    if (journey.stationId && stations.some((station) => station.station_id === journey.stationId)) {
      return journey.stationId;
    }

    return stations[0]?.station_id ?? null;
  }, [journey.stationId, stationsQuery.data?.data]);
  const selectedTicketId = kitchenUrlState.ticketId;

  useEffect(() => {
    if (!stationId || journey.stationId === stationId || journey.orderId || selectedTicketId !== null) {
      return;
    }

    updateKitchenSearch({ ticketId: null }, stationId, { replace: true });
  }, [journey.orderId, journey.stationId, selectedTicketId, stationId, updateKitchenSearch]);

  const ticketsQuery = useQuery({
    queryKey: ['kitchen-tickets', branchId, stationId, ticketStatus],
    queryFn: () => getKitchenStationTickets(stationId as number, {
      branch_id: branchId ?? undefined,
      status: ticketStatus === 'all' ? undefined : ticketStatus,
      include_terminal: false,
    }),
    enabled: !!stationId,
    refetchInterval: 15_000,
  });

  const changesQuery = useQuery({
    queryKey: ['kitchen-changes', stationsQuery.data?.meta?.realtime.current_version],
    queryFn: () => getKitchenChanges(stationsQuery.data?.meta?.realtime.current_version),
    enabled: !!stationsQuery.data?.meta?.realtime.current_version,
    refetchInterval: 20_000,
  });

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
        queryClient.invalidateQueries({ queryKey: ['kitchen-stations'], refetchType: 'active' }),
        queryClient.invalidateQueries({ queryKey: ['kitchen-tickets'], refetchType: 'active' }),
      ]);
    }
  }, [
    changesQuery.data?.data.current_version,
    changesQuery.data?.data.events.length,
    queryClient,
    stationsQuery.data?.meta?.realtime.current_version,
  ]);

  const selectedStation = useMemo(
    () => stationsQuery.data?.data.find((station) => station.station_id === stationId) ?? null,
    [stationId, stationsQuery.data?.data],
  );
  useEffect(() => {
    const tickets = ticketsQuery.data?.data ?? [];
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
  }, [journey.orderId, selectedTicketId, stationId, ticketsQuery.data?.data, updateKitchenSearch]);

  const selectedTicket = useMemo(
    () => ticketsQuery.data?.data.find((ticket) => ticket.ticket_id === selectedTicketId) ?? null,
    [selectedTicketId, ticketsQuery.data?.data],
  );

  const dispatchMutation = useMutation({
    mutationFn: async () => {
      if (!journey.orderId) {
        throw new Error('Không có ngữ cảnh đơn hàng được chuyển sang bếp.');
      }

      return dispatchKitchenOrder(journey.orderId, {
        row_version: journey.orderRowVersion ?? undefined,
      });
    },
    onSuccess: async (dispatchEnvelope) => {
      const dispatchedTicket = dispatchEnvelope.data[0] ?? null;
      const dispatchedStationId = dispatchedTicket?.station?.station_id ?? null;
      const unroutedCount = dispatchEnvelope.meta.unrouted_count ?? 0;

      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['kitchen-stations'] }),
        queryClient.invalidateQueries({ queryKey: ['kitchen-tickets'] }),
        queryClient.invalidateQueries({ queryKey: ['order-detail', journey.orderId] }),
        queryClient.invalidateQueries({ queryKey: ['checkout-order-detail', journey.orderId] }),
      ]);

      if (dispatchedTicket) {
        updateKitchenSearch({ ticketId: dispatchedTicket.ticket_id }, dispatchedStationId, { replace: true });
      } else if (dispatchedStationId) {
        updateKitchenSearch({ ticketId: null }, dispatchedStationId, { replace: true });
      }

      if (!dispatchedTicket && unroutedCount > 0) {
        message.warning(`Đơn hàng #${journey.orderId} chưa tạo phiếu bếp vì ${unroutedCount} món chưa có route bếp.`);
        return;
      }

      message.success(`Đã chuyển đơn hàng #${journey.orderId} sang bếp.`);

      if (unroutedCount > 0) {
        message.warning(`${unroutedCount} món chưa có route bếp nên không tạo phiếu.`);
      }
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể chuyển đơn hàng sang bếp.'));
    },
  });

  const ticketActionMutation = useMutation({
    mutationFn: async (action: 'fire' | 'bump' | 'recall') => {
      if (!selectedTicket) {
        throw new Error('Chưa chọn phiếu bếp.');
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
        queryClient.invalidateQueries({ queryKey: ['kitchen-tickets'] }),
        queryClient.invalidateQueries({ queryKey: ['kitchen-stations'] }),
        queryClient.invalidateQueries({ queryKey: ['order-detail', orderId] }),
        queryClient.invalidateQueries({ queryKey: ['checkout-order-detail', orderId] }),
      ]);
      message.success('Đã cập nhật phiếu bếp.');
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể cập nhật trạng thái phiếu bếp.'));
    },
  });

  async function handleTicketAction(action: 'fire' | 'bump' | 'recall') {
    if (!selectedTicket) {
      return;
    }

    const confirmed = await confirmAction({
      title: `${action === 'fire' ? 'Bắt đầu chế biến' : action === 'bump' ? 'Đánh dấu sẵn sàng' : 'Gọi lại'} phiếu #${selectedTicket.ticket_id}`,
      content: 'Chỉ thực hiện bước chuyển trạng thái an toàn tiếp theo cho phiếu bếp đã chọn.',
      okText: action === 'bump' ? 'Đánh dấu sẵn sàng' : action === 'recall' ? 'Gọi lại' : 'Bắt đầu chế biến',
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

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Bếp / KDS"
        title="Màn hình bếp"
        description="Theo dõi trạm bếp, hàng chờ và trạng thái phiếu theo đúng nhịp ra món."
        context={(
          <>
            <StatusChip label={branchId ? `Chi nhánh #${branchId}` : 'Theo branch mặc định'} tone="processing" />
            <StatusChip label={selectedStation ? `${selectedStation.code} • ${selectedStation.name}` : 'Chưa chọn trạm'} tone={selectedStation ? 'processing' : 'warning'} />
            <StatusChip label={selectedTicket ? `Phiếu #${selectedTicket.ticket_id}` : 'Chưa khóa phiếu'} tone={selectedTicket ? kitchenTone(selectedTicket.ticket_status) : 'warning'} />
            <StatusChip label={`${ticketsQuery.data?.data.length ?? 0} phiếu theo bộ lọc`} tone="default" />
          </>
        )}
        extra={
          <div className="staff-kitchen-toolbar">
            <div className="staff-toolbar-select-wrap">
              <select
                aria-label="Lọc trạng thái phiếu bếp"
                className="staff-toolbar-select"
                value={ticketStatus}
                onChange={handleTicketStatusChange}
              >
                {ticketStatusOptions.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>
            <Button onClick={() => ticketsQuery.refetch()} disabled={!stationId} loading={ticketsQuery.isFetching}>
              Làm mới phiếu bếp
            </Button>
            <Button onClick={() => setShowChangeFeed((value) => !value)}>
              {showChangeFeed ? 'Ẩn live feed' : 'Mở live feed'}
            </Button>
          </div>
        }
      />

      <Card size="small">
        <Space wrap>
          <Button type={ticketStatus === 'Queued' ? 'primary' : 'default'} onClick={() => updateKitchenSearch({ status: 'Queued', ticketId: null }, stationId, { replace: true })}>
            Chờ chế biến
          </Button>
          <Button type={ticketStatus === 'Fired' ? 'primary' : 'default'} onClick={() => updateKitchenSearch({ status: 'Fired', ticketId: null }, stationId, { replace: true })}>
            Đang làm
          </Button>
          <Button type={ticketStatus === 'Ready' ? 'primary' : 'default'} onClick={() => updateKitchenSearch({ status: 'Ready', ticketId: null }, stationId, { replace: true })}>
            Sẵn sàng
          </Button>
          <Button onClick={() => updateKitchenSearch({ status: 'all', ticketId: null }, stationId, { replace: true })}>
            Xóa preset
          </Button>
        </Space>
      </Card>

      <Card title="Chuyển bếp từ ngữ cảnh đơn hàng">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Typography.Text type="secondary">
            Ngữ cảnh chuyển bếp đến từ màn hình đơn hàng. Chỉ dùng thao tác này tại đây khi nhân viên vẫn đang làm việc ở màn hình bếp.
          </Typography.Text>
          <Descriptions bordered size="small" column={2}>
            <Descriptions.Item label="Đơn hàng">{journey.orderId ?? 'Thiếu'}</Descriptions.Item>
            <Descriptions.Item label="Phiên bản đơn hàng">{journey.orderRowVersion ?? 'Không bắt buộc'}</Descriptions.Item>
          </Descriptions>
          <Button type="primary" onClick={() => dispatchMutation.mutate()} disabled={!journey.orderId} loading={dispatchMutation.isPending}>
            Chuyển đơn đã bàn giao sang bếp
          </Button>
        </Space>
      </Card>

      <Card title="Làn khu bếp">
        {stationsQuery.isLoading ? <InlineLoading tip="Đang tải khu bếp..." /> : null}
        {stationsQuery.error ? <InlineError message={formatApiError(stationsQuery.error, 'Không thể tải khu bếp.')} /> : null}
        {!stationsQuery.isLoading && !stationsQuery.error && (stationsQuery.data?.data.length ?? 0) === 0 ? (
          <Empty description="Chưa có khu bếp nào được cấu hình cho chi nhánh hiện tại." />
        ) : null}
        {!stationsQuery.isLoading && !stationsQuery.error && (stationsQuery.data?.data.length ?? 0) > 0 ? (
          <div className="staff-kitchen-station-list" role="list" aria-label="Danh sách khu bếp">
            {(stationsQuery.data?.data ?? []).map((station) => {
              const isSelected = station.station_id === stationId;

              return (
                <button
                  key={station.station_id}
                  type="button"
                  className={`staff-kitchen-station-card${isSelected ? ' staff-card-selected' : ''}`}
                  aria-pressed={isSelected}
                  onClick={() => updateKitchenSearch({ ticketId: null }, station.station_id)}
                >
                  <div className="staff-kitchen-station-card-main">
                    <div className="staff-kitchen-station-card-head">
                      <div className="staff-kitchen-station-copy">
                        <span className="staff-kitchen-station-code">{station.code}</span>
                        <strong>{station.name}</strong>
                      </div>
                      <span
                        className={`staff-kitchen-station-state${isSelected ? ' staff-kitchen-station-state-active' : ''}`}
                      >
                        {isSelected ? 'Đang xem' : 'Chọn'}
                      </span>
                    </div>
                    <div className="staff-kitchen-station-metrics">
                      <span>
                        <strong>{translateUiCode(station.output_mode)}</strong>
                        <small>Chế độ</small>
                      </span>
                      <span>
                        <strong>{station.ticket_counts.queued}</strong>
                        <small>Chờ chế biến</small>
                      </span>
                      <span>
                        <strong>{station.ticket_counts.ready}</strong>
                        <small>Sẵn sàng</small>
                      </span>
                    </div>
                  </div>
                </button>
              );
            })}
          </div>
        ) : null}
      </Card>
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card title={selectedStation ? `Phiếu bếp của ${selectedStation.name}` : 'Phiếu bếp'}>
        {!stationId ? (
          <Empty description="Chọn một khu bếp để xem phiếu bếp." />
        ) : ticketsQuery.isLoading ? (
          <InlineLoading tip="Đang tải phiếu bếp..." />
        ) : ticketsQuery.error ? (
          <InlineError message={formatApiError(ticketsQuery.error, 'Không thể tải phiếu bếp.')} />
        ) : (
          (ticketsQuery.data?.data.length ?? 0) === 0 ? (
            <Empty description="Không có phiếu bếp nào theo bộ lọc khu hiện tại." />
          ) : (
            <div className="staff-mini-list">
              {(ticketsQuery.data?.data ?? []).map((ticket) => (
                <div key={ticket.ticket_id} className="staff-mini-list-item">
                  <div style={{ minWidth: 0 }}>
                    <Button
                      type="link"
                      className="staff-link-button"
                      aria-pressed={ticket.ticket_id === selectedTicketId}
                      onClick={() => updateKitchenSearch({ ticketId: ticket.ticket_id }, stationId)}
                    >
                      {ticket.item?.name ?? ticket.order_item?.item_name_snapshot ?? `Phiếu #${ticket.ticket_id}`}
                    </Button>
                    <Typography.Text type="secondary">
                      {`Đơn #${ticket.order.order_id} • đã chuyển ${ticket.dispatch_count} lần • ${formatRelativeAge(ticket.updated_at)}`}
                    </Typography.Text>
                  </div>
                  <StatusChip label={ticket.ticket_status} tone={kitchenTone(ticket.ticket_status)} />
                </div>
              ))}
            </div>
          )
        )}
      </Card>

      <Card title="Phiếu bếp đang chọn">
        {!selectedTicket ? (
          <Empty description="Chọn một phiếu bếp để xem trạng thái và các thao tác an toàn." />
        ) : (
          <Space orientation="vertical" size={16} style={{ width: '100%' }}>
            <Descriptions bordered size="small" column={1}>
              <Descriptions.Item label="Phiếu bếp">
                #{selectedTicket.ticket_id}
              </Descriptions.Item>
              <Descriptions.Item label="Trạng thái">
                <StatusChip label={selectedTicket.ticket_status} tone={kitchenTone(selectedTicket.ticket_status)} />
              </Descriptions.Item>
              <Descriptions.Item label="Đơn hàng">
                #{selectedTicket.order.order_id}
              </Descriptions.Item>
              <Descriptions.Item label="Đặt bàn">
                #{selectedTicket.order.reservation_id}
              </Descriptions.Item>
              <Descriptions.Item label="Cập nhật lần cuối">
                <Space orientation="vertical" size={2}>
                  <Typography.Text>{formatDateTime(selectedTicket.updated_at)}</Typography.Text>
                  <Typography.Text type="secondary">{formatRelativeAge(selectedTicket.updated_at)}</Typography.Text>
                </Space>
              </Descriptions.Item>
            </Descriptions>
            <div className="staff-action-row">
              <Button onClick={() => handleTicketAction('fire')} disabled={selectedTicket.ticket_status !== 'Queued'} loading={ticketActionMutation.isPending}>
                Bắt đầu chế biến
              </Button>
              <Button onClick={() => handleTicketAction('bump')} disabled={selectedTicket.ticket_status !== 'Fired'} loading={ticketActionMutation.isPending}>
                Đánh dấu sẵn sàng
              </Button>
              <Button danger onClick={() => handleTicketAction('recall')} disabled={selectedTicket.ticket_status !== 'Ready'} loading={ticketActionMutation.isPending}>
                Gọi lại
              </Button>
            </div>
            <Button
              onClick={() =>
                navigate(`/checkout?${buildJourneySearch({
                  source: 'kitchen',
                  tableId: journey.tableId,
                  tableIds: journey.tableIds,
                  reservationId: journey.reservationId,
                  reservationRowVersion: journey.reservationRowVersion,
                  orderId: selectedTicket.order.order_id,
                  orderRowVersion: journey.orderRowVersion,
                  stationId: stationId ?? undefined,
                })}`)
              }
            >
              Mở thanh toán từ đơn hàng hiện tại
            </Button>
          </Space>
        )}
      </Card>

      <Card
        title="Đồng bộ bếp"
        extra={<Typography.Text type="secondary">{showChangeFeed ? 'Đang theo dõi realtime' : 'Đã thu gọn'}</Typography.Text>}
      >
        {!showChangeFeed ? (
          <Typography.Text type="secondary">
            Mở live feed khi cần rà soát sự kiện realtime hoặc xác nhận nhịp đồng bộ của bếp.
          </Typography.Text>
        ) : changesQuery.isLoading ? (
          <InlineLoading tip="Đang đọc luồng thay đổi của bếp..." />
        ) : changesQuery.error ? (
          <InlineError message={formatApiError(changesQuery.error, 'Không thể tải luồng thay đổi của bếp.')} />
        ) : (
          <Space orientation="vertical" size={8}>
            <Typography.Text type="secondary">
              Phiên bản realtime v{changesQuery.data?.data.current_version ?? 0}
            </Typography.Text>
            <Typography.Text type="secondary">
              Sự kiện: {changesQuery.data?.data.events.length ?? 0}
            </Typography.Text>
            <Typography.Text type="secondary">
              Gợi ý poll: {(changesQuery.data?.data.poll_hint_ms ?? 0) / 1000}s
            </Typography.Text>
          </Space>
        )}
      </Card>
    </Space>
  );

  return (
    <div data-testid="kitchen-board-page">
      <SplitWorkspace main={main} side={side} />
    </div>
  );
}

