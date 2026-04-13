import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { StaffSessionProvider } from './session';
import { useStaffSession, useStaffSessionStoreBridge } from './session-context';
import { useAuthStore } from './store/auth-store';
import { buildStaffSession } from '../test/fixtures';

const initialState = useAuthStore.getState();

describe('StaffSessionProvider', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    useAuthStore.setState(initialState, true);
  });

  it('bootstraps through the shared auth store and exposes the session context', async () => {
    const bootstrap = vi.fn().mockImplementation(async () => {
      useAuthStore.setState({
        status: 'authenticated',
        session: buildStaffSession(),
        notice: null,
      });
    });

    useAuthStore.setState({
      ...useAuthStore.getState(),
      status: 'booting',
      session: null,
      notice: null,
      bootstrap,
    });

    render(
      <StaffSessionProvider>
        <Probe />
      </StaffSessionProvider>,
    );

    await waitFor(() => expect(bootstrap).toHaveBeenCalled());
    await waitFor(() => expect(screen.getByText('ready')).toBeInTheDocument());
    expect(screen.getByText('Front Desk')).toBeInTheDocument();
  });

  it('delegates setAuthenticatedSession to the shared auth store', async () => {
    useAuthStore.setState({
      ...useAuthStore.getState(),
      status: 'authenticated',
      session: buildStaffSession({ user: null }),
      notice: null,
      bootstrap: vi.fn().mockResolvedValue(undefined),
    });

    render(
      <StaffSessionProvider>
        <Probe />
      </StaffSessionProvider>,
    );

    fireEvent.click(screen.getByRole('button', { name: 'set-session' }));

    await waitFor(() => expect(screen.getByText('Front Desk')).toBeInTheDocument());
    expect(useAuthStore.getState().status).toBe('authenticated');
  });

  it('relays expire through the shared auth store', async () => {
    useAuthStore.setState({
      ...useAuthStore.getState(),
      status: 'authenticated',
      session: buildStaffSession(),
      notice: null,
      bootstrap: vi.fn().mockResolvedValue(undefined),
    });

    render(
      <StaffSessionProvider>
        <Probe />
      </StaffSessionProvider>,
    );

    fireEvent.click(screen.getByRole('button', { name: 'expire-session' }));

    await waitFor(() => expect(screen.getByText('no-session')).toBeInTheDocument());
    expect(screen.getByText('Expired from context')).toBeInTheDocument();
    expect(screen.getByText('error')).toBeInTheDocument();
  });

  it('falls back to the shared auth store even without a session provider', async () => {
    useAuthStore.setState({
      ...useAuthStore.getState(),
      status: 'authenticated',
      session: buildStaffSession(),
      notice: {
        tone: 'warning',
        message: 'Bridge fallback active',
      },
    });

    render(<FallbackProbe />);

    await waitFor(() => expect(screen.getByText('ready')).toBeInTheDocument());
    expect(screen.getByText('Front Desk')).toBeInTheDocument();
    expect(screen.getByText('Bridge fallback active')).toBeInTheDocument();
    expect(screen.getAllByText('warning')).toHaveLength(2);
  });
});

function Probe() {
  const { booting, session, notice, noticeTone, setAuthenticatedSession, expire } = useStaffSession();

  return (
    <div>
      <span>{booting ? 'booting' : 'ready'}</span>
      <span>{session?.user?.full_name ?? 'no-session'}</span>
      <span>{notice ?? 'no-notice'}</span>
      <span>{noticeTone}</span>
      <button type="button" onClick={() => setAuthenticatedSession(buildStaffSession())}>
        set-session
      </button>
      <button type="button" onClick={() => expire('Expired from context')}>
        expire-session
      </button>
    </div>
  );
}

function FallbackProbe() {
  const { booting, session, notice, noticeTone } = useStaffSession();
  const bridge = useStaffSessionStoreBridge();

  return (
    <div>
      <span>{booting ? 'booting' : 'ready'}</span>
      <span>{session?.user?.full_name ?? 'no-session'}</span>
      <span>{notice ?? 'no-notice'}</span>
      <span>{noticeTone}</span>
      <span>{bridge.noticeTone}</span>
    </div>
  );
}
