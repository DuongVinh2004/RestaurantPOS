import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { App as AntdApp } from 'antd';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { buildStaffSession } from '../../../../test/fixtures';
import { AdminLandingPage } from './AdminLandingPage';

const apiMocks = vi.hoisted(() => ({
  listAdminBranches: vi.fn(),
  listAdminPurchaseOrders: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', async () => {
  const actual = await vi.importActual<object>('../../../../shared/api/staff-api');
  return {
    ...actual,
    listAdminBranches: apiMocks.listAdminBranches,
    listAdminPurchaseOrders: apiMocks.listAdminPurchaseOrders,
  };
});

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();

describe('AdminLandingPage', () => {
  beforeEach(() => {
    Object.defineProperty(window, 'matchMedia', {
      configurable: true,
      value: vi.fn().mockReturnValue({
        matches: false,
        media: '',
        onchange: null,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        addListener: vi.fn(),
        removeListener: vi.fn(),
        dispatchEvent: vi.fn(),
      }),
    });
    useAuthStore.setState(initialAuthState, true);
    useFlowStore.setState(initialFlowState, true);
    apiMocks.listAdminBranches.mockReset();
    apiMocks.listAdminPurchaseOrders.mockReset();
    apiMocks.listAdminBranches.mockResolvedValue({ data: [{ branch_id: 3, branch_code: 'B3', branch_name: 'Branch 3' }] });
    apiMocks.listAdminPurchaseOrders.mockResolvedValue({ data: [{ purchase_order_id: 1 }] });
  });

  it('shows capability-aware admin domains and opens the settings lane', async () => {
    useAuthStore.getState().setSession(buildStaffSession({
      capabilities: ['settings.manage', 'inventory.manage', 'audit.view'],
      known_capabilities: ['settings.manage', 'inventory.manage', 'audit.view'],
    }));
    useFlowStore.getState().setBranchId(3);

    renderPage();

    expect(await screen.findByText('Trung tâm quản trị')).toBeInTheDocument();
    expect(screen.getByText('Sơ đồ tổng quan')).toBeInTheDocument();
    expect(screen.getByText('Phân hệ quản trị')).toBeInTheDocument();
    expect(screen.getByText('Tín hiệu vận hành')).toBeInTheDocument();
    expect(await screen.findByRole('button', { name: 'Chi nhánh và thiết lập' })).toBeInTheDocument();
    expect(screen.getByText('Thực đơn và giá bán')).toBeInTheDocument();
    expect(screen.getAllByText('Cần quyền').length).toBeGreaterThan(0);

    fireEvent.click(screen.getByRole('button', { name: 'Chi nhánh và thiết lập' }));

    await waitFor(() => expect(screen.getByTestId('location')).toHaveTextContent('/admin/settings'));
  });
});

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <AntdApp>
        <MemoryRouter initialEntries={['/admin']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <Routes>
            <Route path="/admin" element={<AdminLandingPage />} />
            <Route path="/admin/settings" element={<LocationProbe />} />
          </Routes>
        </MemoryRouter>
      </AntdApp>
    </QueryClientProvider>,
  );
}

function LocationProbe() {
  const location = useLocation();
  return <div data-testid="location">{location.pathname}</div>;
}
