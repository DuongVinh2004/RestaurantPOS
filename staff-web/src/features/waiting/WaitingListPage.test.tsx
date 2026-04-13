import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../../app/store/auth-store';
import { useFlowStore } from '../../app/store/flow-store';
import { buildStaffSession } from '../../test/fixtures';
import { WaitingListPage } from './WaitingListPage';

const apiMocks = vi.hoisted(() => ({
  advanceWaitingListEntry: vi.fn(),
  buildBoardWindow: vi.fn(() => ({
    from: '2026-04-11T10:00:00Z',
    to: '2026-04-11T14:00:00Z',
  })),
  cancelWaitingListEntry: vi.fn(),
  createWaitingListEntry: vi.fn(),
  getTableBoard: vi.fn(),
  getWaitingListChanges: vi.fn(),
  listWaitingList: vi.fn(),
  notifyWaitingListEntry: vi.fn(),
  seatWaitingListEntry: vi.fn(),
}));

vi.mock('../../core/api/staff-api', () => apiMocks);

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();

describe('WaitingListPage', () => {
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
        capabilities: ['table.board.view', 'waiting_list.manage', 'order.manage'],
        known_capabilities: ['table.board.view', 'waiting_list.manage', 'order.manage'],
      }),
      notice: null,
    }, true);

    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 1,
      selectedTableId: null,
    });

    apiMocks.getTableBoard.mockResolvedValue({
      data: [
        {
          table_id: 12,
          table_code: 'T12',
          zone: 'Main',
          capacity: { template_id: 4, seats: 4 },
          availability: {
            accepts_new_assignment: true,
            is_operationally_blocked: false,
            is_realtime_occupied: false,
            has_reservation_in_range: false,
            has_hold_in_range: false,
            requires_deposit_follow_up: false,
          },
        },
        {
          table_id: 16,
          table_code: 'T16',
          zone: 'Patio',
          capacity: { template_id: 6, seats: 6 },
          availability: {
            accepts_new_assignment: true,
            is_operationally_blocked: false,
            is_realtime_occupied: false,
            has_reservation_in_range: false,
            has_hold_in_range: false,
            requires_deposit_follow_up: false,
          },
        },
      ],
    });
    apiMocks.getWaitingListChanges.mockResolvedValue({
      data: {
        current_version: 8,
        events: [],
        poll_hint_ms: 20000,
      },
    });
    apiMocks.listWaitingList.mockResolvedValue(createWaitingListEnvelope([createWaitingEntry()]));
    apiMocks.notifyWaitingListEntry.mockResolvedValue({
      data: {
        ...createWaitingEntry(),
        status: 'Notified',
        row_version: 3,
      },
    });
    apiMocks.seatWaitingListEntry.mockResolvedValue({
      data: {
        waiting_list: {
          ...createSeatReadyWaitingEntry(),
          status: 'Seated',
          row_version: 4,
        },
        reservation: {
          reservation_id: 77,
          reservation_code: 'RSV-WAIT-001',
          row_version: 3,
          table_ids: [12],
        },
      },
    });
  });

  it('reuses the table_id from board journey context when notifying a waiting entry', async () => {
    renderWithProviders('/waiting-list?source=board&table_id=12');

    fireEvent.click(await screen.findByText('Queue Guest'));
    fireEvent.click(await screen.findByRole('button', { name: 'B\u00e1o kh\u00e1ch hi\u1ec7n t\u1ea1i' }));

    await waitFor(() => expect(apiMocks.notifyWaitingListEntry).toHaveBeenCalledWith(51, {
      table_id: 12,
      hold_minutes: null,
      row_version: 2,
    }));
  });

  it('ignores stale shell table context when the waiting entry has no canonical board table', async () => {
    useFlowStore.setState({
      ...useFlowStore.getState(),
      selectedTableId: 16,
    });

    renderWithProviders('/waiting-list');

    fireEvent.click(await screen.findByText('Queue Guest'));
    fireEvent.click(await screen.findByRole('button', { name: 'B\u00e1o kh\u00e1ch hi\u1ec7n t\u1ea1i' }));

    await waitFor(() => expect(apiMocks.notifyWaitingListEntry).toHaveBeenCalledWith(51, {
      table_id: 12,
      hold_minutes: null,
      row_version: 2,
    }));
  });

  it('preserves board source and table context when seating into the order workspace', async () => {
    apiMocks.listWaitingList.mockResolvedValue(createWaitingListEnvelope([createSeatReadyWaitingEntry()]));

    renderWithProviders('/waiting-list?source=board&table_id=12');

    fireEvent.click(await screen.findByText('Queue Guest'));
    fireEvent.click(await screen.findByRole('button', { name: 'X\u1ebfp b\u00e0n v\u00e0 m\u1edf \u0111\u01a1n h\u00e0ng' }));

    await waitFor(() => expect(apiMocks.seatWaitingListEntry).toHaveBeenCalledWith(51, {
      user_id: 99,
      service_minutes: 120,
      notes: 'Front door queue',
      row_version: 2,
    }));
    await waitFor(() => expect(screen.getByTestId('orders-destination')).toBeInTheDocument());
    expect(screen.getByTestId('location-search').textContent).toContain('source=board');
    expect(screen.getByTestId('location-search').textContent).toContain('table_id=12');
    expect(screen.getByTestId('location-search').textContent).toContain('table_ids=12');
    expect(screen.getByTestId('location-search').textContent).toContain('reservation_id=77');
  });

  it('does not emit the disconnected useForm warning when notify form is not rendered', async () => {
    useFlowStore.setState({
      ...useFlowStore.getState(),
      selectedTableId: 12,
    });
    apiMocks.listWaitingList.mockResolvedValue(createWaitingListEnvelope([createSeatReadyWaitingEntry()]));
    const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    const consoleWarnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});

    try {
      renderWithProviders('/waiting-list?source=board&table_id=12');

      await screen.findByText('Queue Guest');

      await waitFor(() => {
        const combinedCalls = [
          consoleErrorSpy.mock.calls.flat().join(' '),
          consoleWarnSpy.mock.calls.flat().join(' '),
        ].join(' ');
        expect(combinedCalls).not.toContain('useForm');
        expect(combinedCalls).not.toContain('not connected to any Form element');
      });
    } finally {
      consoleErrorSpy.mockRestore();
      consoleWarnSpy.mockRestore();
    }
  });

  it('respects the focus query param so linked conversations open the intended waiting entry', async () => {
    apiMocks.listWaitingList.mockResolvedValue(createWaitingListEnvelope([
      createWaitingEntry(),
      createWaitingEntry({
        waiting_id: 77,
        guest_name: 'Focused Guest',
        phone: '0907777777',
        row_version: 5,
      }),
    ]));

    renderWithProviders('/waiting-list?focus=77');

    await screen.findByText('Queue Guest');

    await waitFor(() => expect(screen.getByText('Đang xem #77')).toBeInTheDocument());
    expect(screen.getAllByText('Focused Guest')).toHaveLength(2);
    expect(screen.getByTestId('location-search').textContent).toContain('focus=77');
  });

  it('keeps the queue search input controlled so clearing presets also clears the visible field', async () => {
    renderWithProviders('/waiting-list');

    const searchInput = await screen.findByLabelText('Tìm hàng chờ');
    fireEvent.change(searchInput, { target: { value: '0901234567' } });

    expect(searchInput).toHaveValue('0901234567');

    fireEvent.click(screen.getByRole('button', { name: 'Xóa preset' }));

    await waitFor(() => expect(searchInput).toHaveValue(''));
  });

  it('opens the board without stale shell table search params when no canonical table context exists', async () => {
    useFlowStore.setState({
      ...useFlowStore.getState(),
      selectedTableId: 16,
    });

    renderWithProviders('/waiting-list');

    await screen.findByText('Queue Guest');
    fireEvent.click(screen.getByRole('button', { name: 'M\u1edf s\u01a1 \u0111\u1ed3 b\u00e0n' }));

    await waitFor(() => expect(screen.getByTestId('tables-destination')).toBeInTheDocument());
    expect(screen.getByTestId('location-search')).toHaveTextContent('');
  });
});

function createWaitingEntry(overrides: Record<string, unknown> = {}) {
  return {
    waiting_id: 51,
    branch_id: 1,
    user_id: 99,
    guest_name: 'Queue Guest',
    phone: '0901234567',
    guest_count: 2,
    requested_at: '2026-04-11T09:30:00Z',
    status: 'Waiting',
    priority: 0,
    notified_at: null,
    notify_expires_at: null,
    notified_by: null,
    seated_at: null,
    cancelled_at: null,
    cancel_reason: null,
    notes: 'Front door queue',
    updated_by: null,
    row_version: 2,
    current_response_state: 'none',
    response: {
      status: null,
      responded_at: null,
      confirmed_arrival_at: null,
    },
    invite_window: {
      notified_at: null,
      expires_at: null,
      is_active: false,
      is_expired: false,
      seconds_remaining: 0,
    },
    invite_lifecycle: {
      requires_explicit_staff_seat: true,
      auto_convert_to_reservation: false,
      seat_readiness: 'not_notified',
      customer_next_step: 'none',
      staff_next_step: 'notify_customer',
      can_staff_seat_now: false,
    },
    invite_hold: {
      has_active_hold: false,
      active: null,
      latest: null,
    },
    orchestration: {
      mode: 'semi_automated_waiting_list_orchestration',
      actionable_state: 'wait_in_queue',
      recommended_action: 'keep_waiting_in_queue',
      released_table: null,
      advance_queue: {
        supported: false,
        can_apply_now: false,
        resulting_action: 'none',
        released_table_available: false,
        next_candidate: null,
        disabled_reason: null,
      },
      actions: [
        {
          key: 'seat',
          method: 'POST',
          href: '/api/v1/staff/waiting-list/51/seat',
          enabled: false,
          reason: 'seat_not_ready',
        },
      ],
    },
    user: {
      user_id: 99,
      full_name: 'Queue Guest',
      email: null,
      phone: '0901234567',
    },
    ...overrides,
  };
}

function createSeatReadyWaitingEntry(overrides: Record<string, unknown> = {}) {
  return createWaitingEntry({
    status: 'Notified',
    current_response_state: 'arrival_confirmed',
    notified_at: '2026-04-11T09:45:00Z',
    notify_expires_at: '2026-04-11T10:15:00Z',
    invite_window: {
      notified_at: '2026-04-11T09:45:00Z',
      expires_at: '2026-04-11T10:15:00Z',
      is_active: true,
      is_expired: false,
      seconds_remaining: 600,
    },
    invite_lifecycle: {
      requires_explicit_staff_seat: true,
      auto_convert_to_reservation: false,
      seat_readiness: 'ready_to_seat',
      customer_next_step: 'wait_for_staff',
      staff_next_step: 'seat_customer',
      can_staff_seat_now: true,
    },
    invite_hold: {
      has_active_hold: true,
      active: {
        table_ids: [12],
      },
      latest: {
        table_ids: [12],
      },
    },
    orchestration: {
      mode: 'semi_automated_waiting_list_orchestration',
      actionable_state: 'seat_customer',
      recommended_action: 'seat_current_customer',
      released_table: {
        table_id: 12,
        table_ids: [12],
        table_code: 'T12',
        zone: 'Main',
        status: 'Available',
        seats: 4,
      },
      advance_queue: {
        supported: false,
        can_apply_now: false,
        resulting_action: 'none',
        released_table_available: true,
        next_candidate: null,
        disabled_reason: null,
      },
      actions: [
        {
          key: 'seat',
          method: 'POST',
          href: '/api/v1/staff/waiting-list/51/seat',
          enabled: true,
          reason: 'canonical_staff_seat_flow',
        },
      ],
    },
    ...overrides,
  });
}

function createWaitingListEnvelope(entries: Array<Record<string, unknown>>) {
  return {
    data: entries,
    meta: {
      summary: {
        ready_to_seat_count: entries.filter((entry) => (
          ((entry.orchestration as { actionable_state?: string } | undefined)?.actionable_state) === 'seat_customer'
        )).length,
        advance_queue_ready_count: 0,
        awaiting_customer_follow_up_count: 0,
        hold_investigation_count: 0,
      },
      realtime: {
        current_version: 8,
        poll_hint_ms: 20000,
      },
    },
  };
}

function LocationProbe() {
  const location = useLocation();
  return <div data-testid="location-search">{location.search}</div>;
}

function renderWithProviders(initialEntry: string) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <AntdApp>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={[initialEntry]} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <Routes>
            <Route path="/waiting-list" element={<WaitingListPage />} />
            <Route path="/orders" element={<div data-testid="orders-destination">orders</div>} />
            <Route path="/tables" element={<div data-testid="tables-destination">tables</div>} />
          </Routes>
          <LocationProbe />
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}
