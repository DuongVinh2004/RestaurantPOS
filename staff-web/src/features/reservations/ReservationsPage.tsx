import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { App, Button, Card, Input, Select, Space, Table, Typography } from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { ReservationEnvelope, StaffReservationLookupEntry } from '../../core/api/sdk';
import {
  assignBestFitTable,
  assignSuggestedTable,
  checkInReservation,
  getActiveOrderByReservation,
  getReservationDetail,
  listReservations,
} from '../../core/api/staff-api';
import { formatApiError, isApiStatus } from '../../core/api/errors';
import { formatDateTime } from '../../core/utils/format';
import { buildJourneySearch } from '../../core/utils/journey';
import { reservationTone } from '../../core/utils/status';
import { PageHeader } from '../../components/layout/PageHeader';
import { SplitWorkspace } from '../../components/layout/SplitWorkspace';
import { EmptyBlock, InlineError, InlineLoading } from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { useFlowStore } from '../../app/store/flow-store';
import { ReservationDetailDrawer } from '../../components/drawers/ReservationDetailDrawer';
import { useJourneyContext } from '../../hooks/useJourneyContext';
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
  const { message } = App.useApp();
  const journey = useJourneyContext();
  const branchId = useFlowStore((state) => state.branchId);
  const selectedTableId = useFlowStore((state) => state.selectedTableId);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const setOrderContext = useFlowStore((state) => state.setOrderContext);
  const [searchDraft, setSearchDraft] = useState('');
  const [detailOpen, setDetailOpen] = useState(false);

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

  useEffect(() => {
    setSearchDraft(query);
  }, [query]);

  useEffect(() => {
    if (!journey.reservationId || selectedReservationId === journey.reservationId) {
      return;
    }

    updateUrlState({ reservationId: journey.reservationId }, { replace: true });
  }, [journey.reservationId, selectedReservationId, updateUrlState]);

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

  useEffect(() => {
    const reservations = reservationsQuery.data?.data ?? [];

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
  }, [reservationsQuery.data?.data, selectedReservation, selectedReservationId, setReservationContext, updateUrlState]);

  useEffect(() => {
    if (selectedReservationId !== null) {
      setDetailOpen(true);
    }
  }, [selectedReservationId]);

  const activeReservationId = selectedReservation?.reservation_id ?? null;

  const reservationDetailQuery = useQuery({
    queryKey: ['reservation-detail', activeReservationId],
    queryFn: () => getReservationDetail(activeReservationId as number),
    enabled: !!activeReservationId,
  });

  const activeOrderQuery = useQuery({
    queryKey: ['reservation-active-order', activeReservationId],
    queryFn: () => getActiveOrderByReservation(activeReservationId as number),
    enabled: !!activeReservationId,
    retry: (failureCount, error) => !isApiStatus(error, 404) && failureCount < 1,
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
        description="Bộ lọc, tìm kiếm và đặt bàn đang chọn đều nằm trên URL của màn hình này. Nhân viên có thể reload, dùng back/forward hoặc chia sẻ liên kết nội bộ mà không làm mất ngữ cảnh triage."
        extra={
          <>
            <Select
              style={{ width: 140 }}
              value={bucket}
              options={bucketOptions}
              onChange={(value) => updateUrlState({ bucket: value, reservationId: null })}
            />
            <Input.Search
              allowClear
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
                    <Typography.Text type="secondary">
                      {reservation.user?.full_name ?? reservation.user?.phone ?? 'Khách vãng lai'}
                    </Typography.Text>
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
              tableId: selectedReservation.table_ids[0],
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
              tableId: selectedReservation?.table_ids[0],
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
            tableId: selectedReservation.table_ids[0],
            orderId: activeOrder?.order_id,
            orderRowVersion: activeOrder?.row_version ?? undefined,
          })}`);
        }}
      />
    </Card>
  );

  return <SplitWorkspace main={main} side={side} />;
}
