import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { useFlowStore } from '../../../../app/store/flow-store';
import { KitchenBoardPage } from './KitchenBoardPage';

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
  useConfirmAction: () => vi.fn(async () => true),
}));

const initialFlowState = useFlowStore.getState();

describe('KitchenBoardPage branch scope', () => {
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
    useFlowStore.setState(initialFlowState, true);
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 9,
    });

    apiMocks.listKitchenStations.mockResolvedValue({
      data: [
        {
          station_id: 21,
          code: 'HOT',
          name: 'Hot Pass',
          output_mode: 'Both',
          ticket_counts: {
            queued: 1,
            fired: 0,
            ready: 0,
          },
        },
      ],
      meta: {
        count: 1,
        realtime: {
          current_version: 12,
          poll_hint_ms: 20000,
        },
      },
    });
    apiMocks.getKitchenStationTickets.mockResolvedValue({
      data: [],
      meta: {
        station_id: 21,
        count: 0,
      },
    });
    apiMocks.getKitchenChanges.mockResolvedValue({
      data: {
        current_version: 12,
        events: [],
        poll_hint_ms: 20000,
      },
    });
  });

  it('uses the shell branch when loading station workload and ticket slices', async () => {
    renderKitchen();

    await waitFor(() => expect(apiMocks.listKitchenStations).toHaveBeenCalledWith(9));
    await waitFor(() => expect(apiMocks.getKitchenStationTickets).toHaveBeenCalledWith(21, expect.objectContaining({
      branch_id: 9,
    })));
  });
});

function renderKitchen() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <AntdApp>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={['/kitchen']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <Routes>
            <Route path="/kitchen" element={<KitchenBoardPage />} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}
