import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { KitchenBoardPage } from './KitchenBoardPage';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { buildStaffSession } from '../../../../test/fixtures';

const currentDir = dirname(fileURLToPath(import.meta.url));
const kitchenSource = readFileSync(resolve(currentDir, './KitchenBoardPage.tsx'), 'utf8');

const confirmActionMock = vi.hoisted(() => vi.fn(async () => true));
const toastMocks = vi.hoisted(() => ({
  error: vi.fn(),
  info: vi.fn(),
  success: vi.fn(),
  warning: vi.fn(),
}));
const apiMocks = vi.hoisted(() => ({
  bumpKitchenTicket: vi.fn(),
  dispatchKitchenOrder: vi.fn(),
  fireKitchenTicket: vi.fn(),
  getKitchenChanges: vi.fn(),
  getKitchenStationTickets: vi.fn(),
  listKitchenStations: vi.fn(),
  recallKitchenTicket: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', () => apiMocks);
vi.mock('../../../../shared/hooks/useConfirmAction', () => ({
  useConfirmAction: () => confirmActionMock,
}));
vi.mock('../../../../shared/ui/feedback/toast', () => ({
  toast: toastMocks,
}));

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();

describe('KitchenBoardPage', () => {
  beforeAll(() => {
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
    confirmActionMock.mockReset();
    confirmActionMock.mockResolvedValue(true);
    useAuthStore.setState(initialAuthState, true);
    useFlowStore.setState(initialFlowState, true);
    useAuthStore.getState().setSession(buildKitchenSession(['kitchen.manage', 'order.manage']));
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 1,
    });
    apiMocks.listKitchenStations.mockResolvedValue(createStationsEnvelope());
    apiMocks.getKitchenStationTickets.mockImplementation(async (stationId: number) => createTicketsEnvelope(
      stationId,
      stationId === 33
        ? [createKitchenTicket({ stationId: 33, ticketId: 801, orderId: 56 })]
        : [],
    ));
    apiMocks.getKitchenChanges.mockResolvedValue(createKitchenChangesEnvelope());
    apiMocks.dispatchKitchenOrder.mockResolvedValue(createKitchenDispatchEnvelope());
    apiMocks.fireKitchenTicket.mockResolvedValue(createKitchenTicketEnvelope({
      ticketId: 801,
      stationId: 33,
      orderId: 56,
      reservationId: 34,
      status: 'Fired',
    }));
    apiMocks.bumpKitchenTicket.mockResolvedValue(createKitchenTicketEnvelope({
      ticketId: 801,
      stationId: 33,
      orderId: 56,
      reservationId: 34,
      status: 'Ready',
    }));
    apiMocks.recallKitchenTicket.mockResolvedValue(createKitchenTicketEnvelope({
      ticketId: 801,
      stationId: 33,
      orderId: 56,
      reservationId: 34,
      status: 'Fired',
    }));
  });

  it('focuses the dispatched station and ticket after kitchen handoff', async () => {
    renderWithProviders('/kitchen?source=order&order_id=56&order_row_version=10');

    fireEvent.click(await screen.findByRole('button', { name: 'Gửi đơn xuống bếp' }));

    await waitFor(() => expect(apiMocks.dispatchKitchenOrder).toHaveBeenCalledWith(56, {
      row_version: 10,
    }));
    await waitFor(() => expect(screen.getByTestId('location-search').textContent).toContain('station_id=33'));
    expect(screen.getByTestId('location-search').textContent).toContain('ticket=801');
  });

  it('warns without focusing a ticket when dispatch only finds unrouted items', async () => {
    apiMocks.dispatchKitchenOrder.mockResolvedValue({
      data: [],
      meta: {
        action: 'kitchen_order_dispatched',
        created_count: 0,
        reused_count: 0,
        unrouted_count: 2,
        pinned_route_count: 0,
      },
    });
    apiMocks.getKitchenStationTickets.mockResolvedValue(createTicketsEnvelope(33, []));

    renderWithProviders('/kitchen?source=order&order_id=56&order_row_version=10');

    fireEvent.click(await screen.findByRole('button', { name: 'Gửi đơn xuống bếp' }));

    await waitFor(() => expect(apiMocks.dispatchKitchenOrder).toHaveBeenCalledWith(56, {
      row_version: 10,
    }));
    await waitFor(() => expect(toastMocks.warning).toHaveBeenCalledWith(expect.stringContaining('chưa tạo phiếu bếp')));
    expect(toastMocks.success).not.toHaveBeenCalledWith(expect.stringContaining('xuống bếp'));
    expect(screen.getByTestId('location-search').textContent).not.toContain('ticket=');
    expect(screen.getByTestId('location-search').textContent).not.toContain('station_id=');
  });

  it('hides order dispatch when the session lacks backend order.manage capability', async () => {
    useAuthStore.getState().setSession(buildKitchenSession(['kitchen.manage']));

    renderWithProviders('/kitchen?source=order&order_id=56&order_row_version=10');

    await waitFor(() => expect(apiMocks.listKitchenStations).toHaveBeenCalled());
    expect(screen.queryByRole('button', { name: 'Gửi đơn xuống bếp' })).not.toBeInTheDocument();
    expect(apiMocks.dispatchKitchenOrder).not.toHaveBeenCalled();
  });

  it('renders ticket notes and order item notes from the canonical kitchen payload', async () => {
    apiMocks.getKitchenStationTickets.mockResolvedValue(createTicketsEnvelope(33, [
      createKitchenTicket({
        stationId: 33,
        ticketId: 801,
        orderId: 56,
        ticketNotes: 'Ưu tiên ra món ngay khi line trống.',
        orderItemNotes: 'Không hành, thêm sốt riêng.',
        orderItemQuantity: 2,
      }),
    ]));

    renderWithProviders('/kitchen?station_id=33&ticket=801');

    expect(await screen.findByText('Ghi chú phiếu bếp')).toBeInTheDocument();
    expect(screen.getByText('Ưu tiên ra món ngay khi line trống.')).toBeInTheDocument();
    expect(screen.getByText('Ghi chú món')).toBeInTheDocument();
    expect(screen.getByText('Không hành, thêm sốt riêng.')).toBeInTheDocument();
    expect(screen.getByText('x2')).toBeInTheDocument();
  });

  it('sends the selected ticket row version with kitchen fast actions', async () => {
    renderWithProviders('/kitchen?station_id=33&ticket=801');

    fireEvent.click(await screen.findByRole('button', { name: 'Bắt đầu làm' }));

    await waitFor(() => expect(confirmActionMock).toHaveBeenCalled());
    await waitFor(() => expect(apiMocks.fireKitchenTicket).toHaveBeenCalledWith(801, 17));
  });

  it('locks kitchen fast actions while confirmation is pending', async () => {
    let resolveConfirm: (value: boolean) => void = () => {};
    confirmActionMock.mockImplementationOnce(() => new Promise<boolean>((resolve) => {
      resolveConfirm = resolve;
    }));

    renderWithProviders('/kitchen?station_id=33&ticket=801');

    const fireButton = await screen.findByRole('button', { name: 'Bắt đầu làm' });
    fireEvent.click(fireButton);

    await waitFor(() => expect(fireButton).toBeDisabled());
    fireEvent.click(fireButton);
    expect(confirmActionMock).toHaveBeenCalledTimes(1);

    await act(async () => {
      resolveConfirm(true);
    });

    await waitFor(() => expect(apiMocks.fireKitchenTicket).toHaveBeenCalledWith(801, 17));
  });

  it('refreshes kitchen reads when a fast action reports a stale row version', async () => {
    apiMocks.fireKitchenTicket.mockRejectedValue({
      status: 409,
      payload: {
        error_code: 'stale_row_version',
        category_code: 'stale_write',
        message: 'Ticket row version is stale.',
      },
    });

    renderWithProviders('/kitchen?station_id=33&ticket=801');

    await waitFor(() => expect(apiMocks.getKitchenStationTickets).toHaveBeenCalled());
    apiMocks.getKitchenStationTickets.mockClear();
    apiMocks.listKitchenStations.mockClear();

    fireEvent.click(await screen.findByRole('button', { name: 'Bắt đầu làm' }));

    await waitFor(() => expect(toastMocks.warning).toHaveBeenCalledWith(expect.stringContaining('đã được tải lại')));
    await waitFor(() => expect(apiMocks.getKitchenStationTickets).toHaveBeenCalled());
    await waitFor(() => expect(apiMocks.listKitchenStations).toHaveBeenCalled());
  });

  it('surfaces capability denial inline when a kitchen fast action is forbidden', async () => {
    apiMocks.fireKitchenTicket.mockRejectedValue({
      status: 403,
      payload: {
        error_code: 'forbidden',
        category_code: 'forbidden_capability',
        required_capability: 'kitchen.manage',
        request_id: 'req-kitchen-403',
        message: 'Capability kitchen.manage is required.',
      },
    });

    renderWithProviders('/kitchen?station_id=33&ticket=801');

    fireEvent.click(await screen.findByRole('button', { name: 'Bắt đầu làm' }));

    await waitFor(() => expect(screen.getByTestId('mutation-status-notice')).toHaveAttribute('data-phase', 'denied'));
    expect(screen.getByText(/kitchen\.manage/i)).toBeInTheDocument();
    expect(screen.getByText(/req-kitchen-403/i)).toBeInTheDocument();
  });

  it('refetches kitchen reads and keeps a conflict notice when a fast action hits a stale ticket route', async () => {
    apiMocks.fireKitchenTicket.mockRejectedValue({
      status: 404,
      payload: {
        error_code: 'not_found',
        request_id: 'req-kitchen-404',
        message: 'Kitchen ticket no longer exists.',
      },
    });

    renderWithProviders('/kitchen?station_id=33&ticket=801');

    await waitFor(() => expect(apiMocks.getKitchenStationTickets).toHaveBeenCalled());
    apiMocks.getKitchenStationTickets.mockClear();
    apiMocks.listKitchenStations.mockClear();

    fireEvent.click(await screen.findByRole('button', { name: 'Bắt đầu làm' }));

    await waitFor(() => expect(screen.getByTestId('mutation-status-notice')).toHaveAttribute('data-phase', 'conflict'));
    await waitFor(() => expect(apiMocks.getKitchenStationTickets).toHaveBeenCalled());
    await waitFor(() => expect(apiMocks.listKitchenStations).toHaveBeenCalled());
    expect(screen.getByText(/không còn khớp với bản ghi đang xem/i)).toBeInTheDocument();
  });

  it('disables kitchen fast actions until the selected ticket row version is available', async () => {
    apiMocks.getKitchenStationTickets.mockResolvedValue(createTicketsEnvelope(33, [
      createKitchenTicket({
        stationId: 33,
        ticketId: 801,
        orderId: 56,
        rowVersion: null,
      }),
    ]));

    renderWithProviders('/kitchen?station_id=33&ticket=801');

    expect(await screen.findByRole('button', { name: 'Bắt đầu làm' })).toBeDisabled();
    expect(apiMocks.fireKitchenTicket).not.toHaveBeenCalled();
  });

  it('shows a retryable inline failure when kitchen dispatch cannot be completed and allows a manual refresh', async () => {
    apiMocks.dispatchKitchenOrder.mockRejectedValue({
      status: 500,
      payload: {
        error_code: 'server_error',
        request_id: 'req-kitchen-500',
        message: 'Kitchen gateway timed out.',
      },
    });

    renderWithProviders('/kitchen?source=order&order_id=56&order_row_version=10');

    await waitFor(() => expect(apiMocks.listKitchenStations).toHaveBeenCalled());
    await waitFor(() => expect(apiMocks.getKitchenStationTickets).toHaveBeenCalled());
    apiMocks.listKitchenStations.mockClear();
    apiMocks.getKitchenStationTickets.mockClear();

    fireEvent.click(await screen.findByRole('button', { name: 'Gửi đơn xuống bếp' }));

    await waitFor(() => expect(screen.getByTestId('mutation-status-notice')).toHaveAttribute('data-phase', 'retriable_failure'));
    expect(screen.getByText('Kitchen gateway timed out.')).toBeInTheDocument();
    expect(screen.getByText(/req-kitchen-500/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Làm mới' }));

    await waitFor(() => expect(apiMocks.listKitchenStations).toHaveBeenCalled());
    await waitFor(() => expect(apiMocks.getKitchenStationTickets).toHaveBeenCalled());
  });

  it('refetches kitchen slices when the realtime feed reports a newer version', async () => {
    apiMocks.getKitchenChanges.mockResolvedValue(createKitchenChangesEnvelope({
      currentVersion: 11,
      events: [
        {
          type: 'kitchen.order_dispatched',
          payload: {
            order_id: 56,
          },
        },
      ],
    }));

    renderWithProviders('/kitchen');

    await waitFor(() => expect(apiMocks.listKitchenStations.mock.calls.length).toBeGreaterThan(1));
    await waitFor(() => expect(apiMocks.getKitchenStationTickets.mock.calls.length).toBeGreaterThan(1));
  });

  it('polls kitchen realtime changes with the active branch after branch switch', async () => {
    renderWithProviders('/kitchen');

    await waitFor(() => expect(apiMocks.getKitchenChanges).toHaveBeenCalledWith(10, 1));

    apiMocks.getKitchenChanges.mockClear();
    apiMocks.listKitchenStations.mockClear();

    act(() => {
      useFlowStore.setState({
        ...useFlowStore.getState(),
        branchId: 2,
      });
    });

    await waitFor(() => expect(apiMocks.listKitchenStations).toHaveBeenCalledWith(2));
    await waitFor(() => expect(apiMocks.getKitchenChanges).toHaveBeenCalledWith(10, 2));
  });

  it('switches the focused station from the station cards', async () => {
    renderWithProviders('/kitchen');

    fireEvent.click(await screen.findByRole('button', { name: /Bếp nóng/i }));

    await waitFor(() => expect(screen.getByTestId('location-search').textContent).toContain('station_id=33'));
  });

  it('updates the ticket filter from the native status select', async () => {
    renderWithProviders('/kitchen');

    fireEvent.change(await screen.findByRole('combobox', { name: 'Lọc phiếu bếp theo trạng thái' }), {
      target: { value: 'Ready' },
    });

    await waitFor(() => expect(screen.getByTestId('location-search').textContent).toContain('status=Ready'));
  });

  it('removes the deprecated antd List usage from the kitchen ticket side panel', () => {
    expect(kitchenSource).not.toMatch(/\bList\b/);
    expect(kitchenSource).not.toContain('<List');
  });
});

function buildKitchenSession(capabilities: Array<string>) {
  return buildStaffSession({
    capabilities,
    known_capabilities: ['kitchen.manage', 'order.manage'],
    startup: {
      allowed_branch_ids: [1, 2],
      branch_access: {
        accessible_branch_ids: [1, 2],
        has_multi_branch_access: true,
        branch_selector_enabled: true,
      },
      assigned_station_ids: [33],
    },
  });
}

function createStationsEnvelope() {
  return {
    data: [
      {
        station_id: 1,
        branch_id: 1,
        code: 'COLD',
        name: 'Cold Pass',
        description: null,
        output_mode: 'Both',
        printer_target: null,
        is_active: true,
        route_count: 1,
        ticket_counts: {
          queued: 0,
          fired: 0,
          ready: 0,
        },
        created_at: null,
        updated_at: null,
      },
      {
        station_id: 33,
        branch_id: 1,
        code: 'HOT',
        name: 'Hot Pass',
        description: null,
        output_mode: 'Both',
        printer_target: null,
        is_active: true,
        route_count: 1,
        ticket_counts: {
          queued: 1,
          fired: 0,
          ready: 0,
        },
        created_at: null,
        updated_at: null,
      },
    ],
    meta: {
      count: 2,
      realtime: {
        enabled: true,
        topic: 'kitchen',
        channel: 'staff.kitchen',
        changes_uri: '/api/v1/staff/kitchen/changes',
        current_version: 10,
        polling_compatible: true,
        default_refresh_targets: ['kitchen'],
        poll_hint_ms: 20000,
      },
    },
  };
}

function createKitchenTicket(overrides: Partial<{
  ticketId: number;
  stationId: number;
  orderId: number;
  reservationId: number;
  status: string;
  rowVersion: number | null;
  ticketNotes: string | null;
  orderItemNotes: string | null;
  orderItemQuantity: number;
  orderItemStatus: string;
  orderItemMatchesTicket: boolean | null;
}> = {}) {
  const status = overrides.status ?? 'Queued';

  return {
    ticket_id: overrides.ticketId ?? 801,
    row_version: overrides.rowVersion === undefined ? 17 : overrides.rowVersion,
    ticket_status: status,
    route_source: 'category',
    dispatch_count: 1,
    recall_count: 0,
    output_mode: 'Both',
    printer_target: null,
    ticket_notes: overrides.ticketNotes ?? null,
    first_dispatched_at: '2026-04-11T09:00:00Z',
    fired_at: status === 'Fired' || status === 'Ready' ? '2026-04-11T09:01:00Z' : null,
    ready_at: status === 'Ready' ? '2026-04-11T09:03:00Z' : null,
    completed_at: null,
    cancelled_at: null,
    last_recalled_at: null,
    created_at: '2026-04-11T09:00:00Z',
    updated_at: '2026-04-11T09:00:00Z',
    order: {
      order_id: overrides.orderId ?? 56,
      reservation_id: overrides.reservationId ?? 34,
    },
    station: {
      station_id: overrides.stationId ?? 33,
      code: (overrides.stationId ?? 33) === 33 ? 'HOT' : 'COLD',
      name: (overrides.stationId ?? 33) === 33 ? 'Hot Pass' : 'Cold Pass',
    },
    route: null,
    routing: {
      route_present: true,
      route_active: true,
      station_matches_route: true,
    },
    item: {
      item_id: 1,
      name: 'Kitchen Bowl',
      category_id: null,
      category_name: null,
    },
    order_item: {
      order_item_id: 1,
      item_id: 1,
      quantity: overrides.orderItemQuantity ?? 1,
      item_name_snapshot: 'Kitchen Bowl',
      status: overrides.orderItemStatus ?? 'Ordered',
      row_version: 19,
      notes: overrides.orderItemNotes ?? null,
    },
    lifecycle: {
      status,
      state_reason: 'test',
      is_terminal: false,
      allowed_actions: [],
    },
    reconciliation: {
      sync_status: 'synced',
      routing_status: 'ok',
      order_item_expected_status: null,
      order_item_matches_ticket: overrides.orderItemMatchesTicket ?? true,
      station_active: true,
      drift_reasons: [],
      next_actions: [],
    },
  };
}

function createTicketsEnvelope(stationId: number, tickets: Array<ReturnType<typeof createKitchenTicket>>) {
  return {
    data: tickets,
    meta: {
      station_id: stationId,
      branch_id: null,
      count: tickets.length,
      branch_scope: {
        requested_branch_id: null,
        accessible_branch_ids: [],
        uses_explicit_entitlement: false,
      },
    },
  };
}

function createKitchenDispatchEnvelope() {
  return {
    data: [createKitchenTicket()],
    meta: {
      action: 'kitchen_order_dispatched',
      created_count: 1,
      reused_count: 0,
      unrouted_count: 0,
      pinned_route_count: 0,
    },
  };
}

function createKitchenTicketEnvelope(overrides: Partial<{
  ticketId: number;
  stationId: number;
  orderId: number;
  reservationId: number;
  status: string;
}> = {}) {
  return {
    data: createKitchenTicket(overrides),
    meta: {
      action: 'kitchen_ticket_updated',
    },
  };
}

function createKitchenChangesEnvelope(overrides: Partial<{
  currentVersion: number;
  events: Array<Record<string, unknown>>;
}> = {}) {
  return {
    data: {
      enabled: true,
      topic: 'kitchen',
      channel: 'staff.kitchen',
      after_version: 10,
      current_version: overrides.currentVersion ?? 10,
      oldest_available_version: 1,
      events: overrides.events ?? [],
      has_changes: (overrides.events ?? []).length > 0,
      stale_cursor: false,
      poll_hint_ms: 20000,
    },
  };
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
            <Route path="/kitchen" element={<><KitchenBoardPage /><LocationProbe /></>} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}

function LocationProbe() {
  const location = useLocation();

  return <div data-testid="location-search">{location.search}</div>;
}
