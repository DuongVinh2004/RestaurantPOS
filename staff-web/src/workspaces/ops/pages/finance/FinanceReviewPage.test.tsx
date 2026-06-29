import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import type {
  FinanceInvoiceEnvelope,
  FinancialReconciliationCollectionEnvelope,
  FinancialReconciliationDetailEnvelope,
  FinancialReconciliationRow,
} from '../../../../shared/api/staff-api';
import {
  getFinanceInvoice,
  getFinancialReconciliationDetail,
  issueFinanceInvoice,
  listFinancialReconciliation,
} from '../../../../shared/api/staff-api';
import { buildStaffSession } from '../../../../test/fixtures';
import { FinanceReviewPage, StaffBenefitsOpsPanel } from './FinanceReviewPage';

vi.mock('../../../../shared/api/staff-api', () => ({
  getFinanceInvoice: vi.fn(),
  getFinancialReconciliationDetail: vi.fn(),
  issueFinanceInvoice: vi.fn(),
  listFinancialReconciliation: vi.fn(),
}));

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();
const financeRow = makeFinanceRow();
const financeCollection: FinancialReconciliationCollectionEnvelope = {
  data: [financeRow],
  meta: {
    action: 'finance_reconciliation_index',
    total: 1,
    per_page: 12,
    current_page: 1,
    last_page: 1,
  },
};
const financeDetail: FinancialReconciliationDetailEnvelope = {
  data: {
    reservation: financeRow.reservation,
    summary: financeRow,
    payments: [],
    method_breakdown: [],
  },
  meta: {
    action: 'finance_reconciliation_show',
  },
};
const financeInvoice: FinanceInvoiceEnvelope = {
  data: {
    invoice: {
      billing_invoice_id: 5,
      reservation_id: 77,
      invoice_number: 'INV-77',
      invoice_status: 'Issued',
      currency: 'VND',
      bill_amounts: {
        subtotal_amount: 180000,
        discount_amount: 0,
        total_amount: 180000,
      },
      issued_at: '2026-04-10T02:10:00Z',
      row_version: 2,
    },
    reservation: financeRow.reservation,
    reconciliation: financeRow,
    method_breakdown: [],
  },
  meta: {
    action: 'finance_invoice_show',
  },
};

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
    vi.mocked(listFinancialReconciliation).mockReset();
    vi.mocked(getFinancialReconciliationDetail).mockReset();
    vi.mocked(getFinanceInvoice).mockReset();
    vi.mocked(issueFinanceInvoice).mockReset();
    vi.mocked(listFinancialReconciliation).mockResolvedValue(financeCollection);
    vi.mocked(getFinancialReconciliationDetail).mockResolvedValue(financeDetail);
    vi.mocked(getFinanceInvoice).mockResolvedValue(financeInvoice);
    vi.mocked(issueFinanceInvoice).mockResolvedValue({
      ...financeInvoice,
      meta: { action: 'finance_invoice_issued', created: true },
    });

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
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 3,
    });
  });

  it('loads finance reconciliation and invoice through canonical staff API wrappers', async () => {
    renderFinanceReviewPage(`${staffRoutePaths.ops.financeReview}?reservation_id=77&reservation_row_version=9&order_id=88&order_row_version=5`);

    expect(await screen.findByRole('heading', { name: /Đối soát tài chính/i })).toBeInTheDocument();
    expect((await screen.findAllByText('RSV-77')).length).toBeGreaterThan(0);
    
    const rows = await screen.findAllByText('RSV-77');
    fireEvent.click(rows[0]);

    expect(await screen.findByText('INV-77')).toBeInTheDocument();
    expect(screen.getAllByText('Đã quyết toán').length).toBeGreaterThan(0);

    expect(listFinancialReconciliation).toHaveBeenCalledWith(expect.objectContaining({
      branch_id: 3,
      reservation_id: 77,
      per_page: 12,
    }));
    expect(getFinancialReconciliationDetail).toHaveBeenCalledWith(77, { branch_id: 3 });
    expect(getFinanceInvoice).toHaveBeenCalledWith(77, { branch_id: 3 });
  });

  it('issues the selected invoice and refreshes finance queries', async () => {
    renderFinanceReviewPage(`${staffRoutePaths.ops.financeReview}?reservation_id=77&reservation_row_version=9&order_id=88&order_row_version=5`);

    // Click row to open detail drawer
    const rows = await screen.findAllByText('RSV-77');
    fireEvent.click(rows[0]);

    const issueButton = await screen.findByRole('button', { name: 'Phát hành hóa đơn' });
    await waitFor(() => expect(issueButton).toBeEnabled());
    fireEvent.click(issueButton);

    await waitFor(() => expect(issueFinanceInvoice).toHaveBeenCalledWith(77, { branch_id: 3 }));
  });

  it('reopens the reservation flow with the canonical journey context', async () => {
    renderFinanceReviewPage(`${staffRoutePaths.ops.financeReview}?reservation_id=77&reservation_row_version=9&order_id=88&order_row_version=5`);

    fireEvent.click(await screen.findByRole('button', { name: 'Mở đặt bàn' }));
    await waitFor(() => expect(screen.getByTestId('reservations-destination')).toBeInTheDocument());
    expect(screen.getByTestId('location-search').textContent).toContain('reservation_id=77');
    expect(screen.getByTestId('location-search').textContent).toContain('reservation_row_version=9');
  });

  it('reopens the refund flow with the canonical journey context', async () => {
    renderFinanceReviewPage(`${staffRoutePaths.ops.financeReview}?reservation_id=77&reservation_row_version=9&order_id=88&order_row_version=5`);
    fireEvent.click(await screen.findByRole('button', { name: 'Mở hoàn tiền' }));
    await waitFor(() => expect(screen.getByTestId('refunds-destination')).toBeInTheDocument());
    expect(screen.getByTestId('location-search').textContent).toContain('source=refund');
    expect(screen.getByTestId('location-search').textContent).toContain('reservation_id=77');
    expect(screen.getByTestId('location-search').textContent).toContain('order_id=88');
  });

  it('keeps staff voucher and loyalty routes explicitly blocked outside the operator contract lane', async () => {
    renderBenefitsOpsPanel();

    expect(await screen.findByText('Voucher và tích điểm nằm ngoài contract vận hành')).toBeInTheDocument();
    expect(screen.getByText(/GET \/api\/v1\/staff\/reservations\/\{reservation_id\}\/vouchers/i)).toBeInTheDocument();
    expect(screen.getByText(/POST \/api\/v1\/staff\/users\/\{user_id\}\/loyalty\/adjust/i)).toBeInTheDocument();
  });
});

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
      mutations: {
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

function makeFinanceRow(): FinancialReconciliationRow {
  return {
    reservation: {
      reservation_id: 77,
      reservation_code: 'RSV-77',
      row_version: 9,
      status: 'Completed',
      deposit_status: 'Paid',
      start_time: '2026-04-10T00:00:00Z',
      end_time: '2026-04-10T01:30:00Z',
      billed_at: '2026-04-10T01:45:00Z',
      updated_at: '2026-04-10T01:50:00Z',
      bill_currency: 'VND',
      customer: {
        user_id: 10,
        full_name: 'Customer',
        email: 'customer@example.com',
        phone: '0900000000',
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
      last_payment_activity_at: '2026-04-10T02:00:00Z',
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
