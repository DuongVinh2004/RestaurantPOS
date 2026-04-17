import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { buildStaffSession } from '../../test/fixtures';
import { useAuthStore } from '../store/auth-store';
import { StaffAppShell } from './StaffAppShell';
import { useStaffShellContext } from './useStaffShellContext';

vi.mock('./useStaffShellContext', () => ({
  useStaffShellContext: vi.fn(),
}));

const initialAuthState = useAuthStore.getState();
const mockedUseStaffShellContext = vi.mocked(useStaffShellContext);

describe('StaffAppShell workspace shells', () => {
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
    useAuthStore.getState().setSession(buildStaffSession());
  });

  it('renders the ops shell with the floor-operations frame', () => {
    mockedUseStaffShellContext.mockReturnValue(buildShellContext('ops'));

    const { container } = renderShell('/ops/dashboard');

    expect(container.querySelector('.staff-shell-layout-ops')).not.toBeNull();
    expect(screen.getByText('Floor operations')).toBeInTheDocument();
    expect(screen.getByText('Tables, reservations, orders, and checkout stay coordinated in one operator lane.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Dashboard' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Ticket queue' })).not.toBeInTheDocument();
    expect(document.title).toBe('Dashboard | Ops | RestaurantPOS Staff');
  });

  it('renders the kitchen shell with the lower-distraction frame', () => {
    mockedUseStaffShellContext.mockReturnValue(buildShellContext('kitchen'));

    const { container } = renderShell('/kitchen/board');

    expect(container.querySelector('.staff-shell-layout-kitchen')).not.toBeNull();
    expect(screen.getByText('Kitchen line')).toBeInTheDocument();
    expect(screen.getByText('Station, queue, and live sync stay in one kitchen lane.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Ticket queue' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Tim' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Reporting' })).not.toBeInTheDocument();
    expect(document.title).toBe('Ticket queue | Kitchen | RestaurantPOS Staff');
  });

  it('renders the admin shell with the back-office frame', () => {
    mockedUseStaffShellContext.mockReturnValue(buildShellContext('admin'));

    const { container } = renderShell('/admin/reporting');

    expect(container.querySelector('.staff-shell-layout-admin')).not.toBeNull();
    expect(screen.getByText('Back office')).toBeInTheDocument();
    expect(screen.getByText('Settings, governance, and read models stay inside one admin lane.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reporting' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Dashboard' })).not.toBeInTheDocument();
    expect(document.title).toBe('Reporting | Admin | RestaurantPOS Staff');
  });
});

function renderShell(initialEntry: string) {
  return render(
    <MemoryRouter initialEntries={[initialEntry]} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <Routes>
        <Route path="*" element={<StaffAppShell />}>
          <Route path="*" element={<div>Workspace page</div>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  );
}

function buildShellContext(workspace: 'ops' | 'kitchen' | 'admin'): ReturnType<typeof useStaffShellContext> {
  const label = workspace === 'ops' ? 'Ops' : workspace === 'kitchen' ? 'Kitchen' : 'Admin';
  const navItem = workspace === 'ops'
    ? {
      key: 'dashboard',
      label: 'Dashboard',
      path: '/ops/dashboard',
      iconKey: 'dashboard' as const,
      workspace,
      description: 'Ops dashboard',
    }
    : workspace === 'kitchen'
      ? {
        key: 'kitchen-board',
        label: 'Ticket queue',
        path: '/kitchen/board',
        iconKey: 'kitchen' as const,
        workspace,
        description: 'Ticket queue',
      }
      : {
        key: 'reporting',
        label: 'Reporting',
        path: '/admin/reporting',
        iconKey: 'reporting' as const,
        workspace,
        description: 'Reporting',
      };

  return {
    activeWorkspace: workspace,
    branchId: 1,
    branchOptions: [{ value: 1, label: 'MAIN - Main branch' }],
    branchesQuery: {
      error: null,
      isLoading: false,
    },
    contextDock: [
      {
        key: 'branch',
        label: 'Branch',
        value: 'MAIN - Main branch',
        tone: 'success' as const,
      },
      {
        key: 'readiness',
        label: 'Readiness',
        value: 'Ready',
        tone: 'success' as const,
      },
      {
        key: 'context',
        label: 'Context',
        value: workspace === 'kitchen' ? 'Expo' : 'Default',
        tone: 'processing' as const,
      },
    ],
    freshnessLabel: 'Updated just now',
    freshnessTone: 'success' as const,
    handleBranchChange: vi.fn(),
    handleWorkspaceSwitch: vi.fn(),
    navigationGroups: [
      {
        key: `${workspace}-group`,
        label,
        items: [navItem],
      },
    ],
    otherBranchWorkItems: [],
    quickNavOptions: [],
    resumeTrayItems: [],
    routeDescriptor: {
      label: navItem.label,
      description: navItem.description,
    },
    routeScopedNotice: null,
    selectedMenuKey: navItem.key,
    session: buildStaffSession(),
    workspaceOptions: [
      {
        workspace,
        label,
        description: `${label} workspace`,
        landingPath: navItem.path,
      },
    ],
  } as unknown as ReturnType<typeof useStaffShellContext>;
}
