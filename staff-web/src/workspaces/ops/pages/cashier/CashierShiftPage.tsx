import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Alert,
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
import type { CashierShiftEnvelope, GetV1StaffCashierShiftsQueryParams } from '../../../../shared/api/sdk';
import {
  closeCashierShift,
  getCurrentCashierShift,
  listCashierShifts,
  openCashierShift,
} from '../../../../shared/api/staff-api';
import { formatApiError, isApiStatus } from '../../../../shared/api/errors';
import { isStaffCashierShiftActionRequired } from '../../../../app/auth/startup';
import { can } from '../../../../shared/auth/capabilities';
import { formatDateTime, formatMoney } from '../../../../shared/utils/format';
import { buildJourneySearch } from '../../../../app/router/journey';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { cashierShiftTone, paymentTone } from '../../../../shared/status/status';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { toast } from '../../../../shared/ui/feedback/toast';
import { EmptyBlock, InlineError, InlineLoading } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { useConfirmAction } from '../../../../shared/hooks/useConfirmAction';
import { useJourneyContext } from '../../../../app/router/useJourneyContext';

type OpenShiftValues = {
  opening_float_amount?: number;
  currency: string;
  terminal_code?: string;
  notes?: string;
};

type CloseShiftValues = {
  actual_cash_amount: number;
  notes?: string;
};

const shiftStatusOptions = [
  { value: 'all', label: 'Tất cả ca' },
  { value: 'Open', label: 'Đang mở' },
  { value: 'Closed', label: 'Đã đóng' },
];

export function CashierShiftPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const message = toast;
  const confirmAction = useConfirmAction();
  const journey = useJourneyContext();
  const session = useAuthStore((state) => state.session);
  const refreshSession = useAuthStore((state) => state.refresh);
  const branchId = useFlowStore((state) => state.branchId);
  const [statusFilter, setStatusFilter] = useState<'all' | 'Open' | 'Closed'>('all');
  const [lookupQuery, setLookupQuery] = useState('');
  const [selectedShiftId, setSelectedShiftId] = useState<number | null>(null);
  const [openForm] = Form.useForm<OpenShiftValues>();
  const [closeForm] = Form.useForm<CloseShiftValues>();

  const currentShiftQuery = useQuery({
    queryKey: ['cashier-shift-current', branchId],
    queryFn: () => getCurrentCashierShift(branchId ?? undefined),
    enabled: !!session,
    retry: (failureCount, error) => !isApiStatus(error, 404) && failureCount < 1,
  });

  const historyQuery = useQuery({
    queryKey: ['cashier-shifts', branchId, statusFilter, lookupQuery],
    queryFn: () =>
      listCashierShifts({
        branch_id: branchId ?? undefined,
        status: statusFilter === 'all' ? undefined : statusFilter,
        q: lookupQuery.trim() || undefined,
        per_page: 12,
        sort: '-opened_at',
      } satisfies GetV1StaffCashierShiftsQueryParams),
    enabled: !!session,
  });

  const currentShift = isApiStatus(currentShiftQuery.error, 404) ? null : currentShiftQuery.data?.data ?? null;

  useEffect(() => {
    if (currentShift?.cashier_shift_id) {
      setSelectedShiftId((existing) => existing ?? currentShift.cashier_shift_id);
      return;
    }

    const firstHistoryId = historyQuery.data?.data[0]?.cashier_shift_id;
    if (firstHistoryId) {
      setSelectedShiftId((existing) => existing ?? firstHistoryId);
    }
  }, [currentShift?.cashier_shift_id, historyQuery.data?.data]);

  const selectedShift = useMemo(() => {
    if (!selectedShiftId) {
      return null;
    }

    if (currentShift?.cashier_shift_id === selectedShiftId) {
      return currentShift;
    }

    return historyQuery.data?.data.find((shift) => shift.cashier_shift_id === selectedShiftId) ?? null;
  }, [currentShift, historyQuery.data?.data, selectedShiftId]);

  const watchedActualCash = Form.useWatch('actual_cash_amount', closeForm);
  const expectedCashAmount = Number(
    selectedShift?.summary?.cash.expected_cash_amount
      ?? selectedShift?.expected_cash_amount
      ?? selectedShift?.opening_float_amount
      ?? 0,
  );
  const varianceAmount = typeof watchedActualCash === 'number'
    ? watchedActualCash - expectedCashAmount
    : null;
  const requiresCashierShift = !!session && isStaffCashierShiftActionRequired(session);
  const returnToCheckoutPath = useMemo(() => {
    if (!session || !can(session, 'settlement.manage')) {
      return null;
    }

    if (!journey.orderId) {
      return null;
    }

  return `${staffRoutePaths.ops.checkout}?${buildJourneySearch({
      source: 'checkout',
      tableId: journey.tableId,
      tableIds: journey.tableIds,
      reservationId: journey.reservationId,
      reservationRowVersion: journey.reservationRowVersion,
      orderId: journey.orderId,
      orderRowVersion: journey.orderRowVersion,
      stationId: journey.stationId,
    })}`;
  }, [
    journey.orderId,
    journey.orderRowVersion,
    journey.reservationId,
    journey.reservationRowVersion,
    journey.stationId,
    journey.tableId,
    journey.tableIds,
    session,
  ]);
  const launchedFromCheckout = journey.source === 'checkout';
  const hasActiveShiftButStartupStale = !!currentShift && requiresCashierShift;

  useEffect(() => {
    if (!selectedShift) {
      return;
    }

    closeForm.setFieldsValue({
      actual_cash_amount: Number(
        selectedShift.summary?.cash.expected_cash_amount
          ?? selectedShift.expected_cash_amount
          ?? selectedShift.opening_float_amount
          ?? 0,
      ),
      notes: selectedShift.closing_note ?? undefined,
    });
  }, [closeForm, selectedShift]);

  const openShiftMutation = useMutation({
    mutationFn: async (values: OpenShiftValues) =>
      openCashierShift({
        opening_float_amount: values.opening_float_amount ?? 0,
        branch_id: branchId ?? session?.startup.default_branch?.branch_id ?? undefined,
        currency: values.currency,
        terminal_code: values.terminal_code?.trim() || null,
        notes: values.notes?.trim() || null,
      }),
    onSuccess: async (envelope) => {
      setSelectedShiftId(envelope.data.cashier_shift_id);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['cashier-shift-current'] }),
        queryClient.invalidateQueries({ queryKey: ['cashier-shifts'] }),
      ]);

      let refreshedSession = null;
      try {
        refreshedSession = await refreshSession();
      } catch {
        // Keep the workflow usable even if startup refresh fails.
      }

      message.success(`Đã mở ca thu ngân ${envelope.data.shift_code}.`);

      const checkoutReady = refreshedSession
        ? !isStaffCashierShiftActionRequired(refreshedSession)
        : false;

      if (launchedFromCheckout && returnToCheckoutPath && checkoutReady) {
        navigate(returnToCheckoutPath, { replace: true });
      }
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể mở ca thu ngân.'));
    },
  });

  const closeShiftMutation = useMutation({
    mutationFn: async (values: CloseShiftValues) => {
      if (!selectedShift) {
        throw new Error('Chọn một ca thu ngân đang mở trước khi đóng.');
      }

      return closeCashierShift(selectedShift.cashier_shift_id, {
        actual_cash_amount: values.actual_cash_amount,
        notes: values.notes?.trim() || null,
        row_version: selectedShift.row_version,
      });
    },
    onSuccess: async (envelope) => {
      setSelectedShiftId(envelope.data.cashier_shift_id);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['cashier-shift-current'] }),
        queryClient.invalidateQueries({ queryKey: ['cashier-shifts'] }),
      ]);

      try {
        await refreshSession();
      } catch {
        // Keep the workflow usable even if startup refresh fails.
      }

      message.success(`Đã đóng ca thu ngân ${envelope.data.shift_code}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể đóng ca thu ngân.'));
    },
  });

  async function handleCloseShift(values: CloseShiftValues) {
    const confirmed = await confirmAction({
      title: `Đóng ca ${selectedShift?.shift_code ?? ''}`,
      content: 'Chỉ đóng ca sau khi đã đối chiếu tiền mặt kiểm đếm với tiền mặt kỳ vọng. Thao tác này dùng phiên bản ca thu ngân đang hiển thị.',
      okText: 'Đóng ca',
    });

    if (confirmed) {
      await closeShiftMutation.mutateAsync(values);
    }
  }

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Ca thu ngân"
        title="Trung tâm ca thu ngân"
        description="Mở ca, theo dõi giao dịch đang chạy và chỉ đóng sau khi đã kiểm đếm khớp tiền mặt."
        meta={selectedShift ? `Phiên bản ca ${selectedShift.row_version}` : undefined}
        context={(
          <>
            <StatusChip label={currentShift?.shift_code ?? 'Chưa có ca mở'} tone={currentShift ? cashierShiftTone(currentShift.status) : 'warning'} />
            <StatusChip label={branchId ? `Lịch sử theo chi nhánh #${branchId}` : 'Lịch sử theo branch mặc định'} tone="default" />
            <StatusChip label={session?.startup.readiness.cashier_shift ?? 'unknown'} tone={paymentTone(session?.startup.readiness.cashier_shift)} />
          </>
        )}
        extra={(
          <Space wrap>
            {returnToCheckoutPath ? (
              <Button type="primary" onClick={() => navigate(returnToCheckoutPath)}>
                Quay lại thanh toán
              </Button>
            ) : null}
            <Button
              onClick={() => {
                void currentShiftQuery.refetch();
                void historyQuery.refetch();
              }}
              loading={currentShiftQuery.isFetching || historyQuery.isFetching}
            >
              Làm mới dữ liệu ca
            </Button>
          </Space>
        )}
      />

      {requiresCashierShift ? (
        <Alert
          type="warning"
          showIcon
          title="Màn hình thanh toán đang chờ ca thu ngân hoạt động"
          description="Startup readiness cho biết phiên nhân viên này phải mở ca thu ngân trước khi hoàn tất nghiệp vụ tài chính. Hãy mở ca tại đây rồi quay lại thanh toán."
        />
      ) : null}

      {hasActiveShiftButStartupStale ? (
        <Alert
          type="info"
          showIcon
          title="Đã có ca thu ngân hoạt động nhưng startup readiness đang cũ"
          description={(
            <Space orientation="vertical" size={12}>
              <Typography.Text>
                Dữ liệu ca hiện tại đã có hiệu lực. Hãy làm mới phiên nhân viên để màn hình thanh toán nhận biết yêu cầu ca đang mở đã được đáp ứng.
              </Typography.Text>
              <Space wrap>
                <Button onClick={() => void refreshSession()}>
                  Làm mới phiên nhân viên
                </Button>
                {returnToCheckoutPath ? (
                  <Button type="primary" onClick={() => navigate(returnToCheckoutPath)}>
                    Quay lại thanh toán
                  </Button>
                ) : null}
              </Space>
            </Space>
          )}
        />
      ) : null}

      {currentShiftQuery.error && !isApiStatus(currentShiftQuery.error, 404) ? (
        <InlineError message={formatApiError(currentShiftQuery.error, 'Không thể tải ca thu ngân hiện tại.')} />
      ) : null}

      <Row gutter={[16, 16]}>
        <Col xs={24} md={8}>
          <Card>
            <Statistic
              title="Ca hiện tại"
              value={currentShift?.shift_code ?? 'Chưa có'}
            />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card>
            <Statistic
              title="Ca gần đây"
              value={historyQuery.data?.meta?.count ?? historyQuery.data?.data.length ?? 0}
            />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card>
            <Statistic
              title="Giao dịch hiện tại"
              value={currentShift?.summary?.payments.payment_count ?? 0}
            />
          </Card>
        </Col>
      </Row>

      <Card title="Ca hiện tại">
        {currentShiftQuery.isLoading ? (
          <InlineLoading tip="Đang tải ca thu ngân hiện tại..." />
        ) : currentShift ? (
          <Descriptions bordered size="small" column={2}>
            <Descriptions.Item label="Ca">
              <Space>
                <Typography.Text strong>{currentShift.shift_code}</Typography.Text>
                <StatusChip label={currentShift.status} tone={cashierShiftTone(currentShift.status)} />
              </Space>
            </Descriptions.Item>
            <Descriptions.Item label="Chi nhánh">
              {currentShift.branch?.branch_code ?? currentShift.branch_id}
            </Descriptions.Item>
            <Descriptions.Item label="Mở lúc">
              {formatDateTime(currentShift.opened_at)}
            </Descriptions.Item>
            <Descriptions.Item label="Thiết bị">
              {currentShift.terminal_code ?? 'Không có'}
            </Descriptions.Item>
            <Descriptions.Item label="Tiền đầu ca">
              {formatMoney(currentShift.opening_float_amount, currentShift.currency)}
            </Descriptions.Item>
            <Descriptions.Item label="Tiền mặt kỳ vọng">
              {formatMoney(currentShift.summary?.cash.expected_cash_amount ?? currentShift.expected_cash_amount, currentShift.currency)}
            </Descriptions.Item>
          </Descriptions>
        ) : (
          <EmptyBlock
            title="Chưa có ca thu ngân đang mở"
            description="Thu ngân đang đăng nhập hiện chưa có ca hoạt động. Hãy mở một ca bên dưới trước khi quay lại thanh toán."
          />
        )}
      </Card>

      <Card title="Mở ca thu ngân">
        <Form<OpenShiftValues>
          form={openForm}
          layout="vertical"
          initialValues={{
            opening_float_amount: 0,
            currency: 'VND',
            terminal_code: 'staff-web-main',
          }}
          onFinish={(values) => openShiftMutation.mutate(values)}
        >
          <Row gutter={16}>
            <Col xs={24} md={8}>
              <Form.Item name="opening_float_amount" label="Tiền đầu ca">
                <InputNumber min={0} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item name="currency" label="Loại tiền" rules={[{ required: true, message: 'Nhập loại tiền.' }]}>
                <Input />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item name="terminal_code" label="Mã thiết bị">
                <Input placeholder="Mã thiết bị nếu cần" />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="notes" label="Ghi chú mở ca">
            <Input.TextArea rows={3} placeholder="Ghi chú mở ca nếu cần" />
          </Form.Item>
          <Alert
            type="info"
            showIcon
            style={{ marginBottom: 16 }}
            title={`Ca mới sẽ mở theo chi nhánh thao tác ${branchId ?? session?.startup.default_branch?.branch_id ?? 'mặc định'} từ shell.`}
          />
          <Button
            type="primary"
            htmlType="submit"
            loading={openShiftMutation.isPending}
            disabled={!!currentShift}
          >
            Mở ca thu ngân
          </Button>
        </Form>
      </Card>

      <Card
        title="Lịch sử ca gần đây"
        extra={(
          <Space wrap>
            <Select
              style={{ width: 140 }}
              value={statusFilter}
              options={shiftStatusOptions}
              onChange={(value) => setStatusFilter(value)}
            />
            <Input.Search
              allowClear
              placeholder="Tìm theo ca / thiết bị"
              style={{ width: 240 }}
              onSearch={setLookupQuery}
            />
          </Space>
        )}
      >
        {historyQuery.isLoading ? <InlineLoading tip="Đang tải lịch sử ca thu ngân..." /> : null}
        {historyQuery.error ? <InlineError message={formatApiError(historyQuery.error, 'Không thể tải lịch sử ca thu ngân.')} /> : null}
        {!historyQuery.isLoading && !historyQuery.error && (historyQuery.data?.data.length ?? 0) === 0 ? (
          <EmptyBlock
            title="Không có lịch sử ca"
            description="Bộ lọc hiện tại không trả về dòng ca thu ngân nào."
          />
        ) : null}
        {(historyQuery.data?.data.length ?? 0) > 0 ? (
          <Table<CashierShiftEnvelope['data']>
            rowKey="cashier_shift_id"
            pagination={false}
            dataSource={historyQuery.data?.data ?? []}
            rowClassName={(shift) => (shift.cashier_shift_id === selectedShiftId ? 'staff-row-selected' : '')}
            onRow={(shift) => ({
              onClick: () => setSelectedShiftId(shift.cashier_shift_id),
            })}
            columns={[
              {
                title: 'Ca',
                render: (_, shift) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text strong>{shift.shift_code}</Typography.Text>
                    <Typography.Text type="secondary">{shift.branch?.branch_code ?? shift.branch_id}</Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Trạng thái',
                render: (_, shift) => <StatusChip label={shift.status} tone={cashierShiftTone(shift.status)} />,
              },
              {
                title: 'Mở lúc',
                render: (_, shift) => formatDateTime(shift.opened_at),
              },
              {
                title: 'Thiết bị',
                dataIndex: 'terminal_code',
                render: (value: string | null | undefined) => value ?? 'Không có',
              },
            ]}
          />
        ) : null}
      </Card>
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card title="Ca đang chọn">
        {!selectedShift ? (
          <EmptyBlock
            title="Chưa chọn ca"
            description="Chọn một ca đang mở hoặc một ca vừa đóng để xem tổng hợp tiền mặt và ngữ cảnh đối soát."
          />
        ) : (
          <Space orientation="vertical" size={16} style={{ width: '100%' }}>
            <Descriptions bordered size="small" column={1}>
              <Descriptions.Item label="Ca">
                <Space>
                  <Typography.Text strong>{selectedShift.shift_code}</Typography.Text>
                  <StatusChip label={selectedShift.status} tone={cashierShiftTone(selectedShift.status)} />
                </Space>
              </Descriptions.Item>
              <Descriptions.Item label="Phiên bản thao tác">
                {selectedShift.row_version}
              </Descriptions.Item>
              <Descriptions.Item label="Nhân viên">
                {selectedShift.cashier?.full_name ?? selectedShift.cashier?.email ?? selectedShift.cashier?.user_id ?? 'Không có'}
              </Descriptions.Item>
              <Descriptions.Item label="Mở lúc">
                {formatDateTime(selectedShift.opened_at)}
              </Descriptions.Item>
              <Descriptions.Item label="Đóng lúc">
                {formatDateTime(selectedShift.closed_at)}
              </Descriptions.Item>
            </Descriptions>

            <Row gutter={[12, 12]}>
              <Col span={12}>
                <Card size="small">
                  <Statistic title="Tiền mặt kỳ vọng" value={formatMoney(expectedCashAmount, selectedShift.currency)} />
                </Card>
              </Col>
              <Col span={12}>
                <Card size="small">
                  <Statistic title="Thu ròng" value={formatMoney(selectedShift.summary?.payments.net_paid_total, selectedShift.currency)} />
                </Card>
              </Col>
            </Row>

            {selectedShift.summary?.methods?.length ? (
              <Card size="small" title="Phương thức thanh toán">
                <Space orientation="vertical" size={12} style={{ width: '100%' }}>
                  {selectedShift.summary.methods.map((method) => (
                    <Descriptions key={`${method.payment_method}-${method.currency}`} bordered size="small" column={1}>
                      <Descriptions.Item label="Phương thức">
                        {method.payment_method} • {method.currency}
                      </Descriptions.Item>
                      <Descriptions.Item label="Đã thu">
                        {formatMoney(method.captured_amount, method.currency)}
                      </Descriptions.Item>
                      <Descriptions.Item label="Đã hoàn">
                        {formatMoney(method.refunded_amount, method.currency)}
                      </Descriptions.Item>
                      <Descriptions.Item label="Ròng">
                        {formatMoney(method.net_amount, method.currency)}
                      </Descriptions.Item>
                    </Descriptions>
                  ))}
                </Space>
              </Card>
            ) : null}

            {selectedShift.status === 'Open' ? (
              <Card size="small" title="Đóng ca">
                <Alert
                  type={varianceAmount === null ? 'info' : varianceAmount === 0 ? 'success' : 'warning'}
                  showIcon
                  style={{ marginBottom: 16 }}
                  title="Review handoff trước khi đóng ca"
                  description="Xác nhận lại tiền mặt kiểm đếm, chênh lệch, ghi chú handoff và các phương thức nổi bật trước khi chốt đóng ca."
                />
                <Form<CloseShiftValues> form={closeForm} layout="vertical" onFinish={handleCloseShift}>
                  <Form.Item
                    name="actual_cash_amount"
                    label="Tiền mặt kiểm đếm"
                    rules={[{ required: true, message: 'Nhập số tiền mặt đã kiểm đếm.' }]}
                  >
                    <InputNumber min={0} style={{ width: '100%' }} />
                  </Form.Item>
                  <Form.Item name="notes" label="Ghi chú đóng ca">
                    <Input.TextArea rows={3} placeholder="Ghi chú đóng ca nếu cần" />
                  </Form.Item>
                  <Alert
                    type={varianceAmount === null ? 'info' : varianceAmount === 0 ? 'success' : 'warning'}
                    showIcon
                    style={{ marginBottom: 16 }}
                    title={
                      varianceAmount === null
                        ? `Tiền mặt kỳ vọng: ${formatMoney(expectedCashAmount, selectedShift.currency)}`
                        : `Chênh lệch: ${formatMoney(varianceAmount, selectedShift.currency)}`
                    }
                  />
                  <Button type="primary" htmlType="submit" loading={closeShiftMutation.isPending} block>
                    Đóng ca thu ngân
                  </Button>
                </Form>
              </Card>
            ) : null}
          </Space>
        )}
      </Card>

      <Card title="Bước chuyển tiếp tiếp theo">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Typography.Text type="secondary">
            Giữ màn hình thanh toán ở trạng thái chờ cho tới khi phiên nhân viên được làm mới và phản ánh đúng ca thu ngân đang hoạt động.
          </Typography.Text>
          {returnToCheckoutPath ? (
              <Button type="primary" onClick={() => navigate(returnToCheckoutPath ?? staffRoutePaths.ops.checkout)}>
              Quay lại thanh toán
            </Button>
          ) : null}
          {can(session, 'settlement.manage') ? (
            <Button
              onClick={() =>
                navigate(`${staffRoutePaths.ops.financeReview}?${buildJourneySearch({
                  source: 'checkout',
                  tableId: journey.tableId,
                  tableIds: journey.tableIds,
                  reservationId: journey.reservationId,
                  reservationRowVersion: journey.reservationRowVersion,
                  orderId: journey.orderId,
                  orderRowVersion: journey.orderRowVersion,
                  stationId: journey.stationId,
                })}`)
              }
            >
              Mở đối soát tài chính
            </Button>
          ) : null}
          <Button
            onClick={() =>
                        navigate(`${staffRoutePaths.ops.tables}?${buildJourneySearch({
                  source: journey.source ?? 'checkout',
                  tableId: journey.tableId,
                  tableIds: journey.tableIds,
                  reservationId: journey.reservationId,
                reservationRowVersion: journey.reservationRowVersion,
                orderId: journey.orderId,
                orderRowVersion: journey.orderRowVersion,
                stationId: journey.stationId,
              })}`)
            }
          >
            Quay lại sơ đồ bàn
          </Button>
          <StatusChip
            label={session?.startup.readiness.cashier_shift ?? 'unknown'}
            tone={paymentTone(session?.startup.readiness.cashier_shift)}
          />
        </Space>
      </Card>
    </Space>
  );

  return <SplitWorkspace main={main} side={side} />;
}
