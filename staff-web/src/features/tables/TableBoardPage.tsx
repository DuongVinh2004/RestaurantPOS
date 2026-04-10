import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useCallback } from 'react';
import {
  App,
  Button,
  Card,
  Col,
  Form,
  Input,
  InputNumber,
  Modal,
  Row,
  Select,
  Space,
  Statistic,
  Table,
  Typography,
} from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { StaffTableBoardRow, StaffTableBoardUnassignedReservation } from '../../core/api/sdk';
import {
  assignBestFitTable,
  assignSuggestedTable,
  buildBoardWindow,
  checkInReservation,
  createWalkInSession,
  getTableBoard,
  getTableBoardChanges,
} from '../../core/api/staff-api';
import { formatApiError } from '../../core/api/errors';
import { formatDateTime } from '../../core/utils/format';
import { buildJourneySearch, mergeJourneySearch } from '../../core/utils/journey';
import { reservationTone, tableTone } from '../../core/utils/status';
import { translateUiCode } from '../../core/utils/translation';
import { PageHeader } from '../../components/layout/PageHeader';
import { SplitWorkspace } from '../../components/layout/SplitWorkspace';
import { EmptyBlock, InlineError, InlineLoading } from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { useAuthStore } from '../../app/store/auth-store';
import { useFlowStore } from '../../app/store/flow-store';
import { useConfirmAction } from '../../hooks/useConfirmAction';
import { useJourneyContext } from '../../hooks/useJourneyContext';
import { can } from '../../core/permissions/capabilities';
import { buildTableBoardSearch, readTableBoardUrlState } from './table-board-url';

type WalkInFormValues = {
  guest_name: string;
  phone?: string;
  guest_count: number;
  service_minutes?: number;
  notes?: string;
};

export function TableBoardPage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const { message } = App.useApp();
  const confirmAction = useConfirmAction();
  const journey = useJourneyContext();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const setTableContext = useFlowStore((state) => state.setTableContext);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const setOrderContext = useFlowStore((state) => state.setOrderContext);
  const windowRange = useMemo(() => buildBoardWindow(), []);
  const [walkInOpen, setWalkInOpen] = useState(false);
  const [walkInForm] = Form.useForm<WalkInFormValues>();
  const boardUrlState = useMemo(() => readTableBoardUrlState(searchParams), [searchParams]);
  const zone = boardUrlState.zone !== '' ? boardUrlState.zone : undefined;
  const selectedTableId = journey.tableId ?? null;

  const updateBoardSearch = useCallback((
    patch: Partial<typeof boardUrlState>,
    context: Parameters<typeof mergeJourneySearch>[1],
    options?: { replace?: boolean },
  ) => {
    const nextLocalSearch = buildTableBoardSearch(searchParams, patch);
    const nextSearch = mergeJourneySearch(nextLocalSearch, context);
    setSearchParams(new URLSearchParams(nextSearch), { replace: options?.replace });
  }, [boardUrlState, searchParams, setSearchParams]);

  const clearBoardSelection = useCallback((options?: { replace?: boolean }) => {
    updateBoardSearch({ zone: boardUrlState.zone }, { source: 'board' }, options);
  }, [boardUrlState.zone, updateBoardSearch]);

  const selectBoardRow = useCallback((row: StaffTableBoardRow) => {
    updateBoardSearch(
      { zone: boardUrlState.zone },
      {
        source: 'board',
        tableId: row.table_id,
        reservationId: row.reservation?.reservation_id,
        reservationRowVersion: row.reservation?.row_version,
        orderId: row.active_order?.order_id,
        orderRowVersion: row.active_order?.row_version,
      },
    );
  }, [boardUrlState.zone, updateBoardSearch]);

  const boardQuery = useQuery({
    queryKey: ['table-board', branchId, zone, windowRange.from, windowRange.to],
    queryFn: () =>
      getTableBoard({
        ...windowRange,
        branch_id: branchId ?? undefined,
        zone,
        include_holds: true,
        group_by: 'zone',
      }),
    refetchInterval: 30_000,
  });

  const selectedTable = useMemo(
    () => boardQuery.data?.data.find((row) => row.table_id === selectedTableId) ?? null,
    [boardQuery.data?.data, selectedTableId],
  );

  useEffect(() => {
    const rows = boardQuery.data?.data ?? [];
    if (rows.length === 0) {
      if (selectedTableId) {
        clearBoardSelection({ replace: true });
      }
      return;
    }

    if (!selectedTableId || selectedTable) {
      return;
    }

    clearBoardSelection({ replace: true });
  }, [boardQuery.data?.data, clearBoardSelection, selectedTable, selectedTableId]);

  const boardChangesQuery = useQuery({
    queryKey: ['table-board-changes', boardQuery.data?.meta.realtime.current_version],
    queryFn: () => getTableBoardChanges(boardQuery.data?.meta.realtime.current_version),
    enabled: !!boardQuery.data?.meta.realtime.current_version,
    refetchInterval: 20_000,
  });

  const walkInMutation = useMutation({
    mutationFn: async (values: WalkInFormValues) => {
      if (!selectedTable) {
        throw new Error('Chá»n bÃ n trÆ°á»›c khi táº¡o phiÃªn khÃ¡ch vÃ£ng lai.');
      }

      return createWalkInSession({
        branch_id: branchId,
        guest_name: values.guest_name,
        phone: values.phone,
        table_ids: [selectedTable.table_id],
        guest_count: values.guest_count,
        service_minutes: values.service_minutes,
        notes: values.notes,
      });
    },
    onSuccess: async (reservationEnvelope) => {
      const reservation = reservationEnvelope.data;
      setWalkInOpen(false);
      walkInForm.resetFields();
      await queryClient.invalidateQueries({ queryKey: ['table-board'] });
      setReservationContext({
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
        source: 'board',
      });
      setTableContext({ tableId: selectedTable?.table_id ?? null, source: 'board' });
      message.success(`ÄÃ£ táº¡o khÃ¡ch vÃ£ng lai ${reservation.reservation_code}.`);
      navigate(`/orders?${buildJourneySearch({
        source: 'board',
        tableId: selectedTable?.table_id ?? undefined,
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
      })}`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ táº¡o phiÃªn phá»¥c vá»¥ khÃ¡ch vÃ£ng lai.'));
    },
  });

  const checkInMutation = useMutation({
    mutationFn: async (table: StaffTableBoardRow) => {
      if (!table.reservation) {
        throw new Error('BÃ n nÃ y khÃ´ng cÃ³ Ä‘áº·t bÃ n Ä‘á»ƒ nháº­n bÃ n.');
      }

      return checkInReservation(table.reservation.reservation_id, {
        row_version: table.actions.check_in?.preferred_payload.row_version ?? table.reservation.row_version,
        table_ids: table.actions.check_in?.preferred_payload.table_ids ?? table.reservation.table_ids,
      });
    },
    onSuccess: async (_, table) => {
      await queryClient.invalidateQueries({ queryKey: ['table-board'] });
      message.success(`ÄÃ£ nháº­n bÃ n cho Ä‘áº·t bÃ n ${table.reservation?.reservation_code}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ nháº­n bÃ n cho Ä‘áº·t bÃ n nÃ y.'));
    },
  });

  const assignCurrentTableMutation = useMutation({
    mutationFn: async (reservation: { reservationId: number; rowVersion: number }) => {
      if (!selectedTable) {
        throw new Error('Chá»n bÃ n Ä‘Ã­ch trÆ°á»›c khi gÃ¡n.');
      }

      return assignSuggestedTable(reservation.reservationId, {
        table_id: selectedTable.table_id,
        row_version: reservation.rowVersion,
        board_from: windowRange.from,
        board_to: windowRange.to,
        zone: selectedTable.zone,
      });
    },
    onSuccess: async (reservationEnvelope) => {
      const reservation = reservationEnvelope.data;
      await queryClient.invalidateQueries({ queryKey: ['table-board'] });
      setReservationContext({
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
        source: 'board',
      });
      message.success(`ÄÃ£ gÃ¡n Ä‘áº·t bÃ n ${reservation.reservation_code} vÃ o bÃ n hiá»‡n táº¡i.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ gÃ¡n Ä‘áº·t bÃ n vÃ o bÃ n hiá»‡n táº¡i.'));
    },
  });

  const assignBestFitMutation = useMutation({
    mutationFn: async (reservation: StaffTableBoardUnassignedReservation) =>
      assignBestFitTable(reservation.reservation_id, {
        row_version: reservation.row_version,
        board_from: windowRange.from,
        board_to: windowRange.to,
        zone,
      }),
    onSuccess: async (reservationEnvelope) => {
      const reservation = reservationEnvelope.data;
      await queryClient.invalidateQueries({ queryKey: ['table-board'] });
      setReservationContext({
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
        source: 'board',
      });
      message.success(`ÄÃ£ gÃ¡n bÃ n phÃ¹ há»£p nháº¥t cho ${reservation.reservation_code}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ gÃ¡n bÃ n phÃ¹ há»£p nháº¥t.'));
    },
  });

  async function handleCheckIn(table: StaffTableBoardRow) {
    const confirmed = await confirmAction({
      title: `Nháº­n bÃ n ${table.reservation?.reservation_code ?? 'Ä‘áº·t bÃ n'}`,
      content: 'Thao tÃ¡c nÃ y Ä‘Æ°a Ä‘áº·t bÃ n sang tráº¡ng thÃ¡i Ä‘ang phá»¥c vá»¥ vÃ  giá»¯ nguyÃªn gÃ¡n bÃ n hiá»‡n táº¡i.',
      okText: 'Nháº­n bÃ n',
    });

    if (confirmed) {
      await checkInMutation.mutateAsync(table);
    }
  }

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Váº­n hÃ nh sÃ n phá»¥c vá»¥"
        title="SÆ¡ Ä‘á»“ bÃ n"
        description="DÃ¹ng sÆ¡ Ä‘á»“ bÃ n lÃ m Ä‘iá»ƒm vÃ o chÃ­nh khi váº­n hÃ nh. Chá»n bÃ n, xem Ä‘áº·t bÃ n hoáº·c Ä‘Æ¡n hiá»‡n táº¡i, tiáº¿p nháº­n khÃ¡ch vÃ£ng lai vÃ  luÃ´n tháº¥y rÃµ viá»‡c cáº§n lÃ m tiáº¿p theo."
        extra={
          <>
            <Select
              allowClear
              placeholder="Lá»c theo khu"
              style={{ width: 160 }}
              value={zone}
              options={(boardQuery.data?.zones ?? []).map((zoneSummary) => ({
                label: `${zoneSummary.zone} (${zoneSummary.summary.table_count})`,
                value: zoneSummary.zone,
              }))}
              onChange={(value) => updateBoardSearch({ zone: value ?? '' }, { source: 'board' }, { replace: true })}
            />
            <Button onClick={() => boardQuery.refetch()} loading={boardQuery.isFetching}>
              LÃ m má»›i sÆ¡ Ä‘á»“
            </Button>
            {session && can(session, 'waiting_list.manage') ? (
              <Button onClick={() => navigate('/waiting-list')}>
                Má»Ÿ danh sÃ¡ch chá»
              </Button>
            ) : null}
          </>
        }
      />

      <Row gutter={[16, 16]}>
        <Col xs={24} md={8}>
          <Card>
            <Statistic title="ÄÆ¡n Ä‘ang phá»¥c vá»¥" value={boardQuery.data?.summary.active_order_count ?? 0} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card>
            <Statistic title="Äáº·t bÃ n chÆ°a gÃ¡n" value={boardQuery.data?.summary.unassigned_reservation_count ?? 0} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card>
            <Statistic title="PhiÃªn báº£n realtime" value={boardQuery.data?.meta.realtime.current_version ?? 0} />
          </Card>
        </Col>
      </Row>

      {boardQuery.isLoading ? <InlineLoading tip="Äang táº£i sÆ¡ Ä‘á»“ bÃ n..." /> : null}
      {boardQuery.error ? <InlineError message={formatApiError(boardQuery.error, 'KhÃ´ng thá»ƒ táº£i sÆ¡ Ä‘á»“ bÃ n.')} /> : null}

      <Row gutter={[16, 16]}>
        {(boardQuery.data?.data ?? []).map((row) => (
          <Col xs={24} sm={12} xl={8} key={row.table_id}>
            <Card
              className={row.table_id === selectedTableId ? 'staff-card-selected' : undefined}
              extra={(
                <Button
                  type={row.table_id === selectedTableId ? 'primary' : 'default'}
                  onClick={() => selectBoardRow(row)}
                >
                  {row.table_id === selectedTableId ? 'Äang táº­p trung' : 'Táº­p trung bÃ n'}
                </Button>
              )}
            >
              <Space orientation="vertical" size={10} style={{ width: '100%' }}>
                <Space align="center" style={{ justifyContent: 'space-between', width: '100%' }}>
                  <Typography.Title level={4} style={{ margin: 0 }}>
                    {row.table_code}
                  </Typography.Title>
                  <StatusChip label={row.board_state} tone={tableTone(row.board_state)} />
                </Space>
                <Typography.Text type="secondary">
                  {row.zone ?? 'ChÆ°a cÃ³ khu'} â€¢ {row.capacity.seats ?? 'KhÃ´ng cÃ³'} chá»—
                </Typography.Text>
                <Space wrap>
                  <StatusChip label={row.realtime_status} tone={tableTone(row.realtime_status)} />
                  {row.reservation ? (
                    <StatusChip label={row.reservation.reservation_code} tone={reservationTone(row.reservation.status)} />
                  ) : null}
                  {row.active_order ? <StatusChip label={`ÄÆ¡n #${row.active_order.order_id}`} tone="warning" /> : null}
                </Space>
                <Typography.Paragraph type="secondary" style={{ marginBottom: 0 }}>
                  Viá»‡c tiáº¿p theo: {translateUiCode(row.operational_hints.preferred_action || 'none')}
                </Typography.Paragraph>
              </Space>
            </Card>
          </Col>
        ))}
      </Row>

      <Card
        title="Danh sÃ¡ch Ä‘áº·t bÃ n chÆ°a gÃ¡n"
        extra={<StatusChip label={`${boardQuery.data?.unassigned_reservations.length ?? 0} Ä‘áº·t bÃ n`} />}
      >
        {(boardQuery.data?.unassigned_reservations.length ?? 0) === 0 ? (
          <EmptyBlock
            title="KhÃ´ng cÃ³ Ä‘áº·t bÃ n chÆ°a gÃ¡n"
            description="Hiá»‡n táº¡i khÃ´ng cÃ³ Ä‘áº·t bÃ n nÃ o Ä‘ang chá» Ä‘Æ°á»£c gÃ¡n bÃ n."
          />
        ) : (
          <Table<StaffTableBoardUnassignedReservation>
            rowKey="reservation_id"
            pagination={false}
            dataSource={boardQuery.data?.unassigned_reservations ?? []}
            columns={[
              {
                title: 'Äáº·t bÃ n',
                render: (_, reservation) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text strong>{reservation.reservation_code}</Typography.Text>
                    <Typography.Text type="secondary">{reservation.guest_count} khÃ¡ch</Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Báº¯t Ä‘áº§u',
                dataIndex: 'start_time',
                render: (value: string | null) => formatDateTime(value),
              },
              {
                title: 'BÃ n phÃ¹ há»£p nháº¥t',
                render: (_, reservation) => reservation.orchestration.best_fit_table?.table_code ?? 'ChÆ°a cÃ³ gá»£i Ã½',
              },
              {
                title: 'HÃ nh Ä‘á»™ng',
                render: (_, reservation) => (
                  <Space wrap>
                    <Button
                      onClick={() => assignBestFitMutation.mutate(reservation)}
                      loading={assignBestFitMutation.isPending}
                    >
                      GÃ¡n phÃ¹ há»£p nháº¥t
                    </Button>
                    <Button
                      onClick={() =>
                        navigate(`/reservations?${buildJourneySearch({
                          source: 'board',
                          reservationId: reservation.reservation_id,
                          reservationRowVersion: reservation.row_version,
                          tableId: selectedTable?.table_id ?? undefined,
                        })}`)
                      }
                    >
                      Má»Ÿ chi tiáº¿t
                    </Button>
                  </Space>
                ),
              },
            ]}
          />
        )}
      </Card>
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card title="Ngá»¯ cáº£nh bÃ n Ä‘ang chá»n">
        {!selectedTable ? (
          <EmptyBlock
            title="ChÆ°a chá»n bÃ n"
            description="Chá»n má»™t bÃ n trÃªn sÆ¡ Ä‘á»“ Ä‘á»ƒ xem Ä‘áº·t bÃ n, Ä‘Æ¡n hiá»‡n táº¡i, khÃ¡ch vÃ£ng lai vÃ  bÆ°á»›c tiáº¿p theo."
          />
        ) : (
          <Space orientation="vertical" size={12} style={{ width: '100%' }}>
            <Space align="center" style={{ justifyContent: 'space-between', width: '100%' }}>
              <Typography.Title level={4} style={{ margin: 0 }}>
                {selectedTable.table_code}
              </Typography.Title>
              <StatusChip label={selectedTable.realtime_status} tone={tableTone(selectedTable.realtime_status)} />
            </Space>

            <Typography.Text type="secondary">
              {selectedTable.zone ?? 'ChÆ°a cÃ³ khu'} â€¢ {selectedTable.capacity.seats ?? 'KhÃ´ng cÃ³'} chá»— â€¢ khung báº£ng {formatDateTime(windowRange.from)} - {formatDateTime(windowRange.to)}
            </Typography.Text>

            {selectedTable.reservation ? (
              <Card size="small" type="inner" title="Äáº·t bÃ n">
                <Space orientation="vertical" size={8}>
                  <Typography.Text strong>{selectedTable.reservation.reservation_code}</Typography.Text>
                  <Space wrap>
                    <StatusChip label={selectedTable.reservation.status} tone={reservationTone(selectedTable.reservation.status)} />
                    <Typography.Text type="secondary">
                      {selectedTable.reservation.guest_count} khÃ¡ch
                    </Typography.Text>
                  </Space>
                  <div className="staff-action-row">
                    <Button
                      onClick={() =>
                        navigate(`/reservations?${buildJourneySearch({
                          source: 'board',
                          tableId: selectedTable.table_id,
                          reservationId: selectedTable.reservation?.reservation_id,
                          reservationRowVersion: selectedTable.reservation?.row_version,
                        })}`)
                      }
                    >
                      Má»Ÿ Ä‘áº·t bÃ n
                    </Button>
                    <Button
                      type="primary"
                      onClick={() => handleCheckIn(selectedTable)}
                      loading={checkInMutation.isPending}
                      disabled={!selectedTable.actions.check_in?.available}
                    >
                      Nháº­n bÃ n
                    </Button>
                  </div>
                </Space>
              </Card>
            ) : null}

            {selectedTable.active_order ? (
              <Card size="small" type="inner" title="ÄÆ¡n Ä‘ang phá»¥c vá»¥">
                <Space orientation="vertical" size={8}>
                  <Typography.Text strong>ÄÆ¡n #{selectedTable.active_order.order_id}</Typography.Text>
                  <Space wrap>
                    <StatusChip label={selectedTable.active_order.status} tone="warning" />
                    <StatusChip label={selectedTable.active_order.order_type} />
                  </Space>
                  <div className="staff-action-row">
                    <Button
                      type="primary"
                      onClick={() =>
                        navigate(`/orders?${buildJourneySearch({
                          source: 'board',
                          tableId: selectedTable.table_id,
                          reservationId: selectedTable.reservation?.reservation_id,
                          reservationRowVersion: selectedTable.reservation?.row_version,
                          orderId: selectedTable.active_order?.order_id,
                          orderRowVersion: selectedTable.active_order?.row_version,
                        })}`)
                      }
                    >
                      Má»Ÿ Ä‘Æ¡n hÃ ng
                    </Button>
                    <Button
                      onClick={() =>
                        navigate(`/checkout?${buildJourneySearch({
                          source: 'board',
                          tableId: selectedTable.table_id,
                          reservationId: selectedTable.reservation?.reservation_id,
                          reservationRowVersion: selectedTable.reservation?.row_version,
                          orderId: selectedTable.active_order?.order_id,
                          orderRowVersion: selectedTable.active_order?.row_version,
                        })}`)
                      }
                    >
                      Má»Ÿ thanh toÃ¡n
                    </Button>
                  </div>
                </Space>
              </Card>
            ) : (
              <Space wrap>
                <Button
                  type="primary"
                  onClick={() => setWalkInOpen(true)}
                  disabled={!selectedTable.availability.accepts_new_assignment}
                >
                  Táº¡o khÃ¡ch vÃ£ng lai cho bÃ n nÃ y
                </Button>
                {session && can(session, 'waiting_list.manage') ? (
                  <Button onClick={() => navigate('/waiting-list')}>
                    ThÃªm khÃ¡ch vÃ o hÃ ng chá»
                  </Button>
                ) : null}
              </Space>
            )}

            <Card size="small" type="inner" title="Äáº·t bÃ n cÃ³ thá»ƒ gÃ¡n cho bÃ n nÃ y">
              {selectedTable.candidate_reservations.length === 0 ? (
                <EmptyBlock
                  title="KhÃ´ng cÃ³ gá»£i Ã½"
                  description="Hiá»‡n khÃ´ng cÃ³ Ä‘áº·t bÃ n nÃ o phÃ¹ há»£p Ä‘á»ƒ gÃ¡n vÃ o bÃ n nÃ y."
                />
              ) : (
                <Table
                  rowKey="reservation_id"
                  size="small"
                  pagination={false}
                  dataSource={selectedTable.candidate_reservations}
                  columns={[
                    {
                      title: 'Äáº·t bÃ n',
                      render: (_, candidate) => (
                        <Space orientation="vertical" size={2}>
                          <Typography.Text strong>{candidate.reservation_code}</Typography.Text>
                          <Typography.Text type="secondary">{candidate.guest_count} khÃ¡ch</Typography.Text>
                        </Space>
                      ),
                    },
                    {
                      title: 'Cá» cáº£nh bÃ¡o',
                      render: (_, candidate) => (
                        <Space wrap>
                          {candidate.flags.due_soon ? <StatusChip label="due_soon" tone="warning" /> : null}
                          {candidate.flags.overdue ? <StatusChip label="overdue" tone="error" /> : null}
                        </Space>
                      ),
                    },
                    {
                      title: 'HÃ nh Ä‘á»™ng',
                      render: (_, candidate) => (
                        <Button
                          size="small"
                          onClick={() =>
                            assignCurrentTableMutation.mutate({
                              reservationId: candidate.reservation_id,
                              rowVersion: candidate.row_version,
                            })
                          }
                          loading={assignCurrentTableMutation.isPending}
                        >
                          DÃ¹ng bÃ n nÃ y
                        </Button>
                      ),
                    },
                  ]}
                />
              )}
            </Card>
          </Space>
        )}
      </Card>

      <Card title="Äá»“ng bá»™ sÆ¡ Ä‘á»“">
        {boardChangesQuery.isLoading ? (
          <InlineLoading tip="Äang kiá»ƒm tra thay Ä‘á»•i sÆ¡ Ä‘á»“..." />
        ) : boardChangesQuery.error ? (
          <InlineError message={formatApiError(boardChangesQuery.error, 'KhÃ´ng thá»ƒ Ä‘á»c luá»“ng thay Ä‘á»•i cá»§a sÆ¡ Ä‘á»“.')} />
        ) : (
          <Space orientation="vertical" size={10}>
            <Typography.Text type="secondary">
              PhiÃªn báº£n realtime v{boardChangesQuery.data?.data.current_version ?? boardQuery.data?.meta.realtime.current_version ?? 0}
            </Typography.Text>
            <Typography.Text type="secondary">
              Sá»± kiá»‡n: {boardChangesQuery.data?.data.events.length ?? 0}
            </Typography.Text>
            <Typography.Text type="secondary">
              Chu ká»³ gá»£i Ã½: {(boardChangesQuery.data?.data.poll_hint_ms ?? boardQuery.data?.meta.realtime.poll_hint_ms ?? 0) / 1000} giÃ¢y
            </Typography.Text>
          </Space>
        )}
      </Card>
    </Space>
  );

  return (
    <div data-testid="table-board-page">
      <SplitWorkspace main={main} side={side} />
      <Modal
        title={`KhÃ¡ch vÃ£ng lai cho ${selectedTable?.table_code ?? 'bÃ n'}`}
        open={walkInOpen}
        onCancel={() => setWalkInOpen(false)}
        footer={null}
        destroyOnHidden
      >
        <Form<WalkInFormValues>
          form={walkInForm}
          layout="vertical"
          initialValues={{ guest_count: 2, service_minutes: 120 }}
          onFinish={(values) => walkInMutation.mutate(values)}
        >
          <Form.Item name="guest_name" label="TÃªn khÃ¡ch" rules={[{ required: true, message: 'Nháº­p tÃªn khÃ¡ch.' }]}>
            <Input placeholder="KhÃ¡ch vÃ£ng lai" />
          </Form.Item>
          <Form.Item name="phone" label="Sá»‘ Ä‘iá»‡n thoáº¡i">
            <Input placeholder="KhÃ´ng báº¯t buá»™c" />
          </Form.Item>
          <Form.Item name="guest_count" label="Sá»‘ khÃ¡ch" rules={[{ required: true, message: 'Nháº­p sá»‘ khÃ¡ch.' }]}>
            <InputNumber min={1} max={30} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="service_minutes" label="Sá»‘ phÃºt phá»¥c vá»¥">
            <InputNumber min={30} max={480} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="notes" label="Ghi chÃº">
            <Input.TextArea rows={3} placeholder="Ghi chÃº phá»¥c vá»¥ náº¿u cáº§n" />
          </Form.Item>
          <div className="staff-modal-footer">
            <Button onClick={() => setWalkInOpen(false)}>
              Há»§y
            </Button>
            <Button type="primary" htmlType="submit" loading={walkInMutation.isPending}>
              Táº¡o khÃ¡ch vÃ£ng lai
            </Button>
          </div>
        </Form>
      </Modal>
    </div>
  );
}

