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
import type { CashierShiftEnvelope, GetV1StaffCashierShiftsQueryParams } from '../../core/api/sdk';
import {
  closeCashierShift,
  getCurrentCashierShift,
  listCashierShifts,
  openCashierShift,
} from '../../core/api/staff-api';
import { formatApiError, isApiStatus } from '../../core/api/errors';
import { can } from '../../core/permissions/capabilities';
import { formatDateTime, formatMoney } from '../../core/utils/format';
import { buildJourneySearch } from '../../core/utils/journey';
import { cashierShiftTone, paymentTone } from '../../core/utils/status';
import { PageHeader } from '../../components/layout/PageHeader';
import { SplitWorkspace } from '../../components/layout/SplitWorkspace';
import { EmptyBlock, InlineError, InlineLoading } from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { useAuthStore } from '../../app/store/auth-store';
import { useFlowStore } from '../../app/store/flow-store';
import { useConfirmAction } from '../../hooks/useConfirmAction';
import { useJourneyContext } from '../../hooks/useJourneyContext';

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
  { value: 'all', label: 'Táº¥t cáº£ ca' },
  { value: 'Open', label: 'Äang má»Ÿ' },
  { value: 'Closed', label: 'ÄÃ£ Ä‘Ã³ng' },
];

export function CashierShiftPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { message } = App.useApp();
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
    queryKey: ['cashier-shift-current'],
    queryFn: getCurrentCashierShift,
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
  const requiresCashierShift = !!session?.startup.readiness.requires_cashier_shift
    && session.startup.readiness.cashier_shift === 'action_required';
  const returnToCheckoutPath = useMemo(() => {
    if (!session || !can(session, 'settlement.manage')) {
      return null;
    }

    if (!journey.orderId) {
      return null;
    }

    return `/checkout?${buildJourneySearch({
      source: 'checkout',
      tableId: journey.tableId,
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

      message.success(`ÄÃ£ má»Ÿ ca thu ngÃ¢n ${envelope.data.shift_code}.`);

      const checkoutReady = refreshedSession
        ? !(refreshedSession.startup.readiness.requires_cashier_shift
          && refreshedSession.startup.readiness.cashier_shift === 'action_required')
        : false;

      if (launchedFromCheckout && returnToCheckoutPath && checkoutReady) {
        navigate(returnToCheckoutPath, { replace: true });
      }
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ má»Ÿ ca thu ngÃ¢n.'));
    },
  });

  const closeShiftMutation = useMutation({
    mutationFn: async (values: CloseShiftValues) => {
      if (!selectedShift) {
        throw new Error('Chá»n má»™t ca thu ngÃ¢n Ä‘ang má»Ÿ trÆ°á»›c khi Ä‘Ã³ng.');
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

      message.success(`ÄÃ£ Ä‘Ã³ng ca thu ngÃ¢n ${envelope.data.shift_code}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ Ä‘Ã³ng ca thu ngÃ¢n.'));
    },
  });

  async function handleCloseShift(values: CloseShiftValues) {
    const confirmed = await confirmAction({
      title: `ÄÃ³ng ca ${selectedShift?.shift_code ?? ''}`,
      content: 'Chá»‰ Ä‘Ã³ng ca sau khi Ä‘Ã£ Ä‘á»‘i chiáº¿u tiá»n máº·t kiá»ƒm Ä‘áº¿m vá»›i tiá»n máº·t ká»³ vá»ng. Thao tÃ¡c nÃ y dÃ¹ng row_version hiá»‡n táº¡i cá»§a ca thu ngÃ¢n.',
      okText: 'ÄÃ³ng ca',
    });

    if (confirmed) {
      await closeShiftMutation.mutateAsync(values);
    }
  }

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Ca thu ngÃ¢n"
        title="Trung tÃ¢m ca thu ngÃ¢n"
        description="Giá»¯ nghiá»‡p vá»¥ ca thu ngÃ¢n rÃµ rÃ ng vÃ  dá»… thao tÃ¡c: má»Ÿ ca khi báº¯t Ä‘áº§u phá»¥c vá»¥, theo dÃµi ca Ä‘ang hoáº¡t Ä‘á»™ng trong lÃºc thanh toÃ¡n diá»…n ra, rá»“i chá»‰ Ä‘Ã³ng sau khi Ä‘Ã£ Ä‘á»‘i chiáº¿u tiá»n máº·t kiá»ƒm Ä‘áº¿m."
        extra={(
          <>
            {returnToCheckoutPath ? (
              <Button type="primary" onClick={() => navigate(returnToCheckoutPath)}>
                Quay láº¡i thanh toÃ¡n
              </Button>
            ) : null}
            <Button
              onClick={() => {
                void currentShiftQuery.refetch();
                void historyQuery.refetch();
              }}
              loading={currentShiftQuery.isFetching || historyQuery.isFetching}
            >
              LÃ m má»›i dá»¯ liá»‡u ca
            </Button>
          </>
        )}
      />

      {requiresCashierShift ? (
        <Alert
          type="warning"
          showIcon
          message="MÃ n hÃ¬nh thanh toÃ¡n Ä‘ang chá» ca thu ngÃ¢n hoáº¡t Ä‘á»™ng"
          description="Startup readiness cho biáº¿t phiÃªn nhÃ¢n viÃªn nÃ y pháº£i má»Ÿ ca thu ngÃ¢n trÆ°á»›c khi hoÃ n táº¥t nghiá»‡p vá»¥ tÃ i chÃ­nh. HÃ£y má»Ÿ ca táº¡i Ä‘Ã¢y rá»“i quay láº¡i thanh toÃ¡n."
        />
      ) : null}

      {hasActiveShiftButStartupStale ? (
        <Alert
          type="info"
          showIcon
          message="ÄÃ£ cÃ³ ca thu ngÃ¢n hoáº¡t Ä‘á»™ng nhÆ°ng startup readiness Ä‘ang cÅ©"
          description={(
            <Space orientation="vertical" size={12}>
              <Typography.Text>
                Dá»¯ liá»‡u ca hiá»‡n táº¡i Ä‘Ã£ cÃ³ hiá»‡u lá»±c. HÃ£y lÃ m má»›i phiÃªn nhÃ¢n viÃªn Ä‘á»ƒ mÃ n hÃ¬nh thanh toÃ¡n nháº­n biáº¿t yÃªu cáº§u ca Ä‘ang má»Ÿ Ä‘Ã£ Ä‘Æ°á»£c Ä‘Ã¡p á»©ng.
              </Typography.Text>
              <Space wrap>
                <Button onClick={() => void refreshSession()}>
                  LÃ m má»›i phiÃªn nhÃ¢n viÃªn
                </Button>
                {returnToCheckoutPath ? (
                  <Button type="primary" onClick={() => navigate(returnToCheckoutPath)}>
                    Quay láº¡i thanh toÃ¡n
                  </Button>
                ) : null}
              </Space>
            </Space>
          )}
        />
      ) : null}

      {currentShiftQuery.error && !isApiStatus(currentShiftQuery.error, 404) ? (
        <InlineError message={formatApiError(currentShiftQuery.error, 'KhÃ´ng thá»ƒ táº£i ca thu ngÃ¢n hiá»‡n táº¡i.')} />
      ) : null}

      <Row gutter={[16, 16]}>
        <Col xs={24} md={8}>
          <Card>
            <Statistic
              title="Ca hiá»‡n táº¡i"
              value={currentShift?.shift_code ?? 'ChÆ°a cÃ³'}
            />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card>
            <Statistic
              title="Ca gáº§n Ä‘Ã¢y"
              value={historyQuery.data?.meta?.count ?? historyQuery.data?.data.length ?? 0}
            />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card>
            <Statistic
              title="Giao dá»‹ch hiá»‡n táº¡i"
              value={currentShift?.summary?.payments.payment_count ?? 0}
            />
          </Card>
        </Col>
      </Row>

      <Card title="Ca hiá»‡n táº¡i">
        {currentShiftQuery.isLoading ? (
          <InlineLoading tip="Äang táº£i ca thu ngÃ¢n hiá»‡n táº¡i..." />
        ) : currentShift ? (
          <Descriptions bordered size="small" column={2}>
            <Descriptions.Item label="Ca">
              <Space>
                <Typography.Text strong>{currentShift.shift_code}</Typography.Text>
                <StatusChip label={currentShift.status} tone={cashierShiftTone(currentShift.status)} />
              </Space>
            </Descriptions.Item>
            <Descriptions.Item label="Chi nhÃ¡nh">
              {currentShift.branch?.branch_code ?? currentShift.branch_id}
            </Descriptions.Item>
            <Descriptions.Item label="Má»Ÿ lÃºc">
              {formatDateTime(currentShift.opened_at)}
            </Descriptions.Item>
            <Descriptions.Item label="Thiáº¿t bá»‹">
              {currentShift.terminal_code ?? 'KhÃ´ng cÃ³'}
            </Descriptions.Item>
            <Descriptions.Item label="Tiá»n Ä‘áº§u ca">
              {formatMoney(currentShift.opening_float_amount, currentShift.currency)}
            </Descriptions.Item>
            <Descriptions.Item label="Tiá»n máº·t ká»³ vá»ng">
              {formatMoney(currentShift.summary?.cash.expected_cash_amount ?? currentShift.expected_cash_amount, currentShift.currency)}
            </Descriptions.Item>
          </Descriptions>
        ) : (
          <EmptyBlock
            title="ChÆ°a cÃ³ ca thu ngÃ¢n Ä‘ang má»Ÿ"
            description="Thu ngÃ¢n Ä‘ang Ä‘Äƒng nháº­p hiá»‡n chÆ°a cÃ³ ca hoáº¡t Ä‘á»™ng. HÃ£y má»Ÿ má»™t ca bÃªn dÆ°á»›i trÆ°á»›c khi quay láº¡i thanh toÃ¡n."
          />
        )}
      </Card>

      <Card title="Má»Ÿ ca thu ngÃ¢n">
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
              <Form.Item name="opening_float_amount" label="Tiá»n Ä‘áº§u ca">
                <InputNumber min={0} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item name="currency" label="Loáº¡i tiá»n" rules={[{ required: true, message: 'Nháº­p loáº¡i tiá»n.' }]}>
                <Input />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item name="terminal_code" label="MÃ£ thiáº¿t bá»‹">
                <Input placeholder="MÃ£ thiáº¿t bá»‹ náº¿u cáº§n" />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="notes" label="Ghi chÃº má»Ÿ ca">
            <Input.TextArea rows={3} placeholder="Ghi chÃº má»Ÿ ca náº¿u cáº§n" />
          </Form.Item>
          <Alert
            type="info"
            showIcon
            style={{ marginBottom: 16 }}
            message={`Äang dÃ¹ng ngá»¯ cáº£nh chi nhÃ¡nh ${branchId ?? session?.startup.default_branch?.branch_id ?? 'máº·c Ä‘á»‹nh'} tá»« shell nhÃ¢n viÃªn.`}
          />
          <Button
            type="primary"
            htmlType="submit"
            loading={openShiftMutation.isPending}
            disabled={!!currentShift}
          >
            Má»Ÿ ca thu ngÃ¢n
          </Button>
        </Form>
      </Card>

      <Card
        title="Lá»‹ch sá»­ ca gáº§n Ä‘Ã¢y"
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
              placeholder="TÃ¬m theo ca / thiáº¿t bá»‹"
              style={{ width: 240 }}
              onSearch={setLookupQuery}
            />
          </Space>
        )}
      >
        {historyQuery.isLoading ? <InlineLoading tip="Äang táº£i lá»‹ch sá»­ ca thu ngÃ¢n..." /> : null}
        {historyQuery.error ? <InlineError message={formatApiError(historyQuery.error, 'KhÃ´ng thá»ƒ táº£i lá»‹ch sá»­ ca thu ngÃ¢n.')} /> : null}
        {!historyQuery.isLoading && !historyQuery.error && (historyQuery.data?.data.length ?? 0) === 0 ? (
          <EmptyBlock
            title="KhÃ´ng cÃ³ lá»‹ch sá»­ ca"
            description="Bá»™ lá»c hiá»‡n táº¡i khÃ´ng tráº£ vá» dÃ²ng ca thu ngÃ¢n nÃ o."
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
                title: 'Tráº¡ng thÃ¡i',
                render: (_, shift) => <StatusChip label={shift.status} tone={cashierShiftTone(shift.status)} />,
              },
              {
                title: 'Má»Ÿ lÃºc',
                render: (_, shift) => formatDateTime(shift.opened_at),
              },
              {
                title: 'Thiáº¿t bá»‹',
                dataIndex: 'terminal_code',
                render: (value: string | null | undefined) => value ?? 'KhÃ´ng cÃ³',
              },
            ]}
          />
        ) : null}
      </Card>
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card title="Ca Ä‘ang chá»n">
        {!selectedShift ? (
          <EmptyBlock
            title="ChÆ°a chá»n ca"
            description="Chá»n má»™t ca Ä‘ang má»Ÿ hoáº·c má»™t ca vá»«a Ä‘Ã³ng Ä‘á»ƒ xem tá»•ng há»£p tiá»n máº·t vÃ  ngá»¯ cáº£nh Ä‘á»‘i soÃ¡t."
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
              <Descriptions.Item label="PhiÃªn báº£n dÃ²ng">
                {selectedShift.row_version}
              </Descriptions.Item>
              <Descriptions.Item label="NhÃ¢n viÃªn">
                {selectedShift.cashier?.full_name ?? selectedShift.cashier?.email ?? selectedShift.cashier?.user_id ?? 'KhÃ´ng cÃ³'}
              </Descriptions.Item>
              <Descriptions.Item label="Má»Ÿ lÃºc">
                {formatDateTime(selectedShift.opened_at)}
              </Descriptions.Item>
              <Descriptions.Item label="ÄÃ³ng lÃºc">
                {formatDateTime(selectedShift.closed_at)}
              </Descriptions.Item>
            </Descriptions>

            <Row gutter={[12, 12]}>
              <Col span={12}>
                <Card size="small">
                  <Statistic title="Tiá»n máº·t ká»³ vá»ng" value={formatMoney(expectedCashAmount, selectedShift.currency)} />
                </Card>
              </Col>
              <Col span={12}>
                <Card size="small">
                  <Statistic title="Thu rÃ²ng" value={formatMoney(selectedShift.summary?.payments.net_paid_total, selectedShift.currency)} />
                </Card>
              </Col>
            </Row>

            {selectedShift.summary?.methods?.length ? (
              <Card size="small" title="PhÆ°Æ¡ng thá»©c thanh toÃ¡n">
                <Space orientation="vertical" size={12} style={{ width: '100%' }}>
                  {selectedShift.summary.methods.map((method) => (
                    <Descriptions key={`${method.payment_method}-${method.currency}`} bordered size="small" column={1}>
                      <Descriptions.Item label="PhÆ°Æ¡ng thá»©c">
                        {method.payment_method} â€¢ {method.currency}
                      </Descriptions.Item>
                      <Descriptions.Item label="ÄÃ£ thu">
                        {formatMoney(method.captured_amount, method.currency)}
                      </Descriptions.Item>
                      <Descriptions.Item label="ÄÃ£ hoÃ n">
                        {formatMoney(method.refunded_amount, method.currency)}
                      </Descriptions.Item>
                      <Descriptions.Item label="RÃ²ng">
                        {formatMoney(method.net_amount, method.currency)}
                      </Descriptions.Item>
                    </Descriptions>
                  ))}
                </Space>
              </Card>
            ) : null}

            {selectedShift.status === 'Open' ? (
              <Card size="small" title="ÄÃ³ng ca">
                <Form<CloseShiftValues> form={closeForm} layout="vertical" onFinish={handleCloseShift}>
                  <Form.Item
                    name="actual_cash_amount"
                    label="Tiá»n máº·t kiá»ƒm Ä‘áº¿m"
                    rules={[{ required: true, message: 'Nháº­p sá»‘ tiá»n máº·t Ä‘Ã£ kiá»ƒm Ä‘áº¿m.' }]}
                  >
                    <InputNumber min={0} style={{ width: '100%' }} />
                  </Form.Item>
                  <Form.Item name="notes" label="Ghi chÃº Ä‘Ã³ng ca">
                    <Input.TextArea rows={3} placeholder="Ghi chÃº Ä‘Ã³ng ca náº¿u cáº§n" />
                  </Form.Item>
                  <Alert
                    type={varianceAmount === null ? 'info' : varianceAmount === 0 ? 'success' : 'warning'}
                    showIcon
                    style={{ marginBottom: 16 }}
                    message={
                      varianceAmount === null
                        ? `Tiá»n máº·t ká»³ vá»ng: ${formatMoney(expectedCashAmount, selectedShift.currency)}`
                        : `ChÃªnh lá»‡ch: ${formatMoney(varianceAmount, selectedShift.currency)}`
                    }
                  />
                  <Button type="primary" htmlType="submit" loading={closeShiftMutation.isPending} block>
                    ÄÃ³ng ca thu ngÃ¢n
                  </Button>
                </Form>
              </Card>
            ) : null}
          </Space>
        )}
      </Card>

      <Card title="BÆ°á»›c chuyá»ƒn tiáº¿p tiáº¿p theo">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Typography.Text type="secondary">
            Giá»¯ mÃ n hÃ¬nh thanh toÃ¡n á»Ÿ tráº¡ng thÃ¡i chá» cho tá»›i khi phiÃªn nhÃ¢n viÃªn Ä‘Æ°á»£c lÃ m má»›i vÃ  pháº£n Ã¡nh Ä‘Ãºng ca thu ngÃ¢n Ä‘ang hoáº¡t Ä‘á»™ng.
          </Typography.Text>
          {returnToCheckoutPath ? (
            <Button type="primary" onClick={() => navigate(returnToCheckoutPath ?? '/checkout')}>
              Quay láº¡i thanh toÃ¡n
            </Button>
          ) : null}
          {can(session, 'settlement.manage') ? (
            <Button
              onClick={() =>
                navigate(`/finance-review?${buildJourneySearch({
                  source: 'checkout',
                  tableId: journey.tableId,
                  reservationId: journey.reservationId,
                  reservationRowVersion: journey.reservationRowVersion,
                  orderId: journey.orderId,
                  orderRowVersion: journey.orderRowVersion,
                  stationId: journey.stationId,
                })}`)
              }
            >
              Má»Ÿ Ä‘á»‘i soÃ¡t tÃ i chÃ­nh
            </Button>
          ) : null}
          <Button
            onClick={() =>
              navigate(`/tables?${buildJourneySearch({
                source: journey.source ?? 'checkout',
                tableId: journey.tableId,
                reservationId: journey.reservationId,
                reservationRowVersion: journey.reservationRowVersion,
                orderId: journey.orderId,
                orderRowVersion: journey.orderRowVersion,
                stationId: journey.stationId,
              })}`)
            }
          >
            Quay láº¡i sÆ¡ Ä‘á»“ bÃ n
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

