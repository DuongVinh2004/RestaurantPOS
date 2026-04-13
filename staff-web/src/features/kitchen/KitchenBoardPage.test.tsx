import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { KitchenBoardPage } from './KitchenBoardPage';

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

vi.mock('../../core/api/staff-api', () => apiMocks);
vi.mock('../../hooks/useConfirmAction', () => ({
  useConfirmAction: () => confirmActionMock,
}));
vi.mock('../../components/feedback/toast', () => ({
  toast: toastMocks,
}));

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

    fireEvent.click(await screen.findByRole('button', { name: 'Chuyển đơn đã bàn giao sang bếp' }));

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

    renderWithProviders('/kitchen?source=order&order_id=56&order_row_version=10');

    fireEvent.click(await screen.findByRole('button', { name: 'Chuyển đơn đã bàn giao sang bếp' }));

    await waitFor(() => expect(apiMocks.dispatchKitchenOrder).toHaveBeenCalledWith(56, {
      row_version: 10,
    }));
    await waitFor(() => expect(toastMocks.warning).toHaveBeenCalledWith(expect.stringContaining('chưa tạo phiếu bếp')));
    expect(toastMocks.success).not.toHaveBeenCalledWith(expect.stringContaining('sang bếp'));
    expect(screen.getByTestId('location-search').textContent).not.toContain('ticket=');
    expect(screen.getByTestId('location-search').textContent).not.toContain('station_id=');
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

  it('switches the focused station from the lightweight station cards', async () => {
    renderWithProviders('/kitchen');

    fireEvent.click(await screen.findByRole('button', { name: /Hot Pass/i }));

    await waitFor(() => expect(screen.getByTestId('location-search').textContent).toContain('station_id=33'));
  });

  it('updates the ticket filter from the native status select', async () => {
    renderWithProviders('/kitchen');

    fireEvent.change(await screen.findByRole('combobox', { name: 'Lọc trạng thái phiếu bếp' }), {
      target: { value: 'Ready' },
    });

    await waitFor(() => expect(screen.getByTestId('location-search').textContent).toContain('status=Ready'));
  });

  it('removes the deprecated antd List usage from the kitchen ticket side panel', () => {
    expect(kitchenSource).not.toMatch(/\bList\b/);
    expect(kitchenSource).not.toContain('<List');
  });
});

function createStationsEnvelope() {
  return {
    data: [
      {
        station_id: 1,
        code: 'COLD',
        name: 'Cold Pass',
        output_mode: 'Both',
        ticket_counts: {
          queued: 0,
          ready: 0,
        },
      },
      {
        station_id: 33,
        code: 'HOT',
        name: 'Hot Pass',
        output_mode: 'Both',
        ticket_counts: {
          queued: 1,
          ready: 0,
        },
      },
    ],
    meta: {
      count: 2,
      realtime: {
        topic: 'kitchen',
        channel: 'staff.kitchen',
        changes_uri: '/api/v1/staff/kitchen/changes',
        current_version: 10,
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
}> = {}) {
  return {
    ticket_id: overrides.ticketId ?? 801,
    ticket_status: overrides.status ?? 'Queued',
    dispatch_count: 1,
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
    item: {
      name: 'Kitchen Bowl',
    },
    order_item: {
      item_name_snapshot: 'Kitchen Bowl',
      status: 'Ordered',
    },
  };
}

function createTicketsEnvelope(stationId: number, tickets: Array<ReturnType<typeof createKitchenTicket>>) {
  return {
    data: tickets,
    meta: {
      station_id: stationId,
      count: tickets.length,
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
      current_version: overrides.currentVersion ?? 10,
      events: overrides.events ?? [],
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
