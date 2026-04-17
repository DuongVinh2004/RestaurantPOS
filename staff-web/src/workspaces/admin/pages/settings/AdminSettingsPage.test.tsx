import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { App as AntdApp } from 'antd';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useFlowStore } from '../../../../app/store/flow-store';
import { AdminSettingsPage } from './AdminSettingsPage';

const apiMocks = vi.hoisted(() => ({
  listAdminBranches: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', async () => {
  const actual = await vi.importActual<object>('../../../../shared/api/staff-api');
  return {
    ...actual,
    listAdminBranches: apiMocks.listAdminBranches,
  };
});

const initialFlowState = useFlowStore.getState();

describe('AdminSettingsPage', () => {
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
    useFlowStore.setState(initialFlowState, true);
    apiMocks.listAdminBranches.mockReset();
    apiMocks.listAdminBranches.mockResolvedValue({
      data: [
        {
          branch_id: 1,
          branch_code: 'MAIN',
          branch_name: 'Main branch',
          timezone: 'Asia/Ho_Chi_Minh',
          currency: 'VND',
          is_default: true,
          is_active: true,
          row_version: 7,
          created_at: '2026-04-17T09:00:00Z',
          updated_at: '2026-04-17T10:00:00Z',
          business_hours: [{ day_of_week: 1, periods: [{ start_time: '08:00', end_time: '18:00' }] }],
          closure_windows: [],
          booking_policy: { reservation: { min_lead_time_minutes: 30, same_day_cutoff_time: '17:00' }, waiting_list: { enabled: true } },
        },
        {
          branch_id: 2,
          branch_code: 'ANNEX',
          branch_name: 'Annex',
          timezone: 'Asia/Ho_Chi_Minh',
          currency: 'VND',
          is_default: false,
          is_active: false,
          row_version: 2,
          created_at: '2026-04-17T09:00:00Z',
          updated_at: '2026-04-17T10:00:00Z',
          business_hours: [],
          closure_windows: [{ reason: 'Maintenance', start_local: '2026-04-18T00:00:00Z', end_local: '2026-04-18T04:00:00Z' }],
          booking_policy: {},
        },
      ],
    });
  });

  it('shows branch registry detail and configuration ownership surfaces', async () => {
    useFlowStore.getState().setBranchId(1);

    renderPage();

    expect(await screen.findByText('Branches and settings lane')).toBeInTheDocument();
    expect(await screen.findByText('Main branch')).toBeInTheDocument();
    expect(screen.getByText('Kitchen routing')).toBeInTheDocument();
    expect(apiMocks.listAdminBranches).toHaveBeenCalledWith({ q: undefined, is_active: true });

    fireEvent.click(screen.getByText('Annex'));

    await waitFor(() => expect(screen.getByText('Maintenance')).toBeInTheDocument());
    expect(screen.getByText('No business hours configured')).toBeInTheDocument();
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
        <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <AdminSettingsPage />
        </MemoryRouter>
      </AntdApp>
    </QueryClientProvider>,
  );
}
