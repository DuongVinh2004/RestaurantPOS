import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CashierPage } from './CashierPage';
import { renderWithSession } from '../../test/render';
import { buildApiError, buildStaffSession } from '../../test/fixtures';
import type { StaffSessionContextValue } from '../../app/session-context';

const apiMocks = vi.hoisted(() => ({
  loadCashierShifts: vi.fn(),
  loadCurrentCashierShift: vi.fn(),
  loadCashierShift: vi.fn(),
  openCashierShift: vi.fn(),
  closeCashierShift: vi.fn(),
  isMissingResource: vi.fn(() => false),
  isUnauthorized: vi.fn(() => false),
}));

vi.mock('../../api/client', () => apiMocks);

describe('CashierPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('hydrates branch and terminal context from the current shift', async () => {
    arrangeCashierFixtures();

    renderWithSession(<CashierPage />, createSessionContext());

    await waitFor(() => expect(screen.getByDisplayValue('5')).toBeInTheDocument());
    expect(screen.getByDisplayValue('POS-01')).toBeInTheDocument();
    expect(screen.getByText('BR-01')).toBeInTheDocument();
  });

  it('opens and closes cashier shifts through canonical wrappers', async () => {
    arrangeCashierFixtures();

    renderWithSession(<CashierPage />, createSessionContext());

    await waitFor(() => expect(screen.getByDisplayValue('5')).toBeInTheDocument());
    expect(screen.getByDisplayValue('POS-01')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Open cashier shift' }));

    await waitFor(() =>
      expect(apiMocks.openCashierShift).toHaveBeenCalledWith(expect.objectContaining({
        opening_float_amount: 100000,
        currency: 'VND',
        branch_id: 5,
        terminal_code: 'POS-01',
      })),
    );

    fireEvent.click(screen.getByRole('button', { name: 'Close shift' }));

    await waitFor(() =>
      expect(apiMocks.closeCashierShift).toHaveBeenCalledWith(301, {
        actual_cash_amount: 140000,
        notes: null,
        row_version: 8,
      }),
    );
  });

  it('loads recent cashier history and still supports manual shift lookup fallback', async () => {
    arrangeCashierFixtures();

    renderWithSession(<CashierPage />, createSessionContext());

    const historyCard = await screen.findByText('SHIFT-300');
    fireEvent.click(historyCard.closest('button') as HTMLButtonElement);
    expect(screen.getByDisplayValue('300')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Shift ID'), { target: { value: '301' } });
    fireEvent.click(screen.getByRole('button', { name: 'Show shift' }));

    await waitFor(() => expect(apiMocks.loadCashierShift).toHaveBeenCalledWith(301));
  });

  it('keeps manual cashier fallback visible when history lookup returns validation errors', async () => {
    arrangeCashierFixtures();
    apiMocks.loadCashierShifts.mockRejectedValueOnce(buildApiError(422, {
      error_code: 'validation_error',
      errors: {
        status: ['Status filter khong hop le.'],
      },
    }));

    renderWithSession(<CashierPage />, createSessionContext());

    expect(await screen.findByText(/status filter khong hop le/i)).toBeInTheDocument();
    expect(screen.getByLabelText('Shift ID')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Show shift' })).toBeInTheDocument();
  });

  it('does not rerun bootstrap history fetches when filter input changes', async () => {
    arrangeCashierFixtures();

    renderWithSession(<CashierPage />, createSessionContext());

    await waitFor(() => expect(apiMocks.loadCurrentCashierShift).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(apiMocks.loadCashierShifts).toHaveBeenCalledTimes(1));

    fireEvent.change(screen.getByLabelText('Shift search'), { target: { value: 'SHIFT' } });
    fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'Closed' } });

    expect(apiMocks.loadCurrentCashierShift).toHaveBeenCalledTimes(1);
    expect(apiMocks.loadCashierShifts).toHaveBeenCalledTimes(1);
  });

  it('shows close-preview variance before the shift is closed', async () => {
    arrangeCashierFixtures();

    renderWithSession(<CashierPage />, createSessionContext());

    await waitFor(() => expect(screen.getByDisplayValue('140000')).toBeInTheDocument());

    fireEvent.change(screen.getByLabelText('Actual cash amount'), { target: { value: '150000' } });

    expect(screen.getByText('Close preview')).toBeInTheDocument();
    expect(screen.getByText('Thua quy')).toBeInTheDocument();
    expect(screen.getByText(/10\.000/)).toBeInTheDocument();
  });
});

function arrangeCashierFixtures() {
  apiMocks.loadCashierShifts.mockResolvedValue({
    data: [
      {
        cashier_shift_id: 300,
        shift_code: 'SHIFT-300',
        status: 'Closed',
        currency: 'VND',
        branch_id: 5,
        branch: {
          branch_id: 5,
          branch_code: 'BR-01',
          branch_name: 'Main Branch',
          is_default: true,
        },
        terminal_code: 'POS-00',
        row_version: 7,
        opening_float_amount: '80000',
        expected_cash_amount: '120000',
        opened_at: '2026-04-07T07:00:00Z',
        closed_at: '2026-04-07T09:00:00Z',
        summary: buildShiftSummary('120000'),
      },
    ],
    meta: {
      total: 1,
    },
  });
  apiMocks.loadCurrentCashierShift.mockResolvedValue({
    data: {
      cashier_shift_id: 301,
      shift_code: 'SHIFT-301',
      status: 'Open',
      currency: 'VND',
      branch_id: 5,
      branch: {
        branch_id: 5,
        branch_code: 'BR-01',
        branch_name: 'Main Branch',
        is_default: true,
      },
      terminal_code: 'POS-01',
      row_version: 8,
      opening_float_amount: '100000',
      expected_cash_amount: '140000',
      summary: buildShiftSummary('140000'),
    },
  });
  apiMocks.openCashierShift.mockResolvedValue({
    data: {
      cashier_shift_id: 301,
      shift_code: 'SHIFT-301',
      status: 'Open',
      currency: 'VND',
      branch_id: 5,
      branch: {
        branch_id: 5,
        branch_code: 'BR-01',
        branch_name: 'Main Branch',
        is_default: true,
      },
      terminal_code: 'POS-01',
      row_version: 8,
      opening_float_amount: '100000',
      expected_cash_amount: '140000',
      summary: buildShiftSummary('140000'),
    },
  });
  apiMocks.closeCashierShift.mockResolvedValue({
    data: {
      cashier_shift_id: 301,
      shift_code: 'SHIFT-301',
      status: 'Closed',
      currency: 'VND',
      branch_id: 5,
      branch: {
        branch_id: 5,
        branch_code: 'BR-01',
        branch_name: 'Main Branch',
        is_default: true,
      },
      terminal_code: 'POS-01',
      row_version: 9,
      opening_float_amount: '100000',
      expected_cash_amount: '140000',
      summary: buildShiftSummary('140000'),
    },
  });
  apiMocks.loadCashierShift.mockResolvedValue({
    data: {
      cashier_shift_id: 301,
      shift_code: 'SHIFT-301',
      status: 'Open',
      currency: 'VND',
      branch_id: 5,
      branch: {
        branch_id: 5,
        branch_code: 'BR-01',
        branch_name: 'Main Branch',
        is_default: true,
      },
      terminal_code: 'POS-01',
      row_version: 8,
      opening_float_amount: '100000',
      expected_cash_amount: '140000',
      summary: buildShiftSummary('140000'),
    },
  });
}

function buildShiftSummary(expectedCashAmount: string) {
  return {
    payments: {
      captured_total: '40000',
      refunded_total: '0',
      payment_count: 1,
      refund_count: 0,
    },
    cash: {
      captured_amount: '40000',
      refunded_amount: '0',
      expected_cash_amount: expectedCashAmount,
    },
    methods: [
      {
        payment_method: 'Cash',
        currency: 'VND',
        captured_amount: '40000',
        refunded_amount: '0',
        net_amount: '40000',
        refund_count: 0,
      },
    ],
  };
}

function createSessionContext(): StaffSessionContextValue {
  return {
    session: buildStaffSession(),
    booting: false,
    notice: null,
    noticeTone: 'success',
    setAuthenticatedSession: vi.fn(),
    setNotice: vi.fn(),
    clearNotice: vi.fn(),
    refresh: vi.fn(),
    logout: vi.fn(),
    expire: vi.fn(),
  };
}
