import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Button, Card, Form, Input, Select, Space, Table, Typography } from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { ReservationEnvelope, StaffReservationLookupEntry } from '../../../../shared/api/sdk';
import {
  cancelReservation,
  checkInReservation,
  createReservation,
  getActiveOrderByReservation,
  getReservationDetail,
  getTableBoard,
  listReservations,
} from '../../../../shared/api/staff-api';
import { formatApiError, formatStaffFacingApiError } from '../../../../shared/api/errors';
import { formatDateTime } from '../../../../shared/utils/format';
import { buildJourneySearch } from '../../../../app/router/journey';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { buildOrderContextLabel, buildReservationContextLabel } from '../../../journey-labels';
import {
  isReservationSnapshotOnlyGuest,
  RESERVATION_SNAPSHOT_GUEST_LABEL,
} from '../../../../domains/reservations/reservation-guest';
import { getPrimaryReservationTableId, getReservationTableLabel } from '../../../../domains/reservations/reservation-tables';
import { reservationTone } from '../../../../shared/status/status';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { MutationStatusNotice } from '../../../../shared/ui/feedback/MutationStatusNotice';
import {
  ApiStateBlock,
  ConflictState,
  EmptyBlock,
  InlineLoading,
} from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { can } from '../../../../shared/auth/capabilities';
import { ReservationDetailDrawer } from '../../../../shared/ui/drawers/ReservationDetailDrawer';
import { useConfirmAction } from '../../../../shared/hooks/useConfirmAction';
import { useJourneyContext } from '../../../../app/router/useJourneyContext';
import { useStaffMutationFeedback } from '../../../../shared/hooks/useStaffMutationFeedback';
import { ReservationCreateModal } from './ReservationCreateModal';
import {
  buildDefaultReservationCreateFormValues,
  buildReservationCreatePayload,
  buildReservationCreateWindow,
  type ReservationCreateFormValues,
} from '../../../../domains/reservations/reservation-create';
import { shouldLookupActiveOrder } from '../../../../domains/reservations/reservation-active-order';
import { buildReservationsSearch, readReservationsUrlState, type ReservationBucket } from '../../../../domains/reservations/reservations-url';

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
  const confirmAction = useConfirmAction();
  const mutationFeedback = useStaffMutationFeedback('reservation-workspace');
  const journey = useJourneyContext();
  const session = useAuthStore((state) => state.session);
  const canOpenCheckout = !!session && can(session, 'settlement.manage');
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

    if (reservationsData.length === 0) {
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
      label: buildReservationContextLabel(
        selectedReservation.reservation_code,
        selectedReservation.reservation_id,
      ),
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
    retry: false,
  });

  const detailReservation = reservationDetailQuery.data?.data ?? null;
  const activeOrderEnvelope = activeOrderQuery.data ?? null;
  const activeOrder = activeOrderEnvelope?.data.order ?? null;
  const canCancelReservation = Boolean(
    detailReservation
    && (detailReservation.status === 'Confirmed' || detailReservation.status === 'Reserved')
    && !activeOrder,
  );

  const refreshReservationWorkspace = useCallback(() => {
    void reservationsQuery.refetch();

    if (activeReservationId) {
      void reservationDetailQuery.refetch();
    }

    if (activeReservationId && canLookupActiveOrder) {
      void activeOrderQuery.refetch();
    }
  }, [
    activeOrderQuery,
    activeReservationId,
    canLookupActiveOrder,
    reservationDetailQuery,
    reservationsQuery,
  ]);

  const createReservationMutation = useMutation({
    onMutate: () => {
      mutationFeedback.setSubmitting(
        'Tạo đặt bàn hộ',
        'Đang kiểm tra bàn đã chọn và gửi thông tin khách sang backend.',
      );
    },
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
        label: buildReservationContextLabel(reservation.reservation_code, reservation.reservation_id),
        source: 'reservation',
      });
      updateUrlState({
        bucket: 'upcoming',
        q: '',
        reservationId: reservation.reservation_id,
      });
      setDetailOpen(true);
      mutationFeedback.setSuccess(
        'Tạo đặt bàn hộ',
        `Đã tạo ${reservation.reservation_code} và khóa đúng ngữ cảnh đặt bàn mới.`,
      );
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Tạo đặt bàn hộ',
        fallbackMessage: formatStaffFacingApiError(
          error,
          'Không thể tạo đặt bàn hộ. Hãy kiểm tra bàn gán, số điện thoại và khung giờ.',
        ),
      });
    },
  });

  function syncSelectedReservation(
    reservation: Pick<StaffReservationLookupEntry, 'reservation_id' | 'row_version' | 'reservation_code'> | null,
  ) {
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
      label: buildReservationContextLabel(reservation.reservation_code, reservation.reservation_id),
      source: 'reservation',
    });
    updateUrlState({ reservationId: reservation.reservation_id });
  }

  const checkInMutation = useMutation({
    onMutate: () => {
      mutationFeedback.setSubmitting(
        'Nhận bàn',
        'Đang chuyển reservation sang trạng thái đang phục vụ với gán bàn hiện tại.',
      );
    },
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
      mutationFeedback.setSuccess(
        'Nhận bàn',
        `Đã chuyển ${reservationEnvelope.data.reservation_code} sang trạng thái đang phục vụ.`,
      );
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Nhận bàn',
        fallbackMessage: formatApiError(error, 'Không thể nhận bàn cho đặt bàn.'),
      });
    },
  });

  const cancelReservationMutation = useMutation({
    onMutate: () => {
      mutationFeedback.setSubmitting(
        'Hủy đặt bàn',
        'Đang gửi yêu cầu hủy reservation và đồng bộ lại danh sách hiện tại.',
      );
    },
    mutationFn: async (reservation: ReservationEnvelope['data']) =>
      cancelReservation(reservation.reservation_id, {
        row_version: reservation.row_version,
      }),
    onSuccess: async (reservationEnvelope) => {
      await queryClient.invalidateQueries({ queryKey: ['reservations'] });
      await queryClient.invalidateQueries({ queryKey: ['table-board'] });
      await queryClient.invalidateQueries({ queryKey: ['reservation-detail', reservationEnvelope.data.reservation_id] });
      syncSelectedReservation(reservationEnvelope.data);
      setDetailOpen(true);
      mutationFeedback.setSuccess(
        'Hủy đặt bàn',
        `Đã hủy ${reservationEnvelope.data.reservation_code}. Hãy kiểm tra lại bucket hiện tại nếu dòng vừa rời khỏi danh sách.`,
      );
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Hủy đặt bàn',
        fallbackMessage: formatApiError(error, 'Không thể hủy đặt bàn này.'),
      });
    },
  });

  async function handleCancelReservation(reservation: ReservationEnvelope['data']) {
    const confirmed = await confirmAction({
      title: `Hủy ${reservation.reservation_code}`,
      okText: 'Hủy đặt bàn',
      danger: true,
      content: (
        <Space direction="vertical" size={10}>
          <Typography.Text>
            Thao tác này sẽ chuyển đặt bàn sang trạng thái <strong>Cancelled</strong> và có thể làm dòng hiện tại rời khỏi bucket đang mở.
          </Typography.Text>
          <div className="staff-mini-list">
            <div className="staff-mini-list-item">
              <Typography.Text strong>Khách</Typography.Text>
              <Typography.Text type="secondary">
                {reservation.user?.full_name ?? reservation.user?.phone ?? reservation.guest?.full_name ?? reservation.guest?.phone ?? 'Khách vãng lai'}
              </Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Bàn hiện tại</Typography.Text>
              <Typography.Text type="secondary">{getReservationTableLabel(reservation)}</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Phiên bản đang gửi</Typography.Text>
              <Typography.Text type="secondary">RV {reservation.row_version}</Typography.Text>
            </div>
          </div>
        </Space>
      ),
    });

    if (confirmed) {
      await cancelReservationMutation.mutateAsync(reservation);
    }
  }

  function openOrderWorkspace() {
    if (!selectedReservation) {
      return;
    }

    if (activeOrder) {
      setOrderContext({
        orderId: activeOrder.order_id,
        orderRowVersion: activeOrder.row_version ?? null,
        label: buildOrderContextLabel(activeOrder.order_id),
        source: 'reservation',
      });
    }

                      navigate(`${staffRoutePaths.ops.orders}?${buildJourneySearch({
      source: 'reservation',
      reservationId: selectedReservation.reservation_id,
      reservationRowVersion: detailReservation?.row_version ?? selectedReservation.row_version,
      tableId: getPrimaryReservationTableId(selectedReservation),
      tableIds: selectedReservation.table_ids,
      orderId: activeOrder?.order_id,
      orderRowVersion: activeOrder?.row_version ?? undefined,
    })}`);
  }

  function openCheckoutWorkspace() {
    if (!selectedReservation || !activeOrder) {
      return;
    }

    setOrderContext({
      orderId: activeOrder.order_id,
      orderRowVersion: activeOrder.row_version ?? null,
      label: buildOrderContextLabel(activeOrder.order_id),
      source: 'reservation',
    });

                      navigate(`${staffRoutePaths.ops.checkout}?${buildJourneySearch({
      source: 'reservation',
      reservationId: selectedReservation.reservation_id,
      reservationRowVersion: detailReservation?.row_version ?? selectedReservation.row_version,
      tableId: getPrimaryReservationTableId(selectedReservation),
      tableIds: selectedReservation.table_ids,
      orderId: activeOrder.order_id,
      orderRowVersion: activeOrder.row_version ?? undefined,
    })}`);
  }

  const main = (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
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
        extra={(
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
              placeholder="Tìm theo đặt bàn / khách / số điện thoại..."
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
        )}
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
        {reservationsQuery.error ? (
          <ApiStateBlock
            error={reservationsQuery.error}
            fallback="Không thể tải danh sách đặt bàn."
            onRetry={() => {
              void reservationsQuery.refetch();
            }}
          />
        ) : null}
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
                  <Space direction="vertical" size={2}>
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

  const side = (
    <Card title="Luồng đặt bàn hiện tại">
      <Space direction="vertical" size={12} style={{ width: '100%' }}>
        <MutationStatusNotice
          feedback={mutationFeedback.feedback}
          onDismiss={mutationFeedback.resetFeedback}
          onRetry={refreshReservationWorkspace}
        />
        <Typography.Text type="secondary">
          Khi đã chọn một đặt bàn, đường dẫn hiện tại sẽ luôn khôi phục lại đúng dòng đang xử lý thay vì phụ thuộc vào store tạm thời của shell.
        </Typography.Text>
        {activeOrderQuery.error ? (
          <ApiStateBlock
            error={activeOrderQuery.error}
            fallback="Khong the khoa active order canonical cho dat ban dang mo."
            onRetry={() => {
              void activeOrderQuery.refetch();
            }}
          />
        ) : null}
        {selectedReservation ? (
          <ConflictState
            title="Gan ban tu workspace dat ban dang bi khoa"
            description="Staff-web khong con cho gan best-fit hoac gan vao ban dang neo tu man nay vi frozen operator contract chua co mutation canon cho table assignment."
            meta="Blocked routes: POST /api/v1/staff/reservations/{id}/assign-best-fit, POST /api/v1/staff/reservations/{id}/assign-table. Missing invariant: full-contract table-assignment write in frozen OpenAPI + generated SDK."
            className="staff-inline-note"
          />
        ) : null}
        <Button disabled={!selectedReservation} onClick={openOrderWorkspace}>
          Tiếp tục sang màn hình đơn hàng
        </Button>
        {canOpenCheckout ? (
          <Button disabled={!selectedReservation || !activeOrder} onClick={openCheckoutWorkspace}>
            Mở thanh toán
          </Button>
        ) : null}
        <Button
          disabled={!selectedReservation}
          onClick={() =>
                      navigate(`${staffRoutePaths.ops.tables}?${buildJourneySearch({
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
        <ReservationDetailDrawer
          open={detailOpen && !!selectedReservation}
          reservation={detailReservation}
          activeOrder={activeOrderEnvelope}
          busy={checkInMutation.isPending || cancelReservationMutation.isPending}
          onClose={() => {
            setDetailOpen(false);
            syncSelectedReservation(null);
          }}
          onCheckIn={detailReservation ? () => checkInMutation.mutate(detailReservation) : undefined}
          onCancelReservation={canCancelReservation && detailReservation ? () => void handleCancelReservation(detailReservation) : undefined}
          onOpenOrder={openOrderWorkspace}
          onOpenCheckout={canOpenCheckout ? openCheckoutWorkspace : undefined}
        />
      </Space>
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

