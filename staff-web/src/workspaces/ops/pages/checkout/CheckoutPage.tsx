import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  Card,
  Descriptions,
  Form,
  Input,
  InputNumber,
  Radio,
  Select,
  Space,
  Typography,
} from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createBillSnapshot,
  finalizeSettlement,
  getCurrentCashierShift,
  getRefundPreview,
  getOrderDetail,
  getSettlementPreview,
  refundAndCancelReservation,
  refundReservation,
} from '../../../../shared/api/staff-api';
import { isApiStatus } from '../../../../shared/api/errors';
import { isStaffCashierShiftActionRequired, requiresStaffCashierShift } from '../../../../app/auth/startup';
import { can } from '../../../../shared/auth/capabilities';
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
import { orderTone, paymentTone } from '../../../../shared/status/status';
import { translateUiCode } from '../../../../shared/utils/translation';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { MutationStatusNotice } from '../../../../shared/ui/feedback/MutationStatusNotice';
import {
  ApiStateBlock,
  BranchPolicyState,
  ConflictState,
  EmptyBlock,
  InlineLoading,
  InlineState,
  PermissionDeniedState,
} from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { useJourneyContext } from '../../../../app/router/useJourneyContext';
import { useConfirmAction } from '../../../../shared/hooks/useConfirmAction';
import { useStaffMutationFeedback } from '../../../../shared/hooks/useStaffMutationFeedback';

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

type RefundMode = 'refund' | 'refund_cancel';

type RefundFormValues = {
  mode: RefundMode;
  refund_scope: 'deposit' | 'final' | 'all';
  refund_amount?: number;
  currency: string;
  payment_method: 'Cash' | 'Card' | 'BankTransfer' | 'Other';
  payment_provider?: 'Cash' | 'Card' | 'BankTransfer' | 'Other';
  transaction_code?: string;
  reason?: string;
  cancel_reason?: string;
  notes?: string;
};

type RefundPreviewData = Awaited<ReturnType<typeof getRefundPreview>>;

type RefundPreviewSignature = {
  reservationId: number;
  mode: RefundMode;
  refundScope: RefundFormValues['refund_scope'];
  refundAmount: string;
  currency: string;
};

const refundModeOptions = [
  { label: 'Chỉ hoàn tiền', value: 'refund' },
  { label: 'Hoàn tiền + hủy đặt bàn', value: 'refund_cancel' },
] satisfies Array<{ label: string; value: RefundMode }>;

const refundScopeOptions = [
  { label: 'Hoàn cọc', value: 'deposit' },
  { label: 'Hoàn phần thanh toán cuối', value: 'final' },
  { label: 'Hoàn toàn bộ', value: 'all' },
] satisfies Array<{ label: string; value: RefundFormValues['refund_scope'] }>;

function buildRefundPreviewSignature(
  reservationId: number,
  values: RefundFormValues,
): RefundPreviewSignature {
  return {
    reservationId,
    mode: values.mode,
    refundScope: values.refund_scope,
    refundAmount: typeof values.refund_amount === 'number' && !Number.isNaN(values.refund_amount)
      ? String(values.refund_amount)
      : '',
    currency: values.currency.trim().toUpperCase(),
  };
}

export function CheckoutPage({
  focusMode = 'checkout',
}: {
  focusMode?: 'checkout' | 'refund';
}) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const confirmAction = useConfirmAction();
  const mutationFeedback = useStaffMutationFeedback('checkout-workspace');
  const journey = useJourneyContext();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const setTableContext = useFlowStore((state) => state.setTableContext);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const setOrderContext = useFlowStore((state) => state.setOrderContext);
  const [snapshotForm] = Form.useForm<SnapshotFormValues>();
  const [settlementForm] = Form.useForm<SettlementFormValues>();
  const [refundForm] = Form.useForm<RefundFormValues>();
  const [previewCurrency, setPreviewCurrency] = useState('VND');
  const [refundPreview, setRefundPreview] = useState<RefundPreviewData | null>(null);
  const [refundPreviewSignature, setRefundPreviewSignature] = useState<RefundPreviewSignature | null>(null);

  const orderId = journey.orderId ?? null;
  const cashierShiftActionRequired = !!session && isStaffCashierShiftActionRequired(session);
  const cashierShiftRequired = !!session && requiresStaffCashierShift(session);
  const cashierShiftManage = !!session && can(session, 'cashier.shift.manage');
  const refundManage = !!session && can(session, 'payment.refund');
  const effectiveBranchId = branchId ?? session?.startup.default_branch?.branch_id ?? undefined;
  const checkoutSource = focusMode === 'refund' ? 'refund' : 'checkout';

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
    refundForm.setFieldsValue({
      mode: refundForm.getFieldValue('mode') ?? 'refund',
      refund_scope: refundForm.getFieldValue('refund_scope') ?? 'all',
      currency: refundForm.getFieldValue('currency') ?? currency,
      payment_method: refundForm.getFieldValue('payment_method') ?? 'Cash',
      payment_provider: refundForm.getFieldValue('payment_provider') ?? 'Cash',
      reason: refundForm.getFieldValue('reason') ?? 'customer_request',
      cancel_reason: refundForm.getFieldValue('cancel_reason') ?? 'customer_request',
    });
  }, [orderQuery.data, refundForm, settlementForm]);

  const previewQuery = useQuery({
    queryKey: ['settlement-preview', orderId, previewCurrency],
    queryFn: () => getSettlementPreview(orderId as number, { currency: previewCurrency }),
    enabled: !!orderId,
  });

  const currentCashierShiftQuery = useQuery({
    queryKey: ['cashier-shift-current', effectiveBranchId],
    queryFn: () => getCurrentCashierShift(effectiveBranchId),
    enabled: !!session && !!orderId && cashierShiftRequired,
    retry: (failureCount, error) => !isApiStatus(error, 404) && failureCount < 1,
  });

  const snapshotMutation = useMutation({
    onMutate: () => {
      mutationFeedback.setSubmitting(
        'Chụp số liệu hóa đơn',
        'Đang lưu snapshot tài chính theo phiên bản đơn hiện tại.',
      );
    },
    mutationFn: async (values: SnapshotFormValues) => {
      const rowVersion = orderQuery.data?.data.order.row_version;

      if (!orderId || !rowVersion) {
        throw new Error('Hãy tải lại đơn hàng để lấy phiên bản mới nhất trước khi chụp số liệu.');
      }

      return createBillSnapshot(orderId, {
        row_version: rowVersion,
        discount_amount: values.discount_amount ?? null,
        notes: values.notes?.trim() || null,
      });
    },
    onSuccess: async () => {
      await invalidateCheckoutReadModels();
      mutationFeedback.setSuccess(
        'Chụp số liệu hóa đơn',
        'Đã lưu snapshot hóa đơn mới nhất cho workspace hiện tại.',
      );
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Chụp số liệu hóa đơn',
        fallbackMessage: 'Không thể chụp số liệu hóa đơn.',
      });
    },
  });

  const finalizeMutation = useMutation({
    onMutate: () => {
      mutationFeedback.setSubmitting(
        'Hoàn tất thanh toán',
        'Đang ghi nhận thanh toán và quyết toán đơn hiện tại.',
      );
    },
    mutationFn: async (values: SettlementFormValues) => {
      const rowVersion = orderQuery.data?.data.order.row_version;

      if (!orderId || !rowVersion) {
        throw new Error('Hãy tải lại đơn hàng để lấy phiên bản mới nhất trước khi chốt thanh toán.');
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
      await invalidateCheckoutReadModels({ includeFinancial: true });
      mutationFeedback.setSuccess(
        'Hoàn tất thanh toán',
        `Đã hoàn tất thanh toán cho đơn #${orderId ?? ''}.`,
      );
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Hoàn tất thanh toán',
        fallbackMessage: 'Không thể hoàn tất thanh toán.',
      });
    },
  });

  const currentOrder = orderQuery.data?.data;
  const preview = previewQuery.data?.data;
  const outstanding = Number(preview?.outstanding_amount ?? currentOrder?.financial_summary.outstanding ?? 0);
  const currentBranchCashierShift = isApiStatus(currentCashierShiftQuery.error, 404)
    ? null
    : currentCashierShiftQuery.data?.data ?? null;
  const cashierShiftCheckPending = cashierShiftRequired
    && (currentCashierShiftQuery.isLoading || currentCashierShiftQuery.isFetching);
  const cashierShiftBlocked = cashierShiftRequired
    && (cashierShiftCheckPending || currentBranchCashierShift === null);
  const finalizeDisabled = !orderId || cashierShiftBlocked;
  const cashierShiftStatusLabel = !cashierShiftRequired
    ? (session?.startup.readiness.cashier_shift ?? 'not_applicable')
    : cashierShiftCheckPending
      ? 'Đang kiểm tra ca'
      : currentBranchCashierShift
        ? 'Có ca chi nhánh'
        : 'Thiếu ca chi nhánh';
  const cashierShiftStatusTone = !cashierShiftRequired
    ? paymentTone(session?.startup.readiness.cashier_shift)
    : currentBranchCashierShift
      ? 'success'
      : 'warning';
  const routeTableIds = journey.tableIds ?? [];
  const resolvedTableIds = routeTableIds.length > 0
    ? routeTableIds
    : getReservationTableIds(currentOrder);
  const primaryTableId = journey.tableId ?? routeTableIds[0] ?? getPrimaryReservationTableId(currentOrder) ?? null;
  const reservationSummary = currentOrder?.reservation ?? null;
  const refundReservationId = reservationSummary?.reservation_id ?? journey.reservationId ?? null;
  const customerLabel = reservationSummary
    ? getReservationGuestLabel(
      reservationSummary,
      currentOrder?.customer?.full_name ?? currentOrder?.customer?.phone ?? 'Khách vãng lai',
    )
    : currentOrder?.customer?.full_name ?? currentOrder?.customer?.phone ?? 'Khách vãng lai';
  const isSnapshotOnlyGuest = isReservationSnapshotOnlyGuest(reservationSummary);
  const tableLabel = getReservationTableLabel(currentOrder);

  useEffect(() => {
    if (!orderId || !currentOrder?.order.row_version) {
      return;
    }

    setOrderContext({
      orderId,
      orderRowVersion: currentOrder.order.row_version,
      label: buildOrderContextLabel(orderId),
      source: journey.source ?? checkoutSource,
    });
  }, [checkoutSource, currentOrder?.order.row_version, journey.source, orderId, setOrderContext]);

  useEffect(() => {
    if (primaryTableId) {
      setTableContext({
        tableId: primaryTableId,
        label: buildTableContextLabel(tableLabel, primaryTableId),
        source: journey.source ?? checkoutSource,
      });
    }

    if (refundReservationId) {
      setReservationContext({
        reservationId: refundReservationId,
        reservationRowVersion: reservationSummary?.row_version ?? journey.reservationRowVersion,
        label: buildReservationContextLabel(
          reservationSummary?.reservation_code ?? null,
          refundReservationId,
        ),
        source: journey.source ?? checkoutSource,
      });
    }
  }, [
    checkoutSource,
    journey.reservationRowVersion,
    journey.source,
    primaryTableId,
    refundReservationId,
    reservationSummary?.reservation_code,
    reservationSummary?.row_version,
    setReservationContext,
    setTableContext,
    tableLabel,
  ]);

  const refundMode = Form.useWatch('mode', refundForm) ?? 'refund';
  const refundScope = Form.useWatch('refund_scope', refundForm) ?? 'all';
  const refundAmount = Form.useWatch('refund_amount', refundForm);
  const refundCurrency = (Form.useWatch('currency', refundForm) ?? previewCurrency).trim().toUpperCase();
  const refundPreviewAmount = Number(refundPreview?.data.refund.refund_amount ?? 0);
  const refundPreviewMatchesCurrentInputs = refundReservationId !== null
    && refundPreviewSignature !== null
    && refundPreviewSignature.reservationId === refundReservationId
    && refundPreviewSignature.mode === refundMode
    && refundPreviewSignature.refundScope === refundScope
    && refundPreviewSignature.refundAmount === (
      typeof refundAmount === 'number' && !Number.isNaN(refundAmount) ? String(refundAmount) : ''
    )
    && refundPreviewSignature.currency === refundCurrency;
  const refundMutationGuardReason = cashierShiftBlocked
    ? cashierShiftCheckPending
      ? 'Đang kiểm tra ca thu ngân của chi nhánh hiện tại trước khi cho phép hoàn tiền.'
      : 'Chi nhánh hiện tại chưa có ca thu ngân đang mở. Hãy mở ca đúng chi nhánh trước khi hoàn tiền hoặc hủy đặt bàn.'
    : refundReservationId === null
      ? 'Đơn hàng hiện tại chưa gắn với đặt bàn nên không thể dùng luồng hoàn tiền theo reservation.'
      : !refundPreview
        ? 'Hãy làm mới preview hoàn tiền trước khi thực hiện nghiệp vụ tài chính này.'
        : !refundPreviewMatchesCurrentInputs
          ? 'Preview hiện tại không còn khớp với mode / phạm vi / số tiền / loại tiền đang hiển thị trên form. Hãy làm mới preview.'
          : Number.isNaN(refundPreviewAmount) || refundPreviewAmount <= 0
            ? 'Preview hiện tại cho thấy không còn số tiền hợp lệ để hoàn.'
            : null;

  async function invalidateCheckoutReadModels(options?: { includeFinancial?: boolean }) {
    const invalidations = [
      queryClient.invalidateQueries({ queryKey: ['checkout-order-detail', orderId] }),
      queryClient.invalidateQueries({ queryKey: ['settlement-preview', orderId] }),
      queryClient.invalidateQueries({ queryKey: ['order-detail', orderId] }),
      queryClient.invalidateQueries({ queryKey: ['reservations'] }),
      queryClient.invalidateQueries({ queryKey: ['table-board'] }),
    ];

    if (refundReservationId) {
      invalidations.push(
        queryClient.invalidateQueries({ queryKey: ['reservation-detail', refundReservationId] }),
        queryClient.invalidateQueries({ queryKey: ['active-order-by-reservation', refundReservationId] }),
        queryClient.invalidateQueries({ queryKey: ['finance-reconciliation-detail', branchId, refundReservationId] }),
        queryClient.invalidateQueries({ queryKey: ['finance-invoice', branchId, refundReservationId] }),
      );
    }

    for (const tableId of resolvedTableIds) {
      invalidations.push(queryClient.invalidateQueries({ queryKey: ['active-order-by-table', tableId] }));
    }

    if (options?.includeFinancial) {
      invalidations.push(
        queryClient.invalidateQueries({ queryKey: ['kitchen-stations'] }),
        queryClient.invalidateQueries({ queryKey: ['kitchen-tickets'] }),
        queryClient.invalidateQueries({ queryKey: ['finance-reconciliation'] }),
        queryClient.invalidateQueries({ queryKey: ['cashier-shift-current'] }),
        queryClient.invalidateQueries({ queryKey: ['cashier-shifts'] }),
        queryClient.invalidateQueries({ queryKey: ['audit-trail'] }),
        queryClient.invalidateQueries({ queryKey: ['reporting-sales'] }),
        queryClient.invalidateQueries({ queryKey: ['reporting-operations'] }),
      );
    }

    await Promise.all(invalidations);
  }

  async function refreshCheckoutWorkspace() {
    const refreshes: Array<Promise<unknown>> = [
      queryClient.invalidateQueries({ queryKey: ['table-board'] }),
      queryClient.invalidateQueries({ queryKey: ['reservations'] }),
    ];

    if (orderId) {
      refreshes.push(orderQuery.refetch(), previewQuery.refetch());
    }

    if (cashierShiftRequired && orderId) {
      refreshes.push(currentCashierShiftQuery.refetch());
    }

    if (refundReservationId) {
      refreshes.push(
        queryClient.invalidateQueries({ queryKey: ['reservation-detail', refundReservationId] }),
        queryClient.invalidateQueries({ queryKey: ['active-order-by-reservation', refundReservationId] }),
      );
    }

    await Promise.all(refreshes);
  }

  const refundPreviewMutation = useMutation({
    onMutate: () => {
      mutationFeedback.setSubmitting(
        'Làm mới preview hoàn tiền',
        'Đang tải preview hoàn tiền mới nhất theo dữ liệu form hiện tại.',
      );
    },
    mutationFn: async (values: RefundFormValues) => {
      if (!refundReservationId) {
        throw new Error('Đơn hàng hiện tại chưa gắn với đặt bàn để lấy preview hoàn tiền.');
      }

      return getRefundPreview(refundReservationId, {
        refund_scope: values.refund_scope,
        refund_amount: typeof values.refund_amount === 'number' ? values.refund_amount : undefined,
        currency: values.currency.trim().toUpperCase(),
        cancel_after_payment: values.mode === 'refund_cancel',
      });
    },
    onSuccess: (envelope, values) => {
      if (!refundReservationId) {
        return;
      }

      setRefundPreview(envelope);
      setRefundPreviewSignature(buildRefundPreviewSignature(refundReservationId, values));
      mutationFeedback.setSuccess(
        'Làm mới preview hoàn tiền',
        'Preview hoàn tiền đã được cập nhật theo dữ liệu form hiện tại.',
      );
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Làm mới preview hoàn tiền',
        fallbackMessage: 'Không thể tải preview hoàn tiền.',
      });
    },
  });

  const refundMutation = useMutation({
    onMutate: (values) => {
      mutationFeedback.setSubmitting(
        values.mode === 'refund_cancel' ? 'Hoàn tiền và hủy đặt bàn' : 'Hoàn tiền',
        values.mode === 'refund_cancel'
          ? 'Đang ghi nhận refund và chuyển reservation sang trạng thái hủy.'
          : 'Đang ghi nhận giao dịch hoàn tiền dựa trên preview hiện tại.',
      );
    },
    mutationFn: async (values: RefundFormValues) => {
      if (!refundPreview || !refundPreview.data.reservation) {
        throw new Error('Hãy làm mới preview hoàn tiền trước khi thực hiện.');
      }

      const reservationId = refundPreview.data.reservation.reservation_id;
      const payload = {
        payment_method: values.payment_method,
        payment_provider: values.payment_provider ?? values.payment_method,
        refund_scope: values.refund_scope,
        refund_amount: typeof values.refund_amount === 'number' ? values.refund_amount : undefined,
        currency: values.currency.trim().toUpperCase(),
        transaction_code: values.transaction_code?.trim() || undefined,
        notes: values.notes?.trim() || undefined,
        reason: values.reason?.trim() || undefined,
        row_version: refundPreview.data.reservation.row_version,
      };

      if (values.mode === 'refund_cancel') {
        return refundAndCancelReservation(reservationId, {
          ...payload,
          cancel_reason: values.cancel_reason?.trim() || undefined,
        });
      }

      return refundReservation(reservationId, payload);
    },
    onSuccess: async (_, values) => {
      const reservationCode = refundPreview?.data.reservation.reservation_code
        ?? currentOrder?.reservation?.reservation_code
        ?? (refundReservationId ? `đặt bàn #${refundReservationId}` : 'đặt bàn hiện tại');

      setRefundPreview(null);
      setRefundPreviewSignature(null);
      await invalidateCheckoutReadModels({ includeFinancial: true });
      mutationFeedback.setSuccess(
        values.mode === 'refund_cancel' ? 'Hoàn tiền và hủy đặt bàn' : 'Hoàn tiền',
        values.mode === 'refund_cancel'
          ? `Đã hoàn tiền và hủy ${reservationCode}.`
          : `Đã tạo hoàn tiền cho ${reservationCode}.`,
      );
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Hoàn tiền',
        fallbackMessage: 'Không thể thực hiện hoàn tiền.',
      });
    },
  });

  const cashierShiftPath = useMemo(() => {
    const search = buildJourneySearch({
      source: checkoutSource,
      tableId: primaryTableId ?? undefined,
      tableIds: resolvedTableIds,
      reservationId: refundReservationId ?? undefined,
      reservationRowVersion: reservationSummary?.row_version ?? journey.reservationRowVersion,
      orderId: orderId ?? undefined,
      orderRowVersion: currentOrder?.order.row_version ?? journey.orderRowVersion,
      stationId: journey.stationId,
    });

                return search ? `${staffRoutePaths.ops.cashierShift}?${search}` : staffRoutePaths.ops.cashierShift;
  }, [
    checkoutSource,
    currentOrder?.order.row_version,
    journey.orderRowVersion,
    journey.reservationRowVersion,
    journey.stationId,
    orderId,
    primaryTableId,
    refundReservationId,
    reservationSummary?.row_version,
    resolvedTableIds,
  ]);

  const checkoutJourneySearch = useMemo(
    () => buildJourneySearch({
      source: 'checkout',
      tableId: primaryTableId ?? undefined,
      tableIds: resolvedTableIds,
      reservationId: refundReservationId ?? undefined,
      reservationRowVersion: reservationSummary?.row_version ?? journey.reservationRowVersion,
      orderId: orderId ?? undefined,
      orderRowVersion: currentOrder?.order.row_version ?? journey.orderRowVersion,
      stationId: journey.stationId,
    }),
    [
      currentOrder?.order.row_version,
      journey.orderRowVersion,
      journey.reservationRowVersion,
      journey.stationId,
      orderId,
      primaryTableId,
      refundReservationId,
      reservationSummary?.row_version,
      resolvedTableIds,
    ],
  );

  const refundJourneySearch = useMemo(
    () => buildJourneySearch({
      source: 'refund',
      tableId: primaryTableId ?? undefined,
      tableIds: resolvedTableIds,
      reservationId: refundReservationId ?? undefined,
      reservationRowVersion: reservationSummary?.row_version ?? journey.reservationRowVersion,
      orderId: orderId ?? undefined,
      orderRowVersion: currentOrder?.order.row_version ?? journey.orderRowVersion,
      stationId: journey.stationId,
    }),
    [
      currentOrder?.order.row_version,
      journey.orderRowVersion,
      journey.reservationRowVersion,
      journey.stationId,
      orderId,
      primaryTableId,
      refundReservationId,
      reservationSummary?.row_version,
      resolvedTableIds,
    ],
  );

  async function handleFinalize(values: SettlementFormValues) {
    const confirmed = await confirmAction({
      title: `Hoàn tất thanh toán cho đơn #${orderId ?? ''}`,
      content: (
        <Space direction="vertical" size={10}>
          <Typography.Text>
            Thao tác này ghi nhận thanh toán và hoàn tất quyết toán ở backend. Chỉ tiếp tục sau khi đã kiểm tra lại tiền thu thực tế và mã giao dịch.
          </Typography.Text>
          <div className="staff-mini-list">
            <div className="staff-mini-list-item">
              <Typography.Text strong>Đơn hàng</Typography.Text>
              <Typography.Text type="secondary">#{orderId ?? 'n/a'}</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Còn phải thu</Typography.Text>
              <Typography.Text type="secondary">{formatMoney(outstanding, values.currency)}</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Thu thực tế</Typography.Text>
              <Typography.Text type="secondary">{formatMoney(values.paid_amount, values.currency)}</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Phương thức</Typography.Text>
              <Typography.Text type="secondary">
                {translateUiCode(values.payment_method)}
                {' • '}
                {translateUiCode(values.payment_provider ?? values.payment_method)}
              </Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Mã giao dịch</Typography.Text>
              <Typography.Text type="secondary">{values.transaction_code?.trim() || 'Không có'}</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Phiên bản đơn</Typography.Text>
              <Typography.Text type="secondary">RV {currentOrder?.order.row_version ?? 'n/a'}</Typography.Text>
            </div>
          </div>
        </Space>
      ),
      okText: 'Hoàn tất thanh toán',
      width: 560,
    });

    if (confirmed) {
      try {
        await finalizeMutation.mutateAsync(values);
      } catch {
        // Mutation onError owns operator feedback.
      }
    }
  }

  async function handleRefundPreview(values: RefundFormValues) {
    try {
      await refundPreviewMutation.mutateAsync(values);
    } catch {
      // Mutation onError owns operator feedback.
    }
  }

  async function handleRefundAction(values: RefundFormValues) {
    const reservationCode = refundPreview?.data.reservation.reservation_code
      ?? currentOrder?.reservation?.reservation_code
      ?? (refundReservationId ? `#${refundReservationId}` : 'đặt bàn hiện tại');

    const confirmed = await confirmAction({
      title: values.mode === 'refund_cancel'
        ? `Hoàn tiền và hủy đặt bàn #${refundReservationId ?? ''}`
        : `Hoàn tiền cho đặt bàn #${refundReservationId ?? ''}`,
      content: (
        <Space direction="vertical" size={10}>
          <Typography.Text>
            {values.mode === 'refund_cancel'
              ? 'Thao tác này vừa tạo hoàn tiền vừa chuyển reservation sang trạng thái Cancelled. Chỉ tiếp tục khi preview hiện tại còn khớp với form.'
              : 'Thao tác này tạo giao dịch hoàn tiền dựa trên preview hiện tại. Chỉ tiếp tục khi đã kiểm tra đúng số tiền, lý do và mã giao dịch.'}
          </Typography.Text>
          <div className="staff-mini-list">
            <div className="staff-mini-list-item">
              <Typography.Text strong>Reservation</Typography.Text>
              <Typography.Text type="secondary">{reservationCode}</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Phạm vi</Typography.Text>
              <Typography.Text type="secondary">{translateUiCode(values.refund_scope)}</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Số tiền preview</Typography.Text>
              <Typography.Text type="secondary">
                {formatMoney(
                  Number(refundPreview?.data.refund.refund_amount ?? values.refund_amount ?? 0),
                  refundPreview?.data.refund.currency ?? values.currency,
                )}
              </Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Phương thức</Typography.Text>
              <Typography.Text type="secondary">
                {translateUiCode(values.payment_method)}
                {' • '}
                {translateUiCode(values.payment_provider ?? values.payment_method)}
              </Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Mã giao dịch</Typography.Text>
              <Typography.Text type="secondary">{values.transaction_code?.trim() || 'Không có'}</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Reservation RV</Typography.Text>
              <Typography.Text type="secondary">RV {refundPreview?.data.reservation.row_version ?? 'n/a'}</Typography.Text>
            </div>
          </div>
        </Space>
      ),
      okText: values.mode === 'refund_cancel' ? 'Hoàn tiền và hủy đặt bàn' : 'Hoàn tiền',
      danger: values.mode === 'refund_cancel',
      width: 560,
    });

    if (confirmed) {
      try {
        await refundMutation.mutateAsync(values);
      } catch {
        // Mutation onError owns operator feedback.
      }
    }
  }

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow={focusMode === 'refund' ? 'Hoàn tiền / hậu kiểm' : 'Thanh toán / quyết toán'}
        title={focusMode === 'refund' ? 'Bàn hoàn tiền' : 'Màn hình thanh toán'}
        description={focusMode === 'refund'
          ? 'Giữ nguyên ngữ cảnh bàn, đơn và đặt bàn để làm mới preview hoàn tiền hoặc hủy đặt bàn mà không phải dò lại lịch sử giao dịch.'
          : 'Xem trước bill, chụp số liệu và hoàn tất thanh toán theo phiên bản đơn hàng hiện tại.'}
        meta={currentOrder ? `Phiên bản đơn ${currentOrder.order.row_version}` : undefined}
        context={(
          <>
            <StatusChip label={orderId ? `Đơn #${orderId}` : 'Chưa có đơn'} tone={orderId ? 'processing' : 'warning'} />
            <StatusChip label={refundReservationId ? `Đặt bàn #${refundReservationId}` : 'Khách vãng lai'} tone="default" />
            {resolvedTableIds.length > 1 ? (
              <StatusChip label={`${resolvedTableIds.length} bàn`} tone="processing" />
            ) : null}
            <StatusChip label={cashierShiftStatusLabel} tone={cashierShiftStatusTone} />
          </>
        )}
        extra={
          <Button
            onClick={() => void refreshCheckoutWorkspace()}
            disabled={!orderId}
            loading={orderQuery.isFetching || previewQuery.isFetching}
          >
            Làm mới dữ liệu
          </Button>
        }
      />

      {focusMode === 'refund' ? (
        refundManage ? (
          <InlineState
            tone="info"
            eyebrow="Hoàn tiền / hậu kiểm"
            title="Workspace đang neo vào bước hoàn tiền"
            description="Ưu tiên làm mới preview hoàn tiền trước, sau đó mới thực hiện refund hoặc refund-cancel để tránh thao tác sai phiên bản reservation."
            className="staff-inline-note"
          />
        ) : (
          <PermissionDeniedState
            title="Phiên hiện tại chưa có quyền hoàn tiền"
            description="Bạn vẫn có thể rà soát bill và ngữ cảnh giao dịch ở đây, nhưng thao tác hoàn tiền chỉ mở khi phiên có capability payment.refund."
            className="staff-inline-note"
          />
        )
      ) : null}

      {!orderId ? (
        <Card className="staff-workspace-detail-card">
          <EmptyBlock
            title="Chưa có ngữ cảnh đơn hàng"
            description="Mở thanh toán từ sơ đồ bàn, màn hình đơn hàng hoặc bếp để mang theo ngữ cảnh đơn hàng hiện tại."
          />
        </Card>
      ) : null}

      {orderQuery.isLoading ? <InlineLoading tip="Đang tải đơn hàng cho thanh toán..." /> : null}
      {orderQuery.error ? (
        <ApiStateBlock
          error={orderQuery.error}
          fallback="Không thể tải đơn hàng cho thanh toán."
          onRetry={() => {
            void orderQuery.refetch();
          }}
        />
      ) : null}

      {currentOrder ? (
        <Card title={`Đơn hàng #${currentOrder.order.order_id}`} className="staff-workspace-detail-card">
          <Descriptions bordered size="small" column={2}>
            <Descriptions.Item label="Trạng thái đơn">
              <StatusChip label={currentOrder.order.status} tone={orderTone(currentOrder.order.status)} />
            </Descriptions.Item>
            <Descriptions.Item label="Trạng thái thanh toán">
              <StatusChip label={currentOrder.order.payment_status ?? 'Pending'} tone={paymentTone(currentOrder.order.payment_status)} />
            </Descriptions.Item>
            <Descriptions.Item label="Đặt bàn">
              {currentOrder.reservation?.reservation_code ?? 'Khách vãng lai'}
            </Descriptions.Item>
            <Descriptions.Item label="Bàn">
              {tableLabel}
            </Descriptions.Item>
            <Descriptions.Item label="Khách">
              <Space wrap size={8}>
                <Typography.Text>{customerLabel}</Typography.Text>
                {isSnapshotOnlyGuest ? (
                  <StatusChip label={RESERVATION_SNAPSHOT_GUEST_LABEL} tone="processing" variant="freshness" />
                ) : null}
              </Space>
            </Descriptions.Item>
            <Descriptions.Item label="Tạm tính">
              {formatMoney(currentOrder.financial_summary.subtotal, currentOrder.financial_summary.currency ?? 'VND')}
            </Descriptions.Item>
            <Descriptions.Item label="Còn thiếu">
              {formatMoney(currentOrder.financial_summary.outstanding, currentOrder.financial_summary.currency ?? 'VND')}
            </Descriptions.Item>
          </Descriptions>
        </Card>
      ) : null}

      {cashierShiftRequired ? (
        <BranchPolicyState
          title="Cần có ca thu ngân"
          body={(
            <Space orientation="vertical" size={12}>
              <Typography.Text>
                {cashierShiftCheckPending
                  ? 'Hệ thống đang kiểm tra ca thu ngân cho chi nhánh hiện tại trước khi cho phép thao tác tài chính.'
                  : cashierShiftActionRequired
                    ? 'Startup hiện tại đang yêu cầu mở ca thu ngân trước khi thao tác tài chính. Hệ thống sẽ chỉ gỡ chặn khi chi nhánh hiện tại có ca đang mở.'
                    : 'Chi nhánh hiện tại phải có ca thu ngân đang mở trước khi chốt thanh toán hoặc hoàn tiền. Màn hình vẫn hiển thị để rà soát bill, nhưng mutation tài chính sẽ bị khóa cho tới khi mở đúng ca chi nhánh.'}
              </Typography.Text>
              {cashierShiftManage ? (
                <Button onClick={() => navigate(cashierShiftPath)}>
                  Mở trung tâm ca thu ngân
                </Button>
              ) : null}
            </Space>
          )}
          className="staff-inline-note"
        />
      ) : null}
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <MutationStatusNotice
        feedback={mutationFeedback.feedback}
        onDismiss={mutationFeedback.resetFeedback}
        onRetry={() => {
          void refreshCheckoutWorkspace();
        }}
      />
      <Card title="Số liệu hóa đơn" className="staff-workspace-form-card">
        <Form<SnapshotFormValues> form={snapshotForm} layout="vertical" onFinish={(values) => snapshotMutation.mutate(values)}>
          <Form.Item name="discount_amount" label="Số tiền giảm giá">
            <InputNumber aria-label="Số tiền giảm giá" min={0} placeholder="0" style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="notes" label="Ghi chú chụp số liệu">
            <Input.TextArea aria-label="Ghi chú chụp số liệu" autoComplete="off" rows={3} placeholder="Ghi chú tài chính nếu cần…" />
          </Form.Item>
          <Button type="primary" htmlType="submit" disabled={!orderId} loading={snapshotMutation.isPending} block>
            Chụp số liệu hóa đơn
          </Button>
        </Form>
      </Card>

      <Card title="Xem trước quyết toán" className="staff-workspace-detail-card">
        {!preview ? (
          previewQuery.isLoading ? (
            <InlineLoading tip="Đang tải bản xem trước quyết toán..." />
          ) : previewQuery.error ? (
            <ApiStateBlock
              error={previewQuery.error}
              fallback="Không thể tải bản xem trước quyết toán."
              onRetry={() => {
                void previewQuery.refetch();
              }}
            />
          ) : (
            <EmptyBlock
              title="Chưa có bản xem trước"
              description="Làm mới bản xem trước sau khi tải đơn hàng hoặc sau mỗi lần cập nhật số liệu hóa đơn."
            />
          )
        ) : (
          <Descriptions bordered size="small" column={1}>
            <Descriptions.Item label="Tổng cộng">
              {formatMoney(preview.total_amount, preview.currency)}
            </Descriptions.Item>
            <Descriptions.Item label="Đã thu">
              {formatMoney(preview.paid_amount, preview.currency)}
            </Descriptions.Item>
            <Descriptions.Item label="Cọc đã áp dụng">
              {formatMoney(preview.deposit_applied_amount, preview.currency)}
            </Descriptions.Item>
            <Descriptions.Item label="Còn thiếu">
              {formatMoney(preview.outstanding_amount, preview.currency)}
            </Descriptions.Item>
            <Descriptions.Item label="Phiên bản bản xem trước">
              {preview.row_version}
            </Descriptions.Item>
          </Descriptions>
        )}
      </Card>

      <Card title="Hoàn tất thanh toán" className="staff-workspace-form-card">
        {cashierShiftBlocked ? (
          <BranchPolicyState
            title="Review trước khi chốt tiền"
            description={cashierShiftCheckPending
              ? 'Đang kiểm tra ca thu ngân của chi nhánh hiện tại. Hãy chờ trạng thái này hoàn tất trước khi chốt thanh toán.'
              : 'Chi nhánh hiện tại còn bị chặn bởi ca thu ngân. Hãy mở ca đúng chi nhánh trước rồi mới chốt thanh toán.'}
            primaryAction={cashierShiftManage ? <Button onClick={() => navigate(cashierShiftPath)}>Mở trung tâm ca thu ngân</Button> : undefined}
            className="staff-inline-note"
          />
        ) : (
          <InlineState
            tone={outstanding > 0 ? 'warning' : 'info'}
            eyebrow="Review trước khi chốt tiền"
            title="Xác nhận lại bill trước khi hoàn tất thanh toán"
            description={`Hãy xác nhận lại số tiền thu, loại tiền, mã giao dịch và phiên bản đơn hàng hiện tại trước khi bấm hoàn tất. Còn thiếu ${formatMoney(outstanding, previewCurrency)}.`}
            className="staff-inline-note"
          />
        )}
        <Form<SettlementFormValues>
          form={settlementForm}
          layout="vertical"
          onFinish={handleFinalize}
        >
          <Form.Item name="payment_method" label="Phương thức thanh toán" rules={[{ required: true, message: 'Chọn phương thức thanh toán.' }]}>
            <Select aria-label="Phương thức thanh toán" options={paymentMethodOptions} />
          </Form.Item>
          <Form.Item name="payment_provider" label="Nhà cung cấp thanh toán">
            <Select aria-label="Nhà cung cấp thanh toán" options={paymentMethodOptions} />
          </Form.Item>
          <Form.Item name="paid_amount" label="Số tiền đã thu" rules={[{ required: true, message: 'Nhập số tiền đã thu.' }]}>
            <InputNumber aria-label="Số tiền đã thu" min={0} placeholder="Nhập số tiền đã thu" style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="currency" label="Loại tiền" rules={[{ required: true, message: 'Nhập loại tiền.' }]}>
            <Input
              aria-label="Loại tiền thanh toán"
              autoComplete="off"
              maxLength={3}
              placeholder="Ví dụ: VND…"
              spellCheck={false}
              style={{ textTransform: 'uppercase' }}
              onBlur={(event) => {
                const nextValue = event.target.value.trim().toUpperCase();
                settlementForm.setFieldValue('currency', nextValue);
                setPreviewCurrency(nextValue);
              }}
              onChange={(event) => setPreviewCurrency(event.target.value.toUpperCase())}
            />
          </Form.Item>
          <Form.Item name="transaction_code" label="Mã giao dịch">
            <Input aria-label="Mã giao dịch thanh toán" autoComplete="off" placeholder="Mã đối soát cổng thanh toán hoặc thu ngân…" />
          </Form.Item>
          <Form.Item name="notes" label="Ghi chú thanh toán">
            <Input.TextArea aria-label="Ghi chú thanh toán" autoComplete="off" rows={3} placeholder="Ghi chú thanh toán nếu cần…" />
          </Form.Item>
          <InlineState
            tone="info"
            eyebrow="Còn phải thu"
            title={`Số tiền còn thiếu hiện tại: ${formatMoney(outstanding, previewCurrency)}`}
            className="staff-inline-note"
          />
          <Button type="primary" htmlType="submit" disabled={finalizeDisabled} loading={finalizeMutation.isPending} block>
            Hoàn tất thanh toán
          </Button>
        </Form>
      </Card>

      {refundManage ? (
        <Card title="Hoàn tiền / hủy đặt bàn" className="staff-workspace-form-card">
          {!refundReservationId ? (
            <EmptyBlock
              title="Chưa có đặt bàn để hoàn tiền"
              description="Luồng refund hiện áp dụng cho đơn hàng đã gắn với reservation và có payment lineage ở backend."
            />
          ) : (
            <Form<RefundFormValues>
              form={refundForm}
              layout="vertical"
              initialValues={{
                mode: 'refund',
                refund_scope: 'all',
                currency: previewCurrency,
                payment_method: 'Cash',
                payment_provider: 'Cash',
                reason: 'customer_request',
                cancel_reason: 'customer_request',
              }}
              onFinish={handleRefundAction}
            >
              <Form.Item name="mode" label="Chế độ hoàn tiền" rules={[{ required: true }]}>
                <Radio.Group aria-label="Chế độ hoàn tiền" optionType="button" buttonStyle="solid" options={refundModeOptions} />
              </Form.Item>
              <Form.Item name="refund_scope" label="Phạm vi hoàn tiền" rules={[{ required: true, message: 'Chọn phạm vi hoàn tiền.' }]}>
                <Select aria-label="Phạm vi hoàn tiền" options={refundScopeOptions} />
              </Form.Item>
              <Form.Item name="refund_amount" label="Số tiền hoàn">
                <InputNumber aria-label="Số tiền hoàn" min={0.01} style={{ width: '100%' }} placeholder="Để trống nếu muốn backend tự tính…" />
              </Form.Item>
              <Form.Item name="currency" label="Loại tiền hoàn" rules={[{ required: true, message: 'Nhập loại tiền.' }]}>
                <Input
                  aria-label="Loại tiền hoàn"
                  autoComplete="off"
                  maxLength={3}
                  placeholder="Ví dụ: VND…"
                  spellCheck={false}
                  style={{ textTransform: 'uppercase' }}
                  onBlur={(event) => {
                    refundForm.setFieldValue('currency', event.target.value.trim().toUpperCase());
                  }}
                />
              </Form.Item>
              <Form.Item name="payment_method" label="Phương thức hoàn" rules={[{ required: true, message: 'Chọn phương thức hoàn.' }]}>
                <Select aria-label="Phương thức hoàn" options={paymentMethodOptions} />
              </Form.Item>
              <Form.Item name="payment_provider" label="Kênh hoàn tiền">
                <Select aria-label="Kênh hoàn tiền" options={paymentMethodOptions} />
              </Form.Item>
              <Form.Item name="transaction_code" label="Mã giao dịch hoàn">
                <Input aria-label="Mã giao dịch hoàn" autoComplete="off" placeholder="Mã đối soát refund nếu có…" />
              </Form.Item>
              <Form.Item name="reason" label="Lý do hoàn tiền">
                <Input aria-label="Lý do hoàn tiền" autoComplete="off" placeholder="Ví dụ: customer_request…" spellCheck={false} />
              </Form.Item>
              {refundMode === 'refund_cancel' ? (
                <Form.Item name="cancel_reason" label="Lý do hủy đặt bàn">
                  <Input aria-label="Lý do hủy đặt bàn" autoComplete="off" placeholder="Ví dụ: customer_request…" spellCheck={false} />
                </Form.Item>
              ) : null}
              <Form.Item name="notes" label="Ghi chú refund">
                <Input.TextArea aria-label="Ghi chú hoàn tiền" autoComplete="off" rows={3} placeholder="Ghi chú refund hoặc refund-cancel nếu cần…" />
              </Form.Item>
              <Button
                onClick={() => {
                  void refundForm.validateFields().then((values) => handleRefundPreview(values));
                }}
                loading={refundPreviewMutation.isPending}
                block
              >
                Làm mới preview hoàn tiền
              </Button>
              {refundMutationGuardReason ? (
                cashierShiftBlocked ? (
                  <BranchPolicyState
                    title="Hoàn tiền đang bị chặn bởi điều kiện vận hành"
                    description={refundMutationGuardReason}
                    primaryAction={cashierShiftManage ? <Button onClick={() => navigate(cashierShiftPath)}>Mở trung tâm ca thu ngân</Button> : undefined}
                    className="staff-inline-note"
                  />
                ) : (
                  <ConflictState
                    title="Preview hoàn tiền chưa sẵn sàng cho mutation"
                    description={refundMutationGuardReason}
                    primaryAction={<Button onClick={() => {
                      void refundForm.validateFields().then((values) => handleRefundPreview(values));
                    }}>Làm mới preview</Button>}
                    className="staff-inline-note"
                  />
                )
              ) : (
                <InlineState
                  tone="success"
                  eyebrow="Sẵn sàng hoàn tiền"
                  title={`Preview hợp lệ cho thao tác ${refundMode === 'refund_cancel' ? 'hoàn tiền + hủy đặt bàn' : 'hoàn tiền'}.`}
                  body={refundPreview ? (
                    <Space orientation="vertical" size={4}>
                      <Typography.Text>
                        Reservation RV: {refundPreview.data.reservation.row_version}
                      </Typography.Text>
                      <Typography.Text>
                        Số tiền sẽ hoàn: {formatMoney(Number(refundPreview.data.refund.refund_amount ?? 0), refundPreview.data.refund.currency ?? refundCurrency)}
                      </Typography.Text>
                    </Space>
                  ) : undefined}
                  className="staff-inline-note"
                />
              )}
              {refundPreview ? (
                <Descriptions bordered size="small" column={1} style={{ marginTop: 16 }}>
                  <Descriptions.Item label="Phạm vi">
                    {translateUiCode(refundPreview.data.refund.refund_scope ?? refundScope)}
                  </Descriptions.Item>
                  <Descriptions.Item label="Số tiền preview">
                    {formatMoney(Number(refundPreview.data.refund.refund_amount ?? 0), refundPreview.data.refund.currency ?? refundCurrency)}
                  </Descriptions.Item>
                  <Descriptions.Item label="Đã hoàn">
                    {formatMoney(Number(refundPreview.data.refund.payment_summary?.refunded_total ?? 0), refundPreview.data.refund.currency ?? refundCurrency)}
                  </Descriptions.Item>
                  <Descriptions.Item label="Net đã thu">
                    {formatMoney(Number(refundPreview.data.refund.payment_summary?.net_paid_total ?? 0), refundPreview.data.refund.currency ?? refundCurrency)}
                  </Descriptions.Item>
                  <Descriptions.Item label="Net cọc">
                    {formatMoney(Number(refundPreview.data.refund.payment_summary?.deposit_net ?? 0), refundPreview.data.refund.currency ?? refundCurrency)}
                  </Descriptions.Item>
                  <Descriptions.Item label="Net thanh toán cuối">
                    {formatMoney(Number(refundPreview.data.refund.payment_summary?.final_net ?? 0), refundPreview.data.refund.currency ?? refundCurrency)}
                  </Descriptions.Item>
                </Descriptions>
              ) : null}
              <Button
                type="primary"
                htmlType="submit"
                disabled={refundMutationGuardReason !== null}
                loading={refundMutation.isPending}
                danger={refundMode === 'refund_cancel'}
                block
                style={{ marginTop: 16 }}
              >
                {refundMode === 'refund_cancel' ? 'Hoàn tiền và hủy đặt bàn' : 'Hoàn tiền'}
              </Button>
            </Form>
          )}
        </Card>
      ) : null}

      <Card title="Bước chuyển tiếp tiếp theo" className="staff-workspace-next-card">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          {refundManage && refundReservationId ? (
            <Button
              type={focusMode === 'refund' ? 'primary' : 'default'}
                onClick={() => navigate(`${staffRoutePaths.ops.refunds}?${refundJourneySearch}`)}
              disabled={focusMode === 'refund' || !refundReservationId}
            >
              {focusMode === 'refund' ? 'Đang ở bàn hoàn tiền' : 'Mở bàn hoàn tiền'}
            </Button>
          ) : null}
          {focusMode === 'refund' ? (
                    <Button onClick={() => navigate(`${staffRoutePaths.ops.checkout}?${checkoutJourneySearch}`)}>
              Quay lại thanh toán
            </Button>
          ) : null}
          <Button
            onClick={() =>
              navigate(`${staffRoutePaths.ops.orders}?${buildJourneySearch({
                source: checkoutSource,
                tableId: primaryTableId ?? undefined,
                tableIds: resolvedTableIds,
                reservationId: refundReservationId ?? undefined,
                reservationRowVersion: reservationSummary?.row_version ?? journey.reservationRowVersion,
                orderId: orderId ?? undefined,
                orderRowVersion: currentOrder?.order.row_version ?? undefined,
                stationId: journey.stationId,
              })}`)
            }
            disabled={!orderId}
          >
            Quay lại đơn hàng
          </Button>
          <Button
            onClick={() =>
              navigate(`${staffRoutePaths.kitchen.landing}?${buildJourneySearch({
                source: checkoutSource,
                tableId: primaryTableId ?? undefined,
                tableIds: resolvedTableIds,
                reservationId: refundReservationId ?? undefined,
                reservationRowVersion: reservationSummary?.row_version ?? journey.reservationRowVersion,
                orderId: orderId ?? undefined,
                orderRowVersion: currentOrder?.order.row_version ?? undefined,
                stationId: journey.stationId,
              })}`)
            }
            disabled={!orderId}
          >
            Quay lại bếp
          </Button>
          <Button
            onClick={() =>
              navigate(`${staffRoutePaths.ops.financeReview}?${buildJourneySearch({
                source: checkoutSource,
                tableId: primaryTableId ?? undefined,
                tableIds: resolvedTableIds,
                reservationId: refundReservationId ?? undefined,
                reservationRowVersion: reservationSummary?.row_version ?? journey.reservationRowVersion,
                orderId: orderId ?? undefined,
                orderRowVersion: currentOrder?.order.row_version ?? undefined,
                stationId: journey.stationId,
              })}`)
            }
            disabled={!orderId}
          >
            Mở đối soát tài chính
          </Button>
          <Button
            onClick={() =>
                      navigate(`${staffRoutePaths.ops.tables}?${buildJourneySearch({
                source: journey.source ?? 'checkout',
                tableId: primaryTableId ?? undefined,
                tableIds: resolvedTableIds,
                reservationId: refundReservationId ?? undefined,
                reservationRowVersion: reservationSummary?.row_version ?? journey.reservationRowVersion,
                orderId: orderId ?? undefined,
                orderRowVersion: currentOrder?.order.row_version ?? journey.orderRowVersion,
              })}`)
            }
          >
            Quay lại sơ đồ bàn
          </Button>
        </Space>
      </Card>
    </Space>
  );

  return <SplitWorkspace main={main} side={side} />;
}
