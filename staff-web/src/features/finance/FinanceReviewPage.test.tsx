import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../../app/store/auth-store';
import { useFlowStore } from '../../app/store/flow-store';
import { buildStaffSession } from '../../test/fixtures';
import { FinanceReviewPage } from './FinanceReviewPage';

const apiMocks = vi.hoisted(() => ({
  getFinanceInvoice: vi.fn(),
  getFinancialReconciliationDetail: vi.fn(),
  issueFinanceInvoice: vi.fn(),
  listFinancialReconciliation: vi.fn(),
}));

vi.mock('../../core/api/staff-api', () => ({
  getFinanceInvoice: apiMocks.getFinanceInvoice,
  getFinancialReconciliationDetail: apiMocks.getFinancialReconciliationDetail,
  issueFinanceInvoice: apiMocks.issueFinanceInvoice,
  listFinancialReconciliation: apiMocks.listFinancialReconciliation,
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
    apiMocks.issueFinanceInvoice.mockReset();
    apiMocks.listFinancialReconciliation.mockReset();
    apiMocks.listFinancialReconciliation.mockResolvedValue({
      data: [],
      meta: {
        page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
      },
    });

    useFlowStore.setState(initialFlowState, true);
    useAuthStore.setState({
      ...initialAuthState,
      status: 'authenticated',
      session: buildStaffSession({
        capabilities: ['reservation.manage'],
        known_capabilities: ['reservation.manage'],
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

    renderFinanceReviewPage('/finance-review?reservation_id=77');

    await screen.findByText('Lệch cọc');
    fireEvent.click(await screen.findByRole('button', { name: 'Mở đặt bàn' }));

    await waitFor(() => expect(screen.getByTestId('reservations-destination')).toBeInTheDocument());
    expect(screen.getByTestId('location-search').textContent).toContain('reservation_id=77');
    expect(screen.getByTestId('location-search').textContent).toContain('reservation_row_version=9');
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

function renderFinanceReviewPage(initialEntry = '/finance-review') {
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
            <Route path="/finance-review" element={<FinanceReviewPage />} />
            <Route path="/reservations" element={<div data-testid="reservations-destination">reservations</div>} />
          </Routes>
          <LocationProbe />
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}
