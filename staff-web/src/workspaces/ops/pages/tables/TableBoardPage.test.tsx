import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { buildStaffSession } from '../../../../test/fixtures';
import { TableBoardPage } from './TableBoardPage';

const confirmActionMock = vi.hoisted(() => vi.fn(async () => true));
const apiMocks = vi.hoisted(() => ({
  assignBestFitTable: vi.fn(),
  assignSuggestedTable: vi.fn(),
  buildBoardWindow: vi.fn(() => ({
    from: '2026-04-11T10:00:00Z',
    to: '2026-04-11T14:00:00Z',
  })),
  checkInReservation: vi.fn(),
  createReservation: vi.fn(),
  createWalkInSession: vi.fn(),
  getTableBoard: vi.fn(),
  getTableBoardChanges: vi.fn(),
  moveReservationTable: vi.fn(),
  releaseStaffTable: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', () => apiMocks);
vi.mock('../../../../shared/hooks/useConfirmAction', () => ({
  useConfirmAction: () => confirmActionMock,
}));

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();

describe('TableBoardPage', () => {
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
        capabilities: ['table.board.view', 'reservation.manage', 'waiting_list.manage', 'table.release'],
        known_capabilities: ['table.board.view', 'reservation.manage', 'waiting_list.manage', 'table.release'],
      }),
      notice: null,
    }, true);

    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 1,
    });

    apiMocks.getTableBoard.mockResolvedValue(createBoardEnvelope());
    apiMocks.getTableBoardChanges.mockResolvedValue({
      data: {
        current_version: 19,
        events: [],
        poll_hint_ms: 20000,
      },
    });
    apiMocks.createReservation.mockResolvedValue({
      data: {
        reservation_id: 88,
        reservation_code: 'RSV-PHONE-001',
        row_version: 5,
      },
    });
    apiMocks.moveReservationTable.mockResolvedValue({
      data: {
        reservation_id: 44,
        reservation_code: 'RSV-CHECKEDIN-001',
        row_version: 9,
        table_ids: [15],
      },
    });
    apiMocks.releaseStaffTable.mockResolvedValue({
      data: {
        table_id: 33,
        table_code: 'T33',
        status: 'Available',
        row_version: 3,
      },
    });
  });

  it('creates a phone reservation with guest snapshot fields for the selected table', async () => {
    const view = renderWithProviders('/ops/tables?source=board&table_id=12');

    fireEvent.click(await screen.findByRole('button', { name: /Đặt bàn hộ cho bàn này/i }));

    const dialog = await screen.findByRole('dialog');
    fireEvent.change(screen.getByPlaceholderText(/Khách gọi điện/i), {
      target: { value: 'Caller Guest' },
    });
    fireEvent.change(screen.getByLabelText(/điện thoại/i), {
      target: { value: '0905566778' },
    });
    fireEvent.change(screen.getByLabelText('Email'), {
      target: { value: 'caller.guest@example.test' },
    });
    fireEvent.change(screen.getByPlaceholderText(/Ví dụ/i), {
      target: { value: 'Phone-in reservation' },
    });

    fireEvent.click(withinLast(dialog, /Tạo đặt bàn hộ/i));

    await waitFor(() => expect(apiMocks.createReservation).toHaveBeenCalledTimes(1));

    const payload = apiMocks.createReservation.mock.calls[0][0];
    expect(payload).toMatchObject({
      branch_id: 1,
      guest_name: 'Caller Guest',
      guest_phone: '0905566778',
      guest_email: 'caller.guest@example.test',
      guest_count: 2,
      table_ids: [12],
      notes: 'Phone-in reservation',
    });

    const startTime = new Date(payload.start_time);
    const endTime = new Date(payload.end_time);
    expect(Number.isNaN(startTime.getTime())).toBe(false);
    expect(Number.isNaN(endTime.getTime())).toBe(false);
    expect(endTime.getTime() - startTime.getTime()).toBe(120 * 60 * 1000);

    await waitFor(() => expect(screen.getByTestId('reservations-destination')).toBeInTheDocument());
    expect(view.container.querySelector('[data-testid="table-board-page"]')).toBeNull();
  });

  it('shows a single primary table status and one next step on each board card', async () => {
    apiMocks.getTableBoard.mockResolvedValue(createBoardEnvelope([
      createBoardRow({
        table_id: 21,
        table_code: 'MAIN-S-01',
        zone: 'SÂN VƯỜN',
        realtime_status: 'available',
        board_state: 'Available',
      }),
    ]));

    renderWithProviders('/ops/tables');

    const card = await screen.findByRole('button', { name: /MAIN-S-01/i });

    expect(within(card).getAllByText('Sẵn bàn')).toHaveLength(1);
    expect(within(card).queryByText('Gợi ý')).not.toBeInTheDocument();
    expect(within(card).getByText('Trạng thái')).toBeInTheDocument();
    expect(within(card).getByText('Trống & sẵn nhận khách')).toBeInTheDocument();
    expect(within(card).getByText('Tiếp theo')).toBeInTheDocument();
    expect(within(card).getByText('Xếp khách vào bàn')).toBeInTheDocument();
  });

  it('keeps the next step actionable for selected and reserved tables', async () => {
    apiMocks.getTableBoard.mockResolvedValue(createBoardEnvelope([
      createBoardRow({
        table_id: 21,
        table_code: 'MAIN-S-01',
        board_state: 'Available',
        realtime_status: 'available',
      }),
      createBoardRow({
        table_id: 22,
        table_code: 'MAIN-S-02',
        board_state: 'reserved_in_range',
        realtime_status: 'reserved',
        reservation: {
          reservation_id: 51,
          reservation_code: 'RSV-051',
          status: 'Reserved',
          row_version: 3,
          table_ids: [22],
          guest_count: 4,
          start_time: '2026-04-11T10:15:00Z',
          end_time: '2026-04-11T12:15:00Z',
          user: {
            full_name: 'Reserved Guest',
          },
          guest: null,
        },
        actions: {
          check_in: {
            available: true,
            method: 'POST',
            endpoint: '/api/v1/staff/reservations/51/check-in',
            required_payload: ['row_version'],
            preferred_payload: {
              row_version: 3,
            },
            checks: {},
          },
          move_table: null,
        },
      }),
    ]));

    renderWithProviders('/ops/tables?source=board&table_id=21');

    const selectedCard = await screen.findByRole('button', { name: /MAIN-S-01/i });
    const reservedCard = await screen.findByRole('button', { name: /MAIN-S-02/i });

    expect(within(selectedCard).getByText('Xếp khách vào bàn')).toBeInTheDocument();
    expect(within(selectedCard).queryByText('Đang mở chi tiết')).not.toBeInTheDocument();
    expect(within(reservedCard).getByText('Nhận khách vào bàn')).toBeInTheDocument();
  });

  it('moves a checked-in reservation to a new table and carries order context forward', async () => {
    apiMocks.getTableBoard.mockResolvedValue(createBoardEnvelope([
      createBoardRow({
        table_id: 12,
        table_code: 'T12',
        realtime_status: 'Occupied',
        board_state: 'occupied_now',
        reservation: {
          reservation_id: 44,
          reservation_code: 'RSV-CHECKEDIN-001',
          status: 'Reserved',
          row_version: 4,
          table_ids: [12],
          guest_count: 2,
          start_time: '2026-04-11T10:15:00Z',
          end_time: '2026-04-11T12:15:00Z',
          user: {
            full_name: 'Checked In Guest',
          },
          guest: null,
        },
        active_order: {
          order_id: 501,
          row_version: 13,
          status: 'Active',
          order_type: 'OnSpot',
        },
        actions: {
          check_in: {
            available: false,
            method: 'POST',
            endpoint: '/api/v1/staff/reservations/44/check-in',
            required_payload: ['row_version'],
            preferred_payload: {
              row_version: 4,
              table_ids: [12],
            },
            checks: {},
          },
          move_table: {
            available: true,
            method: 'POST',
            endpoint: '/api/v1/staff/reservations/44/move-table',
            required_payload: ['from_table_id', 'to_table_id', 'row_version'],
            preferred_payload: {
              from_table_id: 12,
              row_version: 4,
            },
          },
        },
      }),
      createBoardRow({
        table_id: 15,
        table_code: 'T15',
      }),
    ]));

    renderWithProviders('/ops/tables?source=board&table_id=12&reservation_id=44&reservation_row_version=4&order_id=501&order_row_version=13');

    fireEvent.click(await screen.findByRole('button', { name: /Chuyển bàn/i }));
    const moveButtons = screen.getAllByRole('button', { name: /Chuyển bàn/i });
    fireEvent.click(moveButtons[moveButtons.length - 1]);

    await waitFor(() => expect(apiMocks.moveReservationTable).toHaveBeenCalledWith(44, {
      from_table_id: 12,
      to_table_id: 15,
      row_version: 4,
    }));
    await waitFor(() => expect(screen.getByTestId('orders-destination')).toBeInTheDocument());
    expect(screen.getByTestId('location-search').textContent).toContain('source=board');
    expect(screen.getByTestId('location-search').textContent).toContain('table_id=15');
    expect(screen.getByTestId('location-search').textContent).toContain('reservation_id=44');
    expect(screen.getByTestId('location-search').textContent).toContain('order_id=501');
  });

  it('releases an idle occupied table from the board inspector', async () => {
    apiMocks.getTableBoard.mockResolvedValue(createBoardEnvelope([
      createBoardRow({
        table_id: 33,
        table_code: 'T33',
        realtime_status: 'Occupied',
        board_state: 'occupied_now',
        availability: {
          accepts_new_assignment: false,
          is_operationally_blocked: false,
          is_realtime_occupied: true,
          has_reservation_in_range: false,
          has_hold_in_range: false,
          requires_deposit_follow_up: false,
        },
      }),
    ]));

    renderWithProviders('/ops/tables?source=board&table_id=33');

    fireEvent.click(await screen.findByRole('button', { name: /Available/i }));

    await waitFor(() => expect(confirmActionMock).toHaveBeenCalled());
    await waitFor(() => expect(apiMocks.releaseStaffTable).toHaveBeenCalledWith(33));
  });

  it('clears stale reservation and order journey params after releasing a table', async () => {
    apiMocks.getTableBoard.mockResolvedValue(createBoardEnvelope([
      createBoardRow({
        table_id: 33,
        table_code: 'T33',
        realtime_status: 'Occupied',
        board_state: 'occupied_now',
        availability: {
          accepts_new_assignment: false,
          is_operationally_blocked: false,
          is_realtime_occupied: true,
          has_reservation_in_range: false,
          has_hold_in_range: false,
          requires_deposit_follow_up: false,
        },
      }),
    ]));

    renderWithProviders('/ops/tables?source=board&table_id=33&reservation_id=44&reservation_row_version=4&order_id=501&order_row_version=13');

    fireEvent.click(await screen.findByRole('button', { name: /Available/i }));

    await waitFor(() => expect(apiMocks.releaseStaffTable).toHaveBeenCalledWith(33));
    await waitFor(() => {
      expect(screen.getByTestId('location-search').textContent).toContain('table_id=33');
      expect(screen.getByTestId('location-search').textContent).not.toContain('reservation_id=');
      expect(screen.getByTestId('location-search').textContent).not.toContain('order_id=');
    });
  });
});

function withinLast(container: HTMLElement, name: RegExp): HTMLElement {
  const buttons = Array.from(container.querySelectorAll('button'));
  const matched = buttons.filter((button) => name.test(button.textContent ?? ''));
  if (matched.length === 0) {
    throw new Error(`No button matched ${String(name)}`);
  }

  return matched[matched.length - 1] as HTMLElement;
}

function createBoardRow(overrides: Record<string, unknown> = {}) {
  return {
    table_id: 12,
    table_code: 'T12',
    zone: 'Main',
    pos_x: null,
    pos_y: null,
    realtime_status: 'Available',
    board_state: 'Available',
    reservations: [],
    holds: [],
    reservation: null,
    hold: null,
    capacity: {
      template_id: 4,
      seats: 4,
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
    ...overrides,
  };
}

function createBoardEnvelope(rows = [createBoardRow()]) {
  return {
    data: rows,
    zones: [
      {
        zone: 'Main',
        summary: {
          table_count: rows.length,
          available_count: rows.filter((row) => row.board_state === 'Available').length,
          occupied_now_count: 0,
          reserved_in_range_count: 0,
          held_in_range_count: 0,
        },
      },
    ],
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
      filters: {
        from: '2026-04-11T10:00:00Z',
        to: '2026-04-11T14:00:00Z',
        zone: 'Main',
        include_holds: true,
        group_by: 'zone',
      },
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
        current_version: 19,
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
            <Route path="/ops/tables" element={<TableBoardPage />} />
            <Route path="/ops/reservations" element={<div data-testid="reservations-destination">reservations</div>} />
            <Route path="/ops/orders" element={<div data-testid="orders-destination">orders</div>} />
          </Routes>
          <LocationProbe />
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}
