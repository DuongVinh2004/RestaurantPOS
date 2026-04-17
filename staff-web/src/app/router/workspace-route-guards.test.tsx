import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeEach, describe, expect, it } from 'vitest';
import { buildStaffSession } from '../../test/fixtures';
import { useAuthStore } from '../store/auth-store';
import { useWorkspaceStore } from '../store/workspace-store';
import { WorkspaceBoundary, WorkspaceIndexRedirect, WorkspaceRoute } from './workspace-route-guards';
import { staffRoutePaths } from './workspace-paths';

const initialAuthState = useAuthStore.getState();
const initialWorkspaceState = useWorkspaceStore.getState();

describe('workspace route guards', () => {
  beforeEach(() => {
    useAuthStore.setState(initialAuthState, true);
    useWorkspaceStore.setState(initialWorkspaceState, true);
  });

  it('redirects logged-out users to the login route', async () => {
    render(
      <MemoryRouter initialEntries={[staffRoutePaths.ops.tables]}>
        <Routes>
          <Route path={staffRoutePaths.login} element={<LocationProbe />} />
          <Route path={staffRoutePaths.ops.root} element={<WorkspaceBoundary workspace="ops" />}>
            <Route path="tables" element={<LocationProbe />} />
          </Route>
        </Routes>
      </MemoryRouter>,
    );

    await waitFor(() => expect(screen.getByTestId('location')).toHaveTextContent(staffRoutePaths.login));
  });

  it('redirects invalid workspace requests to the next allowed workspace landing path', async () => {
    useAuthStore.getState().setSession(buildStaffSession({
      capabilities: ['reservation.manage'],
      known_capabilities: ['reservation.manage'],
    }));

    render(
      <MemoryRouter initialEntries={[staffRoutePaths.admin.reporting]}>
        <Routes>
          <Route path={staffRoutePaths.ops.root} element={<WorkspaceBoundary workspace="ops" />}>
            <Route path="dashboard" element={<LocationProbe />} />
          </Route>
          <Route path={staffRoutePaths.admin.root} element={<WorkspaceBoundary workspace="admin" />}>
            <Route path="reporting" element={<LocationProbe />} />
          </Route>
        </Routes>
      </MemoryRouter>,
    );

    await waitFor(() => expect(screen.getByTestId('location')).toHaveTextContent(staffRoutePaths.ops.dashboard));
  });

  it('lands multi-workspace users on the primary workspace index destination', async () => {
    useAuthStore.getState().setSession(buildStaffSession({
      capabilities: ['reservation.manage', 'kitchen.manage'],
      known_capabilities: ['reservation.manage', 'kitchen.manage'],
    }));

    render(
      <MemoryRouter initialEntries={['/']}>
        <Routes>
          <Route path="/" element={<WorkspaceIndexRedirect workspace="ops" />} />
          <Route path={staffRoutePaths.ops.dashboard} element={<LocationProbe />} />
        </Routes>
      </MemoryRouter>,
    );

    await waitFor(() => expect(screen.getByTestId('location')).toHaveTextContent(staffRoutePaths.ops.dashboard));
  });

  it('renders a forbidden-capability state when the workspace exists but the page capability is missing', async () => {
    useAuthStore.getState().setSession(buildStaffSession({
      capabilities: ['reservation.manage'],
      known_capabilities: ['reservation.manage'],
    }));

    render(
      <MemoryRouter initialEntries={[staffRoutePaths.ops.tables]}>
        <Routes>
          <Route path={staffRoutePaths.ops.root} element={<WorkspaceBoundary workspace="ops" />}>
            <Route
              path="tables"
              element={(
                <WorkspaceRoute capability="table.board.view" workspace="ops">
                  <div>Tables workspace</div>
                </WorkspaceRoute>
              )}
            />
          </Route>
        </Routes>
      </MemoryRouter>,
    );

    expect(await screen.findByText(/Phiên hiện tại chưa có quyền/i)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Mở màn hình được cấp/i })).toHaveAttribute('href', staffRoutePaths.ops.dashboard);
  });
});

function LocationProbe() {
  const location = useLocation();

  return <div data-testid="location">{`${location.pathname}${location.search}`}</div>;
}
