import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Alert,
  App,
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
} from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type {
  GetV1StaffWaitingListQueryParams,
  StaffWaitingListEntry,
} from '../../core/api/sdk';
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
} from '../../core/api/staff-api';
import { formatApiError } from '../../core/api/errors';
import { can } from '../../core/permissions/capabilities';
import { formatDateTime } from '../../core/utils/format';
import { buildJourneySearch } from '../../core/utils/journey';
import { type StatusTone, waitingTone } from '../../core/utils/status';
import { translateUiCode } from '../../core/utils/translation';
import { PageHeader } from '../../components/layout/PageHeader';
import { SplitWorkspace } from '../../components/layout/SplitWorkspace';
import { EmptyBlock, InlineError, InlineLoading } from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { useAuthStore } from '../../app/store/auth-store';
import { useFlowStore } from '../../app/store/flow-store';
import { useConfirmAction } from '../../hooks/useConfirmAction';

type CreateWaitingValues = {
  guest_name: string;
  phone?: string;
  guest_count: number;
  user_id?: number;
  priority?: number;
  notes?: string;
};

type NotifyWaitingValues = {
  table_id?: number;
  hold_minutes?: number;
};

type SeatWaitingValues = {
  user_id?: number;
  service_minutes?: number;
  notes?: string;
};

type CancelWaitingValues = {
  cancel_reason?: string;
};

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
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { message } = App.useApp();
  const confirmAction = useConfirmAction();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const selectedTableId = useFlowStore((state) => state.selectedTableId);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const setTableContext = useFlowStore((state) => state.setTableContext);
  const [statusFilter, setStatusFilter] = useState<WaitingStatusFilter>('all');
  const [queueMode, setQueueMode] = useState<QueueMode>('active');
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedWaitingId, setSelectedWaitingId] = useState<number | null>(null);
  const [createForm] = Form.useForm<CreateWaitingValues>();
  const [notifyForm] = Form.useForm<NotifyWaitingValues>();
  const [seatForm] = Form.useForm<SeatWaitingValues>();
  const [cancelForm] = Form.useForm<CancelWaitingValues>();
  const boardWindow = useMemo(() => buildBoardWindow(), []);

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
    const currentRows = waitingListQuery.data?.data ?? [];
    if (currentRows.length === 0) {
      if (selectedWaitingId !== null) {
        setSelectedWaitingId(null);
      }
      return;
    }

    if (!selectedWaitingId || !currentRows.some((entry) => entry.waiting_id === selectedWaitingId)) {
      setSelectedWaitingId(currentRows[0].waiting_id);
    }
  }, [selectedWaitingId, waitingListQuery.data?.data]);

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

  useEffect(() => {
    if (!availableTableOptions.length) {
      return;
    }

    const selectedTableStillValid = availableTableOptions.some((option) => option.value === selectedTableId);
    if (selectedTableStillValid) {
      notifyForm.setFieldsValue({ table_id: selectedTableId ?? undefined });
      return;
    }

    if (!notifyForm.getFieldValue('table_id')) {
      notifyForm.setFieldsValue({ table_id: availableTableOptions[0].value });
    }
  }, [availableTableOptions, notifyForm, selectedTableId]);

  useEffect(() => {
    if (!selectedEntry) {
      return;
    }

    seatForm.setFieldsValue({
      user_id: selectedEntry.user_id ?? undefined,
      notes: selectedEntry.notes ?? undefined,
      service_minutes: 120,
    });
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
      setSelectedWaitingId(envelope.data.advanced_waiting_list?.waiting_id ?? envelope.data.source_waiting_list.waiting_id);
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

      setReservationContext({
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
        source: 'reservation',
      });
      if (tableId) {
        setTableContext({
          tableId,
          source: 'reservation',
        });
      }

      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['waiting-list'] }),
        queryClient.invalidateQueries({ queryKey: ['table-board'] }),
        queryClient.invalidateQueries({ queryKey: ['reservations'] }),
      ]);

      message.success(`Đã xếp bàn lượt chờ và tạo đặt bàn ${reservation.reservation_code}.`);
      navigate(`/orders?${buildJourneySearch({
        source: 'reservation',
        tableId,
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
      content: 'Chỉ dùng khi khách hiện tại đã từ chối hoặc cửa sổ báo khách đã hết hạn. Backend sẽ thử báo cho ứng viên hợp lệ tiếp theo của bàn vừa được nhả.',
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

  const main = (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Danh sách chờ"
        title="Hàng chờ phục vụ"
        description="Giữ hàng chờ dễ đọc và có thể thao tác ngay: thêm lượt nhanh, báo khách theo bàn thật, chỉ đẩy hàng chờ khi luồng báo khách cho phép, rồi đưa thẳng sang luồng đặt bàn và đơn hàng."
        extra={(
          <>
            <Select
              style={{ width: 160 }}
              value={statusFilter}
              options={waitingStatusOptions}
              onChange={(value) => setStatusFilter(value)}
            />
            <Select
              style={{ width: 140 }}
              value={queueMode}
              options={queueModeOptions}
              onChange={(value) => setQueueMode(value)}
            />
            <Input.Search
              allowClear
              placeholder="Khách hoặc số điện thoại"
              style={{ width: 220 }}
              onSearch={setSearchTerm}
            />
            <Button onClick={() => waitingListQuery.refetch()} loading={waitingListQuery.isFetching}>
              Làm mới hàng chờ
            </Button>
          </>
        )}
      />

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

      <Card title="Danh sách chờ">
        {waitingListQuery.isLoading ? <InlineLoading tip="Đang tải danh sách chờ..." /> : null}
        {waitingListQuery.error ? <InlineError message={formatApiError(waitingListQuery.error, 'Không thể tải danh sách chờ.')} /> : null}
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
              onClick: () => setSelectedWaitingId(entry.waiting_id),
            })}
            columns={[
              {
                title: 'Khách',
                render: (_, entry) => (
                  <Space direction="vertical" size={2}>
                    <Typography.Text strong>{entry.guest_name ?? `Lượt chờ #${entry.waiting_id}`}</Typography.Text>
                    <Typography.Text type="secondary">{entry.phone ?? `Khách #${entry.user_id ?? 'vãng lai'}`}</Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Thời điểm tạo',
                render: (_, entry) => formatDateTime(entry.requested_at),
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
    </Space>
  );

  const notifySupported = selectedEntry?.status === 'Waiting';
  const seatSupported = !!selectedEntry?.invite_lifecycle.can_staff_seat_now;
  const advanceSupported = !!selectedEntry?.orchestration.advance_queue.supported;
  const selectedReleasedTable = selectedEntry?.orchestration.released_table ?? null;

  const side = (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Card title="Thêm lượt chờ">
        <Form<CreateWaitingValues>
          form={createForm}
          layout="vertical"
          initialValues={{ guest_count: 2, priority: 0 }}
          onFinish={(values) => createMutation.mutate(values)}
        >
          <Form.Item name="guest_name" label="Tên khách" rules={[{ required: true, message: 'Nhập tên khách.' }]}>
            <Input placeholder="Tên khách đang chờ" />
          </Form.Item>
          <Row gutter={12}>
            <Col span={12}>
              <Form.Item name="phone" label="Số điện thoại">
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="guest_count" label="Số khách" rules={[{ required: true, message: 'Nhập số khách.' }]}>
                <InputNumber min={1} max={30} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={12}>
            <Col span={12}>
              <Form.Item name="user_id" label="Mã khách hàng liên kết">
                <InputNumber min={1} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="priority" label="Mức ưu tiên">
                <InputNumber min={-999} max={999} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="notes" label="Ghi chú">
            <Input.TextArea rows={3} placeholder="Ghi chú tiếp đón nếu cần" />
          </Form.Item>
          <Alert
            type="info"
            showIcon
            style={{ marginBottom: 16 }}
            message={`Đang dùng ngữ cảnh chi nhánh ${branchId ?? session?.startup.default_branch?.branch_id ?? 'mặc định'} từ shell nhân viên.`}
          />
          <Button type="primary" htmlType="submit" loading={createMutation.isPending} block>
            Thêm lượt chờ
          </Button>
        </Form>
      </Card>

      <Card title="Lượt chờ đang chọn">
        {!selectedEntry ? (
          <EmptyBlock
            title="Chưa chọn lượt chờ"
            description="Chọn một dòng hàng chờ để xem điều phối, trạng thái báo khách và mức sẵn sàng vào bàn."
          />
        ) : (
          <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Descriptions bordered size="small" column={1}>
              <Descriptions.Item label="Khách">
                {selectedEntry.guest_name ?? `Lượt chờ #${selectedEntry.waiting_id}`}
              </Descriptions.Item>
              <Descriptions.Item label="Trạng thái">
                <Space>
                  <StatusChip label={selectedEntry.status} tone={waitingTone(selectedEntry.status)} />
                  <StatusChip label={selectedEntry.current_response_state} tone={waitingResponseTone(selectedEntry.current_response_state)} />
                </Space>
              </Descriptions.Item>
              <Descriptions.Item label="Cửa sổ mời khách">
                {selectedEntry.invite_window.is_active
                  ? `Còn ${selectedEntry.invite_window.seconds_remaining}s`
                  : selectedEntry.invite_window.is_expired
                    ? 'Đã hết hạn'
                    : 'Chưa kích hoạt'}
              </Descriptions.Item>
              <Descriptions.Item label="Hành động gợi ý">
                {translateUiCode(selectedEntry.orchestration.recommended_action)}
              </Descriptions.Item>
              <Descriptions.Item label="Bàn vừa được nhả">
                {selectedReleasedTable?.table_code ?? 'Không áp dụng'}
              </Descriptions.Item>
              <Descriptions.Item label="Phiên bản dòng">
                {selectedEntry.row_version}
              </Descriptions.Item>
            </Descriptions>

            {notifySupported ? (
              <Card size="small" title="Báo khách">
                <Form<NotifyWaitingValues> form={notifyForm} layout="vertical" onFinish={(values) => notifyMutation.mutate(values)}>
                  {boardAccess && availableTableOptions.length > 0 ? (
                    <Form.Item
                      name="table_id"
                      label="Bàn còn trống"
                      rules={[{ required: true, message: 'Chọn bàn để giữ chỗ khi báo khách.' }]}
                    >
                      <Select
                        showSearch
                        optionFilterProp="label"
                        placeholder="Chọn một bàn còn trống"
                        options={availableTableOptions}
                      />
                    </Form.Item>
                  ) : (
                    <>
                      <Alert
                        type="warning"
                        showIcon
                        style={{ marginBottom: 16 }}
                        message="Không lấy được gợi ý từ sơ đồ bàn"
                        description="Phiên hiện tại không đọc được sơ đồ bàn hoặc chưa có bàn phù hợp. Hãy nhập thủ công mã bàn để giữ chỗ khi báo khách."
                      />
                      <Form.Item
                        name="table_id"
                        label="Mã bàn"
                        rules={[{ required: true, message: 'Nhập mã bàn.' }]}
                      >
                        <InputNumber min={1} style={{ width: '100%' }} />
                      </Form.Item>
                    </>
                  )}
                  <Form.Item name="hold_minutes" label="Số phút giữ chỗ">
                    <InputNumber min={1} max={60} style={{ width: '100%' }} />
                  </Form.Item>
                  <Button type="primary" htmlType="submit" loading={notifyMutation.isPending} block>
                    Báo khách hiện tại
                  </Button>
                </Form>
              </Card>
            ) : null}

            {advanceSupported ? (
              <Card size="small" title="Đẩy hàng chờ">
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                  <Typography.Text type="secondary">
                    Gợi ý kết quả: {translateUiCode(selectedEntry.orchestration.advance_queue.resulting_action)}
                  </Typography.Text>
                  {selectedEntry.orchestration.advance_queue.next_candidate ? (
                    <Alert
                      type="info"
                      showIcon
                      message={`Ứng viên kế tiếp: ${selectedEntry.orchestration.advance_queue.next_candidate.guest_name ?? selectedEntry.orchestration.advance_queue.next_candidate.waiting_id}`}
                      description={`Độ lệch chỗ ngồi ${selectedEntry.orchestration.advance_queue.next_candidate.capacity_fit.seat_delta}`}
                    />
                  ) : null}
                  <Button
                    onClick={() => void handleAdvanceQueue()}
                    disabled={!selectedEntry.orchestration.advance_queue.can_apply_now}
                    loading={advanceMutation.isPending}
                    block
                  >
                    Đẩy hàng chờ
                  </Button>
                </Space>
              </Card>
            ) : null}

            {seatSupported ? (
              <Card size="small" title="Xếp bàn và mở đơn hàng">
                {!selectedEntry.user_id ? (
                  <Alert
                    type="warning"
                    showIcon
                    style={{ marginBottom: 16 }}
                    message="Cần có mã khách hàng trước khi xếp bàn"
                    description="Backend yêu cầu `user_id` khi lượt chờ chưa liên kết khách hàng. Hãy nhập tại đây trước khi chuyển lượt chờ thành đặt bàn."
                  />
                ) : null}
                <Form<SeatWaitingValues> form={seatForm} layout="vertical" onFinish={(values) => seatMutation.mutate(values)}>
                  {!selectedEntry.user_id ? (
                    <Form.Item
                      name="user_id"
                      label="Mã khách hàng"
                      rules={[{ required: true, message: 'Nhập mã khách hàng liên kết.' }]}
                    >
                      <InputNumber min={1} style={{ width: '100%' }} />
                    </Form.Item>
                  ) : null}
                  <Form.Item name="service_minutes" label="Số phút phục vụ">
                    <InputNumber min={30} max={480} style={{ width: '100%' }} />
                  </Form.Item>
                  <Form.Item name="notes" label="Ghi chú xếp bàn">
                    <Input.TextArea rows={3} placeholder="Ghi chú xếp bàn nếu cần" />
                  </Form.Item>
                  <Button type="primary" htmlType="submit" loading={seatMutation.isPending} block>
                    Xếp bàn và mở đơn hàng
                  </Button>
                </Form>
              </Card>
            ) : null}

            <Card size="small" title="Hủy lượt chờ">
              <Form<CancelWaitingValues> form={cancelForm} layout="vertical" onFinish={handleCancel}>
                <Form.Item name="cancel_reason" label="Lý do hủy">
                  <Input.TextArea rows={2} placeholder="Lý do hủy nếu cần" />
                </Form.Item>
                <Button danger htmlType="submit" loading={cancelMutation.isPending} block>
                  Hủy lượt chờ
                </Button>
              </Form>
            </Card>
          </Space>
        )}
      </Card>

      <Card title="Đồng bộ hàng chờ">
        {changesQuery.isLoading ? (
          <InlineLoading tip="Đang đọc thay đổi của danh sách chờ..." />
        ) : changesQuery.error ? (
          <InlineError message={formatApiError(changesQuery.error, 'Không thể tải luồng thay đổi của danh sách chờ.')} />
        ) : (
          <Space direction="vertical" size={8}>
            <Typography.Text type="secondary">
              Phiên bản realtime v{changesQuery.data?.data.current_version ?? waitingListQuery.data?.meta?.realtime.current_version ?? 0}
            </Typography.Text>
            <Typography.Text type="secondary">
              Sự kiện: {changesQuery.data?.data.events.length ?? 0}
            </Typography.Text>
            <Button onClick={() => navigate('/tables')}>
              Mở sơ đồ bàn
            </Button>
          </Space>
        )}
      </Card>
    </Space>
  );

  return <SplitWorkspace main={main} side={side} />;
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

function waitingResponseTone(status: string | null | undefined): StatusTone {
  switch ((status ?? '').toLowerCase()) {
    case 'arrival_confirmed':
    case 'accepted':
      return 'success';
    case 'pending':
      return 'processing';
    case 'declined':
    case 'invite_expired':
      return 'warning';
    default:
      return 'default';
  }
}
