import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { buildStaffSession } from '../../../../test/fixtures';
import { ReservationsPage } from './ReservationsPage';
import { shouldLookupActiveOrder } from '../../../../domains/reservations/reservation-active-order';

const confirmActionMock = vi.hoisted(() => vi.fn(async () => true));
const apiMocks = vi.hoisted(() => ({
  assignBestFitTable: vi.fn(),
  assignSuggestedTable: vi.fn(),
  checkInReservation: vi.fn(),
  createReservation: vi.fn(),
  getActiveOrderByReservation: vi.fn(),
  getReservationDetail: vi.fn(),
  getTableBoard: vi.fn(),
  listReservations: vi.fn(),
  updateReservationStatus: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', () => apiMocks);
vi.mock('../../../../shared/hooks/useConfirmAction', () => ({
  useConfirmAction: () => confirmActionMock,
}));
vi.mock('../../../../shared/ui/drawers/ReservationDetailDrawer', () => ({
  ReservationDetailDrawer: ({
    open,
    reservation,
    onCheckIn,
    onCancelReservation,
    onOpenOrder,
    onOpenCheckout,
  }: {
    open: boolean;
    reservation: { reservation_code: string; status: string } | null;
    onCheckIn?: () => void;
    onCancelReservation?: () => void;
    onOpenOrder?: () => void;
    onOpenCheckout?: () => void;
  }) => (open && reservation ? (
    <div data-testid="reservation-detail-drawer">
      <span>{reservation.reservation_code}</span>
      <span>{reservation.status}</span>
      {onCheckIn ? <button type="button" onClick={onCheckIn}>Check in now</button> : null}
      {onCancelReservation ? <button type="button" onClick={onCancelReservation}>Cancel reservation now</button> : null}
      {onOpenOrder ? <button type="button" onClick={onOpenOrder}>Open order now</button> : null}
      {onOpenCheckout ? <button type="button" onClick={onOpenCheckout}>Open checkout now</button> : null}
    </div>
  ) : null),
}));

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();

describe('ReservationsPage', () => {
  beforeAll(() => {
    const baseGetComputedStyle = window.getComputedStyle.bind(window);
    Object.defineProperty(window, 'getComputedStyle', {
      writable: true,
      value: (element: Element) => baseGetComputedStyle(element),
    });

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
        capabilities: ['reservation.manage', 'table.board.view', 'settlement.manage'],
        known_capabilities: ['reservation.manage', 'table.board.view', 'settlement.manage'],
      }),
      notice: null,
    }, true);

    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 1,
      selectedTableId: null,
    });

    apiMocks.listReservations.mockResolvedValue({
      data: [createReservationLookupEntry()],
      meta: {},
    });

    apiMocks.getTableBoard.mockResolvedValue({
      data: [
        createBoardTableOption({
          table_id: 12,
          table_code: 'T12',
          zone: 'Main',
          seats: 4,
        }),
        createBoardTableOption({
          table_id: 14,
          table_code: 'T14',
          zone: 'Patio',
          seats: 6,
        }),
      ],
      zones: [],
      summary: {
        zone_count: 1,
        active_order_count: 0,
        unassigned_reservation_count: 0,
        unassigned_with_slot_only_candidate_count: 0,
        deposit_acknowledged_reservation_count: 0,
        deposit_intent_submitted_reservation_count: 0,
        deposit_self_service_follow_up_count: 0,
      },
      unassigned_reservations: [],
      orchestration: {
        mode: 'board',
        write_side: {
          assign_suggested_table_supported: true,
          assign_best_fit_supported: true,
          assign_suggested_table_requires_current_candidate: true,
        },
        capacity_policy: {
          close_fit_max_extra_seats: 2,
        },
      },
      meta: {
        filters: {},
        sort: {
          supported: false,
          value: null,
          by: null,
          dir: null,
        },
        pagination: {
          mode: 'none',
          supported: false,
        },
        query_contract: {},
        action: 'board_snapshot',
        supported_actions: {},
        realtime: {
          current_version: 5,
          poll_hint_ms: 20000,
        },
      },
    });

    apiMocks.createReservation.mockResolvedValue({
      data: {
        reservation_id: 91,
        reservation_code: 'RSV-PHONE-002',
        row_version: 7,
      },
    });
    apiMocks.updateReservationStatus.mockResolvedValue({
      data: createReservationLookupEntry({
        status: 'Cancelled',
        cancelled_at: '2026-04-11T12:05:00Z',
        row_version: 8,
        summary: {
          table_count: 2,
          is_active: false,
          is_checked_in: false,
          is_cancelled: true,
          is_completed: false,
          deposit_acknowledged: false,
          deposit_intent_submitted: false,
          deposit_self_service_follow_up: false,
        },
      }),
    });

    apiMocks.getReservationDetail.mockResolvedValue({
      data: createReservationLookupEntry(),
    });

    apiMocks.getActiveOrderByReservation.mockRejectedValue({ status: 404 });
  });

  it('creates a reservation from the reservations page even when no table is preselected', async () => {
    const view = renderWithProviders('/ops/reservations');

    fireEvent.click(await screen.findByRole('button', { name: /Tạo đặt bàn hộ/i }));

    const dialog = await screen.findByRole('dialog');
    fireEvent.change(within(dialog).getByPlaceholderText(/Khách gọi điện…/i), {
      target: { value: 'Caller Guest' },
    });
    fireEvent.change(within(dialog).getByPlaceholderText('090…'), {
      target: { value: '0905566778' },
    });
    fireEvent.change(within(dialog).getByLabelText('Email'), {
      target: { value: 'caller.guest@example.test' },
    });

    const tableSelect = within(dialog).getByRole('combobox');
    fireEvent.mouseDown(tableSelect);
    fireEvent.click(await screen.findByRole('option', { name: /T12/i }));
    fireEvent.mouseDown(tableSelect);
    fireEvent.click(await screen.findByRole('option', { name: /T14/i }));

    const submitButtons = within(dialog).getAllByRole('button', { name: /Tạo đặt bàn hộ/i });
    fireEvent.click(submitButtons[submitButtons.length - 1]);

    await waitFor(() => expect(apiMocks.createReservation).toHaveBeenCalledTimes(1));

    const payload = apiMocks.createReservation.mock.calls[0][0];
    expect(payload).toMatchObject({
      branch_id: 1,
      guest_name: 'Caller Guest',
      guest_phone: '0905566778',
      guest_email: 'caller.guest@example.test',
      guest_count: 2,
      table_ids: [12, 14],
    });

    await waitFor(() => {
      expect(screen.getByTestId('location-search').textContent).toContain('reservation=91');
    });

    expect(await screen.findByText(/Khách snapshot/i)).toBeInTheDocument();
    expect(view.container.querySelector('[data-testid="reservation-detail-drawer"]')).not.toBeNull();
  });

  it('renders an explicit snapshot guest badge for reservation list rows', async () => {
    renderWithProviders('/ops/reservations');

    expect(await screen.findByText(/Khách snapshot/i)).toBeInTheDocument();
    expect(screen.getByText('Caller Guest')).toBeInTheDocument();
  });

  it('labels reservation filters and search so the page header controls stay accessible', async () => {
    renderWithProviders('/ops/reservations');

    await screen.findByText('RSV-PHONE-002');

    expect(screen.getByLabelText('Lọc danh sách đặt bàn')).toBeInTheDocument();
    expect(screen.getByLabelText('Tìm đặt bàn')).toBeInTheDocument();
  });

  it('does not look up an active order before the reservation is checked in', () => {
    expect(shouldLookupActiveOrder(createReservationLookupEntry({
      checked_in_at: null,
      status: 'Confirmed',
    }))).toBe(false);
  });

  it('looks up the active order once the reservation is checked in', () => {
    expect(shouldLookupActiveOrder(createReservationLookupEntry({
      checked_in_at: '2026-04-11T11:55:00Z',
      status: 'CheckedIn',
      summary: {
        table_count: 2,
        is_active: true,
        is_checked_in: true,
        is_cancelled: false,
        is_completed: false,
        deposit_acknowledged: false,
        deposit_intent_submitted: false,
        deposit_self_service_follow_up: false,
      },
    }))).toBe(true);
  });

  it('reuses the canonical active-order reservation cache when opening the order workspace', async () => {
    const activeReservation = createReservationLookupEntry({
      checked_in_at: '2026-04-11T11:55:00Z',
      status: 'CheckedIn',
      summary: {
        table_count: 2,
        is_active: true,
        is_checked_in: true,
        is_cancelled: false,
        is_completed: false,
        deposit_acknowledged: false,
        deposit_intent_submitted: false,
        deposit_self_service_follow_up: false,
      },
    });

    apiMocks.listReservations.mockResolvedValue({
      data: [activeReservation],
      meta: {},
    });
    apiMocks.getReservationDetail.mockResolvedValue({
      data: activeReservation,
    });

    const queryClient = new QueryClient({
      defaultOptions: {
        queries: { retry: false, staleTime: Number.POSITIVE_INFINITY },
        mutations: { retry: false },
      },
    });

    queryClient.setQueryData(['active-order-by-reservation', 91], {
      data: {
        order: {
          order_id: 777,
          row_version: 9,
        },
        table: {
          table_id: 12,
        },
      },
    });

    renderWithProviders('/ops/reservations?reservation=91', queryClient);

    await screen.findByTestId('reservation-detail-drawer');
    fireEvent.click(await screen.findByRole('button', { name: 'Open order now' }));

    await waitFor(() => expect(screen.getByTestId('orders-destination')).toBeInTheDocument());
    expect(screen.getByTestId('location-search').textContent).toContain('order_id=777');
    expect(apiMocks.getActiveOrderByReservation).not.toHaveBeenCalled();
  });

  it('opens checkout directly from an active reservation without another navigation hop', async () => {
    const activeReservation = createReservationLookupEntry({
      checked_in_at: '2026-04-11T11:55:00Z',
      status: 'CheckedIn',
      summary: {
        table_count: 2,
        is_active: true,
        is_checked_in: true,
        is_cancelled: false,
        is_completed: false,
        deposit_acknowledged: false,
        deposit_intent_submitted: false,
        deposit_self_service_follow_up: false,
      },
    });

    apiMocks.listReservations.mockResolvedValue({
      data: [activeReservation],
      meta: {},
    });
    apiMocks.getReservationDetail.mockResolvedValue({
      data: activeReservation,
    });

    const queryClient = new QueryClient({
      defaultOptions: {
        queries: { retry: false, staleTime: Number.POSITIVE_INFINITY },
        mutations: { retry: false },
      },
    });

    queryClient.setQueryData(['active-order-by-reservation', 91], {
      data: {
        order: {
          order_id: 777,
          row_version: 9,
        },
        table: {
          table_id: 12,
        },
      },
    });

    renderWithProviders('/ops/reservations?reservation=91', queryClient);

    await screen.findByTestId('reservation-detail-drawer');
    fireEvent.click(await screen.findByRole('button', { name: 'Open checkout now' }));

    await waitFor(() => expect(screen.getByTestId('checkout-destination')).toBeInTheDocument());
    expect(screen.getByTestId('location-search').textContent).toContain('order_id=777');
    expect(screen.getByTestId('location-search').textContent).toContain('reservation_id=91');
  });

  it('refetches reservation detail after checking in from the reservation workspace', async () => {
    const confirmedReservation = createReservationLookupEntry();
    const checkedInReservation = createReservationLookupEntry({
      status: 'CheckedIn',
      checked_in_at: '2026-04-11T11:55:00Z',
      row_version: 8,
      summary: {
        table_count: 2,
        is_active: true,
        is_checked_in: true,
        is_cancelled: false,
        is_completed: false,
        deposit_acknowledged: false,
        deposit_intent_submitted: false,
        deposit_self_service_follow_up: false,
      },
    });

    apiMocks.listReservations.mockResolvedValue({
      data: [confirmedReservation],
      meta: {},
    });
    apiMocks.getReservationDetail
      .mockResolvedValueOnce({ data: confirmedReservation })
      .mockResolvedValueOnce({ data: checkedInReservation });
    apiMocks.checkInReservation.mockResolvedValue({
      data: checkedInReservation,
    });

    renderWithProviders('/ops/reservations?reservation=91');

    await screen.findByTestId('reservation-detail-drawer');
    fireEvent.click(await screen.findByRole('button', { name: 'Check in now' }));

    await waitFor(() => expect(apiMocks.checkInReservation).toHaveBeenCalledWith(91, {
      row_version: 7,
      table_ids: [12, 14],
    }));
    await waitFor(() => expect(apiMocks.getReservationDetail).toHaveBeenCalledTimes(2));
  });

  it('cancels a confirmed reservation with explicit confirmation and success feedback', async () => {
    renderWithProviders('/ops/reservations?reservation=91');

    await screen.findByTestId('reservation-detail-drawer');
    fireEvent.click(await screen.findByRole('button', { name: 'Cancel reservation now' }));

    await waitFor(() => expect(confirmActionMock).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(apiMocks.updateReservationStatus).toHaveBeenCalledWith(91, {
      status: 'Cancelled',
      row_version: 7,
    }));
    await waitFor(() => expect(screen.getByTestId('mutation-status-notice')).toHaveAttribute('data-phase', 'succeeded'));
  });
});

function createBoardTableOption(overrides: { table_id: number; table_code: string; zone: string; seats: number }) {
  return {
    table_id: overrides.table_id,
    table_code: overrides.table_code,
    zone: overrides.zone,
    pos_x: null,
    pos_y: null,
    realtime_status: 'Available',
    board_state: 'Available',
    reservations: [],
    holds: [],
    reservation: null,
    hold: null,
    capacity: {
      template_id: overrides.table_id,
      seats: overrides.seats,
    },
    availability: {
      accepts_new_assignment: true,
      is_operationally_blocked: false,
      is_realtime_occupied: false,
      has_reservation_in_range: false,
      has_hold_in_range: false,
      requires_deposit_follow_up: false,
    },
    operational_hints: {
      assignment_candidate: true,
      preferred_action: 'create_reservation',
    },
    actions: {
      check_in: null,
      move_table: null,
    },
    candidate_reservations: [],
    current_fit: null,
    active_order: null,
  };
}

function createReservationLookupEntry(overrides: Record<string, unknown> = {}) {
  return {
    reservation_id: 91,
    reservation_code: 'RSV-PHONE-002',
    status: 'Confirmed',
    source: 'Offline',
    guest_count: 2,
    start_time: '2026-04-11T12:00:00Z',
    end_time: '2026-04-11T14:00:00Z',
    checked_in_at: null,
    checked_out_at: null,
    cancelled_at: null,
    cancel_reason: null,
    no_show_at: null,
    notes: 'Phone-in reservation',
    row_version: 7,
    created_at: '2026-04-11T08:00:00Z',
    updated_at: '2026-04-11T08:00:00Z',
    user: {
      user_id: null,
      full_name: 'Caller Guest',
      email: 'caller.guest@example.test',
      phone: '0905566778',
    },
    guest: {
      full_name: 'Caller Guest',
      email: 'caller.guest@example.test',
      phone: '0905566778',
      is_snapshot_only: true,
    },
    table_ids: [12, 14],
    tables: [
      {
        table_id: 12,
        table_code: 'T12',
        zone: 'Main',
        status: 'Available',
        seats: 4,
      },
      {
        table_id: 14,
        table_code: 'T14',
        zone: 'Patio',
        status: 'Available',
        seats: 6,
      },
    ],
    summary: {
      table_count: 2,
      is_active: true,
      is_checked_in: false,
      is_cancelled: false,
      is_completed: false,
      deposit_acknowledged: false,
      deposit_intent_submitted: false,
      deposit_self_service_follow_up: false,
    },
    deposit_self_service: {},
    financials: null,
    ...overrides,
  };
}

function LocationProbe() {
  const location = useLocation();
  return <div data-testid="location-search">{location.search}</div>;
}

function renderWithProviders(initialEntry: string, queryClient?: QueryClient) {
  return render(
    <AntdApp>
      <QueryClientProvider client={queryClient ?? new QueryClient({
        defaultOptions: {
          queries: { retry: false },
          mutations: { retry: false },
        },
      })}>
        <MemoryRouter initialEntries={[initialEntry]}>
          <Routes>
            <Route
              path="/ops/reservations"
              element={(
                <>
                  <ReservationsPage />
                </>
              )}
            />
            <Route path="/ops/orders" element={<div data-testid="orders-destination">orders</div>} />
            <Route path="/ops/checkout" element={<div data-testid="checkout-destination">checkout</div>} />
          </Routes>
          <LocationProbe />
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}
