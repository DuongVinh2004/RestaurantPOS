import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { buildStaffSession } from '../../test/fixtures';
import { staffRoutePaths } from '../router/workspace-paths';
import { useAuthStore } from '../store/auth-store';
import { useFlowStore } from '../store/flow-store';
import { useWorkspaceStore } from '../store/workspace-store';
import { useStaffShellContext } from './useStaffShellContext';

const branchApiMocks = vi.hoisted(() => ({
  listBranches: vi.fn(),
}));

vi.mock('../../shared/api/staff-branch-api', () => branchApiMocks);

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();
const initialWorkspaceState = useWorkspaceStore.getState();

describe('useStaffShellContext workspace routing', () => {
  beforeEach(() => {
    useAuthStore.setState(initialAuthState, true);
    useFlowStore.setState(initialFlowState, true);
    useWorkspaceStore.setState(initialWorkspaceState, true);
    branchApiMocks.listBranches.mockResolvedValue({
      data: [
        {
          branch_id: 1,
          branch_code: 'MAIN',
          branch_name: 'Chi nhanh chinh',
        },
      ],
    });
  });

  it('switches workspaces and redirects to the next workspace landing path without dropping the session', async () => {
    const session = buildStaffSession({
      capabilities: ['reservation.manage', 'kitchen.manage', 'audit.view'],
      known_capabilities: ['reservation.manage', 'kitchen.manage', 'audit.view'],
    });

    useAuthStore.getState().setSession(session);

    renderHarness(staffRoutePaths.ops.dashboard);

    expect(screen.getByTestId('workspace')).toHaveTextContent('ops');
    expect(screen.getByTestId('path')).toHaveTextContent(staffRoutePaths.ops.dashboard);
    expect(screen.getByTestId('nav-items')).toHaveTextContent('dashboard,reservations');

    fireEvent.click(screen.getByRole('button', { name: /switch kitchen/i }));

    await waitFor(() => expect(screen.getByTestId('path')).toHaveTextContent(staffRoutePaths.kitchen.landing));
    await waitFor(() => expect(screen.getByTestId('workspace')).toHaveTextContent('kitchen'));
    expect(screen.getByTestId('nav-items')).toHaveTextContent('kitchen-landing,kitchen-board');
    expect(useWorkspaceStore.getState().activeWorkspace).toBe('kitchen');
    expect(useAuthStore.getState().session?.staff_api_key_id).toBe(session.staff_api_key_id);
  });

  it('realigns the active workspace to the current route owner when the user deep-links into another workspace', async () => {
    const session = buildStaffSession({
      capabilities: ['reservation.manage', 'kitchen.manage', 'reporting.view'],
      known_capabilities: ['reservation.manage', 'kitchen.manage', 'reporting.view'],
    });

    useAuthStore.getState().setSession(session);

    renderHarness(staffRoutePaths.admin.reporting);

    await waitFor(() => expect(screen.getByTestId('workspace')).toHaveTextContent('admin'));
    expect(screen.getByTestId('path')).toHaveTextContent(staffRoutePaths.admin.reporting);
    expect(screen.getByTestId('workspace-options')).toHaveTextContent('ops,kitchen,admin');
    expect(screen.getByTestId('nav-items')).toHaveTextContent('admin-landing,reporting');
  });
});

function renderHarness(initialEntry: string) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <Routes>
          <Route path="*" element={<StaffShellContextHarness />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function StaffShellContextHarness() {
  const location = useLocation();
  const {
    activeWorkspace,
    handleWorkspaceSwitch,
    navigationGroups,
    workspaceOptions,
  } = useStaffShellContext();

  return (
    <div>
      <div data-testid="path">{location.pathname}</div>
      <div data-testid="workspace">{activeWorkspace ?? 'none'}</div>
      <div data-testid="workspace-options">{workspaceOptions.map((option) => option.workspace).join(',')}</div>
      <div data-testid="nav-items">
        {navigationGroups.flatMap((group) => group.items.map((item) => item.key)).join(',')}
      </div>
      <button type="button" onClick={() => handleWorkspaceSwitch('kitchen')}>
        Switch kitchen
      </button>
    </div>
  );
}
