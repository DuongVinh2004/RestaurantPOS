import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { buildApiError, buildStaffSession } from '../../../../test/fixtures';
import { CheckoutPage } from './CheckoutPage';

const confirmActionMock = vi.hoisted(() => vi.fn(async () => true));
const apiMocks = vi.hoisted(() => ({
  createBillSnapshot: vi.fn(),
  finalizeSettlement: vi.fn(),
  getCurrentCashierShift: vi.fn(),
  getOrderDetail: vi.fn(),
  getRefundPreview: vi.fn(),
  getSettlementPreview: vi.fn(),
  refundAndCancelReservation: vi.fn(),
  refundReservation: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', () => apiMocks);
vi.mock('../../../../shared/hooks/useConfirmAction', () => ({
  useConfirmAction: () => confirmActionMock,
}));

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();

describe('CheckoutPage', () => {
  beforeAll(() => {
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
      })),
    });

    class ResizeObserverMock {
      observe() {}
      unobserve() {}
      disconnect() {}
    }

    Object.defineProperty(globalThis, 'ResizeObserver', {
      writable: true,
      value: ResizeObserverMock,
    });
  });

  beforeEach(() => {
    vi.clearAllMocks();
    sessionStorage.clear();
    useFlowStore.setState(initialFlowState, true);
    useAuthStore.setState({
      ...initialAuthState,
      status: 'authenticated',
      session: buildStaffSession({
        capabilities: ['settlement.manage', 'payment.refund', 'cashier.shift.manage'],
        known_capabilities: ['settlement.manage', 'payment.refund', 'cashier.shift.manage'],
      }),
      notice: null,
    }, true);
    apiMocks.getCurrentCashierShift.mockResolvedValue(createCashierShiftEnvelope());
  });

  it('does not query checkout data from stale persisted order context', async () => {
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 1,
      selectedTableId: 12,
      selectedReservationId: 34,
      selectedReservationRowVersion: 5,
      selectedOrderId: 56,
      selectedOrderRowVersion: 7,
      source: 'board',
    });

    renderWithProviders(staffRoutePaths.ops.checkout);

    await waitFor(() => expect(useFlowStore.getState().selectedOrderId).toBeNull());

    expect(apiMocks.getOrderDetail).not.toHaveBeenCalled();
    expect(apiMocks.getSettlementPreview).not.toHaveBeenCalled();
    expect(await screen.findByText('Chưa có ngữ cảnh đơn hàng')).toBeInTheDocument();
  });

  it('renders snapshot guest and multi-table checkout context from route metadata', async () => {
    apiMocks.getOrderDetail.mockResolvedValue(createOrderEnvelope());
    apiMocks.getSettlementPreview.mockResolvedValue(createSettlementPreview());

    renderWithProviders(`${staffRoutePaths.ops.checkout}?source=order&reservation_id=34&reservation_row_version=5&table_ids=12,14&order_id=56&order_row_version=10`);

    expect(await screen.findByText('Khách snapshot')).toBeInTheDocument();
    expect(screen.getByText('T12, T14')).toBeInTheDocument();
  });

  it('uses the refund preview row version when executing refund only', async () => {
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 3,
    });
    apiMocks.getOrderDetail.mockResolvedValue(createOrderEnvelope());
    apiMocks.getSettlementPreview.mockResolvedValue(createSettlementPreview());
    apiMocks.getRefundPreview.mockResolvedValue(createRefundPreviewEnvelope());
    apiMocks.refundReservation.mockResolvedValue({
      data: {
        reservation: createOrderEnvelope().data.reservation,
        refund: createRefundPreviewEnvelope().data.refund,
      },
    });

    const { queryClient } = renderWithProviders(`${staffRoutePaths.ops.refunds}?source=order&reservation_id=34&reservation_row_version=5&table_ids=12,14&order_id=56&order_row_version=10`);
    const invalidateQueriesSpy = vi.spyOn(queryClient, 'invalidateQueries');

    fireEvent.click(await screen.findByRole('button', { name: /Làm mới preview hoàn tiền/i }));

    await waitFor(() => expect(apiMocks.getRefundPreview).toHaveBeenCalledWith(34, expect.objectContaining({
      refund_scope: 'all',
      currency: 'VND',
      cancel_after_payment: false,
    })));

    fireEvent.click(await screen.findByRole('button', { name: 'Hoàn tiền' }));

    await waitFor(() => expect(apiMocks.refundReservation).toHaveBeenCalledWith(34, expect.objectContaining({
      row_version: 12,
      refund_scope: 'all',
      currency: 'VND',
      payment_method: 'Cash',
      payment_provider: 'Cash',
    })));
    await waitFor(() => {
      expect(invalidateQueriesSpy).toHaveBeenCalledWith(expect.objectContaining({
        queryKey: ['finance-reconciliation-detail', 3, 34],
      }));
      expect(invalidateQueriesSpy).toHaveBeenCalledWith(expect.objectContaining({
        queryKey: ['finance-invoice', 3, 34],
      }));
      expect(invalidateQueriesSpy).toHaveBeenCalledWith(expect.objectContaining({
        queryKey: ['audit-trail'],
      }));
      expect(invalidateQueriesSpy).toHaveBeenCalledWith(expect.objectContaining({
        queryKey: ['reporting-sales'],
      }));
      expect(invalidateQueriesSpy).toHaveBeenCalledWith(expect.objectContaining({
        queryKey: ['reporting-operations'],
      }));
    });
  });

  it('requires a fresh preview after switching to refund-cancel mode and executes with the latest preview row version', async () => {
    apiMocks.getOrderDetail.mockResolvedValue(createOrderEnvelope());
    apiMocks.getSettlementPreview.mockResolvedValue(createSettlementPreview());
    apiMocks.getRefundPreview
      .mockResolvedValueOnce(createRefundPreviewEnvelope({ rowVersion: 12 }))
      .mockResolvedValueOnce(createRefundPreviewEnvelope({ rowVersion: 13 }));
    apiMocks.refundAndCancelReservation.mockResolvedValue({
      data: {
        reservation: {
          ...createOrderEnvelope().data.reservation,
          status: 'Cancelled',
          row_version: 13,
        },
        refund: {
          ...createRefundPreviewEnvelope().data.refund,
          cancelled: true,
          reservation_status: 'Cancelled',
        },
      },
    });

    renderWithProviders(`${staffRoutePaths.ops.refunds}?source=order&reservation_id=34&reservation_row_version=5&table_ids=12,14&order_id=56&order_row_version=10`);

    fireEvent.click(await screen.findByRole('button', { name: /Làm mới preview hoàn tiền/i }));
    await waitFor(() => expect(apiMocks.getRefundPreview).toHaveBeenCalledTimes(1));

    fireEvent.click(screen.getByRole('radio', { name: 'Hoàn tiền + hủy đặt bàn' }));

    expect(await screen.findByRole('button', { name: 'Hoàn tiền và hủy đặt bàn' })).toBeDisabled();
    expect(screen.getByText(/Preview hiện tại không còn khớp/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /Làm mới preview hoàn tiền/i }));

    await waitFor(() => expect(apiMocks.getRefundPreview).toHaveBeenLastCalledWith(34, expect.objectContaining({
      refund_scope: 'all',
      currency: 'VND',
      cancel_after_payment: true,
    })));

    const refundCancelButton = await screen.findByRole('button', { name: 'Hoàn tiền và hủy đặt bàn' });
    await waitFor(() => expect(refundCancelButton).toBeEnabled());
    fireEvent.click(refundCancelButton);

    await waitFor(() => expect(apiMocks.refundAndCancelReservation).toHaveBeenCalledWith(34, expect.objectContaining({
      row_version: 13,
      refund_scope: 'all',
      currency: 'VND',
      cancel_reason: 'customer_request',
    })));
  }, 20000);

  it('shows a conflict mutation notice when finalize hits a stale row version', async () => {
    apiMocks.getOrderDetail.mockResolvedValue(createOrderEnvelope());
    apiMocks.getSettlementPreview.mockResolvedValue(createSettlementPreview());
    apiMocks.finalizeSettlement.mockRejectedValue(buildApiError(409, {
      error_code: 'stale_row_version',
      category_code: 'stale_write',
      conflict_type: 'stale_write',
      message: 'The resource was modified by another writer.',
      request_id: 'req-finalize-stale',
    }));

    renderWithProviders(`${staffRoutePaths.ops.checkout}?source=order&reservation_id=34&reservation_row_version=5&table_ids=12,14&order_id=56&order_row_version=10`);

    await waitFor(() => expect(apiMocks.getSettlementPreview).toHaveBeenCalled());
    const settlementAmountInput = await screen.findByLabelText(/Số tiền đã thu/i);
    fireEvent.change(settlementAmountInput, { target: { value: '100000' } });
    const settlementForm = settlementAmountInput.closest('form');
    if (!settlementForm) {
      throw new Error('Settlement form was not rendered.');
    }
    fireEvent.submit(settlementForm);

    await waitFor(() => expect(confirmActionMock).toHaveBeenCalled());
    await waitFor(() => expect(apiMocks.finalizeSettlement).toHaveBeenCalledWith(56, expect.objectContaining({
      row_version: 10,
      payment_method: 'Cash',
      payment_provider: 'Cash',
      paid_amount: 100000,
      currency: 'VND',
    })));
    await waitFor(() => expect(screen.getByTestId('mutation-status-notice')).toHaveAttribute('data-phase', 'conflict'));
  });

  it('checks the active shell branch for cashier shift readiness before enabling checkout mutations', async () => {
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 7,
    });
    apiMocks.getOrderDetail.mockResolvedValue(createOrderEnvelope());
    apiMocks.getSettlementPreview.mockResolvedValue(createSettlementPreview());
    apiMocks.getCurrentCashierShift.mockRejectedValue(buildApiError(404, {
      error_code: 'not_found',
      message: 'No open cashier shift found for the authenticated staff actor.',
    }));

    renderWithProviders(`${staffRoutePaths.ops.checkout}?source=order&reservation_id=34&reservation_row_version=5&table_ids=12,14&order_id=56&order_row_version=10`);

    await waitFor(() => expect(apiMocks.getCurrentCashierShift).toHaveBeenCalledWith(7));
    expect(await screen.findByRole('button', { name: 'Hoàn tất thanh toán' })).toBeDisabled();
    expect(screen.getByText(/Chi nhánh hiện tại còn bị chặn bởi ca thu ngân/i)).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: 'Mở trung tâm ca thu ngân' }).length).toBeGreaterThan(0);
  });

  it('supports a refund-focused route and hydrates shell context labels from the loaded order', async () => {
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 3,
    });
    apiMocks.getOrderDetail.mockResolvedValue(createOrderEnvelope());
    apiMocks.getSettlementPreview.mockResolvedValue(createSettlementPreview());

    renderWithProviders(`${staffRoutePaths.ops.refunds}?source=refund&reservation_id=34&reservation_row_version=5&table_ids=12,14&order_id=56&order_row_version=10`);

    expect(await screen.findByText('Bàn hoàn tiền')).toBeInTheDocument();
    expect(await screen.findByRole('button', { name: 'Quay lại thanh toán' })).toBeInTheDocument();

    await waitFor(() => expect(useFlowStore.getState().selectedReservationLabel).toBe('RSV-34'));
    expect(useFlowStore.getState().selectedTableLabel).toBe('T12, T14');
    expect(useFlowStore.getState().selectedOrderLabel).toBe('Đơn #56');
  });
});

function createOrderEnvelope() {
  return {
    data: {
      order: {
        order_id: 56,
        reservation_id: 34,
        order_type: 'OnSpot',
        status: 'Active',
        row_version: 10,
        payment_status: 'Pending',
      },
      table: null,
      tables: [
        {
          table_id: 12,
          table_code: 'T12',
          zone: 'Main',
          seats: 4,
          status: 'Available',
        },
        {
          table_id: 14,
          table_code: 'T14',
          zone: 'Patio',
          seats: 6,
          status: 'Available',
        },
      ],
      reservation: {
        reservation_id: 34,
        reservation_code: 'RSV-34',
        status: 'Reserved',
        row_version: 5,
        table_ids: [12, 14],
        guest: {
          full_name: 'Caller Guest',
          phone: '0900000000',
          email: 'caller@example.test',
          is_snapshot_only: true,
        },
        user: null,
      },
      customer: {
        full_name: 'Caller Guest',
        phone: '0900000000',
      },
      items: [],
      item_summary: {
        line_count: 0,
        quantity_total: 0,
        active_quantity: 0,
        cancelled_quantity: 0,
        status_counts: {},
        status_quantities: {},
      },
      financial_summary: {
        settlement_scope: 'order',
        subtotal: '100000',
        discount: '0',
        total_due: '100000',
        paid: '0',
        deposit_applied: '0',
        deposit_net: '0',
        final_paid: '0',
        outstanding: '100000',
        currency: 'VND',
        payment_status: 'Pending',
        reservation_payment_summary: null,
      },
    },
    meta: {
      action: 'show',
      selection_policy: 'explicit',
    },
  };
}

function createSettlementPreview() {
  return {
    data: {
      total_amount: '100000',
      paid_amount: '0',
      deposit_applied_amount: '0',
      outstanding_amount: '100000',
      currency: 'VND',
      row_version: 10,
    },
  };
}

function createRefundPreviewEnvelope({ rowVersion = 12 }: { rowVersion?: number } = {}) {
  return {
    data: {
      reservation: {
        ...createOrderEnvelope().data.reservation,
        row_version: rowVersion,
      },
      refund: {
        refund_payment_ids: [401],
        refund_amount: '25000.00',
        currency: 'VND',
        refund_scope: 'all',
        cancelled: false,
        reservation_status: 'Completed',
        payment_summary: {
          deposit_captured: '50000.00',
          deposit_refunded: '25000.00',
          deposit_net: '25000.00',
          final_captured: '50000.00',
          final_refunded: '0.00',
          final_net: '50000.00',
          captured_total: '100000.00',
          refunded_total: '25000.00',
          net_paid_total: '75000.00',
        },
      },
    },
  };
}

function createCashierShiftEnvelope() {
  return {
    data: {
      cashier_shift_id: 44,
      branch_id: 1,
      branch: {
        branch_id: 1,
        branch_code: 'MAIN',
        branch_name: 'Chi nhanh chinh',
        timezone: 'Asia/Ho_Chi_Minh',
        currency: 'VND',
        is_default: true,
        is_active: true,
      },
      shift_code: 'SHIFT-STAFF-WEB',
      status: 'Open',
      currency: 'VND',
      terminal_code: 'POS-01',
      row_version: 7,
      opened_at: '2026-04-07T08:00:00Z',
      closed_at: null,
      opening_float_amount: '0.00',
      expected_cash_amount: null,
      actual_cash_amount: null,
      cash_discrepancy_amount: null,
      opening_note: null,
      closing_note: null,
      cashier: {
        user_id: 42,
        full_name: 'Front Desk',
        email: 'foh@example.test',
      },
      opened_by: {
        user_id: 42,
        full_name: 'Front Desk',
        email: 'foh@example.test',
      },
      closed_by: null,
      summary: {
        payments: {
          captured_total: '0.00',
          refunded_total: '0.00',
          net_paid_total: '0.00',
          deposit_net: '0.00',
          final_net: '0.00',
          payment_count: 0,
          refund_count: 0,
          currency: {
            currency: 'VND',
            currencies: ['VND'],
            has_mixed_currencies: false,
          },
        },
        cash: {
          currency: 'VND',
          opening_float_amount: '0.00',
          captured_amount: '0.00',
          refunded_amount: '0.00',
          expected_cash_amount: '0.00',
          excluded_cash_currencies: [],
          has_excluded_cash_currencies: false,
        },
        methods: [],
      },
      flags: {
        is_open: true,
        has_payments: false,
        has_refunds: false,
        has_mixed_payment_currencies: false,
      },
    },
  };
}

function renderWithProviders(initialEntry: string) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  const view = render(
    <AntdApp>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={[initialEntry]} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <Routes>
            <Route path={staffRoutePaths.ops.checkout} element={<CheckoutPage />} />
            <Route path={staffRoutePaths.ops.refunds} element={<CheckoutPage focusMode="refund" />} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );

  return {
    queryClient,
    ...view,
  };
}
