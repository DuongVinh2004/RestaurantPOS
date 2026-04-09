import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { StaffShell } from './StaffShell';
import { StaffSessionContext, type StaffSessionContextValue } from '../../app/session-context';
import { buildApiError, buildStaffSession } from '../../test/fixtures';

describe('StaffShell', () => {
  it('shows startup context resolved from the staff auth session envelope', () => {
    renderWithShell(createSessionContext());

    expect(screen.getByText('Operator ready')).toBeInTheDocument();
    expect(screen.getByText('Branch MAIN')).toBeInTheDocument();
    expect(screen.getByText('Shift SHIFT-STAFF-WEB')).toBeInTheDocument();
  });

  it('keeps the active session when refresh fails with a non-auth error', async () => {
    const context = createSessionContext({
      refresh: vi.fn().mockRejectedValue(new Error('Gateway timeout.')),
    });

    renderWithShell(context);

    fireEvent.click(screen.getByRole('button', { name: 'Refresh token' }));

    await waitFor(() => expect(context.refresh).toHaveBeenCalled());
    expect(context.expire).not.toHaveBeenCalled();
    expect(context.setNotice).toHaveBeenCalledWith('Gateway timeout.', 'error');
    expect(screen.getByText('Board outlet')).toBeInTheDocument();
  });

  it('expires the session and redirects to login when refresh returns 401', async () => {
    const context = createSessionContext({
      refresh: vi.fn().mockRejectedValue(buildApiError(401, { message: 'Unauthorized.' })),
    });

    renderWithShell(context);

    fireEvent.click(screen.getByRole('button', { name: 'Refresh token' }));

    await waitFor(() => expect(context.expire).toHaveBeenCalled());
    expect(context.setNotice).not.toHaveBeenCalledWith(expect.any(String), 'error');
    expect(await screen.findByText('Login page')).toBeInTheDocument();
  });
});

function renderWithShell(context: StaffSessionContextValue) {
  return render(
    <StaffSessionContext.Provider value={context}>
      <MemoryRouter initialEntries={['/board']}>
        <Routes>
          <Route element={<StaffShell />}>
            <Route path="/board" element={<div>Board outlet</div>} />
            <Route path="/access" element={<div>Access page</div>} />
          </Route>
          <Route path="/login" element={<div>Login page</div>} />
        </Routes>
      </MemoryRouter>
    </StaffSessionContext.Provider>,
  );
}

function createSessionContext(overrides: Partial<StaffSessionContextValue> = {}): StaffSessionContextValue {
  return {
    session: buildStaffSession(),
    booting: false,
    notice: null,
    noticeTone: 'success',
    setAuthenticatedSession: vi.fn(),
    setNotice: vi.fn(),
    clearNotice: vi.fn(),
    refresh: vi.fn().mockResolvedValue(undefined),
    logout: vi.fn().mockResolvedValue(undefined),
    expire: vi.fn(),
    ...overrides,
  };
}
