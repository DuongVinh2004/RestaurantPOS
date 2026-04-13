import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Button, Card, Form, Input, Select, Space, Table, Typography } from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { ReservationEnvelope, StaffReservationLookupEntry } from '../../core/api/sdk';
import {
  assignBestFitTable,
  assignSuggestedTable,
  checkInReservation,
  createReservation,
  getActiveOrderByReservation,
  getReservationDetail,
  getTableBoard,
  listReservations,
} from '../../core/api/staff-api';
import { formatApiError, formatStaffFacingApiError, isApiStatus } from '../../core/api/errors';
import { formatDateTime } from '../../core/utils/format';
import { buildJourneySearch } from '../../core/utils/journey';
import {
  isReservationSnapshotOnlyGuest,
  RESERVATION_SNAPSHOT_GUEST_LABEL,
} from '../../core/utils/reservation-guest';
import { getPrimaryReservationTableId } from '../../core/utils/reservation-tables';
import { reservationTone } from '../../core/utils/status';
import { PageHeader } from '../../components/layout/PageHeader';
import { SplitWorkspace } from '../../components/layout/SplitWorkspace';
import { toast } from '../../components/feedback/toast';
import { EmptyBlock, InlineError, InlineLoading } from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { useAuthStore } from '../../app/store/auth-store';
import { useFlowStore } from '../../app/store/flow-store';
import { can } from '../../core/permissions/capabilities';
import { ReservationDetailDrawer } from '../../components/drawers/ReservationDetailDrawer';
import { useJourneyContext } from '../../hooks/useJourneyContext';
import { ReservationCreateModal } from './ReservationCreateModal';
import {
  buildDefaultReservationCreateFormValues,
  buildReservationCreatePayload,
  buildReservationCreateWindow,
  type ReservationCreateFormValues,
} from './reservation-create';
import { shouldLookupActiveOrder } from './reservation-active-order';
import { buildReservationsSearch, readReservationsUrlState, type ReservationBucket } from './reservations-url';

const bucketOptions: Array<{ value: ReservationBucket; label: string }> = [
  { value: 'upcoming', label: 'Sắp tới' },
  { value: 'today', label: 'Hôm nay' },
  { value: 'all', label: 'Tất cả' },
  { value: 'history', label: 'Lịch sử' },
];

export function ReservationsPage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const message = toast;
  const journey = useJourneyContext();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const selectedTableId = useFlowStore((state) => state.selectedTableId);
  const setTableContext = useFlowStore((state) => state.setTableContext);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const setOrderContext = useFlowStore((state) => state.setOrderContext);
  const [searchDraft, setSearchDraft] = useState('');
  const [detailOpen, setDetailOpen] = useState(false);
  const [reservationCreateOpen, setReservationCreateOpen] = useState(false);
  const [reservationCreateForm] = Form.useForm<ReservationCreateFormValues>();
  const reservationCreateStartTime = Form.useWatch('start_time_local', reservationCreateForm);
  const reservationCreateDuration = Form.useWatch('duration_minutes', reservationCreateForm);

  const urlState = useMemo(() => readReservationsUrlState(searchParams), [searchParams]);
  const bucket = urlState.bucket;
  const query = urlState.q;
  const selectedReservationId = urlState.reservationId;

  const updateUrlState = useCallback((
    patch: Partial<ReturnType<typeof readReservationsUrlState>>,
    options?: { replace?: boolean },
  ) => {
    const nextSearch = buildReservationsSearch(searchParams, patch);
    setSearchParams(new URLSearchParams(nextSearch), { replace: options?.replace });
  }, [searchParams, setSearchParams]);

  const reservationCreateWindow = useMemo(
    () => buildReservationCreateWindow({
      start_time_local: reservationCreateStartTime ?? '',
      duration_minutes: reservationCreateDuration ?? 0,
    }),
    [reservationCreateDuration, reservationCreateStartTime],
  );

  const reservationCreateTableBoardQuery = useQuery({
    queryKey: ['reservation-create-table-board', branchId, reservationCreateWindow?.from, reservationCreateWindow?.to],
    queryFn: () =>
      getTableBoard({
        from: reservationCreateWindow?.from ?? '',
        to: reservationCreateWindow?.to ?? '',
        branch_id: branchId ?? undefined,
        include_holds: true,
        group_by: 'zone',
      }),
    enabled: reservationCreateOpen && !!reservationCreateWindow,
    retry: false,
  });

  const reservationCreateTableOptions = useMemo(
    () =>
      (reservationCreateTableBoardQuery.data?.data ?? []).map((row) => ({
        value: row.table_id,
        label: `${row.table_code} • ${row.zone ?? 'Khu mặc định'} • ${row.capacity.seats} ghế • ${row.board_state}`,
        disabled: !row.availability.accepts_new_assignment,
      })),
    [reservationCreateTableBoardQuery.data?.data],
  );

  const openReservationCreateModal = useCallback(() => {
    reservationCreateForm.resetFields();
    reservationCreateForm.setFieldsValue(buildDefaultReservationCreateFormValues(new Date(), selectedTableId ? {
      table_ids: [selectedTableId],
    } : {}));
    setReservationCreateOpen(true);
  }, [reservationCreateForm, selectedTableId]);

  const closeReservationCreateModal = useCallback(() => {
    setReservationCreateOpen(false);
  }, []);

  useEffect(() => {
    setSearchDraft(query);
  }, [query]);

  useEffect(() => {
    if (!journey.reservationId || selectedReservationId === journey.reservationId) {
      return;
    }

    updateUrlState({ reservationId: journey.reservationId }, { replace: true });
  }, [journey.reservationId, selectedReservationId, updateUrlState]);

  useEffect(() => {
    if (!reservationCreateOpen) {
      return;
    }

    const selectedCreateTableIds = reservationCreateForm.getFieldValue('table_ids') ?? [];
    if (!Array.isArray(selectedCreateTableIds) || selectedCreateTableIds.length === 0) {
      return;
    }

    const availableTableIds = new Set(
      reservationCreateTableOptions
        .filter((option) => !option.disabled)
        .map((option) => option.value),
    );
    const validTableIds = selectedCreateTableIds.filter((tableId) => availableTableIds.has(tableId));

    if (
      validTableIds.length !== selectedCreateTableIds.length
      || validTableIds.some((tableId, index) => tableId !== selectedCreateTableIds[index])
    ) {
      reservationCreateForm.setFieldValue('table_ids', validTableIds);
    }
  }, [reservationCreateForm, reservationCreateOpen, reservationCreateTableOptions]);

  const reservationsQuery = useQuery({
    queryKey: ['reservations', bucket, query, branchId],
    queryFn: () =>
      listReservations({
        bucket,
        q: query.trim() || undefined,
        branch_id: branchId ?? undefined,
        per_page: 20,
        sort: bucket === 'history' ? '-start_time' : 'start_time',
      }),
  });

  const selectedReservation = useMemo(
    () => reservationsQuery.data?.data.find((reservation) => reservation.reservation_id === selectedReservationId) ?? null,
    [reservationsQuery.data?.data, selectedReservationId],
  );
  const reservationsData = reservationsQuery.data?.data;
  const canLookupActiveOrder = useMemo(
    () => shouldLookupActiveOrder(selectedReservation),
    [selectedReservation],
  );

  useEffect(() => {
    if (!reservationsData) {
      return;
    }

    const reservations = reservationsData;

    if (reservations.length === 0) {
      if (selectedReservationId !== null) {
        updateUrlState({ reservationId: null }, { replace: true });
      }

      setReservationContext({
        reservationId: null,
        reservationRowVersion: null,
        source: 'reservation',
      });
      setDetailOpen(false);
      return;
    }

    if (!selectedReservation) {
      if (selectedReservationId !== null) {
        updateUrlState({ reservationId: null }, { replace: true });
      }

      setReservationContext({
        reservationId: null,
        reservationRowVersion: null,
        source: 'reservation',
      });
      return;
    }

    setReservationContext({
      reservationId: selectedReservation.reservation_id,
      reservationRowVersion: selectedReservation.row_version,
      source: 'reservation',
    });
  }, [reservationsData, selectedReservation, selectedReservationId, setReservationContext, updateUrlState]);

  useEffect(() => {
    setDetailOpen(selectedReservationId !== null);
  }, [selectedReservationId]);

  const activeReservationId = selectedReservation?.reservation_id ?? null;

  const reservationDetailQuery = useQuery({
    queryKey: ['reservation-detail', activeReservationId],
    queryFn: () => getReservationDetail(activeReservationId as number),
    enabled: !!activeReservationId,
  });

  const activeOrderQuery = useQuery({
    queryKey: ['active-order-by-reservation', activeReservationId],
    queryFn: () => getActiveOrderByReservation(activeReservationId as number),
    enabled: !!activeReservationId && canLookupActiveOrder,
    retry: (failureCount, error) => !isApiStatus(error, 404) && failureCount < 1,
  });

  const createReservationMutation = useMutation({
    mutationFn: async (values: ReservationCreateFormValues) => {
      if (!Array.isArray(values.table_ids) || values.table_ids.length === 0) {
        throw new Error('Chọn bàn phục vụ trước khi tạo đặt bàn.');
      }

      return createReservation(buildReservationCreatePayload(values, {
        branchId,
        tableIds: values.table_ids,
      }));
    },
    onSuccess: async (reservationEnvelope, values) => {
      const reservation = reservationEnvelope.data;
      const primaryTableId = values.table_ids?.[0] ?? reservation.table_ids?.[0] ?? null;

      setReservationCreateOpen(false);
      reservationCreateForm.resetFields();

      await queryClient.invalidateQueries({ queryKey: ['reservations'] });
      await queryClient.invalidateQueries({ queryKey: ['table-board'] });
      await queryClient.invalidateQueries({ queryKey: ['reservation-detail', reservation.reservation_id] });

      if (primaryTableId) {
        setTableContext({
          tableId: primaryTableId,
          source: 'reservation',
        });
      }

      setReservationContext({
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
        source: 'reservation',
      });
      updateUrlState({
        bucket: 'upcoming',
        q: '',
        reservationId: reservation.reservation_id,
      });
      setDetailOpen(true);
      message.success(`Đã tạo đặt bàn hộ ${reservation.reservation_code}.`);
    },
    onError: (error) => {
      message.error(formatStaffFacingApiError(
        error,
        'Không thể tạo đặt bàn hộ. Hãy kiểm tra bàn gán, số điện thoại và khung giờ.',
      ));
    },
  });

  function syncSelectedReservation(reservation: Pick<StaffReservationLookupEntry, 'reservation_id' | 'row_version'> | null) {
    if (!reservation) {
      setReservationContext({
        reservationId: null,
        reservationRowVersion: null,
        source: 'reservation',
      });
      updateUrlState({ reservationId: null });
      return;
    }

    setReservationContext({
      reservationId: reservation.reservation_id,
      reservationRowVersion: reservation.row_version,
      source: 'reservation',
    });
    updateUrlState({ reservationId: reservation.reservation_id });
  }

  const assignBestFitMutation = useMutation({
    mutationFn: async (reservation: ReservationEnvelope['data']) =>
      assignBestFitTable(reservation.reservation_id, {
        row_version: reservation.row_version,
      }),
    onSuccess: async (reservationEnvelope) => {
      await queryClient.invalidateQueries({ queryKey: ['reservations'] });
      await queryClient.invalidateQueries({ queryKey: ['table-board'] });
      await queryClient.invalidateQueries({ queryKey: ['reservation-detail', reservationEnvelope.data.reservation_id] });
      syncSelectedReservation(reservationEnvelope.data);
      setDetailOpen(true);
      message.success(`Đã gán bàn phù hợp nhất cho ${reservationEnvelope.data.reservation_code}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể gán bàn phù hợp nhất.'));
    },
  });

  const assignCurrentTableMutation = useMutation({
    mutationFn: async (reservation: ReservationEnvelope['data']) => {
      if (!selectedTableId) {
        throw new Error('Mở một bàn trước nếu bạn muốn gán bàn hiện tại.');
      }

      return assignSuggestedTable(reservation.reservation_id, {
        table_id: selectedTableId,
        row_version: reservation.row_version,
      });
    },
    onSuccess: async (reservationEnvelope) => {
      await queryClient.invalidateQueries({ queryKey: ['reservations'] });
      await queryClient.invalidateQueries({ queryKey: ['table-board'] });
      await queryClient.invalidateQueries({ queryKey: ['reservation-detail', reservationEnvelope.data.reservation_id] });
      syncSelectedReservation(reservationEnvelope.data);
      setDetailOpen(true);
      message.success(`Đã gán đặt bàn ${reservationEnvelope.data.reservation_code} vào bàn ${selectedTableId}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể gán bàn hiện tại.'));
    },
  });

  const checkInMutation = useMutation({
    mutationFn: async (reservation: ReservationEnvelope['data']) =>
      checkInReservation(reservation.reservation_id, {
        row_version: reservation.row_version,
        table_ids: Array.isArray(reservation.table_ids) ? reservation.table_ids : undefined,
      }),
    onSuccess: async (reservationEnvelope) => {
      await queryClient.invalidateQueries({ queryKey: ['reservations'] });
      await queryClient.invalidateQueries({ queryKey: ['table-board'] });
      await queryClient.invalidateQueries({ queryKey: ['reservation-detail', reservationEnvelope.data.reservation_id] });
      await queryClient.invalidateQueries({ queryKey: ['active-order-by-reservation', reservationEnvelope.data.reservation_id] });
      syncSelectedReservation(reservationEnvelope.data);
      setDetailOpen(true);
      message.success(`Đã nhận bàn cho ${reservationEnvelope.data.reservation_code}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể nhận bàn cho đặt bàn.'));
    },
  });

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Xử lý đặt bàn"
        title="Danh sách đặt bàn"
        description="Theo dõi các lượt đặt bàn hôm nay, gán bàn nhanh và mở chi tiết mà không mất ngữ cảnh đang xử lý."
        context={(
          <>
            <StatusChip label={bucketOptions.find((option) => option.value === bucket)?.label ?? bucket} tone="processing" />
            <StatusChip label={selectedTableId ? `Bàn đang neo #${selectedTableId}` : 'Chưa neo bàn'} tone={selectedTableId ? 'default' : 'warning'} />
            <StatusChip label={selectedReservationId ? `Đang mở #${selectedReservationId}` : 'Chưa khóa đặt bàn'} tone={selectedReservationId ? 'processing' : 'warning'} />
          </>
        )}
        extra={
          <>
            {session && can(session, 'reservation.manage') ? (
              <Button type="primary" onClick={openReservationCreateModal}>
                Tạo đặt bàn hộ
              </Button>
            ) : null}
            <Select
              aria-label="Lọc danh sách đặt bàn"
              style={{ width: 140 }}
              value={bucket}
              options={bucketOptions}
              onChange={(value) => updateUrlState({ bucket: value, reservationId: null })}
            />
            <Input.Search
              allowClear
              aria-label="Tìm đặt bàn"
              placeholder="Tìm theo đặt bàn / khách / số điện thoại…"
              style={{ width: 260 }}
              value={searchDraft}
              onChange={(event) => {
                const nextValue = event.target.value;
                setSearchDraft(nextValue);
                if (nextValue === '') {
                  updateUrlState({ q: '', reservationId: null }, { replace: true });
                }
              }}
              onSearch={(value) => updateUrlState({ q: value.trim(), reservationId: null })}
            />
          </>
        }
      />

      <Card size="small">
        <Space wrap>
          {bucketOptions.map((option) => (
            <Button
              key={option.value}
              type={bucket === option.value ? 'primary' : 'default'}
              onClick={() => updateUrlState({ bucket: option.value, reservationId: null })}
            >
              {option.label}
            </Button>
          ))}
        </Space>
      </Card>

      <Card>
        {reservationsQuery.isLoading ? <InlineLoading tip="Đang tải danh sách đặt bàn..." /> : null}
        {reservationsQuery.error ? <InlineError message={formatApiError(reservationsQuery.error, 'Không thể tải danh sách đặt bàn.')} /> : null}
        {!reservationsQuery.isLoading && !reservationsQuery.error && (reservationsQuery.data?.data.length ?? 0) === 0 ? (
          <EmptyBlock
            title="Không có đặt bàn"
            description="Bộ lọc hiện tại không trả về dòng đặt bàn nào."
          />
        ) : null}
        {(reservationsQuery.data?.data.length ?? 0) > 0 ? (
          <Table<StaffReservationLookupEntry>
            rowKey="reservation_id"
            pagination={false}
            dataSource={reservationsQuery.data?.data ?? []}
            rowClassName={(reservation) => (reservation.reservation_id === selectedReservationId ? 'staff-row-selected' : '')}
            onRow={(reservation) => ({
              onClick: () => {
                syncSelectedReservation(reservation);
                setDetailOpen(true);
              },
            })}
            columns={[
              {
                title: 'Đặt bàn',
                render: (_, reservation) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text strong>{reservation.reservation_code}</Typography.Text>
                    <Space wrap size={8}>
                      <Typography.Text type="secondary">
                      {reservation.user?.full_name
                        ?? reservation.user?.phone
                        ?? reservation.guest?.full_name
                        ?? reservation.guest?.phone
                        ?? 'Khách vãng lai'}
                      </Typography.Text>
                      {isReservationSnapshotOnlyGuest(reservation) ? (
                        <StatusChip label={RESERVATION_SNAPSHOT_GUEST_LABEL} tone="processing" variant="freshness" />
                      ) : null}
                    </Space>
                  </Space>
                ),
              },
              {
                title: 'Thời gian',
                render: (_, reservation) => formatDateTime(reservation.start_time),
              },
              {
                title: 'Số khách',
                dataIndex: 'guest_count',
              },
              {
                title: 'Bàn',
                render: (_, reservation) => reservation.tables.map((table) => table.table_code).join(', ') || 'Chưa gán',
              },
              {
                title: 'Trạng thái',
                render: (_, reservation) => <StatusChip label={reservation.status} tone={reservationTone(reservation.status)} />,
              },
              {
                title: 'Hành động',
                render: (_, reservation) => (
                  <Button
                    onClick={(event) => {
                      event.stopPropagation();
                      syncSelectedReservation(reservation);
                      setDetailOpen(true);
                    }}
                  >
                    Mở chi tiết
                  </Button>
                ),
              },
            ]}
          />
        ) : null}
      </Card>
    </Space>
  );

  const detailReservation = reservationDetailQuery.data?.data ?? null;
  const activeOrder = isApiStatus(activeOrderQuery.error, 404) ? null : activeOrderQuery.data?.data.order ?? null;
  const side = (
    <Card title="Luồng đặt bàn hiện tại">
      <Space orientation="vertical" size={12} style={{ width: '100%' }}>
        <Typography.Text type="secondary">
          Khi đã chọn một đặt bàn, đường dẫn hiện tại sẽ luôn khôi phục lại đúng dòng đang xử lý thay vì phụ thuộc vào store tạm thời của shell.
        </Typography.Text>
        <Button
          disabled={!selectedReservation}
          onClick={() => {
            if (!selectedReservation) {
              return;
            }

            navigate(`/orders?${buildJourneySearch({
              source: 'reservation',
              reservationId: selectedReservation.reservation_id,
              reservationRowVersion: selectedReservation.row_version,
              tableId: getPrimaryReservationTableId(selectedReservation),
              tableIds: selectedReservation.table_ids,
              orderId: activeOrder?.order_id,
              orderRowVersion: activeOrder?.row_version ?? undefined,
            })}`);
          }}
        >
          Tiếp tục sang màn hình đơn hàng
        </Button>
        <Button
          disabled={!selectedReservation}
          onClick={() =>
            navigate(`/tables?${buildJourneySearch({
              source: journey.source ?? 'reservation',
              tableId: getPrimaryReservationTableId(selectedReservation),
              tableIds: selectedReservation?.table_ids,
              reservationId: selectedReservation?.reservation_id,
              reservationRowVersion: detailReservation?.row_version ?? selectedReservation?.row_version,
              orderId: activeOrder?.order_id,
              orderRowVersion: activeOrder?.row_version ?? undefined,
            })}`)
          }
        >
          Quay lại sơ đồ bàn
        </Button>
      </Space>
      <ReservationDetailDrawer
        open={detailOpen && !!selectedReservation}
        reservation={detailReservation}
        activeOrder={isApiStatus(activeOrderQuery.error, 404) ? null : activeOrderQuery.data ?? null}
        busy={assignBestFitMutation.isPending || assignCurrentTableMutation.isPending || checkInMutation.isPending}
        onClose={() => {
          setDetailOpen(false);
          syncSelectedReservation(null);
        }}
        onAssignBestFit={detailReservation ? () => assignBestFitMutation.mutate(detailReservation) : undefined}
        onAssignCurrentTable={detailReservation && selectedTableId ? () => assignCurrentTableMutation.mutate(detailReservation) : undefined}
        onCheckIn={detailReservation ? () => checkInMutation.mutate(detailReservation) : undefined}
        onOpenOrder={() => {
          if (!selectedReservation) {
            return;
          }

          if (activeOrder) {
            setOrderContext({
              orderId: activeOrder.order_id,
              orderRowVersion: activeOrder.row_version ?? null,
              source: 'reservation',
            });
          }

          navigate(`/orders?${buildJourneySearch({
            source: 'reservation',
            reservationId: selectedReservation.reservation_id,
            reservationRowVersion: detailReservation?.row_version ?? selectedReservation.row_version,
            tableId: getPrimaryReservationTableId(selectedReservation),
            tableIds: selectedReservation.table_ids,
            orderId: activeOrder?.order_id,
            orderRowVersion: activeOrder?.row_version ?? undefined,
          })}`);
        }}
      />
    </Card>
  );

  return (
    <>
      <SplitWorkspace main={main} side={side} />
      <ReservationCreateModal
        open={reservationCreateOpen}
        title="Tạo đặt bàn hộ"
        description="Nhân viên có thể tạo đặt bàn mới cho khách gọi điện ngay từ danh sách này. Nếu chưa neo bàn từ board, hãy chọn bàn phục vụ phù hợp trong khung giờ đã chọn."
        form={reservationCreateForm}
        tableOptions={reservationCreateTableOptions}
        tableLoading={reservationCreateTableBoardQuery.isLoading}
        submitting={createReservationMutation.isPending}
        submitLabel="Tạo đặt bàn hộ"
        onCancel={closeReservationCreateModal}
        onSubmit={(values) => createReservationMutation.mutate(values)}
      />
    </>
  );
}
