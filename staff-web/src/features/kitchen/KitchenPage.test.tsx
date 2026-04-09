import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { KitchenPage } from './KitchenPage';
import { renderWithSession } from '../../test/render';
import { buildApiError, buildStaffSession } from '../../test/fixtures';
import type { StaffSessionContextValue } from '../../app/session-context';

const apiMocks = vi.hoisted(() => ({
  loadKitchenStations: vi.fn(),
  loadKitchenStationTickets: vi.fn(),
  loadKitchenChanges: vi.fn(),
  dispatchKitchenOrder: vi.fn(),
  fireKitchenTicket: vi.fn(),
  bumpKitchenTicket: vi.fn(),
  recallKitchenTicket: vi.fn(),
  isUnauthorized: vi.fn(() => false),
}));

vi.mock('../../api/client', () => apiMocks);

describe('KitchenPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('hydrates order handoff context and loads the selected station queue', async () => {
    arrangeKitchenFixtures();

    renderWithSession(
      <KitchenPage />,
      createSessionContext(),
      {
        initialEntries: ['/kitchen?order_id=9001&order_row_version=14'],
      },
    );

    await waitFor(() => expect(apiMocks.loadKitchenStations).toHaveBeenCalledTimes(1));
    await waitFor(() =>
      expect(apiMocks.loadKitchenStationTickets).toHaveBeenCalledWith(31, {
        status: undefined,
        include_terminal: false,
      }),
    );

    expect(screen.getByDisplayValue('9001')).toBeInTheDocument();
    expect(screen.getByDisplayValue('14')).toBeInTheDocument();
    expect(screen.getAllByText('Grill Station').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Pho Bo').length).toBeGreaterThan(0);
  });

  it('dispatches an order using the route-provided row_version and refreshes kitchen state', async () => {
    arrangeKitchenFixtures();

    renderWithSession(
      <KitchenPage />,
      createSessionContext(),
      {
        initialEntries: ['/kitchen?order_id=9001&order_row_version=14'],
      },
    );

    await waitFor(() => expect(apiMocks.loadKitchenStationTickets).toHaveBeenCalled());
    fireEvent.click(screen.getByRole('button', { name: 'Dispatch order' }));

    await waitFor(() =>
      expect(apiMocks.dispatchKitchenOrder).toHaveBeenCalledWith(9001, {
        row_version: 14,
      }),
    );
    await waitFor(() => expect(apiMocks.loadKitchenStations.mock.calls.length).toBeGreaterThanOrEqual(2));
    expect(screen.getByText(/Da dispatch order #9001 vao kitchen/i)).toBeInTheDocument();
  });

  it('fires a queued ticket through the safe action button and refreshes the queue', async () => {
    arrangeKitchenFixtures();

    renderWithSession(<KitchenPage />, createSessionContext());

    await waitFor(() => expect(screen.getByRole('button', { name: 'Fire ticket' })).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Fire ticket' }));

    await waitFor(() => expect(apiMocks.fireKitchenTicket).toHaveBeenCalledWith(701));
    expect(apiMocks.bumpKitchenTicket).not.toHaveBeenCalled();
    expect(apiMocks.recallKitchenTicket).not.toHaveBeenCalled();
  });

  it('surfaces feature-flag blocks and reloads stale ticket state after transition drift', async () => {
    arrangeKitchenFixtures();
    apiMocks.fireKitchenTicket.mockRejectedValueOnce(buildApiError(422, {
      message: 'Validation error.',
      errors: {
        ticket_id: ['Only queued kitchen tickets can be fired.'],
      },
    }));

    renderWithSession(<KitchenPage />, createSessionContext());

    await waitFor(() => expect(screen.getByRole('button', { name: 'Fire ticket' })).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Fire ticket' }));

    await waitFor(() => expect(apiMocks.loadKitchenStationTickets).toHaveBeenCalledTimes(2));
    expect(screen.getByText(/Kitchen state da doi tren backend/i)).toBeInTheDocument();

    apiMocks.dispatchKitchenOrder.mockRejectedValueOnce(buildApiError(422, {
      message: 'Validation error.',
      errors: {
        feature_flag: ['staff.kitchen_dispatch is disabled for this branch.'],
      },
    }));

    fireEvent.change(screen.getByLabelText('Dispatch order ID'), { target: { value: '9001' } });
    fireEvent.click(screen.getByRole('button', { name: 'Dispatch order' }));

    await waitFor(() => expect(apiMocks.dispatchKitchenOrder).toHaveBeenCalled());
    expect(screen.getByText(/Kitchen mutation dang bi khoa boi feature flag branch nay/i)).toBeInTheDocument();
  });
});

function arrangeKitchenFixtures() {
  apiMocks.loadKitchenStations.mockResolvedValue(createStationCollection());
  apiMocks.loadKitchenStationTickets.mockResolvedValue(createTicketCollection());
  apiMocks.loadKitchenChanges.mockResolvedValue({
    data: {
      enabled: true,
      topic: 'kitchen',
      channel: 'staff.kitchen',
      after_version: 0,
      current_version: 12,
      oldest_available_version: 1,
      events: [],
      has_changes: false,
      stale_cursor: false,
      poll_hint_ms: 30000,
    },
  });
  apiMocks.dispatchKitchenOrder.mockResolvedValue(createDispatchEnvelope());
  apiMocks.fireKitchenTicket.mockResolvedValue({ data: createTicket({ ticket_status: 'Fired' }), meta: { action: 'kitchen_ticket_fired' } });
  apiMocks.bumpKitchenTicket.mockResolvedValue({ data: createTicket({ ticket_status: 'Ready' }), meta: { action: 'kitchen_ticket_bumped' } });
  apiMocks.recallKitchenTicket.mockResolvedValue({ data: createTicket({ ticket_status: 'Fired', recall_count: 1 }), meta: { action: 'kitchen_ticket_recalled' } });
}

function createStationCollection() {
  return {
    data: [
      {
        station_id: 31,
        code: 'GRILL',
        name: 'Grill Station',
        description: 'Main hot line',
        output_mode: 'KDS',
        printer_target: 'KDS-01',
        is_active: true,
        route_count: 2,
        ticket_counts: {
          queued: 1,
          fired: 0,
          ready: 0,
        },
        created_at: '2026-04-07T09:00:00Z',
        updated_at: '2026-04-07T09:10:00Z',
      },
    ],
    meta: {
      count: 1,
      realtime: {
        enabled: true,
        topic: 'kitchen',
        channel: 'staff.kitchen',
        current_version: 9,
        changes_uri: '/api/v1/staff/kitchen/changes',
        polling_compatible: true,
        default_refresh_targets: ['kitchen'],
        poll_hint_ms: 30000,
      },
    },
  };
}

function createTicketCollection() {
  return {
    data: [createTicket()],
    meta: {
      station_id: 31,
      count: 1,
    },
  };
}

function createDispatchEnvelope() {
  return {
    data: [createTicket()],
    meta: {
      action: 'kitchen_order_dispatched',
      created_count: 1,
      reused_count: 0,
      unrouted_count: 0,
      pinned_route_count: 0,
    },
  };
}

function createTicket(overrides: Partial<ReturnType<typeof baseTicket>> = {}) {
  return {
    ...baseTicket(),
    ...overrides,
  };
}

function baseTicket() {
  return {
    ticket_id: 701,
    ticket_status: 'Queued',
    route_source: 'Category',
    dispatch_count: 1,
    recall_count: 0,
    output_mode: 'KDS',
    printer_target: 'KDS-01',
    ticket_notes: 'No peanuts',
    order: {
      order_id: 9001,
      reservation_id: 77,
    },
    station: {
      station_id: 31,
      code: 'GRILL',
      name: 'Grill Station',
    },
    route: {
      route_id: 41,
      category_id: 5,
      sort_order: 10,
      is_active: true,
    },
    routing: {
      route_present: true,
      route_active: true,
      station_matches_route: true,
    },
    order_item: {
      order_item_id: 801,
      item_id: 501,
      quantity: 1,
      status: 'Ordered',
      notes: 'No peanuts',
      item_name_snapshot: 'Pho Bo',
    },
    item: {
      item_id: 501,
      name: 'Pho Bo',
      category_id: 5,
      category_name: 'Noodles',
    },
    first_dispatched_at: '2026-04-07T09:11:00Z',
    fired_at: null,
    ready_at: null,
    completed_at: null,
    cancelled_at: null,
    last_recalled_at: null,
    created_at: '2026-04-07T09:11:00Z',
    updated_at: '2026-04-07T09:11:00Z',
  };
}

function createSessionContext(overrides: Partial<StaffSessionContextValue['session']> = {}): StaffSessionContextValue {
  return {
    session: buildStaffSession({
      capabilities: ['order.manage'],
      known_capabilities: ['order.manage', 'table.board.view', 'settlement.manage'],
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
