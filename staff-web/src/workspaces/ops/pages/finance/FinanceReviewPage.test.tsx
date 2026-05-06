import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { buildStaffSession } from '../../../../test/fixtures';
import { FinanceReviewPage, StaffBenefitsOpsPanel } from './FinanceReviewPage';

const apiMocks = vi.hoisted(() => ({
  adjustStaffUserLoyalty: vi.fn(),
  applyStaffReservationVoucher: vi.fn(),
  getFinanceInvoice: vi.fn(),
  getFinancialReconciliationDetail: vi.fn(),
  getStaffReservationLoyalty: vi.fn(),
  getStaffUserLoyalty: vi.fn(),
  issueFinanceInvoice: vi.fn(),
  listFinancialReconciliation: vi.fn(),
  listStaffReservationVouchers: vi.fn(),
  redeemStaffReservationLoyalty: vi.fn(),
  releaseStaffReservationLoyalty: vi.fn(),
  releaseStaffReservationVoucher: vi.fn(),
  removeStaffReservationVoucher: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', () => ({
  adjustStaffUserLoyalty: apiMocks.adjustStaffUserLoyalty,
  applyStaffReservationVoucher: apiMocks.applyStaffReservationVoucher,
  getFinanceInvoice: apiMocks.getFinanceInvoice,
  getFinancialReconciliationDetail: apiMocks.getFinancialReconciliationDetail,
  getStaffReservationLoyalty: apiMocks.getStaffReservationLoyalty,
  getStaffUserLoyalty: apiMocks.getStaffUserLoyalty,
  issueFinanceInvoice: apiMocks.issueFinanceInvoice,
  listFinancialReconciliation: apiMocks.listFinancialReconciliation,
  listStaffReservationVouchers: apiMocks.listStaffReservationVouchers,
  redeemStaffReservationLoyalty: apiMocks.redeemStaffReservationLoyalty,
  releaseStaffReservationLoyalty: apiMocks.releaseStaffReservationLoyalty,
  releaseStaffReservationVoucher: apiMocks.releaseStaffReservationVoucher,
  removeStaffReservationVoucher: apiMocks.removeStaffReservationVoucher,
}));

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();

describe('FinanceReviewPage', () => {
  beforeAll(() => {
    const baseGetComputedStyle = window.getComputedStyle.bind(window);
    Object.defineProperty(window, 'getComputedStyle', {
      writable: true,
      value: (element: Element) => baseGetComputedStyle(element),
    });

    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: (query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: () => undefined,
        removeListener: () => undefined,
        addEventListener: () => undefined,
        removeEventListener: () => undefined,
        dispatchEvent: () => false,
      }),
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
    apiMocks.getFinanceInvoice.mockReset();
    apiMocks.getFinancialReconciliationDetail.mockReset();
    apiMocks.getStaffReservationLoyalty.mockReset();
    apiMocks.getStaffUserLoyalty.mockReset();
    apiMocks.issueFinanceInvoice.mockReset();
    apiMocks.listFinancialReconciliation.mockReset();
    apiMocks.listStaffReservationVouchers.mockReset();
    apiMocks.applyStaffReservationVoucher.mockReset();
    apiMocks.removeStaffReservationVoucher.mockReset();
    apiMocks.releaseStaffReservationVoucher.mockReset();
    apiMocks.redeemStaffReservationLoyalty.mockReset();
    apiMocks.releaseStaffReservationLoyalty.mockReset();
    apiMocks.adjustStaffUserLoyalty.mockReset();
    apiMocks.listFinancialReconciliation.mockResolvedValue({
      data: [],
      meta: {
        page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
      },
    });
    apiMocks.listStaffReservationVouchers.mockResolvedValue({ data: [] });
    apiMocks.getStaffReservationLoyalty.mockResolvedValue({ data: { redeemed_points: 0 } });
    apiMocks.getStaffUserLoyalty.mockResolvedValue({ data: { points_balance: 1200 } });
    apiMocks.applyStaffReservationVoucher.mockResolvedValue({ data: { action: 'voucher_applied' } });
    apiMocks.removeStaffReservationVoucher.mockResolvedValue({ data: { action: 'voucher_removed' } });
    apiMocks.releaseStaffReservationVoucher.mockResolvedValue({ data: { action: 'voucher_released' } });
    apiMocks.redeemStaffReservationLoyalty.mockResolvedValue({ data: { action: 'loyalty_redeemed' } });
    apiMocks.releaseStaffReservationLoyalty.mockResolvedValue({ data: { action: 'loyalty_released' } });
    apiMocks.adjustStaffUserLoyalty.mockResolvedValue({ data: { action: 'loyalty_adjusted' } });

    useFlowStore.setState(initialFlowState, true);
    useAuthStore.setState({
      ...initialAuthState,
      status: 'authenticated',
      session: buildStaffSession({
        capabilities: ['reservation.manage', 'payment.refund'],
        known_capabilities: ['reservation.manage', 'payment.refund'],
      }),
      notice: null,
    });
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 3,
    });
  });

  it('keeps finance review filters accessible and lets staff clear ad-hoc filters quickly', async () => {
    renderFinanceReviewPage();

    const reservationCodeInput = await screen.findByLabelText('Lọc theo mã đặt bàn');
    expect(screen.getByLabelText('Lọc theo trạng thái đặt bàn')).toBeInTheDocument();
    expect(screen.getByLabelText('Lọc theo trạng thái cọc')).toBeInTheDocument();
    expect(screen.getByLabelText('Lọc theo trạng thái chênh lệch')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Mở bộ lọc nâng cao' }));

    expect(await screen.findByLabelText('Lọc theo loại tiền thanh toán')).toBeInTheDocument();
    expect(screen.getByLabelText('Lọc theo mã thu ngân')).toBeInTheDocument();
    expect(screen.getByLabelText('Từ ngày hoạt động thanh toán')).toBeInTheDocument();
    expect(screen.getByLabelText('Đến ngày hoạt động thanh toán')).toBeInTheDocument();

    fireEvent.change(reservationCodeInput, { target: { value: 'RSV-22' } });
    await waitFor(() => expect(reservationCodeInput).toHaveValue('RSV-22'));

    const clearFiltersButton = screen.getByRole('button', { name: 'Xóa bộ lọc' });
    await waitFor(() => expect(clearFiltersButton).toBeEnabled());
    fireEvent.click(clearFiltersButton);

    await waitFor(() => expect(reservationCodeInput).toHaveValue(''));
  });

  it('shows a Vietnamese warning when the activity date range is invalid', async () => {
    renderFinanceReviewPage(`${staffRoutePaths.ops.financeReview}?activity_from=2026-05-10&activity_to=2026-05-01`);

    expect(await screen.findByText('Khoảng ngày hoạt động thanh toán không hợp lệ')).toBeInTheDocument();
    expect(apiMocks.listFinancialReconciliation).not.toHaveBeenCalled();
  });

  it('lets staff retry the reconciliation list from the inline error state', async () => {
    apiMocks.listFinancialReconciliation.mockRejectedValue(new Error('List unavailable'));

    renderFinanceReviewPage();

    const retryButton = await screen.findByRole('button', { name: 'Tải lại dữ liệu đối soát' });
    const attemptsBeforeRetry = apiMocks.listFinancialReconciliation.mock.calls.length;
    fireEvent.click(retryButton);

    await waitFor(() => {
      expect(apiMocks.listFinancialReconciliation.mock.calls.length).toBeGreaterThan(attemptsBeforeRetry);
    });
  });

  it('retries both finance detail queries when staff reloads the selected detail panel', async () => {
    apiMocks.listFinancialReconciliation.mockResolvedValue({
      data: [createFinanceRow()],
      meta: {
        page: 1,
        per_page: 15,
        total: 1,
        last_page: 1,
      },
    });
    apiMocks.getFinancialReconciliationDetail.mockRejectedValue(new Error('Detail unavailable'));
    apiMocks.getFinanceInvoice.mockRejectedValue(new Error('Invoice unavailable'));

    renderFinanceReviewPage(`${staffRoutePaths.ops.financeReview}?reservation_id=77`);

    const retryButton = await screen.findByRole('button', { name: 'Tải lại khung chi tiết' });
    const detailAttemptsBeforeRetry = apiMocks.getFinancialReconciliationDetail.mock.calls.length;
    const invoiceAttemptsBeforeRetry = apiMocks.getFinanceInvoice.mock.calls.length;
    fireEvent.click(retryButton);

    await waitFor(() => {
      expect(apiMocks.getFinancialReconciliationDetail.mock.calls.length).toBeGreaterThan(detailAttemptsBeforeRetry);
      expect(apiMocks.getFinanceInvoice.mock.calls.length).toBeGreaterThan(invoiceAttemptsBeforeRetry);
    });
  });

  it('lets staff retry the invoice status block without leaving the finance detail view', async () => {
    apiMocks.listFinancialReconciliation.mockResolvedValue({
      data: [createFinanceRow()],
      meta: {
        page: 1,
        per_page: 15,
        total: 1,
        last_page: 1,
      },
    });
    apiMocks.getFinancialReconciliationDetail.mockResolvedValue({
      data: createFinanceDetail(),
      meta: {
        action: 'financial_reconciliation_show',
      },
    });
    apiMocks.getFinanceInvoice.mockRejectedValue(new Error('Invoice unavailable'));

    renderFinanceReviewPage(`${staffRoutePaths.ops.financeReview}?reservation_id=77`);

    await screen.findByText('Lệch cọc');
    await waitFor(() => {
      expect(apiMocks.getFinanceInvoice.mock.calls.length).toBeGreaterThanOrEqual(1);
    });

    const retryButton = await screen.findByRole('button', { name: 'Tải lại trạng thái hóa đơn' }, { timeout: 5000 });
    const attemptsBeforeRetry = apiMocks.getFinanceInvoice.mock.calls.length;
    fireEvent.click(retryButton);

    await waitFor(() => {
      expect(apiMocks.getFinanceInvoice.mock.calls.length).toBeGreaterThan(attemptsBeforeRetry);
    });
  });

  it('reopens reservation flow with reservation row_version from reconciliation detail', async () => {
    apiMocks.listFinancialReconciliation.mockResolvedValue({
      data: [createFinanceRow()],
      meta: {
        page: 1,
        per_page: 15,
        total: 1,
        last_page: 1,
      },
    });
    apiMocks.getFinancialReconciliationDetail.mockResolvedValue({
      data: createFinanceDetail(),
      meta: {
        action: 'financial_reconciliation_show',
      },
    });
    apiMocks.getFinanceInvoice.mockRejectedValue(Object.assign(new Error('Not found'), { status: 404 }));

    renderFinanceReviewPage(`${staffRoutePaths.ops.financeReview}?reservation_id=77`);

    await screen.findByText('Lệch cọc');
    fireEvent.click(await screen.findByRole('button', { name: 'Mở đặt bàn' }));

    await waitFor(() => expect(screen.getByTestId('reservations-destination')).toBeInTheDocument());
    expect(screen.getByTestId('location-search').textContent).toContain('reservation_id=77');
    expect(screen.getByTestId('location-search').textContent).toContain('reservation_row_version=9');
  });

  it('opens the refund workspace with the selected reservation context', async () => {
    apiMocks.listFinancialReconciliation.mockResolvedValue({
      data: [createFinanceRow()],
      meta: {
        page: 1,
        per_page: 15,
        total: 1,
        last_page: 1,
      },
    });
    apiMocks.getFinancialReconciliationDetail.mockResolvedValue({
      data: createFinanceDetail(),
      meta: {
        action: 'financial_reconciliation_show',
      },
    });
    apiMocks.getFinanceInvoice.mockRejectedValue(Object.assign(new Error('Not found'), { status: 404 }));

    renderFinanceReviewPage(`${staffRoutePaths.ops.financeReview}?reservation_id=77&order_id=88&order_row_version=5`);

    await screen.findByText('Lệch cọc');
    fireEvent.click(await screen.findByRole('button', { name: 'Mở bàn hoàn tiền' }));

    await waitFor(() => expect(screen.getByTestId('refunds-destination')).toBeInTheDocument());
    expect(screen.getByTestId('location-search').textContent).toContain('source=refund');
    expect(screen.getByTestId('location-search').textContent).toContain('reservation_id=77');
    expect(screen.getByTestId('location-search').textContent).toContain('reservation_row_version=9');
    expect(screen.getByTestId('location-search').textContent).toContain('order_id=88');
  });

  it('applies voucher and redeems loyalty with the reservation row_version', async () => {
    apiMocks.listStaffReservationVouchers.mockResolvedValue({
      data: [{ code: 'WELCOME', description: 'Welcome voucher' }],
    });

    renderBenefitsOpsPanel();

    expect(await screen.findByText('Voucher / điểm thưởng')).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Mã voucher staff áp dụng'), { target: { value: 'WELCOME' } });
    fireEvent.click(screen.getByRole('button', { name: 'Áp dụng voucher' }));

    await waitFor(() => {
      expect(apiMocks.applyStaffReservationVoucher).toHaveBeenCalledWith(77, {
        row_version: 9,
        voucher_code: 'WELCOME',
      });
    });

    fireEvent.change(screen.getByLabelText('Số điểm staff sử dụng'), { target: { value: '100' } });
    fireEvent.change(screen.getByLabelText('Lý do staff sử dụng điểm'), { target: { value: 'Khách dùng điểm tại quầy' } });
    fireEvent.click(screen.getByRole('button', { name: 'Sử dụng điểm' }));

    await waitFor(() => {
      expect(apiMocks.redeemStaffReservationLoyalty).toHaveBeenCalledWith(77, {
        row_version: 9,
        points: 100,
        reason: 'Khách dùng điểm tại quầy',
      });
    });

    fireEvent.change(screen.getByLabelText('Số điểm staff điều chỉnh'), { target: { value: '25' } });
    fireEvent.change(screen.getByLabelText('Lý do staff điều chỉnh điểm'), { target: { value: 'Bù điểm từ hóa đơn trước' } });
    fireEvent.click(screen.getByRole('button', { name: 'Điều chỉnh điểm' }));

    await waitFor(() => {
      expect(apiMocks.adjustStaffUserLoyalty).toHaveBeenCalledWith(15, {
        points: 25,
        reason: 'Bù điểm từ hóa đơn trước',
      });
    });
  });

  it('shows Vietnamese retry actions for voucher and loyalty query failures', async () => {
    apiMocks.listStaffReservationVouchers.mockRejectedValue(new Error('Voucher unavailable'));
    apiMocks.getStaffReservationLoyalty.mockRejectedValue(new Error('Reservation loyalty unavailable'));
    apiMocks.getStaffUserLoyalty.mockRejectedValue(new Error('User loyalty unavailable'));

    renderBenefitsOpsPanel();

    const voucherRetry = await screen.findByRole('button', { name: 'Tải lại voucher' });
    const voucherAttemptsBeforeRetry = apiMocks.listStaffReservationVouchers.mock.calls.length;
    fireEvent.click(voucherRetry);
    await waitFor(() => {
      expect(apiMocks.listStaffReservationVouchers.mock.calls.length).toBeGreaterThan(voucherAttemptsBeforeRetry);
    });

    const reservationLoyaltyRetry = screen.getByRole('button', { name: 'Tải lại điểm thưởng của đặt bàn' });
    const reservationLoyaltyAttemptsBeforeRetry = apiMocks.getStaffReservationLoyalty.mock.calls.length;
    fireEvent.click(reservationLoyaltyRetry);
    await waitFor(() => {
      expect(apiMocks.getStaffReservationLoyalty.mock.calls.length).toBeGreaterThan(reservationLoyaltyAttemptsBeforeRetry);
    });

    const userLoyaltyRetry = screen.getByRole('button', { name: 'Tải lại điểm thưởng của khách' });
    const userLoyaltyAttemptsBeforeRetry = apiMocks.getStaffUserLoyalty.mock.calls.length;
    fireEvent.click(userLoyaltyRetry);
    await waitFor(() => {
      expect(apiMocks.getStaffUserLoyalty.mock.calls.length).toBeGreaterThan(userLoyaltyAttemptsBeforeRetry);
    });
  });
});

function createFinanceRow() {
  return {
    reservation: {
      reservation_id: 77,
      reservation_code: 'RSV-77',
      row_version: 9,
      status: 'Completed',
      deposit_status: 'Paid',
      start_time: '2026-04-11T10:00:00Z',
      end_time: '2026-04-11T12:00:00Z',
      billed_at: '2026-04-11T12:15:00Z',
      updated_at: '2026-04-11T12:20:00Z',
      bill_currency: 'VND',
      customer: {
        user_id: 15,
        full_name: 'Le Thu',
        email: 'le-thu@example.test',
        phone: '0909000000',
      },
    },
    payment_summary: {
      payment_count: 2,
      refund_count: 0,
      captured_amount: 180000,
      refunded_amount: 0,
      net_paid_amount: 180000,
      deposit_captured_amount: 50000,
      deposit_refunded_amount: 0,
      deposit_net_amount: 50000,
      final_captured_amount: 130000,
      final_refunded_amount: 0,
      final_net_amount: 130000,
      over_refunded_amount: 0,
      last_payment_activity_at: '2026-04-11T12:15:00Z',
      last_refund_at: null,
      currency: {
        currency: 'VND',
        has_mixed_currencies: false,
      },
    },
    reconciliation: {
      deposit_required_amount: 50000,
      deposit_recorded_paid_amount: 50000,
      deposit_computed_net_amount: 50000,
      deposit_sync_gap_amount: 0,
      final_bill_amount: 180000,
      bill_outstanding_amount: 0,
      bill_overpaid_amount: 0,
    },
    flags: {
      has_refunds: false,
      has_payments: true,
      has_discrepancy: false,
      has_deposit_sync_gap: false,
      has_over_refund: false,
      has_mixed_payment_currencies: false,
      has_bill_outstanding: false,
      has_bill_overpaid: false,
      discrepancy_reasons: [],
      is_fully_settled: true,
    },
  };
}

function createFinanceDetail() {
  return {
    reservation: createFinanceRow().reservation,
    summary: createFinanceRow(),
    payments: [],
    method_breakdown: [],
  };
}

function LocationProbe() {
  const location = useLocation();
  return <div data-testid="location-search">{location.search}</div>;
}

function renderFinanceReviewPage(initialEntry: string = staffRoutePaths.ops.financeReview) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <AntdApp>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={[initialEntry]} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <Routes>
            <Route path={staffRoutePaths.ops.financeReview} element={<FinanceReviewPage />} />
            <Route path={staffRoutePaths.ops.reservations} element={<div data-testid="reservations-destination">reservations</div>} />
            <Route path={staffRoutePaths.ops.refunds} element={<div data-testid="refunds-destination">refunds</div>} />
          </Routes>
          <LocationProbe />
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}

function renderBenefitsOpsPanel() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
      mutations: {
        retry: false,
      },
    },
  });

  return render(
    <AntdApp>
      <QueryClientProvider client={queryClient}>
        <StaffBenefitsOpsPanel
          reservationId={77}
          reservationRowVersion={9}
          customerUserId={15}
          session={buildStaffSession({
            capabilities: ['voucher.manage', 'loyalty.view', 'loyalty.redeem', 'loyalty.adjust'],
            known_capabilities: ['voucher.manage', 'loyalty.view', 'loyalty.redeem', 'loyalty.adjust'],
          })}
          onMutationSettled={() => undefined}
        />
      </QueryClientProvider>
    </AntdApp>,
  );
}
