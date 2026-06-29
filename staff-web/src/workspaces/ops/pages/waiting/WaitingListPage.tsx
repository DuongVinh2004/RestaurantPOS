import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import {
  Button,
  Card,
  Col,
  Descriptions,
  Form,
  Input,
  InputNumber,
  Row,
  Select,
  Space,
  Statistic,
  Table,
  Typography,
  Segmented,
} from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type {
  GetV1StaffWaitingListQueryParams,
  StaffWaitingListEntry,
} from '../../../../shared/api/sdk';
import {
  advanceWaitingListEntry,
  buildBoardWindow,
  cancelWaitingListEntry,
  createWaitingListEntry,
  getTableBoard,
  getWaitingListChanges,
  listWaitingList,
  notifyWaitingListEntry,
  seatWaitingListEntry,
} from '../../../../shared/api/staff-api';
import { formatApiError } from '../../../../shared/api/errors';
import { can } from '../../../../shared/auth/capabilities';
import { formatDateTime, formatRelativeAge } from '../../../../shared/utils/format';
import { buildJourneySearch } from '../../../../app/router/journey';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { type StatusTone, waitingTone } from '../../../../shared/status/status';
import { translateUiCode } from '../../../../shared/utils/translation';

import { WaitingCreateModal, type CreateWaitingValues } from './WaitingCreateModal';
import { WaitingDetailDrawer, type NotifyWaitingValues, type SeatWaitingValues, type CancelWaitingValues, waitingResponseTone } from './WaitingDetailDrawer';
import { toast } from '../../../../shared/ui/feedback/toast';
import {
  ApiStateBlock,
  BranchPolicyState,
  EmptyBlock,
  InlineLoading,
  InlineState,
} from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { useConfirmAction } from '../../../../shared/hooks/useConfirmAction';
import { useJourneyContext } from '../../../../app/router/useJourneyContext';


type WaitingStatusFilter = 'all' | 'Waiting' | 'Notified' | 'Seated' | 'Cancelled';
type QueueMode = 'active' | 'all';

const waitingStatusOptions = [
  { value: 'all', label: 'Tất cả trạng thái' },
  { value: 'Waiting', label: 'Đang chờ' },
  { value: 'Notified', label: 'Đã báo khách' },
  { value: 'Seated', label: 'Đã vào bàn' },
  { value: 'Cancelled', label: 'Đã hủy' },
] satisfies Array<{ value: WaitingStatusFilter; label: string }>;

const queueModeOptions = [
  { value: 'active', label: 'Chỉ mục đang hoạt động' },
  { value: 'all', label: 'Tất cả mục' },
] satisfies Array<{ value: QueueMode; label: string }>;

export function WaitingListPage() {
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [detailDrawerOpen, setDetailDrawerOpen] = useState(false);

  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const message = toast;
  const confirmAction = useConfirmAction();
  const journey = useJourneyContext();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const setTableContext = useFlowStore((state) => state.setTableContext);
  const [statusFilter, setStatusFilter] = useState<WaitingStatusFilter>('all');
  const [queueMode, setQueueMode] = useState<QueueMode>('active');
  const [searchDraft, setSearchDraft] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedWaitingId, setSelectedWaitingId] = useState<number | null>(null);
  const [createForm] = Form.useForm<CreateWaitingValues>();
  const [notifyForm] = Form.useForm<NotifyWaitingValues>();
  const [seatForm] = Form.useForm<SeatWaitingValues>();
  const [cancelForm] = Form.useForm<CancelWaitingValues>();
  const boardWindow = useMemo(() => buildBoardWindow(), []);
  const lastAppliedWaitingChangeVersionRef = useRef<number | null>(null);
  const focusedWaitingId = useMemo(() => readWaitingListFocus(searchParams), [searchParams]);

  const waitingFilters = useMemo(() => toWaitingListSearchFilters(searchTerm), [searchTerm]);
  const boardAccess = !!session && can(session, 'table.board.view');

  const waitingListQuery = useQuery({
    queryKey: ['waiting-list', branchId, statusFilter, queueMode, waitingFilters.phone, waitingFilters.guest_name],
    queryFn: () =>
      listWaitingList({
        branch_id: branchId ?? undefined,
        status: statusFilter === 'all' ? undefined : statusFilter,
        active_only: queueMode === 'active',
        phone: waitingFilters.phone,
        guest_name: waitingFilters.guest_name,
        per_page: 20,
        sort: '-priority',
      } satisfies GetV1StaffWaitingListQueryParams),
    enabled: !!session,
  });

  const boardQuery = useQuery({
    queryKey: ['waiting-list-board-options', branchId, boardWindow.from, boardWindow.to],
    queryFn: () =>
      getTableBoard({
        ...boardWindow,
        branch_id: branchId ?? undefined,
        include_holds: true,
        group_by: 'zone',
      }),
    enabled: boardAccess,
  });

  const changesQuery = useQuery({
    queryKey: ['waiting-list-changes', waitingListQuery.data?.meta?.realtime.current_version],
    queryFn: () => getWaitingListChanges(waitingListQuery.data?.meta?.realtime.current_version),
    enabled: !!waitingListQuery.data?.meta?.realtime.current_version,
    refetchInterval: 20_000,
  });

  useEffect(() => {
    const currentVersion = waitingListQuery.data?.meta?.realtime.current_version ?? null;
    const latestVersion = changesQuery.data?.data.current_version ?? null;
    const eventCount = changesQuery.data?.data.events.length ?? 0;

    if (currentVersion === null || latestVersion === null) {
      return;
    }

    if (latestVersion === currentVersion) {
      lastAppliedWaitingChangeVersionRef.current = latestVersion;
      return;
    }

    if (
      (latestVersion > currentVersion || eventCount > 0)
      && lastAppliedWaitingChangeVersionRef.current !== latestVersion
    ) {
      lastAppliedWaitingChangeVersionRef.current = latestVersion;
      void queryClient.invalidateQueries({ queryKey: ['waiting-list'], refetchType: 'active' });
    }
  }, [
    changesQuery.data?.data.current_version,
    changesQuery.data?.data.events.length,
    queryClient,
    waitingListQuery.data?.meta?.realtime.current_version,
  ]);

  useEffect(() => {
    if (!waitingListQuery.data) {
      return;
    }

    const currentRows = waitingListQuery.data?.data ?? [];
    if (currentRows.length === 0) {
      if (selectedWaitingId !== null) {
        setSelectedWaitingId(null);
      }
      if (focusedWaitingId !== null) {
        setSearchParams(setWaitingListFocus(searchParams, null), { replace: true });
      }
      return;
    }

    if (focusedWaitingId !== null) {
      if (currentRows.some((entry) => entry.waiting_id === focusedWaitingId)) {
        if (selectedWaitingId !== focusedWaitingId) {
          setSelectedWaitingId(focusedWaitingId);
        }
        return;
      }

      setSelectedWaitingId(currentRows[0].waiting_id);
      setSearchParams(setWaitingListFocus(searchParams, currentRows[0].waiting_id), { replace: true });
      return;
    }

    if (!selectedWaitingId || !currentRows.some((entry) => entry.waiting_id === selectedWaitingId)) {
      setSelectedWaitingId(currentRows[0].waiting_id);
    }
  }, [focusedWaitingId, searchParams, selectedWaitingId, setSearchParams, waitingListQuery.data]);

  const selectedEntry = useMemo(
    () => waitingListQuery.data?.data.find((entry) => entry.waiting_id === selectedWaitingId) ?? null,
    [selectedWaitingId, waitingListQuery.data?.data],
  );

  const availableTables = useMemo(
    () => (boardQuery.data?.data ?? []).filter((table) => table.availability.accepts_new_assignment),
    [boardQuery.data?.data],
  );

  const availableTableOptions = useMemo(
    () => availableTables.map((table) => ({
      value: table.table_id,
      label: `${table.table_code} • ${table.zone ?? 'Chưa chia khu'} • ${table.capacity.seats ?? 'Chưa rõ'} chỗ`,
    })),
    [availableTables],
  );
  const canonicalBoardContext = useMemo(() => getCanonicalWaitingTableContext(selectedEntry), [selectedEntry]);
  const explicitBoardJourneyTableId = journey.source === 'board' ? journey.tableId ?? null : null;
  const preferredNotifyTableId = canonicalBoardContext?.tableId ?? explicitBoardJourneyTableId ?? null;

  useEffect(() => {
    if (!selectedEntry || selectedEntry.status !== 'Waiting') {
      return;
    }

    if (!preferredNotifyTableId) {
      return;
    }

    if (notifyForm.getFieldValue('table_id') === preferredNotifyTableId) {
      return;
    }

    notifyForm.setFieldsValue({ table_id: preferredNotifyTableId });
  }, [notifyForm, preferredNotifyTableId, selectedEntry]);

  useEffect(() => {
    if (!selectedEntry || selectedEntry.status !== 'Waiting') {
      return;
    }

    if (!availableTableOptions.length) {
      return;
    }

    const currentTableId = notifyForm.getFieldValue('table_id');
    const currentTableStillValid = availableTableOptions.some((option) => option.value === currentTableId);
    if (currentTableStillValid) {
      return;
    }

    const preferredTableStillValid = availableTableOptions.some((option) => option.value === preferredNotifyTableId);
    if (preferredTableStillValid) {
      notifyForm.setFieldsValue({ table_id: preferredNotifyTableId ?? undefined });
      return;
    }

    notifyForm.setFieldsValue({ table_id: availableTableOptions[0].value });
  }, [availableTableOptions, notifyForm, preferredNotifyTableId, selectedEntry]);

  useEffect(() => {
    if (!selectedEntry) {
      return;
    }

    if (selectedEntry.invite_lifecycle.can_staff_seat_now) {
      seatForm.setFieldsValue({
        user_id: selectedEntry.user_id ?? undefined,
        notes: selectedEntry.notes ?? undefined,
        service_minutes: 120,
      });
    }

    cancelForm.setFieldsValue({
      cancel_reason: selectedEntry.cancel_reason ?? undefined,
    });
  }, [cancelForm, seatForm, selectedEntry]);

  const createMutation = useMutation({
    mutationFn: async (values: CreateWaitingValues) =>
      createWaitingListEntry({
        branch_id: branchId ?? undefined,
        guest_name: values.guest_name.trim(),
        phone: values.phone?.trim() || null,
        guest_count: values.guest_count,
        user_id: values.user_id ?? null,
        priority: values.priority ?? 0,
        notes: values.notes?.trim() || null,
      }),
    onSuccess: async (envelope) => {
      createForm.resetFields();
      setSelectedWaitingId(envelope.data.waiting_id);
      setSearchParams(setWaitingListFocus(searchParams, envelope.data.waiting_id));
      await queryClient.invalidateQueries({ queryKey: ['waiting-list'] });
      message.success(`Đã tạo lượt chờ #${envelope.data.waiting_id}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể tạo lượt chờ.'));
    },
  });

  const notifyMutation = useMutation({
    mutationFn: async (values: NotifyWaitingValues) => {
      if (!selectedEntry) {
        throw new Error('Chọn một lượt chờ trước khi báo khách.');
      }

      if (!values.table_id) {
        throw new Error('Chọn hoặc nhập mã bàn trước khi báo khách.');
      }

      return notifyWaitingListEntry(selectedEntry.waiting_id, {
        table_id: values.table_id,
        hold_minutes: values.hold_minutes ?? null,
        row_version: selectedEntry.row_version,
      });
    },
    onSuccess: async (envelope) => {
      setSelectedWaitingId(envelope.data.waiting_id);
      setSearchParams(setWaitingListFocus(searchParams, envelope.data.waiting_id));
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['waiting-list'] }),
        queryClient.invalidateQueries({ queryKey: ['table-board'] }),
      ]);
      message.success(`Đã báo khách cho lượt chờ #${envelope.data.waiting_id}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể báo khách đang chờ.'));
    },
  });

  const advanceMutation = useMutation({
    mutationFn: async () => {
      if (!selectedEntry) {
        throw new Error('Chọn một lượt chờ trước khi đẩy hàng chờ.');
      }

      return advanceWaitingListEntry(selectedEntry.waiting_id, {
        row_version: selectedEntry.row_version,
      });
    },
    onSuccess: async (envelope) => {
      const nextWaitingId = envelope.data.advanced_waiting_list?.waiting_id ?? envelope.data.source_waiting_list.waiting_id;
      setSelectedWaitingId(nextWaitingId);
      setSearchParams(setWaitingListFocus(searchParams, nextWaitingId));
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['waiting-list'] }),
        queryClient.invalidateQueries({ queryKey: ['table-board'] }),
      ]);
      message.success(`Đã đẩy hàng chờ: ${translateUiCode(String(envelope.data.automation.result ?? 'completed'))}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể đẩy hàng chờ.'));
    },
  });

  const seatMutation = useMutation({
    mutationFn: async (values: SeatWaitingValues) => {
      if (!selectedEntry) {
        throw new Error('Chọn một lượt chờ trước khi đưa khách vào bàn.');
      }

      return seatWaitingListEntry(selectedEntry.waiting_id, {
        user_id: values.user_id ?? selectedEntry.user_id ?? undefined,
        service_minutes: values.service_minutes ?? undefined,
        notes: values.notes?.trim() || null,
        row_version: selectedEntry.row_version,
      });
    },
    onSuccess: async (envelope) => {
      const reservation = envelope.data.reservation;
      const tableId = reservation.table_ids?.[0];
      const nextSource = journey.source ?? 'reservation';

      setReservationContext({
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
        source: nextSource,
      });
      if (tableId) {
        setTableContext({
          tableId,
          source: nextSource,
        });
      }

      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['waiting-list'] }),
        queryClient.invalidateQueries({ queryKey: ['table-board'] }),
        queryClient.invalidateQueries({ queryKey: ['reservations'] }),
      ]);

      message.success(`Đã xếp bàn lượt chờ và tạo đặt bàn ${reservation.reservation_code}.`);
                      navigate(`${staffRoutePaths.ops.orders}?${buildJourneySearch({
        source: nextSource,
        tableId,
        tableIds: reservation.table_ids,
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
      })}`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể đưa khách chờ vào bàn.'));
    },
  });

  const cancelMutation = useMutation({
    mutationFn: async (values: CancelWaitingValues) => {
      if (!selectedEntry) {
        throw new Error('Chọn một lượt chờ trước khi hủy.');
      }

      return cancelWaitingListEntry(selectedEntry.waiting_id, {
        cancel_reason: values.cancel_reason?.trim() || null,
        row_version: selectedEntry.row_version,
      });
    },
    onSuccess: async (envelope) => {
      setSelectedWaitingId(envelope.data.waiting_id);
      setSearchParams(setWaitingListFocus(searchParams, envelope.data.waiting_id));
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['waiting-list'] }),
        queryClient.invalidateQueries({ queryKey: ['table-board'] }),
      ]);
      message.success(`Đã hủy lượt chờ #${envelope.data.waiting_id}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể hủy khách đang chờ.'));
    },
  });

  async function handleAdvanceQueue() {
    const confirmed = await confirmAction({
      title: `Đẩy hàng chờ cho lượt #${selectedEntry?.waiting_id ?? ''}`,
      content: 'Chỉ dùng khi khách hiện tại đã từ chối hoặc cửa sổ báo khách đã hết hạn. Hệ thống sẽ thử báo cho ứng viên hợp lệ tiếp theo của bàn vừa được nhả.',
      okText: 'Đẩy hàng chờ',
    });

    if (confirmed) {
      await advanceMutation.mutateAsync();
    }
  }

  async function handleCancel(values: CancelWaitingValues) {
    const confirmed = await confirmAction({
      title: `Hủy lượt chờ #${selectedEntry?.waiting_id ?? ''}`,
      content: 'Chỉ hủy khi lượt chờ này không còn cần được xử lý nữa.',
      okText: 'Hủy lượt chờ',
      danger: true,
    });

    if (confirmed) {
      await cancelMutation.mutateAsync(values);
    }
  }

  
  useEffect(() => {
    if (selectedWaitingId !== null) {
      setDetailDrawerOpen(true);
    } else {
      setDetailDrawerOpen(false);
    }
  }, [selectedWaitingId]);

  const main = (
    <div className="staff-workspace-fluid staff-workspace-flex-column" style={{ display: 'flex', flexDirection: 'column', gap: '16px', width: '100%' }}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '12px', background: '#fff', padding: '12px 16px', borderRadius: '8px', border: '1px solid #f0f0f0' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
          <Typography.Title level={4} style={{ margin: 0 }}>Hàng chờ phục vụ</Typography.Title>
          <Segmented
            options={waitingStatusOptions}
            value={statusFilter}
            onChange={(value) => setStatusFilter(value as any)}
          />
          <Select
            aria-label="Lọc phạm vi hàng chờ"
            style={{ width: 140 }}
            value={queueMode}
            options={queueModeOptions}
            onChange={(value) => setQueueMode(value)}
          />
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
          <Input
            allowClear
            aria-label="Tìm hàng chờ"
            placeholder="Khách hoặc số điện thoại…"
            style={{ width: 220 }}
            value={searchDraft}
            onChange={(event) => {
              const nextValue = event.target.value;
              setSearchDraft(nextValue);
              if (nextValue === '') {
                setSearchTerm('');
              }
            }}
            onPressEnter={(e) => {
              const normalizedValue = e.currentTarget.value.trim();
              setSearchDraft(e.currentTarget.value);
              setSearchTerm(normalizedValue);
            }}
          />
          <Button onClick={() => waitingListQuery.refetch()} loading={waitingListQuery.isFetching}>
            Làm mới
          </Button>
          {session && can(session, 'waiting_list.manage') ? (
            <Button type="primary" onClick={() => setCreateModalOpen(true)}>
              Thêm lượt chờ
            </Button>
          ) : null}
        </div>
      </div>

      <Row gutter={[16, 16]}>
        <Col xs={24} md={6}>
          <Card>
            <Statistic title="Sẵn sàng vào bàn" value={waitingListQuery.data?.meta?.summary.ready_to_seat_count ?? 0} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card>
            <Statistic title="Có thể đẩy hàng chờ" value={waitingListQuery.data?.meta?.summary.advance_queue_ready_count ?? 0} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card>
            <Statistic title="Đang chờ phản hồi" value={waitingListQuery.data?.meta?.summary.awaiting_customer_follow_up_count ?? 0} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card>
            <Statistic title="Cần kiểm tra giữ chỗ" value={waitingListQuery.data?.meta?.summary.hold_investigation_count ?? 0} />
          </Card>
        </Col>
      </Row>

      <Card bodyStyle={{ padding: 0 }} bordered={false} style={{ overflow: 'hidden' }}>
        {waitingListQuery.isLoading ? <InlineLoading tip="Đang tải danh sách chờ..." /> : null}
        {waitingListQuery.error ? (
          <ApiStateBlock
            error={waitingListQuery.error}
            fallback="Không thể tải danh sách chờ."
            onRetry={() => {
              void waitingListQuery.refetch();
            }}
          />
        ) : null}
        {!waitingListQuery.isLoading && !waitingListQuery.error && (waitingListQuery.data?.data.length ?? 0) === 0 ? (
          <EmptyBlock
            title="Không có lượt chờ"
            description="Bộ lọc hiện tại không trả về lượt chờ nào."
          />
        ) : null}
        {(waitingListQuery.data?.data.length ?? 0) > 0 ? (
          <Table<StaffWaitingListEntry>
            rowKey="waiting_id"
            pagination={false}
            dataSource={waitingListQuery.data?.data ?? []}
            rowClassName={(entry) => (entry.waiting_id === selectedWaitingId ? 'staff-row-selected' : '')}
            onRow={(entry) => ({
              onClick: () => {
                setSelectedWaitingId(entry.waiting_id);
                setSearchParams(setWaitingListFocus(searchParams, entry.waiting_id));
              },
            })}
            columns={[
              {
                title: 'Khách',
                render: (_, entry) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text strong>{entry.guest_name ?? `Lượt chờ #${entry.waiting_id}`}</Typography.Text>
                    <Typography.Text type="secondary">{entry.phone ?? `Khách #${entry.user_id ?? 'vãng lai'}`}</Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Thời điểm tạo',
                render: (_, entry) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text>{formatDateTime(entry.requested_at)}</Typography.Text>
                    <Typography.Text type="secondary">{formatRelativeAge(entry.requested_at)}</Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Số khách',
                dataIndex: 'guest_count',
              },
              {
                title: 'Trạng thái',
                render: (_, entry) => <StatusChip label={entry.status} tone={waitingTone(entry.status)} />,
              },
              {
                title: 'Phản hồi',
                render: (_, entry) => <StatusChip label={entry.current_response_state} tone={waitingResponseTone(entry.current_response_state)} />,
              },
              {
                title: 'Gợi ý hành động',
                render: (_, entry) => translateUiCode(entry.orchestration.recommended_action),
              },
            ]}
          />
        ) : null}
      </Card>
      
      {changesQuery.error && (
        <ApiStateBlock
          error={changesQuery.error}
          fallback="Không thể tải luồng thay đổi của danh sách chờ."
          onRetry={() => {
            void changesQuery.refetch();
          }}
        />
      )}
    </div>
  );

  return (
    <>
      <div data-testid="waiting-list-page" style={{ padding: '16px', background: '#f5f7fa', minHeight: '100%', width: '100%' }}>
        {main}
      </div>
      <WaitingCreateModal
        open={createModalOpen}
        form={createForm}
        submitting={createMutation.isPending}
        branchId={branchId}
        defaultBranchId={session?.startup.default_branch?.branch_id}
        onCancel={() => setCreateModalOpen(false)}
        onSubmit={(values) => {
          createMutation.mutate(values);
          setCreateModalOpen(false);
        }}
      />
      <WaitingDetailDrawer
        open={detailDrawerOpen}
        selectedEntry={selectedEntry}
        notifySupported={true}
        seatSupported={true}
        advanceSupported={true}
        selectedReleasedTable={null}
        boardAccess={boardAccess}
        availableTableOptions={availableTableOptions}
        notifyForm={notifyForm}
        seatForm={seatForm}
        cancelForm={cancelForm}
        busy={notifyMutation.isPending || seatMutation.isPending || cancelMutation.isPending || advanceMutation.isPending}
        onClose={() => {
          setDetailDrawerOpen(false);
          setSelectedWaitingId(null);
          setSearchParams(setWaitingListFocus(searchParams, null));
        }}
        onNotify={(values) => notifyMutation.mutate(values)}
        onSeat={(values) => seatMutation.mutate(values)}
        onCancel={(values) => handleCancel(values)}
        onAdvanceQueue={() => handleAdvanceQueue()}
      />
    </>
  );

}

function toWaitingListSearchFilters(searchTerm: string): { guest_name?: string; phone?: string } {
  const trimmed = searchTerm.trim();
  if (trimmed === '') {
    return {};
  }

  return /\d/.test(trimmed) && !/[a-zA-Z]/.test(trimmed)
    ? { phone: trimmed }
    : { guest_name: trimmed };
}

function readWaitingListFocus(search: string | URLSearchParams): number | null {
  const params = search instanceof URLSearchParams ? search : new URLSearchParams(search);
  const rawValue = params.get('focus');

  if (!rawValue) {
    return null;
  }

  const parsed = Number(rawValue);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function setWaitingListFocus(search: string | URLSearchParams, waitingId: number | null): URLSearchParams {
  const params = search instanceof URLSearchParams ? new URLSearchParams(search) : new URLSearchParams(search);

  if (typeof waitingId === 'number' && Number.isInteger(waitingId) && waitingId > 0) {
    params.set('focus', String(waitingId));
    return params;
  }

  params.delete('focus');
  return params;
}

function getCanonicalWaitingTableContext(entry: StaffWaitingListEntry | null): { tableId: number; tableIds: Array<number> } | null {
  if (!entry) {
    return null;
  }

  const tableIds = Array.from(new Set([
    entry.orchestration.released_table?.table_id,
    ...(entry.orchestration.released_table?.table_ids ?? []),
    ...(entry.invite_hold.active?.table_ids ?? []),
    ...(entry.invite_hold.latest?.table_ids ?? []),
  ].filter((value): value is number => typeof value === 'number' && Number.isInteger(value) && value > 0)));

  const [tableId] = tableIds;
  if (tableId === undefined) {
    return null;
  }

  return {
    tableId,
    tableIds,
  };
}
