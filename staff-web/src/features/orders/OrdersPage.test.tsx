import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { OrdersPage } from './OrdersPage';
import { renderWithSession } from '../../test/render';
import { buildApiError, buildStaffSession } from '../../test/fixtures';
import type { StaffSessionContextValue } from '../../app/session-context';

const apiMocks = vi.hoisted(() => ({
  boardWindow: vi.fn(() => ({ from: '2026-04-07T09:00:00Z', to: '2026-04-07T13:00:00Z' })),
  loadTableBoard: vi.fn(),
  loadMenuItems: vi.fn(),
  createTableOrder: vi.fn(),
  loadOrderDetail: vi.fn(),
  loadStaffReservations: vi.fn(),
  loadReservationOrders: vi.fn(),
  addOrderItems: vi.fn(),
  isUnauthorized: vi.fn(() => false),
}));

vi.mock('../../api/client', () => apiMocks);

describe('OrdersPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('creates an order from the selected table reservation row_version', async () => {
    arrangeOrderFixtures();

    renderWithSession(<OrdersPage />, createSessionContext());

    await waitFor(() => expect(screen.getByRole('button', { name: 'Tai active order' })).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Tao order' }));

    await waitFor(() =>
      expect(apiMocks.createTableOrder).toHaveBeenCalledWith(10, {
        reservation_id: 77,
        row_version: 9,
        notes: null,
      }),
    );
  });

  it('adds items to the loaded order with the latest order row_version', async () => {
    arrangeOrderFixtures();

    renderWithSession(<OrdersPage />, createSessionContext());

    await waitFor(() => expect(screen.getByRole('button', { name: 'Tai active order' })).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Tai active order' }));

    await waitFor(() => expect(apiMocks.loadOrderDetail).toHaveBeenCalledWith(9001));

    fireEvent.click(screen.getByRole('button', { name: 'Them item vao order' }));

    await waitFor(() =>
      expect(apiMocks.addOrderItems).toHaveBeenCalledWith(9001, {
        row_version: 14,
        items: [
          {
            menu_item_id: 501,
            qty: 1,
            note: null,
          },
        ],
      }),
    );
  });

  it('falls back to manual IDs when the operator overrides the board source', async () => {
    arrangeOrderFixtures();

    renderWithSession(<OrdersPage />, createSessionContext());

    await waitFor(() => expect(screen.getByRole('button', { name: 'Dung manual IDs' })).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Dung manual IDs' }));
    fireEvent.change(screen.getByLabelText('Table ID'), { target: { value: '15' } });
    fireEvent.change(screen.getByLabelText('Reservation ID'), { target: { value: '88' } });
    fireEvent.change(screen.getByLabelText('Row version'), { target: { value: '11' } });
    fireEvent.click(screen.getByRole('button', { name: 'Tao order' }));

    await waitFor(() =>
      expect(apiMocks.createTableOrder).toHaveBeenCalledWith(15, {
        reservation_id: 88,
        row_version: 11,
        notes: null,
      }),
    );
  });

  it('hydrates board handoff context from the route so the operator can continue without manual reconstruction', async () => {
    arrangeOrderFixtures();

    renderWithSession(
      <OrdersPage />,
      createSessionContext(),
      {
        initialEntries: ['/orders?source=board&table_id=10&reservation_id=77&reservation_row_version=9&order_id=9001'],
      },
    );

    await waitFor(() => expect(apiMocks.loadOrderDetail).toHaveBeenCalledWith(9001));
    expect(screen.getByDisplayValue('10')).toBeInTheDocument();
    expect(screen.getByDisplayValue('77')).toBeInTheDocument();
    expect(screen.getByDisplayValue('9')).toBeInTheDocument();
    expect(screen.getByDisplayValue('9001')).toBeInTheDocument();
  });

  it('exposes settlement and kitchen handoff links with the loaded order context', async () => {
    arrangeOrderFixtures();

    renderWithSession(<OrdersPage />, createSessionContext());

    await waitFor(() => expect(screen.getByRole('button', { name: 'Tai active order' })).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Tai active order' }));

    await waitFor(() => expect(apiMocks.loadOrderDetail).toHaveBeenCalledWith(9001));
    expect(screen.getByRole('link', { name: 'Mo bill snapshot' })).toHaveAttribute(
      'href',
      '/settlement?source=board&table_id=10&reservation_id=77&reservation_row_version=9&order_id=9001',
    );
    expect(screen.getByRole('link', { name: 'Mo Kitchen dispatch' })).toHaveAttribute(
      'href',
      '/kitchen?source=board&table_id=10&reservation_id=77&reservation_row_version=9&order_id=9001&order_row_version=14',
    );
  });

  it('does not expose a settlement handoff link when cashier shift readiness still blocks finance flows', async () => {
    arrangeOrderFixtures();
    const startup = buildStaffSession().startup;

    renderWithSession(
      <OrdersPage />,
      createSessionContext({
        startup: {
          ...startup,
          active_cashier_shift: null,
          readiness: {
            ...startup.readiness,
            cashier_shift: 'action_required',
          },
        },
      }),
    );

    await waitFor(() => expect(screen.getByRole('button', { name: 'Tai active order' })).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Tai active order' }));

    await waitFor(() => expect(apiMocks.loadOrderDetail).toHaveBeenCalledWith(9001));
    expect(screen.queryByRole('link', { name: 'Mo bill snapshot' })).not.toBeInTheDocument();
    expect(screen.getByText(/Can active cashier shift truoc khi mo bill snapshot tu Orders/i)).toBeInTheDocument();
  });

  it('uses canonical reservation lookup to load historical order candidates', async () => {
    arrangeOrderFixtures({
      reservationOrders: [
        {
          order_id: 9102,
          reservation_id: 88,
          order_type: 'OnSpot',
          status: 'Completed',
          row_version: 7,
        },
        {
          order_id: 9103,
          reservation_id: 88,
          order_type: 'OnSpot',
          status: 'Closed',
          row_version: 8,
        },
      ],
    });

    renderWithSession(<OrdersPage />, createSessionContext());

    const lookupCard = (await screen.findAllByText('RES-88'))[0];
    fireEvent.click(lookupCard.closest('button') as HTMLButtonElement);

    await waitFor(() => expect(apiMocks.loadReservationOrders).toHaveBeenCalledWith(88));

    fireEvent.click(screen.getByRole('button', { name: /Order #9103/i }));

    await waitFor(() => expect(apiMocks.loadOrderDetail).toHaveBeenCalledWith(9103));
  });

  it('keeps manual fallback visible when reservation lookup returns forbidden', async () => {
    arrangeOrderFixtures();
    apiMocks.loadStaffReservations.mockRejectedValueOnce(buildApiError(403, {
      error_code: 'forbidden',
      required_capability: 'reservation.manage',
      message: 'Forbidden.',
    }));

    renderWithSession(<OrdersPage />, createSessionContext());

    await waitFor(() => expect(apiMocks.loadStaffReservations).toHaveBeenCalled());
    expect(screen.getByLabelText('Table ID')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Tao order' })).toBeInTheDocument();
  });

  it('auto-loads the canonical order when a reservation has exactly one candidate', async () => {
    arrangeOrderFixtures();

    renderWithSession(<OrdersPage />, createSessionContext());

    const lookupCard = (await screen.findAllByText('RES-88'))[0];
    fireEvent.click(lookupCard.closest('button') as HTMLButtonElement);

    await waitFor(() => expect(apiMocks.loadReservationOrders).toHaveBeenCalledWith(88));
    await waitFor(() => expect(apiMocks.loadOrderDetail).toHaveBeenCalledWith(9102));
    expect(screen.getByDisplayValue('9102')).toBeInTheDocument();
  });

  it('degrades reservation-order lookup failures into a manual fallback path', async () => {
    arrangeOrderFixtures();
    apiMocks.loadReservationOrders.mockRejectedValueOnce(buildApiError(403, {
      error_code: 'forbidden',
      required_capability: 'reservation.manage',
      message: 'Forbidden.',
    }));

    renderWithSession(<OrdersPage />, createSessionContext());

    const lookupCard = (await screen.findAllByText('RES-88'))[0];
    fireEvent.click(lookupCard.closest('button') as HTMLButtonElement);

    await waitFor(() => expect(apiMocks.loadReservationOrders).toHaveBeenCalledWith(88));
    expect(screen.getByLabelText('Order ID')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Order #9102/i })).not.toBeInTheDocument();
  });

  it('does not rerun bootstrap fetches when selection or search input changes', async () => {
    arrangeOrderFixtures();

    renderWithSession(<OrdersPage />, createSessionContext());

    await waitFor(() => expect(apiMocks.loadTableBoard).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(apiMocks.loadStaffReservations).toHaveBeenCalledTimes(1));

    const lookupCard = (await screen.findAllByText('RES-88'))[0];
    fireEvent.click(lookupCard.closest('button') as HTMLButtonElement);

    await waitFor(() => expect(apiMocks.loadReservationOrders).toHaveBeenCalledWith(88));
    expect(apiMocks.loadTableBoard).toHaveBeenCalledTimes(1);
    expect(apiMocks.loadStaffReservations).toHaveBeenCalledTimes(1);

    fireEvent.change(screen.getByLabelText('Reservation search'), { target: { value: 'RES-8' } });

    expect(apiMocks.loadTableBoard).toHaveBeenCalledTimes(1);
    expect(apiMocks.loadStaffReservations).toHaveBeenCalledTimes(1);
  });
});

function arrangeOrderFixtures({
  boardOrderId = 9001,
  reservationOrders = [
    {
      order_id: 9102,
      reservation_id: 88,
      order_type: 'OnSpot',
      status: 'Completed',
      row_version: 7,
    },
  ],
}: {
  boardOrderId?: number | null;
  reservationOrders?: Array<{
    order_id: number;
    reservation_id: number;
    order_type: string;
    status: string;
    row_version: number;
  }>;
} = {}) {
  apiMocks.loadTableBoard.mockResolvedValue({
    data: [
      {
        table_id: 10,
        table_code: 'T1',
        zone: 'Main',
        reservation: {
          reservation_id: 77,
          reservation_code: 'RES-77',
          row_version: 9,
          guest_count: 4,
        },
        active_order: boardOrderId ? {
          order_id: boardOrderId,
        } : null,
      },
    ],
  });

  apiMocks.loadMenuItems.mockResolvedValue({
    data: [
      {
        item_id: 501,
        code: 'MI-501',
        name: 'Pho Bo',
        category_name: 'Noodles',
        description: 'Pho dac biet',
        is_available: true,
        price: {
          amount: '65000',
          currency: 'VND',
        },
      },
    ],
    meta: {
      total: 1,
    },
  });
  apiMocks.loadStaffReservations.mockResolvedValue({
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
  apiMocks.loadReservationOrders.mockResolvedValue({
    data: reservationOrders,
  });

  apiMocks.createTableOrder.mockResolvedValue({
    data: {
      order_id: 9001,
    },
  });

  apiMocks.loadOrderDetail.mockImplementation(async (orderId: number) => ({
    data: {
      order: {
        order_id: orderId,
        row_version: orderId === 9001 ? 14 : 7,
        status: 'Open',
        payment_status: 'Pending',
        created_at: '2026-04-07T09:10:00Z',
        totals: {
          subtotal: '65000',
          total_due: '65000',
          outstanding: '65000',
          currency: 'VND',
        },
      },
      reservation: {
        reservation_code: orderId === 9001 ? 'RES-77' : 'RES-88',
      },
      customer: {
        full_name: 'Nguyen Van A',
      },
      items: [],
      item_summary: {
        quantity_total: 0,
      },
    },
  }));

  apiMocks.addOrderItems.mockResolvedValue({
    data: {
      order_id: 9001,
    },
  });
}

function createSessionContext(overrides: Partial<StaffSessionContextValue['session']> = {}): StaffSessionContextValue {
  return {
    session: buildStaffSession({
      capabilities: ['table.board.view', 'reservation.manage', 'order.manage', 'settlement.manage', 'payment.refund', 'conversation.manage'],
      known_capabilities: ['table.board.view', 'reservation.manage', 'order.manage', 'settlement.manage', 'payment.refund', 'conversation.manage'],
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
