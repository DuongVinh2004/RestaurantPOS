import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  Card,
  Descriptions,
  Form,
  Input,
  InputNumber,
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
} from '../../../../shared/api/staff-api';
import { formatApiError, isApiStatus } from '../../../../shared/api/errors';
import { formatMoney } from '../../../../shared/utils/format';
import { buildJourneySearch } from '../../../../app/router/journey';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import {
  buildOrderContextLabel,
  buildReservationContextLabel,
  buildTableContextLabel,
} from '../../../journey-labels';
import {
  getReservationGuestLabel,
  isReservationSnapshotOnlyGuest,
  RESERVATION_SNAPSHOT_GUEST_LABEL,
} from '../../../../domains/reservations/reservation-guest';
import {
  getPrimaryReservationTableId,
  getReservationTableIds,
  getReservationTableLabel,
} from '../../../../domains/reservations/reservation-tables';
import { orderTone } from '../../../../shared/status/status';
import { translateUiCode } from '../../../../shared/utils/translation';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { toast } from '../../../../shared/ui/feedback/toast';
import {
  ApiStateBlock,
  ConflictState,
  EmptyBlock,
  InlineLoading,
  InlineState,
} from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { useFlowStore } from '../../../../app/store/flow-store';
import { useJourneyContext } from '../../../../app/router/useJourneyContext';
import { useConfirmAction } from '../../../../shared/hooks/useConfirmAction';
import { canEditOrderItem, getAllowedOrderItemStatuses, type StaffOrderItemTransitionStatus } from '../../../../domains/orders/order-item-workflow';

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
type OrderDetailEnvelope = Awaited<ReturnType<typeof getOrderDetail>>;
type OrderMutationEnvelope = Awaited<ReturnType<typeof updateOrderItem>>;
type OrderLineItem = OrderDetailData['items'][number];
type MenuItemData = Awaited<ReturnType<typeof listMenuItems>>['data'][number];
type MenuDraft = {
  qty: number;
  note?: string;
};
type AddItemMutationResult = {
  action: 'added' | 'merged';
  orderEnvelope: OrderMutationEnvelope;
  orderItemId?: number;
};

function mergeOrderMutationEnvelopeIntoDetail(
  current: OrderDetailEnvelope | undefined,
  orderEnvelope: OrderMutationEnvelope,
): OrderDetailEnvelope | undefined {
  if (!current) {
    return current;
  }

  const updatedOrder = orderEnvelope.data;
  const updatedItems = updatedOrder.items ?? current.data.items;
  const updatedTotals = updatedOrder.totals;

  return {
    ...current,
    data: {
      ...current.data,
      order: {
        ...current.data.order,
        ...updatedOrder,
        items: updatedOrder.items ?? current.data.order.items,
        totals: updatedOrder.totals ?? current.data.order.totals,
      },
      items: updatedItems,
      financial_summary: updatedTotals
        ? {
          ...current.data.financial_summary,
          subtotal: updatedTotals.subtotal ?? current.data.financial_summary.subtotal,
          discount: updatedTotals.discount ?? current.data.financial_summary.discount,
          total_due: updatedTotals.total_due ?? current.data.financial_summary.total_due,
          paid: updatedTotals.paid ?? current.data.financial_summary.paid,
          deposit_applied: updatedTotals.deposit_applied ?? current.data.financial_summary.deposit_applied,
          deposit_net: updatedTotals.deposit_net ?? current.data.financial_summary.deposit_net,
          final_paid: updatedTotals.final_paid ?? current.data.financial_summary.final_paid,
          outstanding: updatedTotals.outstanding ?? current.data.financial_summary.outstanding,
          currency: updatedTotals.currency ?? current.data.financial_summary.currency,
          payment_status: updatedOrder.payment_status ?? current.data.financial_summary.payment_status,
        }
        : current.data.financial_summary,
    },
  };
}

function normalizeOrderNote(note: string | null | undefined): string {
  return (note ?? '').trim();
}

function getOrderLineName(item: OrderLineItem): string {
  return item.item?.name ?? item.item_name_snapshot ?? `Món #${item.item_id}`;
}

function getMenuInitial(name: string): string {
  return name.trim().charAt(0).toUpperCase() || 'M';
}

function normalizeMenuQty(value: number | null): number {
  if (!value || Number.isNaN(value)) {
    return 1;
  }

  return Math.min(30, Math.max(1, Math.trunc(value)));
}

function canMergeIntoOrderLine(item: OrderLineItem): item is OrderLineItem & { row_version: number } {
  return canEditOrderItem(item.status) && item.row_version !== null && item.row_version !== undefined;
}

function canMergeWithinPaymentStatus(paymentStatus: string | null | undefined): boolean {
  const normalizedStatus = (paymentStatus ?? '').toLowerCase();

  return normalizedStatus !== 'success' && normalizedStatus !== 'partial' && normalizedStatus !== 'paid';
}

function findMergeableOrderLine(
  orderItems: OrderLineItem[],
  menuItemId: number,
  note: string | null | undefined,
  paymentStatus: string | null | undefined,
): (OrderLineItem & { row_version: number }) | null {
  if (!canMergeWithinPaymentStatus(paymentStatus)) {
    return null;
  }

  const normalizedNote = normalizeOrderNote(note);

  for (const item of orderItems) {
    if (
      item.item_id === menuItemId
      && canMergeIntoOrderLine(item)
      && normalizeOrderNote(item.notes) === normalizedNote
    ) {
      return item;
    }
  }

  return null;
}

export function OrderWorkspacePage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const message = toast;
  const confirmAction = useConfirmAction();
  const journey = useJourneyContext();
  const setTableContext = useFlowStore((state) => state.setTableContext);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const setOrderContext = useFlowStore((state) => state.setOrderContext);
  const [menuSearch, setMenuSearch] = useState('');
  const [selectedItemId, setSelectedItemId] = useState<number | null>(null);
  const [menuDrafts, setMenuDrafts] = useState<Record<number, MenuDraft>>({});
  const [routeOrderRecoveryEnabled, setRouteOrderRecoveryEnabled] = useState(false);
  const [editItemForm] = Form.useForm<EditItemValues>();

  const routeTableIds = useMemo(() => journey.tableIds ?? [], [journey.tableIds]);
  const tableId = journey.tableId ?? routeTableIds[0] ?? null;
  const reservationId = journey.reservationId ?? null;
  const reservationRowVersion = journey.reservationRowVersion ?? null;
  const routeOrderId = journey.orderId ?? null;

  useEffect(() => {
    setRouteOrderRecoveryEnabled(false);
  }, [routeOrderId]);

  const activeOrderByTableQuery = useQuery({
    queryKey: ['active-order-by-table', tableId],
    queryFn: () => getActiveOrderByTable(tableId as number),
    enabled: !!tableId && (!routeOrderId || routeOrderRecoveryEnabled),
    retry: (failureCount, error) => !isApiStatus(error, 404) && failureCount < 1,
  });

  const activeOrderByReservationQuery = useQuery({
    queryKey: ['active-order-by-reservation', reservationId],
    queryFn: () => getActiveOrderByReservation(reservationId as number),
    enabled: !!reservationId && !activeOrderByTableQuery.data && (!routeOrderId || routeOrderRecoveryEnabled),
    retry: (failureCount, error) => !isApiStatus(error, 404) && failureCount < 1,
  });
  const activeOrderLookupPending = activeOrderByTableQuery.isFetching || activeOrderByReservationQuery.isFetching;

  const resolvedOrderId = routeOrderId && !routeOrderRecoveryEnabled
    ? routeOrderId
    : activeOrderByTableQuery.data?.data.order.order_id
      ?? activeOrderByReservationQuery.data?.data.order.order_id
      ?? null;
  const activeOrderRowVersion = activeOrderByTableQuery.data?.data.order.row_version
    ?? activeOrderByReservationQuery.data?.data.order.row_version
    ?? null;

  const orderDetailQuery = useQuery({
    queryKey: ['order-detail', resolvedOrderId],
    queryFn: () => getOrderDetail(resolvedOrderId as number),
    enabled: !!resolvedOrderId,
    retry: (failureCount, error) => !isApiStatus(error, 404) && failureCount < 1,
  });
  const resolvedOrderRowVersion = routeOrderId && !routeOrderRecoveryEnabled
    ? journey.orderRowVersion
    : orderDetailQuery.data?.data.order.row_version
      ?? activeOrderRowVersion
      ?? journey.orderRowVersion
      ?? null;

  useEffect(() => {
    if (!routeOrderId || routeOrderRecoveryEnabled) {
      return;
    }

    if (isApiStatus(orderDetailQuery.error, 404)) {
      setRouteOrderRecoveryEnabled(true);
    }
  }, [orderDetailQuery.error, routeOrderId, routeOrderRecoveryEnabled]);

  useEffect(() => {
    if (!resolvedOrderId) {
      return;
    }

    setOrderContext({
      orderId: resolvedOrderId,
      orderRowVersion: resolvedOrderRowVersion ?? null,
      label: buildOrderContextLabel(resolvedOrderId),
      source: journey.source ?? 'order',
    });
  }, [
    journey.source,
    resolvedOrderId,
    resolvedOrderRowVersion,
    setOrderContext,
  ]);

  const reservationDetailQuery = useQuery({
    queryKey: ['order-reservation-detail', reservationId],
    queryFn: () => getReservationDetail(reservationId as number),
    enabled: !!reservationId && !resolvedOrderId && !activeOrderLookupPending,
  });

  useEffect(() => {
    if (!routeOrderId || !routeOrderRecoveryEnabled || !resolvedOrderId || resolvedOrderId === routeOrderId) {
      return;
    }

    navigate(`${staffRoutePaths.ops.orders}?${buildJourneySearch({
      source: journey.source ?? 'order',
      tableId: tableId ?? undefined,
      tableIds: routeTableIds.length > 0 ? routeTableIds : undefined,
      reservationId: reservationId ?? undefined,
      reservationRowVersion: reservationRowVersion ?? undefined,
      orderId: resolvedOrderId,
      orderRowVersion: resolvedOrderRowVersion ?? undefined,
    })}`, { replace: true });
  }, [
    journey.source,
    navigate,
    reservationId,
    reservationRowVersion,
    resolvedOrderId,
    resolvedOrderRowVersion,
    routeOrderId,
    routeOrderRecoveryEnabled,
    routeTableIds,
    tableId,
  ]);

  const reservationSummary = orderDetailQuery.data?.data.reservation ?? reservationDetailQuery.data?.data ?? null;
  const resolvedTableIds = useMemo(
    () => {
      if (routeTableIds.length > 0) {
        return routeTableIds;
      }

      const orderTables = getReservationTableIds(orderDetailQuery.data?.data);
      if (orderTables.length > 0) {
        return orderTables;
      }

      return getReservationTableIds(reservationSummary);
    },
    [orderDetailQuery.data?.data, reservationSummary, routeTableIds],
  );
  const primaryTableId = tableId ?? getPrimaryReservationTableId(orderDetailQuery.data?.data) ?? getPrimaryReservationTableId(reservationSummary) ?? null;
  const reservationTableLabel = getReservationTableLabel(orderDetailQuery.data?.data ?? reservationSummary);
  const customerLabel = reservationSummary
    ? getReservationGuestLabel(
      reservationSummary,
      orderDetailQuery.data?.data.customer?.full_name ?? orderDetailQuery.data?.data.customer?.phone ?? 'Khách vãng lai',
    )
    : orderDetailQuery.data?.data.customer?.full_name
      ?? orderDetailQuery.data?.data.customer?.phone
      ?? 'Khách vãng lai';
  const isSnapshotOnlyGuest = isReservationSnapshotOnlyGuest(reservationSummary);

  useEffect(() => {
    if (primaryTableId) {
      setTableContext({
        tableId: primaryTableId,
        label: buildTableContextLabel(reservationTableLabel, primaryTableId),
        source: journey.source ?? 'order',
      });
    }

    if (reservationId) {
      setReservationContext({
        reservationId,
        reservationRowVersion: reservationSummary?.row_version ?? reservationRowVersion,
        label: buildReservationContextLabel(
          reservationSummary?.reservation_code ?? null,
          reservationId,
        ),
        source: journey.source ?? 'order',
      });
    }
  }, [
    journey.source,
    primaryTableId,
    reservationId,
    reservationRowVersion,
    reservationSummary?.reservation_code,
    reservationSummary?.row_version,
    reservationTableLabel,
    setReservationContext,
    setTableContext,
  ]);

  const orderItems = useMemo(
    () => orderDetailQuery.data?.data.items ?? [],
    [orderDetailQuery.data?.data.items],
  );
  const currentPaymentStatus = orderDetailQuery.data?.data.order.payment_status
    ?? orderDetailQuery.data?.data.financial_summary.payment_status
    ?? null;
  const paymentMergeLocked = !canMergeWithinPaymentStatus(currentPaymentStatus);
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

  const menuQuery = useQuery({
    queryKey: ['staff-menu-items', menuSearch],
    queryFn: () => listMenuItems({
      q: menuSearch.trim() || undefined,
      per_page: 32,
      service_time: new Date().toISOString(),
    }),
  });
  const menuItems = useMemo(
    () => menuQuery.data?.data ?? [],
    [menuQuery.data?.data],
  );
  const menuCategorySummary = useMemo(
    () => {
      const categoryNames = new Set<string>();
      menuItems.forEach((item) => {
        if (item.category_name) {
          categoryNames.add(item.category_name);
        }
      });

      return Array.from(categoryNames).slice(0, 4);
    },
    [menuItems],
  );

  function getMenuDraft(itemId: number): MenuDraft {
    return menuDrafts[itemId] ?? { qty: 1, note: '' };
  }

  function updateMenuDraft(itemId: number, patch: Partial<MenuDraft>) {
    setMenuDrafts((currentDrafts) => ({
      ...currentDrafts,
      [itemId]: {
        qty: currentDrafts[itemId]?.qty ?? 1,
        note: currentDrafts[itemId]?.note ?? '',
        ...patch,
      },
    }));
  }

  function clearMenuDraft(itemId: number) {
    setMenuDrafts((currentDrafts) => {
      const nextDrafts = { ...currentDrafts };
      delete nextDrafts[itemId];
      return nextDrafts;
    });
  }

  function syncOrderMutationEnvelope(orderEnvelope: OrderMutationEnvelope) {
    const orderId = orderEnvelope.data.order_id;

    queryClient.setQueryData<OrderDetailEnvelope | undefined>(
      ['order-detail', orderId],
      (current) => mergeOrderMutationEnvelopeIntoDetail(current, orderEnvelope),
    );
    queryClient.setQueryData<OrderDetailEnvelope | undefined>(
      ['checkout-order-detail', orderId],
      (current) => mergeOrderMutationEnvelopeIntoDetail(current, orderEnvelope),
    );

    setOrderContext({
      orderId,
      orderRowVersion: orderEnvelope.data.row_version ?? null,
      label: buildOrderContextLabel(orderId),
      source: journey.source ?? 'order',
    });
  }

  const createOrderMutation = useMutation({
    mutationFn: async (values: CreateOrderValues) => {
      const effectiveTableId = primaryTableId;
      const effectiveReservationId = reservationId;
      const effectiveRowVersion = reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? null;

      if (!effectiveTableId || !effectiveReservationId || !effectiveRowVersion) {
        throw new Error('Tạo đơn cần có mã bàn, mã đặt bàn và phiên bản đặt bàn hiện tại.');
      }

      return createTableOrder(effectiveTableId, {
        reservation_id: effectiveReservationId,
        notes: values.notes?.trim() || null,
        row_version: effectiveRowVersion,
      });
    },
    onSuccess: async (orderEnvelope) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['table-board'] }),
        queryClient.invalidateQueries({ queryKey: ['active-order-by-table', primaryTableId] }),
        queryClient.invalidateQueries({ queryKey: ['active-order-by-reservation', reservationId] }),
      ]);
      const orderId = orderEnvelope.data.order_id;
      setOrderContext({
        orderId,
        orderRowVersion: orderEnvelope.data.row_version ?? null,
        label: buildOrderContextLabel(orderId),
        source: 'order',
      });
      message.success(`Đã tạo đơn hàng #${orderId}.`);
      navigate(`${staffRoutePaths.ops.orders}?${buildJourneySearch({
        source: journey.source ?? 'order',
        tableId: primaryTableId ?? undefined,
        tableIds: resolvedTableIds,
        reservationId: reservationId ?? undefined,
        reservationRowVersion: reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? undefined,
        orderId,
        orderRowVersion: orderEnvelope.data.row_version ?? undefined,
      })}`, { replace: true });
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể tạo đơn hàng cho bàn và đặt bàn đã chọn.'));
    },
  });

  const addItemMutation = useMutation({
    mutationFn: async (values: AddItemValues): Promise<AddItemMutationResult> => {
      const orderId = resolvedOrderId;
      const rowVersion = orderDetailQuery.data?.data.order.row_version;

      if (!orderId || !rowVersion) {
        throw new Error('Hãy tải lại đơn hàng hiện tại để lấy phiên bản mới nhất trước khi thao tác.');
      }

      const mergeTarget = findMergeableOrderLine(orderItems, values.menu_item_id, values.note, currentPaymentStatus);
      if (mergeTarget) {
        const orderEnvelope = await updateOrderItem(orderId, mergeTarget.order_item_id, {
          qty: mergeTarget.quantity + values.qty,
          note: mergeTarget.notes,
          order_row_version: rowVersion,
          row_version: mergeTarget.row_version,
        });

        return {
          action: 'merged',
          orderEnvelope,
          orderItemId: mergeTarget.order_item_id,
        };
      }

      const orderEnvelope = await addOrderItems(orderId, {
        row_version: rowVersion,
        items: [
          {
            menu_item_id: values.menu_item_id,
            qty: values.qty,
            note: values.note?.trim() || null,
          },
        ],
      });
      const newItems = orderEnvelope.data.items ?? [];
      const lastItem = newItems[newItems.length - 1];

      return {
        action: 'added',
        orderEnvelope,
        orderItemId: lastItem?.order_item_id,
      };
    },
    onSuccess: async (result, values) => {
      clearMenuDraft(values.menu_item_id);
      syncOrderMutationEnvelope(result.orderEnvelope);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['order-detail', resolvedOrderId] }),
        queryClient.invalidateQueries({ queryKey: ['checkout-order-detail', resolvedOrderId] }),
      ]);
      if (result.orderItemId) {
        setSelectedItemId(result.orderItemId);
      }
      message.success(
        result.action === 'merged'
          ? 'Đã cộng số lượng vào dòng món chưa khóa.'
          : 'Đã thêm món vào đơn hàng hiện tại.',
      );
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể thêm món vào đơn hàng.'));
    },
  });

  const updateItemMutation = useMutation({
    mutationFn: async (values: EditItemValues) => {
      const orderId = resolvedOrderId;
      const orderRowVersion = orderDetailQuery.data?.data.order.row_version;
      const itemRowVersion = selectedItem?.row_version;

      if (!orderId || !selectedItem || !orderRowVersion || !itemRowVersion) {
        throw new Error('Hãy tải lại chi tiết đơn hàng để có phiên bản mới nhất của đơn và dòng món.');
      }

      return updateOrderItem(orderId, selectedItem.order_item_id, {
        qty: values.qty,
        note: values.note?.trim() || null,
        order_row_version: orderRowVersion,
        row_version: itemRowVersion,
      });
    },
    onSuccess: async (orderEnvelope) => {
      syncOrderMutationEnvelope(orderEnvelope);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['order-detail', resolvedOrderId] }),
        queryClient.invalidateQueries({ queryKey: ['checkout-order-detail', resolvedOrderId] }),
      ]);
      message.success('Đã cập nhật dòng món đã chọn.');
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể cập nhật dòng món đã chọn.'));
    },
  });

  const updateItemStatusMutation = useMutation({
    mutationFn: async (status: StaffOrderItemTransitionStatus) => {
      const orderId = resolvedOrderId;
      const orderRowVersion = orderDetailQuery.data?.data.order.row_version;
      const itemRowVersion = selectedItem?.row_version;

      if (!orderId || !selectedItem || !orderRowVersion || !itemRowVersion) {
        throw new Error('Hãy tải lại chi tiết đơn hàng để có phiên bản mới nhất của đơn và dòng món.');
      }

      return updateOrderItemStatus(orderId, selectedItem.order_item_id, {
        status,
        order_row_version: orderRowVersion,
        row_version: itemRowVersion,
      });
    },
    onSuccess: async (orderEnvelope, status) => {
      syncOrderMutationEnvelope(orderEnvelope);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['order-detail', resolvedOrderId] }),
        queryClient.invalidateQueries({ queryKey: ['checkout-order-detail', resolvedOrderId] }),
        queryClient.invalidateQueries({ queryKey: ['kitchen-stations'] }),
        queryClient.invalidateQueries({ queryKey: ['kitchen-tickets'] }),
      ]);
      message.success(`Đã chuyển dòng món sang trạng thái ${translateUiCode(status)}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể cập nhật trạng thái dòng món.'));
    },
  });

  const dispatchMutation = useMutation({
    mutationFn: async () => {
      if (!resolvedOrderId) {
        throw new Error('Chọn hoặc tạo một đơn hàng đang phục vụ trước khi chuyển bếp.');
      }

      if (resolvedOrderRowVersion === null || resolvedOrderRowVersion === undefined) {
        throw new Error('Hãy tải lại đơn hàng để lấy phiên bản mới nhất trước khi chuyển bếp.');
      }

      return dispatchKitchenOrder(resolvedOrderId, {
        row_version: resolvedOrderRowVersion,
      });
    },
    onSuccess: async (dispatchEnvelope) => {
      const dispatchedStationId = dispatchEnvelope.data[0]?.station?.station_id;
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['kitchen-stations'] }),
        queryClient.invalidateQueries({ queryKey: ['kitchen-tickets'] }),
        queryClient.invalidateQueries({ queryKey: ['order-detail', resolvedOrderId] }),
        queryClient.invalidateQueries({ queryKey: ['checkout-order-detail', resolvedOrderId] }),
      ]);
      message.success('Đã chuyển đơn hàng sang bếp.');
      navigate(`${staffRoutePaths.kitchen.landing}?${buildJourneySearch({
        source: 'order',
        tableId: primaryTableId ?? undefined,
        tableIds: resolvedTableIds,
        reservationId: reservationId ?? undefined,
        reservationRowVersion: reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? undefined,
        orderId: resolvedOrderId ?? undefined,
        orderRowVersion: resolvedOrderRowVersion ?? undefined,
        stationId: dispatchedStationId,
      })}`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể chuyển đơn hàng sang bếp.'));
    },
  });

  async function handleDispatch() {
    const confirmed = await confirmAction({
      title: `Chuyển bếp đơn hàng #${resolvedOrderId ?? ''}`,
      content: 'Thao tác này gửi trạng thái đơn hàng hiện tại sang phiếu bếp. Chỉ tiếp tục khi các dòng món đã sẵn sàng để chế biến.',
      okText: 'Chuyển bếp',
    });

    if (confirmed) {
      await dispatchMutation.mutateAsync();
    }
  }

  async function handleUpdateItem(values: EditItemValues) {
    await updateItemMutation.mutateAsync(values);
  }

  function handleAddMenuItem(item: MenuItemData) {
    const draft = getMenuDraft(item.item_id);
    addItemMutation.mutate({
      menu_item_id: item.item_id,
      qty: normalizeMenuQty(draft.qty),
      note: normalizeOrderNote(draft.note) || undefined,
    });
  }

  async function handleStatusTransition(status: StaffOrderItemTransitionStatus) {
    if (!selectedItem) {
      return;
    }

    const confirmed = await confirmAction({
      title: `Chuyển dòng #${selectedItem.order_item_id} sang ${translateUiCode(status)}`,
      content: 'Chỉ dùng bước chuyển vòng đời hợp lệ tiếp theo cho dòng món đã chọn.',
      okText: status === 'Cancelled' ? 'Hủy món' : `Đánh dấu ${translateUiCode(status)}`,
      danger: status === 'Cancelled',
    });

    if (confirmed) {
      await updateItemStatusMutation.mutateAsync(status);
    }
  }

  const staleRouteOrderRecovered = routeOrderId !== null && routeOrderRecoveryEnabled;
  const itemConcurrencyMissing = orderItems.some((item) => !item.row_version) || (orderItems.length > 0 && !orderDetailQuery.data?.data.order.row_version);
  const selectedItemEditable = canEditOrderItem(selectedItem?.status);
  const allowedStatusTransitions = getAllowedOrderItemStatuses(selectedItem?.status);

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }} className="staff-order-workspace-main">
      <PageHeader
        className="staff-order-page-header"
        eyebrow="Vòng đời đơn hàng"
        title="Workspace đơn hàng đang phục vụ"
        description="Giữ ngữ cảnh bàn, đơn và dòng món trong cùng một nhịp thao tác để thêm món, sửa món và chuyển bếp nhanh hơn."
        extra={
          <>
            <Button onClick={() => orderDetailQuery.refetch()} disabled={!resolvedOrderId} loading={orderDetailQuery.isFetching}>
              Làm mới đơn hàng
            </Button>
            <Button
              type="primary"
              onClick={handleDispatch}
              disabled={!resolvedOrderId || resolvedOrderRowVersion === null || resolvedOrderRowVersion === undefined}
              loading={dispatchMutation.isPending}
            >
              Chuyển sang bếp
            </Button>
          </>
        }
      />

      {orderDetailQuery.isLoading || activeOrderByTableQuery.isLoading || activeOrderByReservationQuery.isLoading ? (
        <InlineLoading tip="Đang xác định đơn hàng hiện tại..." />
      ) : null}
      {orderDetailQuery.error && !(staleRouteOrderRecovered && isApiStatus(orderDetailQuery.error, 404)) ? (
        <ApiStateBlock
          error={orderDetailQuery.error}
          fallback="Không thể tải chi tiết đơn hàng."
          onRetry={() => {
            void orderDetailQuery.refetch();
          }}
        />
      ) : null}

      {staleRouteOrderRecovered && resolvedOrderId && resolvedOrderId !== routeOrderId ? (
        <InlineState
          tone="info"
          eyebrow="Đã tự phục hồi ngữ cảnh"
          title={`Đã khôi phục sang đơn hàng đang phục vụ #${resolvedOrderId}`}
          description="Ngữ cảnh order cũ trên URL không còn hợp lệ. Workspace đã dò lại active order theo bàn hoặc đặt bàn hiện tại."
          className="staff-inline-note"
        />
      ) : null}

      {!resolvedOrderId ? (
        <Card title="Chưa có đơn hàng đang phục vụ">
          <Space orientation="vertical" size={16} style={{ width: '100%' }}>
            <Typography.Paragraph type="secondary" style={{ marginBottom: 0 }}>
              Backend chưa trả về đơn hàng đang phục vụ cho bàn hoặc đặt bàn hiện tại. Nếu khách đã bắt đầu dùng dịch vụ,
              hãy tạo đơn đầu tiên tại đây để tiếp tục luồng phục vụ.
            </Typography.Paragraph>
            <Descriptions bordered size="small" column={1}>
              <Descriptions.Item label="Bàn">{reservationTableLabel}</Descriptions.Item>
              <Descriptions.Item label="Đặt bàn">{reservationId ?? 'Thiếu'}</Descriptions.Item>
              <Descriptions.Item label="Phiên bản đặt bàn">
                {reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? 'Thiếu'}
              </Descriptions.Item>
            </Descriptions>
            {reservationDetailQuery.error ? (
              <ApiStateBlock
                error={reservationDetailQuery.error}
                fallback="Không thể tải chi tiết đặt bàn để tạo đơn hàng."
                onRetry={() => {
                  void reservationDetailQuery.refetch();
                }}
              />
            ) : null}
            <Form<CreateOrderValues> layout="vertical" onFinish={(values) => createOrderMutation.mutate(values)}>
              <Form.Item name="notes" label="Ghi chú đơn hàng">
                <Input.TextArea rows={3} placeholder="Ghi chú phục vụ nếu cần" />
              </Form.Item>
              <Button type="primary" htmlType="submit" loading={createOrderMutation.isPending}>
                Tạo đơn hàng
              </Button>
            </Form>
          </Space>
        </Card>
      ) : (
        <Card title={`Đơn hàng #${resolvedOrderId}`} className="staff-order-current-card">
          {!orderDetailQuery.data ? (
            <EmptyBlock
            title="Chưa có chi tiết đơn hàng"
            description="Đã xác định được đơn hàng nhưng phần đọc chi tiết vẫn chưa tải xong."
            />
          ) : (
            <Space orientation="vertical" size={16} style={{ width: '100%' }}>
              <Descriptions bordered size="small" column={2}>
                <Descriptions.Item label="Trạng thái">
                  <StatusChip label={orderDetailQuery.data.data.order.status} tone={orderTone(orderDetailQuery.data.data.order.status)} />
                </Descriptions.Item>
                <Descriptions.Item label="Thanh toán">
                  <StatusChip label={orderDetailQuery.data.data.order.payment_status ?? 'Pending'} tone={orderTone(orderDetailQuery.data.data.order.payment_status)} />
                </Descriptions.Item>
                <Descriptions.Item label="Đặt bàn">
                  {orderDetailQuery.data.data.reservation?.reservation_code ?? 'Khách vãng lai'}
                </Descriptions.Item>
                <Descriptions.Item label="Bàn">
                  {reservationTableLabel}
                </Descriptions.Item>
                <Descriptions.Item label="Khách">
                  <Space wrap size={8}>
                    <Typography.Text>{customerLabel}</Typography.Text>
                    {isSnapshotOnlyGuest ? (
                      <StatusChip label={RESERVATION_SNAPSHOT_GUEST_LABEL} tone="processing" variant="freshness" />
                    ) : null}
                  </Space>
                </Descriptions.Item>
              </Descriptions>

              {itemConcurrencyMissing ? (
                <ConflictState
                  title="Chi tiết dòng món đang thiếu phiên bản mới nhất"
                  description="Vẫn có thể thêm món và chuyển bếp, nhưng thao tác sửa hoặc đổi trạng thái theo từng dòng sẽ bị khóa cho tới khi tải lại chi tiết đơn hàng."
                  primaryAction={<Button onClick={() => void orderDetailQuery.refetch()}>Tải lại chi tiết</Button>}
                  className="staff-inline-note"
                />
              ) : null}

              <Space orientation="vertical" size={12} style={{ width: '100%' }}>
                <Typography.Text strong>Danh sách món</Typography.Text>
                {orderDetailQuery.data.data.items.length === 0 ? (
                  <EmptyBlock
                    title="Chưa có dòng món nào"
                    description="Thêm món từ cột bên phải để bắt đầu phục vụ cho đơn hàng hiện tại."
                  />
                ) : (
                  orderDetailQuery.data.data.items.map((item) => (
                    <Card
                      key={item.order_item_id}
                      size="small"
                      className={`staff-order-line-card ${item.order_item_id === selectedItemId ? 'staff-order-line-card-selected' : ''}`}
                      extra={(
                        <Space wrap size={8}>
                          <StatusChip label={item.status} tone={orderTone(item.status)} />
                          <Typography.Text strong>{formatMoney(item.line_total, item.currency)}</Typography.Text>
                          <Button
                            size="small"
                            type={item.order_item_id === selectedItemId ? 'primary' : 'default'}
                            onClick={() => setSelectedItemId(item.order_item_id)}
                          >
                            {item.order_item_id === selectedItemId ? 'Đang chọn' : 'Xem'}
                          </Button>
                        </Space>
                      )}
                    >
                      <div className="staff-order-line-body">
                        <div className="staff-order-line-copy">
                          <Typography.Text strong>{getOrderLineName(item)}</Typography.Text>
                          <Typography.Text type="secondary">
                            {item.notes ? `Ghi chú: ${item.notes}` : 'Không có ghi chú bếp'}
                          </Typography.Text>
                        </div>
                        <div className="staff-order-line-meta">
                          <span>{item.quantity} phần</span>
                          <span>{formatMoney(item.unit_price, item.currency)} / phần</span>
                          <span>{item.row_version ? `Cập nhật dòng v${item.row_version}` : 'Cần tải lại dòng'}</span>
                        </div>
                      </div>
                    </Card>
                  ))
                )}
              </Space>

              <Descriptions bordered size="small" column={2}>
                <Descriptions.Item label="Tạm tính">
                  {formatMoney(orderDetailQuery.data.data.financial_summary.subtotal, orderDetailQuery.data.data.financial_summary.currency ?? 'VND')}
                </Descriptions.Item>
                <Descriptions.Item label="Tổng cần thu">
                  {formatMoney(orderDetailQuery.data.data.financial_summary.total_due, orderDetailQuery.data.data.financial_summary.currency ?? 'VND')}
                </Descriptions.Item>
                <Descriptions.Item label="Đã thu">
                  {formatMoney(orderDetailQuery.data.data.financial_summary.paid, orderDetailQuery.data.data.financial_summary.currency ?? 'VND')}
                </Descriptions.Item>
                <Descriptions.Item label="Còn thiếu">
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
    <Space orientation="vertical" size={16} style={{ width: '100%' }} className="staff-order-workspace-side">
      <Card title="Gọi món nhanh" className="staff-order-menu-panel">
        <Space orientation="vertical" size={14} style={{ width: '100%' }}>
          <div className="staff-order-menu-hint">
            Chọn món trực tiếp từ danh sách. Dòng món chưa khóa sẽ được cộng số lượng nếu cùng món và cùng ghi chú bếp.
          </div>
          {paymentMergeLocked ? (
            <div className="staff-order-menu-guard">
              Đơn đã ghi nhận thanh toán. Món gọi thêm sẽ tách thành dòng mới để không lẫn phần đã thu.
            </div>
          ) : null}
          <Input.Search
            allowClear
            placeholder="Tìm món"
            value={menuSearch}
            onChange={(event) => setMenuSearch(event.target.value)}
            onSearch={setMenuSearch}
          />
          {menuCategorySummary.length > 0 ? (
            <div className="staff-order-menu-category-row">
              {menuCategorySummary.map((categoryName) => (
                <span key={categoryName}>{categoryName}</span>
              ))}
            </div>
          ) : null}
          {menuQuery.isLoading ? <InlineLoading tip="Đang tải danh mục món..." /> : null}
          {menuQuery.error ? (
            <ApiStateBlock
              error={menuQuery.error}
              fallback="Không thể tải danh mục món cho nhân viên."
              onRetry={() => {
                void menuQuery.refetch();
              }}
            />
          ) : null}
          {!menuQuery.isLoading && !menuQuery.error && menuItems.length === 0 ? (
            <EmptyBlock
              title="Không tìm thấy món"
              description="Thử đổi từ khóa hoặc kiểm tra danh mục món đang mở bán."
            />
          ) : null}
          <div className="staff-order-menu-list">
            {menuItems.map((item) => {
              const draft = getMenuDraft(item.item_id);
              const mergeTarget = findMergeableOrderLine(orderItems, item.item_id, draft.note, currentPaymentStatus);
              const isAddingThisItem = addItemMutation.isPending && addItemMutation.variables?.menu_item_id === item.item_id;

              return (
                <div key={item.item_id} className={`staff-order-menu-item ${!item.is_available ? 'staff-order-menu-item-disabled' : ''}`}>
                  <div className="staff-order-menu-thumb">
                    {item.img_url ? <img src={item.img_url} alt={item.name} /> : <span>{getMenuInitial(item.name)}</span>}
                  </div>
                  <div className="staff-order-menu-copy">
                    <div className="staff-order-menu-title-row">
                      <Typography.Text strong>{item.name}</Typography.Text>
                      <Typography.Text strong>{formatMoney(item.price.amount, item.price.currency ?? 'VND')}</Typography.Text>
                    </div>
                    <Typography.Text type="secondary">
                      {item.category_name ?? item.code}
                      {mergeTarget ? ` • đang có ${mergeTarget.quantity} phần chưa khóa` : ''}
                    </Typography.Text>
                    {item.description ? (
                      <Typography.Text type="secondary" className="staff-order-menu-description">
                        {item.description}
                      </Typography.Text>
                    ) : null}
                    {!item.is_available ? (
                      <Typography.Text className="staff-order-menu-disabled-reason" type="secondary">
                        Tạm ngừng nhận gọi món ở ca hiện tại.
                      </Typography.Text>
                    ) : null}
                    <Input
                      size="small"
                      placeholder="Ghi chú bếp cho món này"
                      value={draft.note}
                      onChange={(event) => updateMenuDraft(item.item_id, { note: event.target.value })}
                    />
                  </div>
                  <div className="staff-order-menu-actions">
                    <InputNumber
                      aria-label={`Số lượng ${item.name}`}
                      min={1}
                      max={30}
                      value={draft.qty}
                      onChange={(value) => updateMenuDraft(item.item_id, { qty: normalizeMenuQty(value) })}
                    />
                    <Button
                      type={mergeTarget ? 'default' : 'primary'}
                      disabled={!resolvedOrderId || !item.is_available}
                      loading={isAddingThisItem}
                      onClick={() => handleAddMenuItem(item)}
                    >
                      {mergeTarget ? 'Cộng số lượng' : 'Thêm món'}
                    </Button>
                    {!item.is_available ? <StatusChip label="Ngừng bán" tone="warning" variant="severity" /> : null}
                  </div>
                </div>
              );
            })}
          </div>
        </Space>
      </Card>

      <Card title="Dòng món đang chọn" className="staff-order-selected-item-card">
        <Form<EditItemValues> layout="vertical" form={editItemForm} onFinish={handleUpdateItem}>
          {!selectedItem ? (
            <EmptyBlock
              title="Chưa chọn dòng món"
              description="Chọn một dòng món để sửa số lượng, ghi chú hoặc chuyển qua các bước của bếp và phục vụ."
            />
          ) : (
            <Space orientation="vertical" size={16} style={{ width: '100%' }}>
              <Descriptions bordered size="small" column={1}>
                <Descriptions.Item label="Dòng món">
                  #{selectedItem.order_item_id}
                </Descriptions.Item>
                <Descriptions.Item label="Món">
                  {selectedItem.item?.name ?? selectedItem.item_name_snapshot ?? `Món #${selectedItem.item_id}`}
                </Descriptions.Item>
                <Descriptions.Item label="Trạng thái">
                  <StatusChip label={selectedItem.status} tone={orderTone(selectedItem.status)} />
                </Descriptions.Item>
                <Descriptions.Item label="Phiên bản đơn hàng">
                  {orderDetailQuery.data?.data.order.row_version ?? 'Thiếu'}
                </Descriptions.Item>
                <Descriptions.Item label="Phiên bản dòng món">
                  {selectedItem.row_version ?? 'Thiếu'}
                </Descriptions.Item>
              </Descriptions>

              <Form.Item name="qty" label="Số lượng" rules={[{ required: true, message: 'Nhập số lượng món.' }]}>
                <InputNumber min={1} max={30} style={{ width: '100%' }} disabled={!selectedItemEditable || itemConcurrencyMissing} />
              </Form.Item>
              <Form.Item name="note" label="Ghi chú">
                <Input.TextArea rows={3} placeholder="Ghi chú cho bếp hoặc phục vụ" disabled={!selectedItemEditable || itemConcurrencyMissing} />
              </Form.Item>
              <Button
                type="primary"
                htmlType="submit"
                block
                disabled={!selectedItemEditable || itemConcurrencyMissing}
                loading={updateItemMutation.isPending}
              >
                Lưu thay đổi dòng món
              </Button>

              <Space wrap>
                {allowedStatusTransitions.length === 0 ? (
                  <Typography.Text type="secondary">
                    Dòng món này đã ở trạng thái cuối và không thể chuyển tiếp.
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
                      Đánh dấu {translateUiCode(status)}
                    </Button>
                  ))
                )}
              </Space>
            </Space>
          )}
        </Form>
      </Card>

      <Card title="Bước chuyển tiếp tiếp theo" className="staff-order-next-step-card">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Typography.Text type="secondary">
            Tiếp tục dùng ngữ cảnh từ route và store để nhân viên không phải nhập lại các ID.
          </Typography.Text>
          <Button
            type="primary"
            disabled={!resolvedOrderId}
            onClick={() =>
              navigate(`${staffRoutePaths.kitchen.landing}?${buildJourneySearch({
                source: 'order',
                tableId: primaryTableId ?? undefined,
                tableIds: resolvedTableIds,
                reservationId: reservationId ?? undefined,
                reservationRowVersion: reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? undefined,
                orderId: resolvedOrderId ?? undefined,
                orderRowVersion: orderDetailQuery.data?.data.order.row_version ?? undefined,
              })}`)
            }
          >
            Mở màn hình bếp
          </Button>
          <Button
            disabled={!resolvedOrderId}
            onClick={() =>
              navigate(`${staffRoutePaths.ops.checkout}?${buildJourneySearch({
                source: 'order',
                tableId: primaryTableId ?? undefined,
                tableIds: resolvedTableIds,
                reservationId: reservationId ?? undefined,
                reservationRowVersion: reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? undefined,
                orderId: resolvedOrderId ?? undefined,
                orderRowVersion: orderDetailQuery.data?.data.order.row_version ?? undefined,
              })}`)
            }
          >
            Mở thanh toán
          </Button>
          <Button
            onClick={() =>
              navigate(`${staffRoutePaths.ops.tables}?${buildJourneySearch({
                source: journey.source ?? 'order',
                tableId: primaryTableId ?? undefined,
                tableIds: resolvedTableIds,
                reservationId: reservationId ?? undefined,
                reservationRowVersion: reservationRowVersion ?? reservationDetailQuery.data?.data.row_version ?? undefined,
                orderId: resolvedOrderId ?? undefined,
                orderRowVersion: orderDetailQuery.data?.data.order.row_version ?? undefined,
              })}`)
            }
          >
            Quay lại sơ đồ bàn
          </Button>
        </Space>
      </Card>
    </Space>
  );

  return <SplitWorkspace main={main} side={side} variant="detail-heavy" className="staff-order-split-workspace" />;
}
