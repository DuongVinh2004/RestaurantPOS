import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { RefundsPage } from './RefundsPage';
import { renderWithSession } from '../../test/render';
import { buildApiError, buildStaffSession } from '../../test/fixtures';
import type { StaffSessionContextValue } from '../../app/session-context';

const apiMocks = vi.hoisted(() => ({
  boardWindow: vi.fn(() => ({ from: '2026-04-07T09:00:00Z', to: '2026-04-07T13:00:00Z' })),
  loadTableBoard: vi.fn(),
  loadStaffReservations: vi.fn(),
  loadRefundPreview: vi.fn(),
  refundReservation: vi.fn(),
  refundAndCancelReservation: vi.fn(),
  isUnauthorized: vi.fn(() => false),
}));

vi.mock('../../api/client', () => apiMocks);

describe('RefundsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('uses canonical reservation lookup as a one-click refund preview source', async () => {
    arrangeRefundFixtures();

    renderWithSession(<RefundsPage />, createSessionContext());

    const suggestion = (await screen.findAllByText('RES-77'))[0];
    fireEvent.click(suggestion.closest('button') as HTMLButtonElement);

    await waitFor(() => expect(apiMocks.loadRefundPreview).toHaveBeenCalledWith(77, expect.any(Object)));
  });

  it('executes refund-only with the row_version from refund preview', async () => {
    arrangeRefundFixtures();

    renderWithSession(<RefundsPage />, createSessionContext());

    await waitFor(() => expect(screen.getByRole('button', { name: 'Refund preview' })).toBeInTheDocument());
    fireEvent.change(screen.getByLabelText('Reservation ID'), { target: { value: '77' } });
    fireEvent.click(screen.getByRole('button', { name: 'Refund preview' }));

    await waitFor(() => expect(apiMocks.loadRefundPreview).toHaveBeenCalledWith(77, expect.any(Object)));

    fireEvent.click(screen.getByRole('button', { name: 'Refund only' }));

    await waitFor(() =>
      expect(apiMocks.refundReservation).toHaveBeenCalledWith(77, expect.objectContaining({
        payment_method: 'Cash',
        payment_provider: 'Cash',
        refund_scope: 'all',
        row_version: 13,
      })),
    );
  });

  it('keeps manual refund path visible when reservation lookup is forbidden', async () => {
    arrangeRefundFixtures();
    apiMocks.loadStaffReservations.mockRejectedValueOnce(buildApiError(403, {
      error_code: 'forbidden',
      required_capability: 'reservation.manage',
      message: 'Forbidden.',
    }));

    renderWithSession(<RefundsPage />, createSessionContext());

    expect(await screen.findByText(/reservation\.manage/i)).toBeInTheDocument();
    expect(screen.getByLabelText('Reservation ID')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Refund preview' })).toBeInTheDocument();
  });

  it('does not rerun bootstrap fetches when search input changes', async () => {
    arrangeRefundFixtures();

    renderWithSession(<RefundsPage />, createSessionContext());

    await waitFor(() => expect(apiMocks.loadTableBoard).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(apiMocks.loadStaffReservations).toHaveBeenCalledTimes(1));

    fireEvent.change(screen.getByLabelText('Reservation search'), { target: { value: 'RES-7' } });

    expect(apiMocks.loadTableBoard).toHaveBeenCalledTimes(1);
    expect(apiMocks.loadStaffReservations).toHaveBeenCalledTimes(1);
  });

  it('requires preview refresh when refund inputs drift after preview', async () => {
    arrangeRefundFixtures();

    renderWithSession(<RefundsPage />, createSessionContext());

    await waitFor(() => expect(screen.getByRole('button', { name: 'Refund preview' })).toBeInTheDocument());
    fireEvent.change(screen.getByLabelText('Reservation ID'), { target: { value: '77' } });
    fireEvent.click(screen.getByRole('button', { name: 'Refund preview' }));

    await waitFor(() => expect(apiMocks.loadRefundPreview).toHaveBeenCalledWith(77, expect.any(Object)));

    fireEvent.change(screen.getByLabelText('Refund scope'), { target: { value: 'deposit' } });

    expect(screen.getByRole('button', { name: 'Refund only' })).toBeDisabled();
    expect(screen.getByText(/preview hien tai khong con khop/i)).toBeInTheDocument();
  });
});

function arrangeRefundFixtures(overrides: { board?: Record<string, unknown> } = {}) {
  apiMocks.loadTableBoard.mockResolvedValue(overrides.board ?? { data: [] });
  apiMocks.loadStaffReservations.mockResolvedValue({
    data: [
      {
        reservation_id: 77,
        reservation_code: 'RES-77',
        status: 'Completed',
        source: 'staff',
        guest_count: 4,
        start_time: '2026-04-07T09:00:00Z',
        end_time: '2026-04-07T11:00:00Z',
        checked_in_at: null,
        checked_out_at: null,
        cancelled_at: null,
        cancel_reason: null,
        no_show_at: null,
        notes: null,
        row_version: 13,
        created_at: '2026-04-07T08:00:00Z',
        updated_at: '2026-04-07T08:30:00Z',
        user: {
          user_id: 3,
          full_name: 'Nguyen Van A',
          email: 'nguyen@example.test',
          phone: '0909000111',
        },
        table_ids: [10],
        tables: [
          {
            table_id: 10,
            table_code: 'T1',
            zone: 'Main',
            status: 'Occupied',
            seats: 4,
          },
        ],
        summary: {
          table_count: 1,
          is_active: false,
          is_checked_in: false,
          is_cancelled: false,
          is_completed: true,
          deposit_acknowledged: false,
          deposit_intent_submitted: false,
          deposit_self_service_follow_up: false,
        },
        deposit_self_service: {},
        financials: null,
      },
    ],
  });
  apiMocks.loadRefundPreview.mockResolvedValue({
    data: {
      reservation: {
        reservation_id: 77,
        reservation_code: 'RES-77',
        row_version: 13,
      },
      refund: {
        refund_payment_ids: [1],
        refund_amount: '65000',
        currency: 'VND',
        refund_scope: 'all',
        cancelled: false,
        reservation_status: 'CheckedIn',
        payment_summary: {
          deposit_net: '15000',
          final_net: '50000',
          refunded_total: '0',
          net_paid_total: '65000',
        },
      },
    },
  });
  apiMocks.refundReservation.mockResolvedValue({ data: { reservation_id: 77 } });
  apiMocks.refundAndCancelReservation.mockResolvedValue({ data: { reservation_id: 77 } });
}

function createSessionContext(): StaffSessionContextValue {
  return {
    session: buildStaffSession({
      capabilities: ['table.board.view', 'reservation.manage', 'order.manage', 'settlement.manage', 'payment.refund', 'conversation.manage'],
      known_capabilities: ['table.board.view', 'reservation.manage', 'order.manage', 'settlement.manage', 'payment.refund', 'conversation.manage'],
    }),
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
