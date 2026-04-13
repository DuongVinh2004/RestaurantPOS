import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { useFlowStore } from '../../app/store/flow-store';
import { StaffApiError } from '../../core/api/http';
import { OrderWorkspacePage } from './OrderWorkspacePage';

const confirmActionMock = vi.hoisted(() => vi.fn(async () => true));
const apiMocks = vi.hoisted(() => ({
  addOrderItems: vi.fn(),
  createTableOrder: vi.fn(),
  dispatchKitchenOrder: vi.fn(),
  getActiveOrderByReservation: vi.fn(),
  getActiveOrderByTable: vi.fn(),
  getOrderDetail: vi.fn(),
  getReservationDetail: vi.fn(),
  listMenuItems: vi.fn(),
  updateOrderItem: vi.fn(),
  updateOrderItemStatus: vi.fn(),
}));

vi.mock('../../core/api/staff-api', () => apiMocks);
vi.mock('../../hooks/useConfirmAction', () => ({
  useConfirmAction: () => confirmActionMock,
}));

const initialFlowState = useFlowStore.getState();

describe('OrderWorkspacePage', () => {
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
    sessionStorage.clear();
    useFlowStore.setState(initialFlowState, true);
    apiMocks.getActiveOrderByTable.mockRejectedValue(new StaffApiError(404, {
      error_code: 'not_found',
      message: 'Order not found.',
      request_id: 'req-order-missing',
    }, 'Not Found'));
    apiMocks.getActiveOrderByReservation.mockRejectedValue(new StaffApiError(404, {
      error_code: 'not_found',
      message: 'Order not found.',
      request_id: 'req-order-reservation-missing',
    }, 'Not Found'));
    apiMocks.getReservationDetail.mockResolvedValue(createReservationDetailEnvelope());
    apiMocks.listMenuItems.mockResolvedValue({ data: [] });
  });

  it('ignores stale flow-store ids when the url has no active order journey', async () => {
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 1,
      selectedTableId: 12,
      selectedReservationId: 34,
      selectedReservationRowVersion: 5,
      selectedOrderId: 56,
      selectedOrderRowVersion: 7,
      source: 'board',
    });

    const view = renderWithProviders('/orders');

    await waitFor(() => expect(apiMocks.listMenuItems).toHaveBeenCalled());
    await waitFor(() => expect(useFlowStore.getState().selectedOrderId).toBeNull());

    expect(apiMocks.getActiveOrderByTable).not.toHaveBeenCalled();
    expect(apiMocks.getActiveOrderByReservation).not.toHaveBeenCalled();
    expect(apiMocks.getOrderDetail).not.toHaveBeenCalled();
    expect(apiMocks.getReservationDetail).not.toHaveBeenCalled();
    expect(view.container.querySelector('.ant-card-head-title')).not.toBeNull();
  });

  it('recovers the active order by table when the route order id is stale', async () => {
    apiMocks.getOrderDetail
      .mockRejectedValueOnce(new StaffApiError(404, {
        error_code: 'not_found',
        message: 'Order not found.',
        request_id: 'req-order-stale',
      }, 'Not Found'))
      .mockResolvedValueOnce(createOrderDetailEnvelope({
        orderId: 72,
        orderRowVersion: 8,
      }));
    apiMocks.getActiveOrderByTable.mockResolvedValue(createActiveOrderEnvelope({
      orderId: 72,
      rowVersion: 8,
    }));
    apiMocks.listMenuItems.mockResolvedValue(createMenuEnvelope());

    renderWithProviders('/orders?source=board&table_id=12&reservation_id=34&reservation_row_version=5&order_id=999&order_row_version=1');

    await waitFor(() => expect(apiMocks.getActiveOrderByTable).toHaveBeenCalledWith(12));
    expect(await screen.findByText('Đã khôi phục sang đơn hàng đang phục vụ #72')).toBeInTheDocument();
    await waitFor(() => expect(screen.getByTestId('location-search').textContent).toContain('order_id=72'));
    expect(screen.getByTestId('location-search').textContent).toContain('order_row_version=8');
  });

  it('adds quantity to an existing editable matching item instead of creating a duplicate line', async () => {
    apiMocks.getOrderDetail.mockResolvedValue(createOrderDetailEnvelope({
      items: [
        createOrderItem({
          order_item_id: 201,
          item_id: 100,
          quantity: 1,
          status: 'Ordered',
          row_version: 3,
        }),
      ],
    }));
    apiMocks.listMenuItems.mockResolvedValue(createMenuEnvelope());
    apiMocks.updateOrderItem.mockResolvedValue({ data: { order_id: 56, row_version: 11 } });

    renderWithProviders('/orders?source=board&table_id=12&reservation_id=34&reservation_row_version=5&order_id=56&order_row_version=10');

    await waitFor(() => expect(apiMocks.getOrderDetail).toHaveBeenCalled());
    await waitFor(() => expect(apiMocks.listMenuItems).toHaveBeenCalled());

    const mergeButton = await screen.findByRole('button', { name: /Cộng số lượng|Cộng SL/i }, { timeout: 10000 });
    fireEvent.click(mergeButton);

    await waitFor(() => expect(apiMocks.updateOrderItem).toHaveBeenCalledWith(56, 201, {
      qty: 2,
      note: null,
      order_row_version: 10,
      row_version: 3,
    }));
    expect(apiMocks.addOrderItems).not.toHaveBeenCalled();
  }, 10000);

  it('creates a new line for the same item when the existing line is already locked by lifecycle status', async () => {
    apiMocks.getOrderDetail.mockResolvedValue(createOrderDetailEnvelope({
      items: [
        createOrderItem({
          order_item_id: 201,
          item_id: 100,
          quantity: 1,
          status: 'Served',
          row_version: 3,
        }),
      ],
    }));
    apiMocks.listMenuItems.mockResolvedValue(createMenuEnvelope());
    apiMocks.addOrderItems.mockResolvedValue({ data: { order_id: 56, row_version: 11 } });

    renderWithProviders('/orders?source=board&table_id=12&reservation_id=34&reservation_row_version=5&order_id=56&order_row_version=10');

    await waitFor(() => expect(apiMocks.getOrderDetail).toHaveBeenCalled());
    await waitFor(() => expect(apiMocks.listMenuItems).toHaveBeenCalled());

    const addButton = await screen.findByRole('button', { name: /Thêm món|Thêm/i }, { timeout: 10000 });
    fireEvent.click(addButton);

    await waitFor(() => expect(apiMocks.addOrderItems).toHaveBeenCalledWith(56, {
      row_version: 10,
      items: [
        {
          menu_item_id: 100,
          qty: 1,
          note: null,
        },
      ],
    }));
    expect(apiMocks.updateOrderItem).not.toHaveBeenCalled();
  });

  it('creates a new line for the same item when the order already has payment activity', async () => {
    apiMocks.getOrderDetail.mockResolvedValue(createOrderDetailEnvelope({
      paymentStatus: 'Partial',
      items: [
        createOrderItem({
          order_item_id: 201,
          item_id: 100,
          quantity: 1,
          status: 'Ordered',
          row_version: 3,
        }),
      ],
    }));
    apiMocks.listMenuItems.mockResolvedValue(createMenuEnvelope());
    apiMocks.addOrderItems.mockResolvedValue({ data: { order_id: 56, row_version: 11 } });

    renderWithProviders('/orders?source=board&table_id=12&reservation_id=34&reservation_row_version=5&order_id=56&order_row_version=10');

    await waitFor(() => expect(apiMocks.getOrderDetail).toHaveBeenCalled());
    await waitFor(() => expect(apiMocks.listMenuItems).toHaveBeenCalled());

    expect(await screen.findByText(/Đơn đã ghi nhận thanh toán\./i, {}, { timeout: 10000 })).toBeInTheDocument();
    const addButton = await screen.findByRole('button', { name: /Thêm món|Thêm/i }, { timeout: 10000 });
    fireEvent.click(addButton);

    await waitFor(() => expect(apiMocks.addOrderItems).toHaveBeenCalledWith(56, {
      row_version: 10,
      items: [
        {
          menu_item_id: 100,
          qty: 1,
          note: null,
        },
      ],
    }));
    expect(apiMocks.updateOrderItem).not.toHaveBeenCalled();
  });

  it('renders snapshot guest and multi-table context from journey metadata', async () => {
    apiMocks.getOrderDetail.mockResolvedValue(createOrderDetailEnvelope());
    apiMocks.listMenuItems.mockResolvedValue(createMenuEnvelope());

    renderWithProviders('/orders?source=reservation&reservation_id=34&reservation_row_version=5&table_ids=12,14&order_id=56&order_row_version=10');

    expect(await screen.findByText('Khách snapshot')).toBeInTheDocument();
    expect(screen.getAllByText('T12, T14')).not.toHaveLength(0);
  });
  it('navigates to kitchen with the dispatched station context', async () => {
    apiMocks.getOrderDetail.mockResolvedValue(createOrderDetailEnvelope());
    apiMocks.listMenuItems.mockResolvedValue(createMenuEnvelope());
    apiMocks.dispatchKitchenOrder.mockResolvedValue(createKitchenDispatchEnvelope({
      ticketId: 801,
      stationId: 33,
      orderId: 56,
      reservationId: 34,
    }));

    renderWithProviders('/orders?source=board&table_id=12&reservation_id=34&reservation_row_version=5&order_id=56&order_row_version=10');

    fireEvent.click(await screen.findByRole('button', { name: 'Chuyển sang bếp' }));

    await waitFor(() => expect(confirmActionMock).toHaveBeenCalled());
    await waitFor(() => expect(apiMocks.dispatchKitchenOrder).toHaveBeenCalledWith(56, {
      row_version: 10,
    }));
    await waitFor(() => expect(screen.getByTestId('kitchen-destination')).toBeInTheDocument());
    expect(screen.getByTestId('location-search').textContent).toContain('order_id=56');
    expect(screen.getByTestId('location-search').textContent).toContain('station_id=33');
  });
});

function createMenuEnvelope() {
  return {
    data: [
      {
        item_id: 100,
        category_id: 10,
        category_name: 'Nước uống',
        code: 'WATER',
        name: 'House Water',
        description: 'Nước suối phục vụ tại bàn',
        img_url: '/menu/water.jpg',
        is_available: true,
        price: {
          price_id: 1,
          amount: '10000',
          currency: 'VND',
          effective_from: null,
          effective_to: null,
        },
        preorder: {
          enabled: true,
          cutoff_minutes: 0,
          quota_per_day: null,
          requires_preview_validation: false,
        },
        created_at: null,
        updated_at: null,
      },
    ],
    meta: {
      current_page: 1,
      per_page: 32,
      from: 1,
      to: 1,
      total: 1,
      last_page: 1,
      has_more_pages: false,
      service_time: '2026-04-10T09:00:00Z',
      filters: {
        category_id: null,
        preorder_only: false,
        q: null,
      },
    },
  };
}

function createOrderItem(overrides: Partial<{
  order_item_id: number;
  item_id: number;
  quantity: number;
  status: string;
  row_version: number | null;
  notes: string | null;
}> = {}) {
  const itemId = overrides.item_id ?? 100;

  return {
    order_item_id: overrides.order_item_id ?? 201,
    item_id: itemId,
    quantity: overrides.quantity ?? 1,
    status: overrides.status ?? 'Ordered',
    row_version: overrides.row_version ?? 3,
    item_name_snapshot: 'House Water',
    unit_price: '10000',
    currency: 'VND',
    line_total: '10000',
    notes: overrides.notes ?? null,
    item: {
      name: 'House Water',
      code: 'WATER',
    },
  };
}

function createOrderDetailEnvelope(overrides: Partial<{
  items: ReturnType<typeof createOrderItem>[];
  paymentStatus: string;
  orderId: number;
  orderRowVersion: number;
  reservationId: number;
  reservationRowVersion: number;
}> = {}) {
  const paymentStatus = overrides.paymentStatus ?? 'Pending';
  const orderId = overrides.orderId ?? 56;
  const orderRowVersion = overrides.orderRowVersion ?? 10;
  const reservationId = overrides.reservationId ?? 34;
  const reservationRowVersion = overrides.reservationRowVersion ?? 5;

  return {
    data: {
      order: {
        order_id: orderId,
        reservation_id: reservationId,
        order_type: 'OnSpot',
        status: 'Active',
        row_version: orderRowVersion,
        payment_status: paymentStatus,
      },
      table: null,
      tables: [
        {
          table_id: 12,
          table_code: 'T12',
          zone: 'Main',
          seats: 4,
          status: 'Available',
        },
        {
          table_id: 14,
          table_code: 'T14',
          zone: 'Patio',
          seats: 6,
          status: 'Available',
        },
      ],
      reservation: {
        reservation_id: reservationId,
        reservation_code: `RSV-${reservationId}`,
        status: 'Reserved',
        row_version: reservationRowVersion,
        table_ids: [12, 14],
        guest: {
          full_name: 'Caller Guest',
          phone: '0900000000',
          email: 'caller@example.test',
          is_snapshot_only: true,
        },
        user: null,
      },
      customer: {
        full_name: 'Khách bàn 12',
        phone: '0900000000',
      },
      items: overrides.items ?? [],
      item_summary: {
        line_count: overrides.items?.length ?? 0,
        quantity_total: overrides.items?.reduce((total, item) => total + item.quantity, 0) ?? 0,
        active_quantity: overrides.items?.reduce((total, item) => total + item.quantity, 0) ?? 0,
        cancelled_quantity: 0,
        status_counts: {},
        status_quantities: {},
      },
      financial_summary: {
        settlement_scope: 'order',
        subtotal: '10000',
        discount: '0',
        total_due: '10000',
        paid: '0',
        deposit_applied: '0',
        deposit_net: '0',
        final_paid: '0',
        outstanding: '10000',
        currency: 'VND',
        payment_status: paymentStatus,
        reservation_payment_summary: null,
      },
    },
    meta: {
      action: 'show',
      selection_policy: 'explicit',
    },
  };
}

function createActiveOrderEnvelope(overrides: Partial<{
  orderId: number;
  rowVersion: number;
}> = {}) {
  return {
    data: {
      order: {
        order_id: overrides.orderId ?? 56,
        row_version: overrides.rowVersion ?? 10,
      },
    },
  };
}

function createReservationDetailEnvelope() {
  return {
    data: {
      reservation_id: 34,
      reservation_code: 'RSV-34',
      row_version: 5,
      table_ids: [12, 14],
      guest: {
        full_name: 'Caller Guest',
        phone: '0900000000',
        email: 'caller@example.test',
        is_snapshot_only: true,
      },
      user: null,
    },
  };
}

function createKitchenDispatchEnvelope(overrides: Partial<{
  ticketId: number;
  stationId: number;
  orderId: number;
  reservationId: number;
}> = {}) {
  return {
    data: [
      {
        ticket_id: overrides.ticketId ?? 801,
        ticket_status: 'Queued',
        station: {
          station_id: overrides.stationId ?? 33,
          code: 'HOT',
          name: 'Hot Pass',
        },
        order: {
          order_id: overrides.orderId ?? 56,
          reservation_id: overrides.reservationId ?? 34,
        },
      },
    ],
    meta: {
      action: 'kitchen_order_dispatched',
      created_count: 1,
      reused_count: 0,
      unrouted_count: 0,
      pinned_route_count: 0,
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
            <Route path="/orders" element={<><OrderWorkspacePage /><LocationProbe /></>} />
            <Route path="/kitchen" element={<><div data-testid="kitchen-destination" /><LocationProbe /></>} />
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
