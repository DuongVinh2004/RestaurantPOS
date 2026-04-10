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
  addOrderItems,
  createTableOrder,
  dispatchKitchenOrder,
  getActiveOrderByReservation,
  getActiveOrderByTable,
  getOrderDetail,
  getReservationDetail,
  listMenuItems,
  updateOrderItem,
  updateOrderItemStatus,
} from '../../core/api/staff-api';
import { formatApiError, isApiStatus } from '../../core/api/errors';
import { formatMoney } from '../../core/utils/format';
import { buildJourneySearch } from '../../core/utils/journey';
import { orderTone } from '../../core/utils/status';
import { translateUiCode } from '../../core/utils/translation';
import { PageHeader } from '../../components/layout/PageHeader';
import { SplitWorkspace } from '../../components/layout/SplitWorkspace';
import { EmptyBlock, InlineError, InlineLoading } from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { useFlowStore } from '../../app/store/flow-store';
import { useJourneyContext } from '../../hooks/useJourneyContext';
import { useConfirmAction } from '../../hooks/useConfirmAction';
import { canEditOrderItem, getAllowedOrderItemStatuses, type StaffOrderItemTransitionStatus } from './order-item-workflow';

type CreateOrderValues = {
  notes?: string;
};

type AddItemValues = {
  menu_item_id: number;
  qty: number;
  note?: string;
};

type EditItemValues = {
  qty: number;
  note?: string;
};

type OrderDetailData = Awaited<ReturnType<typeof getOrderDetail>>['data'];
type OrderLineItem = OrderDetailData['items'][number];

export function OrderWorkspacePage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { message } = App.useApp();
  const confirmAction = useConfirmAction();
  const journey = useJourneyContext();
  const setOrderContext = useFlowStore((state) => state.setOrderContext);
  const [menuSearch, setMenuSearch] = useState('');
  const [selectedItemId, setSelectedItemId] = useState<number | null>(null);
  const [addItemForm] = Form.useForm<AddItemValues>();
  const [editItemForm] = Form.useForm<EditItemValues>();

  const tableId = journey.tableId ?? null;
  const reservationId = journey.reservationId ?? null;
  const reservationRowVersion = journey.reservationRowVersion ?? null;
  const routeOrderId = journey.orderId ?? null;

  const activeOrderByTableQuery = useQuery({
    queryKey: ['active-order-by-table', tableId],
    queryFn: () => getActiveOrderByTable(tableId as number),
    enabled: !routeOrderId && !!tableId,
    retry: (failureCount, error) => !isApiStatus(error, 404) && failureCount < 1,
  });

  const activeOrderByReservationQuery = useQuery({
    queryKey: ['active-order-by-reservation', reservationId],
    queryFn: () => getActiveOrderByReservation(reservationId as number),
    enabled: !routeOrderId && !activeOrderByTableQuery.data && !!reservationId,
    retry: (failureCount, error) => !isApiStatus(error, 404) && failureCount < 1,
  });

  const resolvedOrderId = routeOrderId
    ?? activeOrderByTableQuery.data?.data.order.order_id
    ?? activeOrderByReservationQuery.data?.data.order.order_id
    ?? null;

  useEffect(() => {
    if (!resolvedOrderId) {
      return;
    }

    const resolvedOrderRowVersion = routeOrderId
      ? journey.orderRowVersion
      : activeOrderByTableQuery.data?.data.order.row_version
        ?? activeOrderByReservationQuery.data?.data.order.row_version;

    setOrderContext({
      orderId: resolvedOrderId,
      orderRowVersion: resolvedOrderRowVersion ?? null,
      source: journey.source ?? 'order',
    });
  }, [
    activeOrderByReservationQuery.data?.data.order.row_version,
    activeOrderByTableQuery.data?.data.order.row_version,
    journey.orderRowVersion,
    journey.source,
    resolvedOrderId,
    routeOrderId,
    setOrderContext,
  ]);

  const reservationDetailQuery = useQuery({
    queryKey: ['order-reservation-detail', reservationId],
    queryFn: () => getReservationDetail(reservationId as number),
    enabled: !!reservationId && !resolvedOrderId,
  });

  const orderDetailQuery = useQuery({
    queryKey: ['order-detail', resolvedOrderId],
    queryFn: () => getOrderDetail(resolvedOrderId as number),
    enabled: !!resolvedOrderId,
  });

  const orderItems = orderDetailQuery.data?.data.items ?? [];
  const selectedItem = useMemo<OrderLineItem | null>(
    () => orderItems.find((item) => item.order_item_id === selectedItemId) ?? null,
    [orderItems, selectedItemId],
  );

  useEffect(() => {
    if (!orderItems.length) {
      setSelectedItemId(null);
      return;
    }

    if (!selectedItemId || !orderItems.some((item) => item.order_item_id === selectedItemId)) {
      setSelectedItemId(orderItems[0]?.order_item_id ?? null);
    }
  }, [orderItems, selectedItemId]);

  useEffect(() => {
    if (!selectedItem) {
      editItemForm.resetFields();
      return;
    }

    editItemForm.setFieldsValue({
      qty: selectedItem.quantity,
      note: selectedItem.notes ?? undefined,
    });
  }, [editItemForm, selectedItem]);

  useEffect(() => {
    if (!resolvedOrderId || !orderDetailQuery.data?.data.order.row_version) {
      return;
    }

    setOrderContext({
      orderId: resolvedOrderId,
      orderRowVersion: orderDetailQuery.data.data.order.row_version,
      source: journey.source ?? 'order',
    });
  }, [journey.source, orderDetailQuery.data?.data.order.row_version, resolvedOrderId, setOrderContext]);

  const menuQuery = useQuery({
    queryKey: ['staff-menu-items', menuSearch],
    queryFn: () => listMenuItems({
      q: menuSearch.trim() || undefined,
      per_page: 20,
      service_time: new Date().toISOString(),
    }),
  });

  const createOrderMutation = useMutation({
    mutationFn: async (values: CreateOrderValues) => {
      const effectiveTableId = tableId;
      const effectiveReservationId = reservationId;
      const effectiveRowVersion = reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? null;

      if (!effectiveTableId || !effectiveReservationId || !effectiveRowVersion) {
        throw new Error('Táº¡o Ä‘Æ¡n cáº§n cÃ³ mÃ£ bÃ n, mÃ£ Ä‘áº·t bÃ n vÃ  phiÃªn báº£n Ä‘áº·t bÃ n hiá»‡n táº¡i.');
      }

      return createTableOrder(effectiveTableId, {
        reservation_id: effectiveReservationId,
        notes: values.notes?.trim() || null,
        row_version: effectiveRowVersion,
      });
    },
    onSuccess: async (orderEnvelope) => {
      await queryClient.invalidateQueries({ queryKey: ['table-board'] });
      const orderId = orderEnvelope.data.order_id;
      setOrderContext({
        orderId,
        orderRowVersion: orderEnvelope.data.row_version ?? null,
        source: 'order',
      });
      message.success(`ÄÃ£ táº¡o Ä‘Æ¡n hÃ ng #${orderId}.`);
      navigate(`/orders?${buildJourneySearch({
        source: journey.source ?? 'order',
        tableId: tableId ?? undefined,
        reservationId: reservationId ?? undefined,
        reservationRowVersion: reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? undefined,
        orderId,
        orderRowVersion: orderEnvelope.data.row_version ?? undefined,
      })}`, { replace: true });
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ táº¡o Ä‘Æ¡n hÃ ng cho bÃ n vÃ  Ä‘áº·t bÃ n Ä‘Ã£ chá»n.'));
    },
  });

  const addItemMutation = useMutation({
    mutationFn: async (values: AddItemValues) => {
      const orderId = resolvedOrderId;
      const rowVersion = orderDetailQuery.data?.data.order.row_version;

      if (!orderId || !rowVersion) {
        throw new Error('HÃ£y táº£i Ä‘Æ¡n hÃ ng hiá»‡n táº¡i trÆ°á»›c Ä‘á»ƒ láº¥y `row_version` má»›i nháº¥t.');
      }

      return addOrderItems(orderId, {
        row_version: rowVersion,
        items: [
          {
            menu_item_id: values.menu_item_id,
            qty: values.qty,
            note: values.note?.trim() || null,
          },
        ],
      });
    },
    onSuccess: async () => {
      addItemForm.resetFields();
      await queryClient.invalidateQueries({ queryKey: ['order-detail', resolvedOrderId] });
      message.success('ÄÃ£ thÃªm mÃ³n vÃ o Ä‘Æ¡n hÃ ng hiá»‡n táº¡i.');
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ thÃªm mÃ³n vÃ o Ä‘Æ¡n hÃ ng.'));
    },
  });

  const updateItemMutation = useMutation({
    mutationFn: async (values: EditItemValues) => {
      const orderId = resolvedOrderId;
      const orderRowVersion = orderDetailQuery.data?.data.order.row_version;
      const itemRowVersion = selectedItem?.row_version;

      if (!orderId || !selectedItem || !orderRowVersion || !itemRowVersion) {
        throw new Error('HÃ£y táº£i láº¡i chi tiáº¿t Ä‘Æ¡n hÃ ng Ä‘á»ƒ cÃ³ `row_version` má»›i nháº¥t cá»§a Ä‘Æ¡n vÃ  dÃ²ng mÃ³n.');
      }

      return updateOrderItem(orderId, selectedItem.order_item_id, {
        qty: values.qty,
        note: values.note?.trim() || null,
        order_row_version: orderRowVersion,
        row_version: itemRowVersion,
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['order-detail', resolvedOrderId] });
      message.success('ÄÃ£ cáº­p nháº­t dÃ²ng mÃ³n Ä‘Ã£ chá»n.');
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ cáº­p nháº­t dÃ²ng mÃ³n Ä‘Ã£ chá»n.'));
    },
  });

  const updateItemStatusMutation = useMutation({
    mutationFn: async (status: StaffOrderItemTransitionStatus) => {
      const orderId = resolvedOrderId;
      const orderRowVersion = orderDetailQuery.data?.data.order.row_version;
      const itemRowVersion = selectedItem?.row_version;

      if (!orderId || !selectedItem || !orderRowVersion || !itemRowVersion) {
        throw new Error('HÃ£y táº£i láº¡i chi tiáº¿t Ä‘Æ¡n hÃ ng Ä‘á»ƒ cÃ³ `row_version` má»›i nháº¥t cá»§a Ä‘Æ¡n vÃ  dÃ²ng mÃ³n.');
      }

      return updateOrderItemStatus(orderId, selectedItem.order_item_id, {
        status,
        order_row_version: orderRowVersion,
        row_version: itemRowVersion,
      });
    },
    onSuccess: async (_, status) => {
      await queryClient.invalidateQueries({ queryKey: ['order-detail', resolvedOrderId] });
      message.success(`ÄÃ£ chuyá»ƒn dÃ²ng mÃ³n sang tráº¡ng thÃ¡i ${translateUiCode(status)}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ cáº­p nháº­t tráº¡ng thÃ¡i dÃ²ng mÃ³n.'));
    },
  });

  const dispatchMutation = useMutation({
    mutationFn: async () => {
      if (!resolvedOrderId) {
        throw new Error('Chá»n hoáº·c táº¡o má»™t Ä‘Æ¡n hÃ ng Ä‘ang phá»¥c vá»¥ trÆ°á»›c khi chuyá»ƒn báº¿p.');
      }

      return dispatchKitchenOrder(resolvedOrderId, {
        row_version: orderDetailQuery.data?.data.order.row_version ?? undefined,
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['kitchen-stations'] });
      message.success('ÄÃ£ chuyá»ƒn Ä‘Æ¡n hÃ ng sang báº¿p.');
      navigate(`/kitchen?${buildJourneySearch({
        source: 'order',
        tableId: tableId ?? undefined,
        reservationId: reservationId ?? undefined,
        reservationRowVersion: reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? undefined,
        orderId: resolvedOrderId ?? undefined,
        orderRowVersion: orderDetailQuery.data?.data.order.row_version ?? undefined,
      })}`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ chuyá»ƒn Ä‘Æ¡n hÃ ng sang báº¿p.'));
    },
  });

  async function handleDispatch() {
    const confirmed = await confirmAction({
      title: `Chuyá»ƒn báº¿p Ä‘Æ¡n hÃ ng #${resolvedOrderId ?? ''}`,
      content: 'Thao tÃ¡c nÃ y gá»­i tráº¡ng thÃ¡i Ä‘Æ¡n hÃ ng hiá»‡n táº¡i sang phiáº¿u báº¿p. Chá»‰ tiáº¿p tá»¥c khi cÃ¡c dÃ²ng mÃ³n Ä‘Ã£ sáºµn sÃ ng Ä‘á»ƒ cháº¿ biáº¿n.',
      okText: 'Chuyá»ƒn báº¿p',
    });

    if (confirmed) {
      await dispatchMutation.mutateAsync();
    }
  }

  async function handleUpdateItem(values: EditItemValues) {
    await updateItemMutation.mutateAsync(values);
  }

  async function handleStatusTransition(status: StaffOrderItemTransitionStatus) {
    if (!selectedItem) {
      return;
    }

    const confirmed = await confirmAction({
      title: `Chuyá»ƒn dÃ²ng #${selectedItem.order_item_id} sang ${translateUiCode(status)}`,
      content: 'Chá»‰ dÃ¹ng bÆ°á»›c chuyá»ƒn vÃ²ng Ä‘á»i há»£p lá»‡ tiáº¿p theo cho dÃ²ng mÃ³n Ä‘Ã£ chá»n.',
      okText: status === 'Cancelled' ? 'Há»§y mÃ³n' : `ÄÃ¡nh dáº¥u ${translateUiCode(status)}`,
      danger: status === 'Cancelled',
    });

    if (confirmed) {
      await updateItemStatusMutation.mutateAsync(status);
    }
  }

  const itemConcurrencyMissing = orderItems.some((item) => !item.row_version) || (orderItems.length > 0 && !orderDetailQuery.data?.data.order.row_version);
  const selectedItemEditable = canEditOrderItem(selectedItem?.status);
  const allowedStatusTransitions = getAllowedOrderItemStatuses(selectedItem?.status);

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="VÃ²ng Ä‘á»i Ä‘Æ¡n hÃ ng"
        title="MÃ n hÃ¬nh Ä‘Æ¡n hÃ ng Ä‘ang phá»¥c vá»¥"
        description="XÃ¡c Ä‘á»‹nh Ä‘Æ¡n hÃ ng hiá»‡n táº¡i tá»« sÆ¡ Ä‘á»“ bÃ n hoáº·c ngá»¯ cáº£nh Ä‘áº·t bÃ n, táº¡o khi cáº§n, chá»‰nh sá»­a dÃ²ng mÃ³n vá»›i row-version an toÃ n rá»“i chuyá»ƒn sang báº¿p."
        extra={
          <>
            <Button onClick={() => orderDetailQuery.refetch()} disabled={!resolvedOrderId} loading={orderDetailQuery.isFetching}>
              LÃ m má»›i Ä‘Æ¡n hÃ ng
            </Button>
            <Button type="primary" onClick={handleDispatch} disabled={!resolvedOrderId} loading={dispatchMutation.isPending}>
              Chuyá»ƒn sang báº¿p
            </Button>
          </>
        }
      />

      {orderDetailQuery.isLoading || activeOrderByTableQuery.isLoading || activeOrderByReservationQuery.isLoading ? (
        <InlineLoading tip="Äang xÃ¡c Ä‘á»‹nh Ä‘Æ¡n hÃ ng hiá»‡n táº¡i..." />
      ) : null}
      {orderDetailQuery.error ? <InlineError message={formatApiError(orderDetailQuery.error, 'KhÃ´ng thá»ƒ táº£i chi tiáº¿t Ä‘Æ¡n hÃ ng.')} /> : null}

      {!resolvedOrderId ? (
        <Card title="ChÆ°a cÃ³ Ä‘Æ¡n hÃ ng Ä‘ang phá»¥c vá»¥">
          <Space orientation="vertical" size={16} style={{ width: '100%' }}>
            <Typography.Paragraph type="secondary" style={{ marginBottom: 0 }}>
              Backend chÆ°a tráº£ vá» Ä‘Æ¡n hÃ ng Ä‘ang phá»¥c vá»¥ cho bÃ n hoáº·c Ä‘áº·t bÃ n hiá»‡n táº¡i. Náº¿u khÃ¡ch Ä‘Ã£ báº¯t Ä‘áº§u dÃ¹ng dá»‹ch vá»¥,
              hÃ£y táº¡o Ä‘Æ¡n Ä‘áº§u tiÃªn táº¡i Ä‘Ã¢y Ä‘á»ƒ tiáº¿p tá»¥c luá»“ng phá»¥c vá»¥.
            </Typography.Paragraph>
            <Descriptions bordered size="small" column={1}>
              <Descriptions.Item label="BÃ n">{tableId ?? 'Thiáº¿u'}</Descriptions.Item>
              <Descriptions.Item label="Äáº·t bÃ n">{reservationId ?? 'Thiáº¿u'}</Descriptions.Item>
              <Descriptions.Item label="PhiÃªn báº£n Ä‘áº·t bÃ n">
                {reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? 'Thiáº¿u'}
              </Descriptions.Item>
            </Descriptions>
            {reservationDetailQuery.error ? (
              <InlineError message={formatApiError(reservationDetailQuery.error, 'KhÃ´ng thá»ƒ táº£i chi tiáº¿t Ä‘áº·t bÃ n Ä‘á»ƒ táº¡o Ä‘Æ¡n hÃ ng.')} />
            ) : null}
            <Form<CreateOrderValues> layout="vertical" onFinish={(values) => createOrderMutation.mutate(values)}>
              <Form.Item name="notes" label="Ghi chÃº Ä‘Æ¡n hÃ ng">
                <Input.TextArea rows={3} placeholder="Ghi chÃº phá»¥c vá»¥ náº¿u cáº§n" />
              </Form.Item>
              <Button type="primary" htmlType="submit" loading={createOrderMutation.isPending}>
                Táº¡o Ä‘Æ¡n hÃ ng
              </Button>
            </Form>
          </Space>
        </Card>
      ) : (
        <Card title={`ÄÆ¡n hÃ ng #${resolvedOrderId}`}>
          {!orderDetailQuery.data ? (
            <EmptyBlock
            title="ChÆ°a cÃ³ chi tiáº¿t Ä‘Æ¡n hÃ ng"
            description="ÄÃ£ xÃ¡c Ä‘á»‹nh Ä‘Æ°á»£c Ä‘Æ¡n hÃ ng nhÆ°ng pháº§n Ä‘á»c chi tiáº¿t váº«n chÆ°a táº£i xong."
            />
          ) : (
            <Space orientation="vertical" size={16} style={{ width: '100%' }}>
              <Descriptions bordered size="small" column={2}>
                <Descriptions.Item label="Tráº¡ng thÃ¡i">
                  <StatusChip label={orderDetailQuery.data.data.order.status} tone={orderTone(orderDetailQuery.data.data.order.status)} />
                </Descriptions.Item>
                <Descriptions.Item label="Thanh toÃ¡n">
                  <StatusChip label={orderDetailQuery.data.data.order.payment_status ?? 'Pending'} tone={orderTone(orderDetailQuery.data.data.order.payment_status)} />
                </Descriptions.Item>
                <Descriptions.Item label="Äáº·t bÃ n">
                  {orderDetailQuery.data.data.reservation?.reservation_code ?? 'KhÃ¡ch vÃ£ng lai'}
                </Descriptions.Item>
                <Descriptions.Item label="KhÃ¡ch">
                  {orderDetailQuery.data.data.customer?.full_name ?? orderDetailQuery.data.data.customer?.phone ?? 'KhÃ¡ch vÃ£ng lai'}
                </Descriptions.Item>
              </Descriptions>

              {itemConcurrencyMissing ? (
                <Alert
                  type="warning"
                  showIcon
                  title="Cáº£nh bÃ¡o Ä‘á»“ng thá»i trÃªn dÃ²ng mÃ³n"
                  description="Ãt nháº¥t má»™t dÃ²ng mÃ³n Ä‘ang thiáº¿u row_version má»›i nháº¥t. Váº«n cÃ³ thá»ƒ thÃªm mÃ³n vÃ  chuyá»ƒn báº¿p, nhÆ°ng thao tÃ¡c sá»­a hoáº·c Ä‘á»•i tráº¡ng thÃ¡i theo tá»«ng dÃ²ng sáº½ bá»‹ khÃ³a cho tá»›i khi táº£i láº¡i chi tiáº¿t Ä‘Æ¡n hÃ ng vá»›i Ä‘áº§y Ä‘á»§ trÆ°á»ng Ä‘á»“ng thá»i."
                />
              ) : null}

              <Space orientation="vertical" size={12} style={{ width: '100%' }}>
                <Typography.Text strong>Danh sÃ¡ch mÃ³n</Typography.Text>
                {orderDetailQuery.data.data.items.length === 0 ? (
                  <EmptyBlock
                    title="ChÆ°a cÃ³ dÃ²ng mÃ³n nÃ o"
                    description="ThÃªm mÃ³n tá»« cá»™t bÃªn pháº£i Ä‘á»ƒ báº¯t Ä‘áº§u phá»¥c vá»¥ cho Ä‘Æ¡n hÃ ng hiá»‡n táº¡i."
                  />
                ) : (
                  orderDetailQuery.data.data.items.map((item) => (
                    <Card
                      key={item.order_item_id}
                      size="small"
                      style={item.order_item_id === selectedItemId ? { background: '#faf7f2', borderColor: '#d6b27d' } : undefined}
                      extra={(
                        <Space wrap size={8}>
                          <StatusChip label={item.status} tone={orderTone(item.status)} />
                          <Typography.Text strong>{formatMoney(item.line_total, item.currency)}</Typography.Text>
                          <Button
                            size="small"
                            type={item.order_item_id === selectedItemId ? 'primary' : 'default'}
                            onClick={() => setSelectedItemId(item.order_item_id)}
                          >
                            {item.order_item_id === selectedItemId ? 'Äang chá»n' : 'Xem'}
                          </Button>
                        </Space>
                      )}
                    >
                      <Space orientation="vertical" size={4} style={{ width: '100%' }}>
                        <Typography.Text strong>
                          {`${item.item?.name ?? item.item_name_snapshot ?? `MÃ³n #${item.item_id}`} x ${item.quantity}`}
                        </Typography.Text>
                        <Typography.Text type="secondary">
                          {`PhiÃªn báº£n ${item.row_version ?? 'thiáº¿u'} | ${item.notes ?? 'KhÃ´ng cÃ³ ghi chÃº'}`}
                        </Typography.Text>
                      </Space>
                    </Card>
                  ))
                )}
              </Space>

              <Descriptions bordered size="small" column={2}>
                <Descriptions.Item label="Táº¡m tÃ­nh">
                  {formatMoney(orderDetailQuery.data.data.financial_summary.subtotal, orderDetailQuery.data.data.financial_summary.currency ?? 'VND')}
                </Descriptions.Item>
                <Descriptions.Item label="Tá»•ng cáº§n thu">
                  {formatMoney(orderDetailQuery.data.data.financial_summary.total_due, orderDetailQuery.data.data.financial_summary.currency ?? 'VND')}
                </Descriptions.Item>
                <Descriptions.Item label="ÄÃ£ thu">
                  {formatMoney(orderDetailQuery.data.data.financial_summary.paid, orderDetailQuery.data.data.financial_summary.currency ?? 'VND')}
                </Descriptions.Item>
                <Descriptions.Item label="CÃ²n thiáº¿u">
                  {formatMoney(orderDetailQuery.data.data.financial_summary.outstanding, orderDetailQuery.data.data.financial_summary.currency ?? 'VND')}
                </Descriptions.Item>
              </Descriptions>
            </Space>
          )}
        </Card>
      )}
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card title="ThÃªm mÃ³n">
        <Space orientation="vertical" size={16} style={{ width: '100%' }}>
          <Input.Search
            allowClear
            placeholder="TÃ¬m mÃ³n"
            onSearch={setMenuSearch}
          />
          {menuQuery.isLoading ? <InlineLoading tip="Äang táº£i danh má»¥c mÃ³n..." /> : null}
          {menuQuery.error ? <InlineError message={formatApiError(menuQuery.error, 'KhÃ´ng thá»ƒ táº£i danh má»¥c mÃ³n cho nhÃ¢n viÃªn.')} /> : null}
          <Form<AddItemValues>
            layout="vertical"
            form={addItemForm}
            onFinish={(values) => addItemMutation.mutate(values)}
            initialValues={{ qty: 1 }}
          >
            <Form.Item name="menu_item_id" label="MÃ³n" rules={[{ required: true, message: 'Chá»n má»™t mÃ³n.' }]}>
              <Select
                showSearch
                placeholder="Chá»n mÃ³n"
                optionFilterProp="label"
                options={(menuQuery.data?.data ?? []).map((item) => ({
                  label: `${item.name} | ${formatMoney(item.price.amount, item.price.currency ?? 'VND')}`,
                  value: item.item_id,
                }))}
              />
            </Form.Item>
            <Form.Item name="qty" label="Sá»‘ lÆ°á»£ng" rules={[{ required: true, message: 'Nháº­p sá»‘ lÆ°á»£ng mÃ³n.' }]}>
              <InputNumber min={1} max={30} style={{ width: '100%' }} />
            </Form.Item>
            <Form.Item name="note" label="Ghi chÃº">
              <Input.TextArea rows={3} placeholder="Ghi chÃº cho báº¿p náº¿u cáº§n" />
            </Form.Item>
            <Button
              type="primary"
              htmlType="submit"
              disabled={!resolvedOrderId}
              loading={addItemMutation.isPending}
              block
            >
              ThÃªm mÃ³n vÃ o Ä‘Æ¡n hÃ ng
            </Button>
          </Form>
        </Space>
      </Card>

      <Card title="DÃ²ng mÃ³n Ä‘ang chá»n">
        <Form<EditItemValues> layout="vertical" form={editItemForm} onFinish={handleUpdateItem}>
          {!selectedItem ? (
            <EmptyBlock
              title="ChÆ°a chá»n dÃ²ng mÃ³n"
              description="Chá»n má»™t dÃ²ng mÃ³n Ä‘á»ƒ sá»­a sá»‘ lÆ°á»£ng, ghi chÃº hoáº·c chuyá»ƒn qua cÃ¡c bÆ°á»›c cá»§a báº¿p vÃ  phá»¥c vá»¥."
            />
          ) : (
            <Space orientation="vertical" size={16} style={{ width: '100%' }}>
              <Descriptions bordered size="small" column={1}>
                <Descriptions.Item label="DÃ²ng mÃ³n">
                  #{selectedItem.order_item_id}
                </Descriptions.Item>
                <Descriptions.Item label="MÃ³n">
                  {selectedItem.item?.name ?? selectedItem.item_name_snapshot ?? `MÃ³n #${selectedItem.item_id}`}
                </Descriptions.Item>
                <Descriptions.Item label="Tráº¡ng thÃ¡i">
                  <StatusChip label={selectedItem.status} tone={orderTone(selectedItem.status)} />
                </Descriptions.Item>
                <Descriptions.Item label="PhiÃªn báº£n Ä‘Æ¡n hÃ ng">
                  {orderDetailQuery.data?.data.order.row_version ?? 'Thiáº¿u'}
                </Descriptions.Item>
                <Descriptions.Item label="PhiÃªn báº£n dÃ²ng mÃ³n">
                  {selectedItem.row_version ?? 'Thiáº¿u'}
                </Descriptions.Item>
              </Descriptions>

              <Form.Item name="qty" label="Sá»‘ lÆ°á»£ng" rules={[{ required: true, message: 'Nháº­p sá»‘ lÆ°á»£ng mÃ³n.' }]}>
                <InputNumber min={1} max={30} style={{ width: '100%' }} disabled={!selectedItemEditable || itemConcurrencyMissing} />
              </Form.Item>
              <Form.Item name="note" label="Ghi chÃº">
                <Input.TextArea rows={3} placeholder="Ghi chÃº cho báº¿p hoáº·c phá»¥c vá»¥" disabled={!selectedItemEditable || itemConcurrencyMissing} />
              </Form.Item>
              <Button
                type="primary"
                htmlType="submit"
                block
                disabled={!selectedItemEditable || itemConcurrencyMissing}
                loading={updateItemMutation.isPending}
              >
                LÆ°u thay Ä‘á»•i dÃ²ng mÃ³n
              </Button>

              <Space wrap>
                {allowedStatusTransitions.length === 0 ? (
                  <Typography.Text type="secondary">
                    DÃ²ng mÃ³n nÃ y Ä‘Ã£ á»Ÿ tráº¡ng thÃ¡i cuá»‘i vÃ  khÃ´ng thá»ƒ chuyá»ƒn tiáº¿p.
                  </Typography.Text>
                ) : (
                  allowedStatusTransitions.map((status) => (
                    <Button
                      key={status}
                      onClick={() => handleStatusTransition(status)}
                      danger={status === 'Cancelled'}
                      disabled={itemConcurrencyMissing}
                      loading={updateItemStatusMutation.isPending}
                    >
                      ÄÃ¡nh dáº¥u {translateUiCode(status)}
                    </Button>
                  ))
                )}
              </Space>
            </Space>
          )}
        </Form>
      </Card>

      <Card title="BÆ°á»›c chuyá»ƒn tiáº¿p tiáº¿p theo">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Typography.Text type="secondary">
            Tiáº¿p tá»¥c dÃ¹ng ngá»¯ cáº£nh tá»« route vÃ  store Ä‘á»ƒ nhÃ¢n viÃªn khÃ´ng pháº£i nháº­p láº¡i cÃ¡c ID.
          </Typography.Text>
          <Button
            type="primary"
            disabled={!resolvedOrderId}
            onClick={() =>
              navigate(`/kitchen?${buildJourneySearch({
                source: 'order',
                tableId: tableId ?? undefined,
                reservationId: reservationId ?? undefined,
                reservationRowVersion: reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? undefined,
                orderId: resolvedOrderId ?? undefined,
                orderRowVersion: orderDetailQuery.data?.data.order.row_version ?? undefined,
              })}`)
            }
          >
            Má»Ÿ mÃ n hÃ¬nh báº¿p
          </Button>
          <Button
            disabled={!resolvedOrderId}
            onClick={() =>
              navigate(`/checkout?${buildJourneySearch({
                source: 'order',
                tableId: tableId ?? undefined,
                reservationId: reservationId ?? undefined,
                reservationRowVersion: reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? undefined,
                orderId: resolvedOrderId ?? undefined,
                orderRowVersion: orderDetailQuery.data?.data.order.row_version ?? undefined,
              })}`)
            }
          >
            Má»Ÿ thanh toÃ¡n
          </Button>
          <Button
            onClick={() =>
              navigate(`/tables?${buildJourneySearch({
                source: journey.source ?? 'order',
                tableId: tableId ?? undefined,
                reservationId: reservationId ?? undefined,
                reservationRowVersion: reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? undefined,
                orderId: resolvedOrderId ?? undefined,
                orderRowVersion: orderDetailQuery.data?.data.order.row_version ?? undefined,
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

