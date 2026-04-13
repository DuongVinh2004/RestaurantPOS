import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../../app/store/auth-store';
import { useFlowStore } from '../../app/store/flow-store';
import { buildStaffSession } from '../../test/fixtures';
import { DashboardPage } from './DashboardPage';

const staffApiMocks = vi.hoisted(() => ({
  buildBoardWindow: vi.fn(() => ({
    from: '2026-04-10T09:00:00Z',
    to: '2026-04-10T13:00:00Z',
  })),
  getCurrentCashierShift: vi.fn(),
  getTableBoard: vi.fn(),
  listBranches: vi.fn(),
  listConversations: vi.fn(),
  listDailyInventoryReporting: vi.fn(),
  listDailyOperationsReporting: vi.fn(),
  listDailySalesReporting: vi.fn(),
  listFinancialReconciliation: vi.fn(),
  listKitchenStations: vi.fn(),
  listReservations: vi.fn(),
  listWaitingList: vi.fn(),
}));

vi.mock('../../core/api/staff-api', () => staffApiMocks);

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();

describe('DashboardPage branch scope', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useFlowStore.setState(initialFlowState, true);
    useAuthStore.setState({
      ...initialAuthState,
      status: 'authenticated',
      session: buildStaffSession({
        capabilities: ['kitchen.manage', 'settlement.manage', 'cashier.shift.manage'],
        known_capabilities: ['kitchen.manage', 'settlement.manage', 'cashier.shift.manage'],
      }),
      notice: null,
    }, true);
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 7,
    });

    staffApiMocks.listBranches.mockResolvedValue({ data: [] });
    staffApiMocks.listKitchenStations.mockResolvedValue({ data: [], meta: {} });
    staffApiMocks.listFinancialReconciliation.mockResolvedValue({ data: [], meta: {} });
    staffApiMocks.getCurrentCashierShift.mockRejectedValue({
      response: { status: 404 },
    });
  });

  it('passes the shell branch to kitchen, finance and current cashier queries', async () => {
    renderDashboard();

    await waitFor(() => expect(staffApiMocks.listKitchenStations).toHaveBeenCalledWith(7));
    await waitFor(() => expect(staffApiMocks.getCurrentCashierShift).toHaveBeenCalledWith(7));
    await waitFor(() => expect(staffApiMocks.listFinancialReconciliation).toHaveBeenCalledWith(expect.objectContaining({
      branch_id: 7,
    })));
  });
});

function renderDashboard() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
    },
  });

  return render(
    <AntdApp>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={['/dashboard']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <Routes>
            <Route path="/dashboard" element={<DashboardPage />} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}
