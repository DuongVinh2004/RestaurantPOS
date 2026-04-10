import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useCallback } from 'react';
import {
  App,
  Button,
  Card,
  Descriptions,
  Empty,
  List,
  Select,
  Space,
  Table,
  Typography,
} from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { KitchenOrderItemTicket } from '../../core/api/sdk';
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
import { formatDateTime } from '../../core/utils/format';
import { buildJourneySearch, mergeJourneySearch } from '../../core/utils/journey';
import { kitchenTone } from '../../core/utils/status';
import { translateUiCode } from '../../core/utils/translation';
import { PageHeader } from '../../components/layout/PageHeader';
import { SplitWorkspace } from '../../components/layout/SplitWorkspace';
import { InlineError, InlineLoading } from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { useFlowStore } from '../../app/store/flow-store';
import { useJourneyContext } from '../../hooks/useJourneyContext';
import { useConfirmAction } from '../../hooks/useConfirmAction';
import { buildKitchenBoardSearch, readKitchenBoardUrlState, type KitchenTicketStatusFilter } from './kitchen-board-url';

const ticketStatusOptions = [
  { value: 'all', label: 'Táº¥t cáº£ phiáº¿u' },
  { value: 'Queued', label: 'Chá» cháº¿ biáº¿n' },
  { value: 'Fired', label: 'Äang cháº¿ biáº¿n' },
  { value: 'Ready', label: 'Sáºµn sÃ ng' },
  { value: 'Completed', label: 'HoÃ n táº¥t' },
  { value: 'Cancelled', label: 'ÄÃ£ há»§y' },
];

export function KitchenBoardPage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const { message } = App.useApp();
  const confirmAction = useConfirmAction();
  const journey = useJourneyContext();
  const kitchenUrlState = useMemo(() => readKitchenBoardUrlState(searchParams), [searchParams]);
  const ticketStatus = kitchenUrlState.status;

  const stationsQuery = useQuery({
    queryKey: ['kitchen-stations'],
    queryFn: listKitchenStations,
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

  useEffect(() => {
    if (!stationId || journey.stationId === stationId) {
      return;
    }

    updateKitchenSearch({ ticketId: null }, stationId, { replace: true });
  }, [journey.stationId, stationId, updateKitchenSearch]);

  const ticketsQuery = useQuery({
    queryKey: ['kitchen-tickets', stationId, ticketStatus],
    queryFn: () => getKitchenStationTickets(stationId as number, {
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

  const selectedStation = useMemo(
    () => stationsQuery.data?.data.find((station) => station.station_id === stationId) ?? null,
    [stationId, stationsQuery.data?.data],
  );

  const selectedTicketId = kitchenUrlState.ticketId;
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
        throw new Error('KhÃ´ng cÃ³ ngá»¯ cáº£nh Ä‘Æ¡n hÃ ng Ä‘Æ°á»£c chuyá»ƒn sang báº¿p.');
      }

      return dispatchKitchenOrder(journey.orderId, {
        row_version: journey.orderRowVersion ?? undefined,
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['kitchen-stations'] });
      await queryClient.invalidateQueries({ queryKey: ['kitchen-tickets'] });
      message.success(`ÄÃ£ chuyá»ƒn Ä‘Æ¡n hÃ ng #${journey.orderId} sang báº¿p.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ chuyá»ƒn Ä‘Æ¡n hÃ ng sang báº¿p.'));
    },
  });

  const ticketActionMutation = useMutation({
    mutationFn: async (action: 'fire' | 'bump' | 'recall') => {
      if (!selectedTicket) {
        throw new Error('ChÆ°a chá»n phiáº¿u báº¿p.');
      }

      if (action === 'fire') {
        await fireKitchenTicket(selectedTicket.ticket_id);
        return;
      }
      if (action === 'bump') {
        await bumpKitchenTicket(selectedTicket.ticket_id);
        return;
      }

      await recallKitchenTicket(selectedTicket.ticket_id);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['kitchen-tickets'] });
      await queryClient.invalidateQueries({ queryKey: ['kitchen-stations'] });
      message.success('ÄÃ£ cáº­p nháº­t phiáº¿u báº¿p.');
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ cáº­p nháº­t tráº¡ng thÃ¡i phiáº¿u báº¿p.'));
    },
  });

  async function handleTicketAction(action: 'fire' | 'bump' | 'recall') {
    if (!selectedTicket) {
      return;
    }

    const confirmed = await confirmAction({
      title: `${action === 'fire' ? 'Báº¯t Ä‘áº§u cháº¿ biáº¿n' : action === 'bump' ? 'ÄÃ¡nh dáº¥u sáºµn sÃ ng' : 'Gá»i láº¡i'} phiáº¿u #${selectedTicket.ticket_id}`,
      content: 'Chá»‰ thá»±c hiá»‡n bÆ°á»›c chuyá»ƒn tráº¡ng thÃ¡i an toÃ n tiáº¿p theo cho phiáº¿u báº¿p Ä‘Ã£ chá»n.',
      okText: action === 'bump' ? 'ÄÃ¡nh dáº¥u sáºµn sÃ ng' : action === 'recall' ? 'Gá»i láº¡i' : 'Báº¯t Ä‘áº§u cháº¿ biáº¿n',
      danger: action === 'recall',
    });

    if (confirmed) {
      await ticketActionMutation.mutateAsync(action);
    }
  }

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Báº¿p / KDS"
        title="MÃ n hÃ¬nh báº¿p"
        description="Giá»¯ viá»‡c Ä‘á»c khu báº¿p vÃ  chuyá»ƒn tráº¡ng thÃ¡i phiáº¿u báº¿p nhanh, rÃµ vÃ  an toÃ n. MÃ n hÃ¬nh nÃ y dÃ¹ng trá»±c tiáº¿p API khu báº¿p/phiáº¿u báº¿p, cÃ²n thao tÃ¡c chuyá»ƒn báº¿p nháº­n ngá»¯ cáº£nh tá»« mÃ n hÃ¬nh Ä‘Æ¡n hÃ ng."
        extra={
          <>
            <Select
              style={{ width: 160 }}
              value={ticketStatus}
              options={ticketStatusOptions}
              onChange={(value) => updateKitchenSearch({ status: value as KitchenTicketStatusFilter, ticketId: null }, stationId, { replace: true })}
            />
            <Button onClick={() => ticketsQuery.refetch()} disabled={!stationId} loading={ticketsQuery.isFetching}>
              LÃ m má»›i phiáº¿u báº¿p
            </Button>
          </>
        }
      />

      <Card title="Chuyá»ƒn báº¿p tá»« ngá»¯ cáº£nh Ä‘Æ¡n hÃ ng">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Typography.Text type="secondary">
            Ngá»¯ cáº£nh chuyá»ƒn báº¿p Ä‘áº¿n tá»« mÃ n hÃ¬nh Ä‘Æ¡n hÃ ng. Chá»‰ dÃ¹ng thao tÃ¡c nÃ y táº¡i Ä‘Ã¢y khi nhÃ¢n viÃªn váº«n Ä‘ang lÃ m viá»‡c á»Ÿ mÃ n hÃ¬nh báº¿p.
          </Typography.Text>
          <Descriptions bordered size="small" column={2}>
            <Descriptions.Item label="ÄÆ¡n hÃ ng">{journey.orderId ?? 'Thiáº¿u'}</Descriptions.Item>
            <Descriptions.Item label="PhiÃªn báº£n Ä‘Æ¡n hÃ ng">{journey.orderRowVersion ?? 'KhÃ´ng báº¯t buá»™c'}</Descriptions.Item>
          </Descriptions>
          <Button type="primary" onClick={() => dispatchMutation.mutate()} disabled={!journey.orderId} loading={dispatchMutation.isPending}>
            Chuyá»ƒn Ä‘Æ¡n Ä‘Ã£ bÃ n giao sang báº¿p
          </Button>
        </Space>
      </Card>

      <Card title="LÃ n khu báº¿p">
        {stationsQuery.isLoading ? <InlineLoading tip="Äang táº£i khu báº¿p..." /> : null}
        {stationsQuery.error ? <InlineError message={formatApiError(stationsQuery.error, 'KhÃ´ng thá»ƒ táº£i khu báº¿p.')} /> : null}
        <Table
          rowKey="station_id"
          pagination={false}
          dataSource={stationsQuery.data?.data ?? []}
          rowSelection={{
            type: 'radio',
            selectedRowKeys: stationId ? [stationId] : [],
            onChange: (keys) => updateKitchenSearch({ ticketId: null }, (keys[0] as number | undefined) ?? null),
          }}
          columns={[
            {
              title: 'Khu báº¿p',
              render: (_, station) => (
                <Space orientation="vertical" size={2}>
                  <Typography.Text strong>{station.name}</Typography.Text>
                  <Typography.Text type="secondary">{station.code}</Typography.Text>
                </Space>
              ),
            },
            {
              title: 'Cháº¿ Ä‘á»™',
              render: (_, station) => translateUiCode(station.output_mode),
            },
            {
              title: 'Chá» cháº¿ biáº¿n',
              render: (_, station) => station.ticket_counts.queued,
            },
            {
              title: 'Sáºµn sÃ ng',
              render: (_, station) => station.ticket_counts.ready,
            },
          ]}
        />
      </Card>
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card title={selectedStation ? `Phiáº¿u báº¿p cá»§a ${selectedStation.name}` : 'Phiáº¿u báº¿p'}>
        {!stationId ? (
          <Empty description="Chá»n má»™t khu báº¿p Ä‘á»ƒ xem phiáº¿u báº¿p." />
        ) : ticketsQuery.isLoading ? (
          <InlineLoading tip="Äang táº£i phiáº¿u báº¿p..." />
        ) : ticketsQuery.error ? (
          <InlineError message={formatApiError(ticketsQuery.error, 'KhÃ´ng thá»ƒ táº£i phiáº¿u báº¿p.')} />
        ) : (
          <List
            size="small"
            bordered
            dataSource={ticketsQuery.data?.data ?? []}
            locale={{ emptyText: 'KhÃ´ng cÃ³ phiáº¿u báº¿p nÃ o theo bá»™ lá»c khu hiá»‡n táº¡i.' }}
            renderItem={(ticket) => (
              <List.Item
                actions={[<StatusChip key="status" label={ticket.ticket_status} tone={kitchenTone(ticket.ticket_status)} />]}
              >
                <List.Item.Meta
                  title={(
                    <Button
                      type="link"
                      className="staff-link-button"
                      aria-pressed={ticket.ticket_id === selectedTicketId}
                      onClick={() => updateKitchenSearch({ ticketId: ticket.ticket_id }, stationId)}
                    >
                      {ticket.item?.name ?? ticket.order_item?.item_name_snapshot ?? `Phiáº¿u #${ticket.ticket_id}`}
                    </Button>
                  )}
                  description={`ÄÆ¡n #${ticket.order.order_id} â€¢ Ä‘Ã£ chuyá»ƒn ${ticket.dispatch_count} láº§n`}
                />
              </List.Item>
            )}
          />
        )}
      </Card>

      <Card title="Phiáº¿u báº¿p Ä‘ang chá»n">
        {!selectedTicket ? (
          <Empty description="Chá»n má»™t phiáº¿u báº¿p Ä‘á»ƒ xem tráº¡ng thÃ¡i vÃ  cÃ¡c thao tÃ¡c an toÃ n." />
        ) : (
          <Space orientation="vertical" size={16} style={{ width: '100%' }}>
            <Descriptions bordered size="small" column={1}>
              <Descriptions.Item label="Phiáº¿u báº¿p">
                #{selectedTicket.ticket_id}
              </Descriptions.Item>
              <Descriptions.Item label="Tráº¡ng thÃ¡i">
                <StatusChip label={selectedTicket.ticket_status} tone={kitchenTone(selectedTicket.ticket_status)} />
              </Descriptions.Item>
              <Descriptions.Item label="ÄÆ¡n hÃ ng">
                #{selectedTicket.order.order_id}
              </Descriptions.Item>
              <Descriptions.Item label="Äáº·t bÃ n">
                #{selectedTicket.order.reservation_id}
              </Descriptions.Item>
              <Descriptions.Item label="Cáº­p nháº­t láº§n cuá»‘i">
                {formatDateTime(selectedTicket.updated_at)}
              </Descriptions.Item>
            </Descriptions>
            <div className="staff-action-row">
              <Button onClick={() => handleTicketAction('fire')} disabled={selectedTicket.ticket_status !== 'Queued'} loading={ticketActionMutation.isPending}>
                Báº¯t Ä‘áº§u cháº¿ biáº¿n
              </Button>
              <Button onClick={() => handleTicketAction('bump')} disabled={selectedTicket.ticket_status !== 'Fired'} loading={ticketActionMutation.isPending}>
                ÄÃ¡nh dáº¥u sáºµn sÃ ng
              </Button>
              <Button danger onClick={() => handleTicketAction('recall')} disabled={selectedTicket.ticket_status !== 'Ready'} loading={ticketActionMutation.isPending}>
                Gá»i láº¡i
              </Button>
            </div>
            <Button
              onClick={() =>
                navigate(`/checkout?${buildJourneySearch({
                  source: 'kitchen',
                  tableId: journey.tableId,
                  reservationId: journey.reservationId,
                  reservationRowVersion: journey.reservationRowVersion,
                  orderId: selectedTicket.order.order_id,
                  orderRowVersion: journey.orderRowVersion,
                  stationId: stationId ?? undefined,
                })}`)
              }
            >
              Má»Ÿ thanh toÃ¡n tá»« Ä‘Æ¡n hÃ ng hiá»‡n táº¡i
            </Button>
          </Space>
        )}
      </Card>

      <Card title="Äá»“ng bá»™ báº¿p">
        {changesQuery.isLoading ? (
          <InlineLoading tip="Äang Ä‘á»c luá»“ng thay Ä‘á»•i cá»§a báº¿p..." />
        ) : changesQuery.error ? (
          <InlineError message={formatApiError(changesQuery.error, 'KhÃ´ng thá»ƒ táº£i luá»“ng thay Ä‘á»•i cá»§a báº¿p.')} />
        ) : (
          <Space orientation="vertical" size={8}>
            <Typography.Text type="secondary">
              PhiÃªn báº£n realtime v{changesQuery.data?.data.current_version ?? 0}
            </Typography.Text>
            <Typography.Text type="secondary">
              Sá»± kiá»‡n: {changesQuery.data?.data.events.length ?? 0}
            </Typography.Text>
            <Typography.Text type="secondary">
              Gá»£i Ã½ poll: {(changesQuery.data?.data.poll_hint_ms ?? 0) / 1000}s
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

