import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SettlementPage } from './SettlementPage';
import { renderWithSession } from '../../test/render';
import { buildStaffSession } from '../../test/fixtures';
import { RestaurantPosApiError } from '../../core/api/sdk';
import type { StaffSessionContextValue } from '../../app/session-context';

const apiMocks = vi.hoisted(() => ({
  buildBoardWindow: vi.fn(() => ({ from: '2026-04-07T09:00:00Z', to: '2026-04-07T13:00:00Z' })),
  getTableBoard: vi.fn(),
  listReservations: vi.fn(),
  listReservationOrders: vi.fn(),
  getOrderDetail: vi.fn(),
  getSettlementPreview: vi.fn(),
  createBillSnapshot: vi.fn(),
  finalizeSettlement: vi.fn(),
}));

vi.mock('../../core/api/staff-api', () => apiMocks);

describe('SettlementPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('finalizes settlement with the current order row_version', async () => {
    arrangeSettlementFixtures();

    renderWithSession(<SettlementPage />, createSessionContext());

    await waitFor(() => expect(screen.getByRole('button', { name: 'Tai order' })).toBeInTheDocument());
    expect(apiMocks.getTableBoard).toHaveBeenCalledWith({
      from: '2026-04-07T09:00:00Z',
      to: '2026-04-07T13:00:00Z',
      include_holds: true,
      group_by: 'zone',
    });
    expect(apiMocks.listReservations).toHaveBeenCalledWith({
      bucket: 'all',
      q: undefined,
      per_page: 8,
      sort: '-start_time',
    });
    fireEvent.change(screen.getByLabelText('Order ID'), { target: { value: '9001' } });
    fireEvent.click(screen.getByRole('button', { name: 'Tai order' }));

    await waitFor(() => expect(apiMocks.getOrderDetail).toHaveBeenCalledWith(9001));

    fireEvent.click(screen.getByRole('button', { name: 'Settlement preview' }));

    await waitFor(() => expect(apiMocks.getSettlementPreview).toHaveBeenCalledWith(9001, expect.any(Object)));

    fireEvent.click(screen.getByRole('button', { name: 'Finalize settlement' }));

    await waitFor(() =>
      expect(apiMocks.finalizeSettlement).toHaveBeenCalledWith(9001, expect.objectContaining({
        payment_method: 'Cash',
        payment_provider: 'Cash',
        paid_amount: 65000,
        currency: 'VND',
        row_version: 21,
      })),
    );
  });

  it('hydrates board handoff context from the route and auto-loads the hinted order', async () => {
    arrangeSettlementFixtures();

    renderWithSession(
      <SettlementPage />,
      createSessionContext(),
      {
        initialEntries: ['/settlement?source=board&table_id=10&reservation_id=77&order_id=9001'],
      },
    );

    await waitFor(() => expect(apiMocks.getOrderDetail).toHaveBeenCalledWith(9001));
    expect(screen.getByDisplayValue('9001')).toBeInTheDocument();
    expect(screen.getByText('Order #9001')).toBeInTheDocument();
  });

  it('auto-loads the canonical order when a reservation has exactly one candidate', async () => {
    arrangeSettlementFixtures();

    renderWithSession(<SettlementPage />, createSessionContext());

    const lookupCard = (await screen.findAllByText('RES-88'))[0];
    fireEvent.click(lookupCard.closest('button') as HTMLButtonElement);

    await waitFor(() => expect(apiMocks.listReservationOrders).toHaveBeenCalledWith(88));
    await waitFor(() => expect(apiMocks.getOrderDetail).toHaveBeenCalledWith(9102));
    expect(screen.getByDisplayValue('9102')).toBeInTheDocument();
  });

  it('keeps manual order fallback visible when reservation lookup is forbidden', async () => {
    arrangeSettlementFixtures();
    apiMocks.listReservations.mockRejectedValueOnce(buildCoreApiError(403, {
      error_code: 'forbidden',
      required_capability: 'reservation.manage',
      message: 'Forbidden.',
    }));

    renderWithSession(<SettlementPage />, createSessionContext());

    await waitFor(() => expect(apiMocks.listReservations).toHaveBeenCalled());
    expect(screen.getByLabelText('Order ID')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Settlement preview' })).toBeInTheDocument();
  });

  it('keeps manual fallback active when session lacks reservation.manage', async () => {
    arrangeSettlementFixtures();

    renderWithSession(
      <SettlementPage />,
      createSessionContext({
        capabilities: ['settlement.manage'],
        known_capabilities: ['settlement.manage'],
      }),
    );

    expect(await screen.findByText(/Reservation lookup khong duoc cap quyen/i)).toBeInTheDocument();
    expect(screen.getByLabelText('Order ID')).toBeInTheDocument();
  });

  it('degrades reservation-order lookup failures into a manual fallback path', async () => {
    arrangeSettlementFixtures();
    apiMocks.listReservationOrders.mockRejectedValueOnce(buildCoreApiError(403, {
      error_code: 'forbidden',
      required_capability: 'reservation.manage',
      message: 'Forbidden.',
    }));

    renderWithSession(<SettlementPage />, createSessionContext());

    const lookupCard = (await screen.findAllByText('RES-88'))[0];
    fireEvent.click(lookupCard.closest('button') as HTMLButtonElement);

    await waitFor(() => expect(apiMocks.listReservationOrders).toHaveBeenCalledWith(88));
    expect(screen.getByLabelText('Order ID')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Order #9102/i })).not.toBeInTheDocument();
  });

  it('refreshes canonical lookup data after finalize to avoid stale reservation context', async () => {
    arrangeSettlementFixtures({
      boardOrderId: null,
      reservationOrderId: 9102,
    });

    renderWithSession(<SettlementPage />, createSessionContext());

    const lookupCard = (await screen.findAllByText('RES-88'))[0];
    fireEvent.click(lookupCard.closest('button') as HTMLButtonElement);

    await waitFor(() => expect(apiMocks.getOrderDetail).toHaveBeenCalledWith(9102));
    apiMocks.listReservations.mockClear();
    apiMocks.listReservationOrders.mockClear();

    fireEvent.click(screen.getByRole('button', { name: 'Settlement preview' }));

    await waitFor(() => expect(apiMocks.getSettlementPreview).toHaveBeenCalledWith(9102, expect.any(Object)));

    fireEvent.click(screen.getByRole('button', { name: 'Finalize settlement' }));

    await waitFor(() => expect(apiMocks.finalizeSettlement).toHaveBeenCalledWith(9102, expect.objectContaining({
      row_version: 7,
    })));
    await waitFor(() => expect(apiMocks.listReservations).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(apiMocks.listReservationOrders).toHaveBeenCalledTimes(1));
  });

  it('does not rerun bootstrap fetches when reservation selection or search input changes', async () => {
    arrangeSettlementFixtures({
      boardOrderId: null,
      reservationOrderId: 9102,
    });

    renderWithSession(<SettlementPage />, createSessionContext());

    await waitFor(() => expect(apiMocks.getTableBoard).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(apiMocks.listReservations).toHaveBeenCalledTimes(1));

    const lookupCard = (await screen.findAllByText('RES-88'))[0];
    fireEvent.click(lookupCard.closest('button') as HTMLButtonElement);

    await waitFor(() => expect(apiMocks.listReservationOrders).toHaveBeenCalledWith(88));
    expect(apiMocks.getTableBoard).toHaveBeenCalledTimes(1);
    expect(apiMocks.listReservations).toHaveBeenCalledTimes(1);

    fireEvent.change(screen.getByLabelText('Reservation search'), { target: { value: 'RES-8' } });

    expect(apiMocks.getTableBoard).toHaveBeenCalledTimes(1);
    expect(apiMocks.listReservations).toHaveBeenCalledTimes(1);
  });

  it('requires a fresh preview when settlement currency changes after preview', async () => {
    arrangeSettlementFixtures();

    renderWithSession(<SettlementPage />, createSessionContext());

    await waitFor(() => expect(screen.getByRole('button', { name: 'Tai order' })).toBeInTheDocument());
    fireEvent.change(screen.getByLabelText('Order ID'), { target: { value: '9001' } });
    fireEvent.click(screen.getByRole('button', { name: 'Tai order' }));

    await waitFor(() => expect(apiMocks.getOrderDetail).toHaveBeenCalledWith(9001));

    fireEvent.click(screen.getByRole('button', { name: 'Settlement preview' }));

    await waitFor(() => expect(apiMocks.getSettlementPreview).toHaveBeenCalledWith(9001, expect.any(Object)));

    fireEvent.change(screen.getByLabelText('Currency'), { target: { value: 'USD' } });

    expect(screen.getByRole('button', { name: 'Finalize settlement' })).toBeDisabled();
    expect(screen.getByText(/currency da thay doi sau preview/i)).toBeInTheDocument();
  });
});

function arrangeSettlementFixtures({
  boardOrderId = 9001,
  reservationOrderId = 9102,
}: {
  boardOrderId?: number | null;
  reservationOrderId?: number;
} = {}) {
  apiMocks.getTableBoard.mockResolvedValue({
    data: [
      {
        table_id: 10,
        table_code: 'T1',
        reservation: {
          reservation_id: 77,
          reservation_code: 'RES-77',
        },
        active_order: boardOrderId ? { order_id: boardOrderId } : null,
      },
    ],
  });
  apiMocks.listReservations.mockResolvedValue({
    data: [
      {
        reservation_id: 88,
        reservation_code: 'RES-88',
        status: 'Completed',
        source: 'staff',
        guest_count: 2,
        start_time: '2026-04-07T08:00:00Z',
        end_time: '2026-04-07T09:00:00Z',
        checked_in_at: null,
        checked_out_at: null,
        cancelled_at: null,
        cancel_reason: null,
        no_show_at: null,
        notes: null,
        row_version: 12,
        created_at: '2026-04-07T08:00:00Z',
        updated_at: '2026-04-07T08:30:00Z',
        user: {
          user_id: 5,
          full_name: 'Tran Thi B',
          email: 'tran@example.test',
          phone: '0909000222',
        },
        table_ids: [15],
        tables: [
          {
            table_id: 15,
            table_code: 'T15',
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
  apiMocks.listReservationOrders.mockResolvedValue({
    data: [
      {
        order_id: reservationOrderId,
        reservation_id: 88,
        order_type: 'OnSpot',
        status: 'Completed',
        row_version: 7,
      },
    ],
  });
  apiMocks.getOrderDetail.mockImplementation(async (orderId: number) => ({
    data: {
      order: {
        order_id: orderId,
        row_version: orderId === reservationOrderId ? 7 : 21,
        created_at: '2026-04-07T09:05:00Z',
        totals: {
          outstanding: '65000',
          currency: 'VND',
        },
      },
      reservation: {
        reservation_code: orderId === reservationOrderId ? 'RES-88' : 'RES-77',
      },
    },
  }));
  apiMocks.getSettlementPreview.mockImplementation(async (orderId: number) => ({
    data: {
      order_id: orderId,
      reservation_id: orderId === reservationOrderId ? 88 : 77,
      row_version: orderId === reservationOrderId ? 7 : 21,
      total_amount: 65000,
      currency: 'VND',
      paid_amount: 0,
      deposit_applied_amount: 0,
      final_paid_amount: 0,
      outstanding_amount: 65000,
      payment_status: 'Pending',
      order_status: 'Open',
      reservation_status: 'CheckedIn',
    },
  }));
  apiMocks.createBillSnapshot.mockResolvedValue({ data: { order_id: 9001 } });
  apiMocks.finalizeSettlement.mockImplementation(async (orderId: number) => ({
    data: {
      order_id: orderId,
      reservation_id: orderId === reservationOrderId ? 88 : 77,
      row_version: orderId === reservationOrderId ? 8 : 22,
      total_amount: 65000,
      currency: 'VND',
      paid_amount: 65000,
      deposit_applied_amount: 0,
      final_paid_amount: 65000,
      outstanding_amount: 0,
      payment_status: 'Paid',
      order_status: 'Closed',
      reservation_status: 'Completed',
    },
  }));
}

function buildCoreApiError<T>(status: number, payload: T, message = 'API request failed') {
  return new RestaurantPosApiError(message, status, payload);
}

function createSessionContext(overrides: Partial<StaffSessionContextValue['session']> = {}): StaffSessionContextValue {
  return {
    session: buildStaffSession({
      capabilities: ['table.board.view', 'reservation.manage', 'settlement.manage'],
      known_capabilities: ['table.board.view', 'reservation.manage', 'settlement.manage'],
      ...overrides,
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
