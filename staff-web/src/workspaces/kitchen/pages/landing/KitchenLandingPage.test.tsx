import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { buildStaffSession } from '../../../../test/fixtures';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { KitchenLandingPage } from './KitchenLandingPage';

const apiMocks = vi.hoisted(() => ({
  getKitchenChanges: vi.fn(),
  listKitchenStations: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', () => apiMocks);

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();

describe('KitchenLandingPage', () => {
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
  });

  beforeEach(() => {
    vi.clearAllMocks();
    useAuthStore.setState(initialAuthState, true);
    useFlowStore.setState(initialFlowState, true);
    useAuthStore.getState().setSession(buildStaffSession({
      capabilities: ['kitchen.manage'],
      known_capabilities: ['kitchen.manage'],
      startup: {
        assigned_station_ids: [33],
        allowed_branch_ids: [9],
        default_branch_id: 9,
        branch_access: {
          accessible_branch_ids: [9],
          default_branch_id: 9,
          current_branch_id: 9,
        },
      },
    }));
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 9,
    });

    apiMocks.listKitchenStations.mockResolvedValue(createStationsEnvelope());
    apiMocks.getKitchenChanges.mockResolvedValue(createKitchenChangesEnvelope());
  });

  it('opens the ticket queue with assigned station context', async () => {
    renderLanding();

    fireEvent.click(await screen.findByRole('button', { name: /Hot Pass/i }));

    await waitFor(() => expect(screen.getByTestId('location')).toHaveTextContent('/kitchen/board'));
    expect(screen.getByTestId('location')).toHaveTextContent('station_id=33');
    expect(useFlowStore.getState().selectedStationId).toBe(33);
    expect(apiMocks.getKitchenChanges).toHaveBeenCalledWith(10, 9);
  });

  it('shows a station assignment guard when startup has no assigned station', async () => {
    useAuthStore.getState().setSession(buildStaffSession({
      capabilities: ['kitchen.manage'],
      known_capabilities: ['kitchen.manage'],
      startup: {
        assigned_station_ids: [],
        allowed_branch_ids: [9],
        default_branch_id: 9,
      },
    }));

    renderLanding();

    expect(await screen.findByText('No kitchen station is assigned')).toBeInTheDocument();
  });
});

function createStationsEnvelope() {
  return {
    data: [
      {
        station_id: 33,
        branch_id: 9,
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
      count: 1,
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

function createKitchenChangesEnvelope() {
  return {
    data: {
      enabled: true,
      topic: 'kitchen',
      channel: 'staff.kitchen',
      after_version: 10,
      current_version: 10,
      oldest_available_version: 1,
      events: [],
      has_changes: false,
      stale_cursor: false,
      poll_hint_ms: 20000,
    },
  };
}

function renderLanding() {
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
            <Route path="/kitchen" element={<><KitchenLandingPage /><LocationProbe /></>} />
            <Route path="/kitchen/board" element={<LocationProbe />} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}

function LocationProbe() {
  const location = useLocation();

  return <div data-testid="location">{`${location.pathname}${location.search}`}</div>;
}
