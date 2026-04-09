import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { StaffSessionProvider } from './session';
import { useStaffSession } from './session-context';
import { buildApiError, buildStaffSession } from '../test/fixtures';

const clientMocks = vi.hoisted(() => ({
  getStaffToken: vi.fn(),
  getCurrentStaffSession: vi.fn(),
  clearStaffSession: vi.fn(),
  logoutStaff: vi.fn(),
  refreshStaffSession: vi.fn(),
  formatApiError: vi.fn((error: unknown, fallback: string) => (error instanceof Error ? error.message : fallback)),
  isUnauthorized: vi.fn((error: unknown) => Boolean((error as { status?: number })?.status === 401)),
}));

vi.mock('../api/client', () => clientMocks);

describe('StaffSessionProvider', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('restores the staff session from auth/me when a token exists', async () => {
    clientMocks.getStaffToken.mockReturnValue('staff-token');
    clientMocks.getCurrentStaffSession.mockResolvedValue(buildStaffSession());

    render(
      <StaffSessionProvider>
        <Probe />
      </StaffSessionProvider>,
    );

    await waitFor(() => expect(screen.getByText('ready')).toBeInTheDocument());
    expect(screen.getByText('Front Desk')).toBeInTheDocument();
  });

  it('clears the local token and exposes a relogin notice when restore gets 401', async () => {
    clientMocks.getStaffToken.mockReturnValue('expired-token');
    clientMocks.getCurrentStaffSession.mockRejectedValue(buildApiError(401, { message: 'Unauthorized.' }));

    render(
      <StaffSessionProvider>
        <Probe />
      </StaffSessionProvider>,
    );

    await waitFor(() => expect(screen.getByText('ready')).toBeInTheDocument());
    expect(clientMocks.clearStaffSession).toHaveBeenCalled();
    expect(screen.getByText(/Phien staff da het han/i)).toBeInTheDocument();
    expect(screen.getByText('error')).toBeInTheDocument();
  });

  it('keeps the stored token and surfaces a retry notice when restore fails with a non-auth error', async () => {
    clientMocks.getStaffToken.mockReturnValue('staff-token');
    clientMocks.getCurrentStaffSession.mockRejectedValue(buildApiError(500, { message: 'Gateway timeout.' }, 'Gateway timeout.'));

    render(
      <StaffSessionProvider>
        <Probe />
      </StaffSessionProvider>,
    );

    await waitFor(() => expect(screen.getByText('ready')).toBeInTheDocument());
    expect(clientMocks.clearStaffSession).not.toHaveBeenCalled();
    expect(screen.getByText('Gateway timeout.')).toBeInTheDocument();
    expect(screen.getByText('error')).toBeInTheDocument();
  });
});

function Probe() {
  const { booting, session, notice, noticeTone } = useStaffSession();

  return (
    <div>
      <span>{booting ? 'booting' : 'ready'}</span>
      <span>{session?.user?.full_name ?? 'no-session'}</span>
      <span>{notice ?? 'no-notice'}</span>
      <span>{noticeTone}</span>
    </div>
  );
}
