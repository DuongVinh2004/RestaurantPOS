import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Alert,
  App,
  Button,
  Card,
  Descriptions,
  Form,
  Input,
  InputNumber,
  Select,
  Space,
  Typography,
} from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createBillSnapshot,
  finalizeSettlement,
  getOrderDetail,
  getSettlementPreview,
} from '../../core/api/staff-api';
import { formatApiError } from '../../core/api/errors';
import { can } from '../../core/permissions/capabilities';
import { formatMoney } from '../../core/utils/format';
import { buildJourneySearch } from '../../core/utils/journey';
import { orderTone, paymentTone } from '../../core/utils/status';
import { translateUiCode } from '../../core/utils/translation';
import { PageHeader } from '../../components/layout/PageHeader';
import { SplitWorkspace } from '../../components/layout/SplitWorkspace';
import { EmptyBlock, InlineError, InlineLoading } from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { useAuthStore } from '../../app/store/auth-store';
import { useFlowStore } from '../../app/store/flow-store';
import { useJourneyContext } from '../../hooks/useJourneyContext';
import { useConfirmAction } from '../../hooks/useConfirmAction';

const paymentMethodOptions = ['Cash', 'Card', 'BankTransfer', 'Other'].map((value) => ({
  value,
  label: translateUiCode(value),
}));

type SnapshotFormValues = {
  discount_amount?: number;
  notes?: string;
};

type SettlementFormValues = {
  payment_method: 'Cash' | 'Card' | 'BankTransfer' | 'Other';
  payment_provider?: 'Cash' | 'Card' | 'BankTransfer' | 'Other';
  paid_amount: number;
  currency: string;
  transaction_code?: string;
  notes?: string;
};

export function CheckoutPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { message } = App.useApp();
  const confirmAction = useConfirmAction();
  const journey = useJourneyContext();
  const session = useAuthStore((state) => state.session);
  const setOrderContext = useFlowStore((state) => state.setOrderContext);
  const [snapshotForm] = Form.useForm<SnapshotFormValues>();
  const [settlementForm] = Form.useForm<SettlementFormValues>();
  const [previewCurrency, setPreviewCurrency] = useState('VND');

  const orderId = journey.orderId ?? null;
  const cashierShiftRequired = !!session?.startup.readiness.requires_cashier_shift
    && session.startup.readiness.cashier_shift === 'action_required';
  const cashierShiftManage = !!session && can(session, 'cashier.shift.manage');

  const orderQuery = useQuery({
    queryKey: ['checkout-order-detail', orderId],
    queryFn: () => getOrderDetail(orderId as number),
    enabled: !!orderId,
  });

  useEffect(() => {
    if (!orderQuery.data) {
      return;
    }

    const currency = orderQuery.data.data.financial_summary.currency ?? 'VND';
    setPreviewCurrency(currency);
    settlementForm.setFieldsValue({
      payment_method: 'Cash',
      payment_provider: 'Cash',
      paid_amount: Number(orderQuery.data.data.financial_summary.outstanding ?? 0),
      currency,
    });
  }, [orderQuery.data, settlementForm]);

  const previewQuery = useQuery({
    queryKey: ['settlement-preview', orderId, previewCurrency],
    queryFn: () => getSettlementPreview(orderId as number, { currency: previewCurrency }),
    enabled: !!orderId,
  });

  const snapshotMutation = useMutation({
    mutationFn: async (values: SnapshotFormValues) => {
      const rowVersion = orderQuery.data?.data.order.row_version;

      if (!orderId || !rowVersion) {
        throw new Error('HÃ£y táº£i Ä‘Æ¡n hÃ ng trÆ°á»›c Ä‘á»ƒ láº¥y `row_version` má»›i nháº¥t.');
      }

      return createBillSnapshot(orderId, {
        row_version: rowVersion,
        discount_amount: values.discount_amount ?? null,
        notes: values.notes?.trim() || null,
      });
    },
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['checkout-order-detail', orderId] }),
        queryClient.invalidateQueries({ queryKey: ['settlement-preview', orderId] }),
        queryClient.invalidateQueries({ queryKey: ['table-board'] }),
      ]);
      message.success('ÄÃ£ chá»¥p sá»‘ liá»‡u hÃ³a Ä‘Æ¡n.');
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ chá»¥p sá»‘ liá»‡u hÃ³a Ä‘Æ¡n.'));
    },
  });

  const finalizeMutation = useMutation({
    mutationFn: async (values: SettlementFormValues) => {
      const rowVersion = orderQuery.data?.data.order.row_version;

      if (!orderId || !rowVersion) {
        throw new Error('HÃ£y táº£i Ä‘Æ¡n hÃ ng trÆ°á»›c Ä‘á»ƒ láº¥y `row_version` má»›i nháº¥t.');
      }

      return finalizeSettlement(orderId, {
        payment_method: values.payment_method,
        payment_provider: values.payment_provider ?? values.payment_method,
        paid_amount: values.paid_amount,
        currency: values.currency,
        transaction_code: values.transaction_code?.trim() || null,
        notes: values.notes?.trim() || null,
        row_version: rowVersion,
      });
    },
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['checkout-order-detail', orderId] }),
        queryClient.invalidateQueries({ queryKey: ['settlement-preview', orderId] }),
        queryClient.invalidateQueries({ queryKey: ['table-board'] }),
      ]);
      message.success('ÄÃ£ hoÃ n táº¥t thanh toÃ¡n.');
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ hoÃ n táº¥t thanh toÃ¡n.'));
    },
  });

  const currentOrder = orderQuery.data?.data;
  const preview = previewQuery.data?.data;
  const outstanding = Number(preview?.outstanding_amount ?? currentOrder?.financial_summary.outstanding ?? 0);
  const finalizeDisabled = !orderId || cashierShiftRequired;

  useEffect(() => {
    if (!orderId || !currentOrder?.order.row_version) {
      return;
    }

    setOrderContext({
      orderId,
      orderRowVersion: currentOrder.order.row_version,
      source: journey.source ?? 'checkout',
    });
  }, [currentOrder?.order.row_version, journey.source, orderId, setOrderContext]);

  const cashierShiftPath = useMemo(() => {
    const search = buildJourneySearch({
      source: 'checkout',
      tableId: journey.tableId,
      reservationId: journey.reservationId,
      reservationRowVersion: journey.reservationRowVersion,
      orderId: orderId ?? undefined,
      orderRowVersion: currentOrder?.order.row_version ?? journey.orderRowVersion,
      stationId: journey.stationId,
    });

    return search ? `/cashier-shift?${search}` : '/cashier-shift';
  }, [
    currentOrder?.order.row_version,
    journey.orderRowVersion,
    journey.reservationId,
    journey.reservationRowVersion,
    journey.stationId,
    journey.tableId,
    orderId,
  ]);

  async function handleFinalize(values: SettlementFormValues) {
    const confirmed = await confirmAction({
      title: `HoÃ n táº¥t thanh toÃ¡n cho Ä‘Æ¡n #${orderId ?? ''}`,
      content: 'Thao tÃ¡c nÃ y ghi nháº­n thanh toÃ¡n vÃ  hoÃ n táº¥t quyáº¿t toÃ¡n á»Ÿ backend. Chá»‰ tiáº¿p tá»¥c sau khi Ä‘Ã£ kiá»ƒm tra sá»‘ tiá»n thu vÃ  mÃ£ giao dá»‹ch.',
      okText: 'HoÃ n táº¥t thanh toÃ¡n',
    });

    if (confirmed) {
      await finalizeMutation.mutateAsync(values);
    }
  }

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Thanh toÃ¡n / quyáº¿t toÃ¡n"
        title="MÃ n hÃ¬nh thanh toÃ¡n"
        description="Chá»‰ dÃ¹ng luá»“ng tÃ i chÃ­nh chuáº©n: chá»¥p sá»‘ liá»‡u hÃ³a Ä‘Æ¡n, xem trÆ°á»›c quyáº¿t toÃ¡n, rá»“i hoÃ n táº¥t vá»›i `row_version` hiá»‡n táº¡i Ä‘á»ƒ trÃ¡nh gá»­i láº·p."
        extra={
          <>
            <Button onClick={() => orderQuery.refetch()} disabled={!orderId} loading={orderQuery.isFetching}>
              LÃ m má»›i Ä‘Æ¡n hÃ ng
            </Button>
            <Button onClick={() => previewQuery.refetch()} disabled={!orderId} loading={previewQuery.isFetching}>
              LÃ m má»›i báº£n xem trÆ°á»›c
            </Button>
          </>
        }
      />

      {!orderId ? (
        <Card>
          <EmptyBlock
            title="ChÆ°a cÃ³ ngá»¯ cáº£nh Ä‘Æ¡n hÃ ng"
            description="Má»Ÿ thanh toÃ¡n tá»« sÆ¡ Ä‘á»“ bÃ n, mÃ n hÃ¬nh Ä‘Æ¡n hÃ ng hoáº·c báº¿p Ä‘á»ƒ mang theo ngá»¯ cáº£nh Ä‘Æ¡n hÃ ng hiá»‡n táº¡i."
          />
        </Card>
      ) : null}

      {orderQuery.isLoading ? <InlineLoading tip="Äang táº£i Ä‘Æ¡n hÃ ng cho thanh toÃ¡n..." /> : null}
      {orderQuery.error ? <InlineError message={formatApiError(orderQuery.error, 'KhÃ´ng thá»ƒ táº£i Ä‘Æ¡n hÃ ng cho thanh toÃ¡n.')} /> : null}

      {currentOrder ? (
        <Card title={`ÄÆ¡n hÃ ng #${currentOrder.order.order_id}`}>
          <Descriptions bordered size="small" column={2}>
                <Descriptions.Item label="Tráº¡ng thÃ¡i Ä‘Æ¡n">
              <StatusChip label={currentOrder.order.status} tone={orderTone(currentOrder.order.status)} />
            </Descriptions.Item>
                <Descriptions.Item label="Tráº¡ng thÃ¡i thanh toÃ¡n">
                  <StatusChip label={currentOrder.order.payment_status ?? 'Pending'} tone={paymentTone(currentOrder.order.payment_status)} />
                </Descriptions.Item>
                <Descriptions.Item label="Äáº·t bÃ n">
                  {currentOrder.reservation?.reservation_code ?? 'KhÃ¡ch vÃ£ng lai'}
                </Descriptions.Item>
                <Descriptions.Item label="KhÃ¡ch">
                  {currentOrder.customer?.full_name ?? currentOrder.customer?.phone ?? 'KhÃ¡ch vÃ£ng lai'}
                </Descriptions.Item>
                <Descriptions.Item label="Táº¡m tÃ­nh">
              {formatMoney(currentOrder.financial_summary.subtotal, currentOrder.financial_summary.currency ?? 'VND')}
            </Descriptions.Item>
                <Descriptions.Item label="CÃ²n thiáº¿u">
              {formatMoney(currentOrder.financial_summary.outstanding, currentOrder.financial_summary.currency ?? 'VND')}
            </Descriptions.Item>
          </Descriptions>
        </Card>
      ) : null}

      {cashierShiftRequired ? (
        <Alert
          type="warning"
          showIcon
          title="Cáº§n cÃ³ ca thu ngÃ¢n"
          description={(
            <Space orientation="vertical" size={12}>
              <Typography.Text>
                Tráº¡ng thÃ¡i sáºµn sÃ ng tá»« backend cho biáº¿t phiÃªn nhÃ¢n viÃªn nÃ y cáº§n cÃ³ ca thu ngÃ¢n Ä‘ang má»Ÿ trÆ°á»›c khi thá»±c hiá»‡n nghiá»‡p vá»¥ tÃ i chÃ­nh. MÃ n hÃ¬nh thanh toÃ¡n váº«n hiá»ƒn thá»‹ Ä‘á»ƒ nhÃ¢n viÃªn xem bill, nhÆ°ng nÃºt hoÃ n táº¥t bá»‹ khÃ³a cho tá»›i khi yÃªu cáº§u ca thu ngÃ¢n Ä‘Æ°á»£c Ä‘Ã¡p á»©ng.
              </Typography.Text>
              {cashierShiftManage ? (
                <Button onClick={() => navigate(cashierShiftPath)}>
                  Má»Ÿ trung tÃ¢m ca thu ngÃ¢n
                </Button>
              ) : null}
            </Space>
          )}
        />
      ) : null}
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card title="Sá»‘ liá»‡u hÃ³a Ä‘Æ¡n">
        <Form<SnapshotFormValues> form={snapshotForm} layout="vertical" onFinish={(values) => snapshotMutation.mutate(values)}>
          <Form.Item name="discount_amount" label="Sá»‘ tiá»n giáº£m giÃ¡">
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="notes" label="Ghi chÃº chá»¥p sá»‘ liá»‡u">
            <Input.TextArea rows={3} placeholder="Ghi chÃº tÃ i chÃ­nh náº¿u cáº§n" />
          </Form.Item>
          <Button type="primary" htmlType="submit" disabled={!orderId} loading={snapshotMutation.isPending} block>
            Chá»¥p sá»‘ liá»‡u hÃ³a Ä‘Æ¡n
          </Button>
        </Form>
      </Card>

      <Card title="Xem trÆ°á»›c quyáº¿t toÃ¡n">
        {!preview ? (
          previewQuery.isLoading ? (
            <InlineLoading tip="Äang táº£i báº£n xem trÆ°á»›c quyáº¿t toÃ¡n..." />
          ) : previewQuery.error ? (
            <InlineError message={formatApiError(previewQuery.error, 'KhÃ´ng thá»ƒ táº£i báº£n xem trÆ°á»›c quyáº¿t toÃ¡n.')} />
          ) : (
            <EmptyBlock
              title="ChÆ°a cÃ³ báº£n xem trÆ°á»›c"
              description="LÃ m má»›i báº£n xem trÆ°á»›c sau khi táº£i Ä‘Æ¡n hÃ ng hoáº·c sau má»—i láº§n cáº­p nháº­t sá»‘ liá»‡u hÃ³a Ä‘Æ¡n."
            />
          )
        ) : (
          <Descriptions bordered size="small" column={1}>
            <Descriptions.Item label="Tá»•ng cá»™ng">
              {formatMoney(preview.total_amount, preview.currency)}
            </Descriptions.Item>
            <Descriptions.Item label="ÄÃ£ thu">
              {formatMoney(preview.paid_amount, preview.currency)}
            </Descriptions.Item>
            <Descriptions.Item label="Cá»c Ä‘Ã£ Ã¡p dá»¥ng">
              {formatMoney(preview.deposit_applied_amount, preview.currency)}
            </Descriptions.Item>
            <Descriptions.Item label="CÃ²n thiáº¿u">
              {formatMoney(preview.outstanding_amount, preview.currency)}
            </Descriptions.Item>
            <Descriptions.Item label="PhiÃªn báº£n báº£n xem trÆ°á»›c">
              {preview.row_version}
            </Descriptions.Item>
          </Descriptions>
        )}
      </Card>

      <Card title="HoÃ n táº¥t thanh toÃ¡n">
        <Form<SettlementFormValues>
          form={settlementForm}
          layout="vertical"
          onFinish={handleFinalize}
        >
          <Form.Item name="payment_method" label="PhÆ°Æ¡ng thá»©c thanh toÃ¡n" rules={[{ required: true, message: 'Chá»n phÆ°Æ¡ng thá»©c thanh toÃ¡n.' }]}>
            <Select options={paymentMethodOptions} />
          </Form.Item>
          <Form.Item name="payment_provider" label="NhÃ  cung cáº¥p thanh toÃ¡n">
            <Select options={paymentMethodOptions} />
          </Form.Item>
          <Form.Item name="paid_amount" label="Sá»‘ tiá»n Ä‘Ã£ thu" rules={[{ required: true, message: 'Nháº­p sá»‘ tiá»n Ä‘Ã£ thu.' }]}>
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="currency" label="Loáº¡i tiá»n" rules={[{ required: true, message: 'Nháº­p loáº¡i tiá»n.' }]}>
            <Input
              onChange={(event) => setPreviewCurrency(event.target.value.toUpperCase())}
            />
          </Form.Item>
          <Form.Item name="transaction_code" label="MÃ£ giao dá»‹ch">
            <Input placeholder="MÃ£ Ä‘á»‘i soÃ¡t cá»•ng thanh toÃ¡n / thu ngÃ¢n náº¿u cÃ³" />
          </Form.Item>
          <Form.Item name="notes" label="Ghi chÃº thanh toÃ¡n">
            <Input.TextArea rows={3} placeholder="Ghi chÃº thanh toÃ¡n náº¿u cáº§n" />
          </Form.Item>
          <Alert
            type="info"
            showIcon
            title={`Sá»‘ tiá»n cÃ²n thiáº¿u hiá»‡n táº¡i: ${formatMoney(outstanding, previewCurrency)}`}
            style={{ marginBottom: 16 }}
          />
          <Button type="primary" htmlType="submit" disabled={finalizeDisabled} loading={finalizeMutation.isPending} block>
            HoÃ n táº¥t thanh toÃ¡n
          </Button>
        </Form>
      </Card>

      <Card title="BÆ°á»›c chuyá»ƒn tiáº¿p tiáº¿p theo">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Button
            onClick={() =>
              navigate(`/kitchen?${buildJourneySearch({
                source: 'checkout',
                tableId: journey.tableId,
                reservationId: journey.reservationId,
                reservationRowVersion: journey.reservationRowVersion,
                orderId: orderId ?? undefined,
                orderRowVersion: currentOrder?.order.row_version ?? undefined,
                stationId: journey.stationId,
              })}`)
            }
            disabled={!orderId}
          >
            Quay láº¡i báº¿p
          </Button>
          <Button
            onClick={() =>
              navigate(`/finance-review?${buildJourneySearch({
                source: 'checkout',
                tableId: journey.tableId,
                reservationId: journey.reservationId,
                reservationRowVersion: journey.reservationRowVersion,
                orderId: orderId ?? undefined,
                orderRowVersion: currentOrder?.order.row_version ?? undefined,
                stationId: journey.stationId,
              })}`)
            }
            disabled={!orderId}
          >
            Má»Ÿ Ä‘á»‘i soÃ¡t tÃ i chÃ­nh
          </Button>
          <Button
            onClick={() =>
              navigate(`/tables?${buildJourneySearch({
                source: journey.source ?? 'checkout',
                tableId: journey.tableId,
                reservationId: journey.reservationId,
                reservationRowVersion: journey.reservationRowVersion,
                orderId: orderId ?? undefined,
                orderRowVersion: currentOrder?.order.row_version ?? journey.orderRowVersion,
              })}`)
            }
          >
            Quay láº¡i sÆ¡ Ä‘á»“ bÃ n
          </Button>
        </Space>
      </Card>
    </Space>
  );

  return <SplitWorkspace main={main} side={side} />;
}

