import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { useFlowStore } from '../../app/store/flow-store';
import { OrderWorkspacePage } from './OrderWorkspacePage';

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
});

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
            <Route path="/orders" element={<OrderWorkspacePage />} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}
