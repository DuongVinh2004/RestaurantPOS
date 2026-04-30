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
    expect(container.querySelector('.staff-shell-workspace-kicker')?.textContent).toBe('Vận hành');
    expect(screen.queryByText('Tables, reservations, orders, and checkout stay coordinated in one operator lane.')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Tổng quan' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Phiếu bếp' })).not.toBeInTheDocument();
    expect(document.title).toBe('Tổng quan | Vận hành | RestaurantPOS Staff');
  });

  it('renders the kitchen shell with the lower-distraction frame', () => {
    mockedUseStaffShellContext.mockReturnValue(buildShellContext('kitchen'));

    const { container } = renderShell('/kitchen/board');

    expect(container.querySelector('.staff-shell-layout-kitchen')).not.toBeNull();
    expect(container.querySelector('.staff-shell-workspace-kicker')?.textContent).toBe('Bếp');
    expect(screen.queryByText('Station, queue, and live sync stay in one kitchen lane.')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Phiếu bếp' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Tìm nhanh' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Báo cáo' })).not.toBeInTheDocument();
    expect(document.title).toBe('Phiếu bếp | Bếp | RestaurantPOS Staff');
  });

  it('renders the admin shell with the back-office frame', () => {
    mockedUseStaffShellContext.mockReturnValue(buildShellContext('admin'));

    const { container } = renderShell('/admin/reporting');

    expect(container.querySelector('.staff-shell-layout-admin')).not.toBeNull();
    expect(container.querySelector('.staff-shell-workspace-kicker')?.textContent).toBe('Quản trị');
    expect(screen.queryByText('Settings, governance, and read models stay inside one admin lane.')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Báo cáo' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Tổng quan' })).not.toBeInTheDocument();
    expect(document.title).toBe('Báo cáo | Quản trị | RestaurantPOS Staff');
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
  const label = workspace === 'ops' ? 'Vận hành' : workspace === 'kitchen' ? 'Bếp' : 'Quản trị';
  const navItem = workspace === 'ops'
    ? {
      key: 'dashboard',
      label: 'Tổng quan',
      path: '/ops/dashboard',
      iconKey: 'dashboard' as const,
      workspace,
      description: 'Tổng quan vận hành',
    }
    : workspace === 'kitchen'
      ? {
        key: 'kitchen-board',
        label: 'Phiếu bếp',
        path: '/kitchen/board',
        iconKey: 'kitchen' as const,
        workspace,
        description: 'Phiếu bếp',
      }
      : {
        key: 'reporting',
        label: 'Báo cáo',
        path: '/admin/reporting',
        iconKey: 'reporting' as const,
        workspace,
        description: 'Báo cáo',
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
