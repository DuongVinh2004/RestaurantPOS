import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { AccessPage } from './AccessPage';
import { StaffSessionContext, type StaffSessionContextValue } from '../../app/session-context';
import { buildStaffSession } from '../../test/fixtures';

describe('AccessPage', () => {
  it('surfaces backend startup blockers when the operator shell is not ready', () => {
    const baseSession = buildStaffSession();
    const context = createSessionContext({
      session: buildStaffSession({
        startup: {
          ...baseSession.startup,
          readiness: {
            ...baseSession.startup.readiness,
            branch: 'missing',
            operator_ready: false,
          },
        },
      }),
    });

    render(
      <StaffSessionContext.Provider value={context}>
        <AccessPage />
      </StaffSessionContext.Provider>,
    );

    expect(screen.getByText('Session da xac thuc nhung chua du context van hanh')).toBeInTheDocument();
    expect(screen.getByText('Branch missing')).toBeInTheDocument();
    expect(screen.getByText('Backend chua resolve duoc default branch cho startup contract.')).toBeInTheDocument();
  });

  it('explains that finance flows stay locked until the startup shift is ready', () => {
    const baseSession = buildStaffSession();
    const context = createSessionContext({
      session: buildStaffSession({
        startup: {
          ...baseSession.startup,
          active_cashier_shift: null,
          readiness: {
            ...baseSession.startup.readiness,
            cashier_shift: 'action_required',
            operator_ready: true,
          },
        },
      }),
    });

    render(
      <StaffSessionContext.Provider value={context}>
        <AccessPage />
      </StaffSessionContext.Provider>,
    );

    expect(screen.getByText('Session startup da san sang cho shell nhung finance flows dang khoa')).toBeInTheDocument();
    expect(screen.getByText(/Board, orders, va inbox/i)).toBeInTheDocument();
    expect(screen.getByText(/Mo cashier shift hien hanh de mo settlement, refund, va cashier flows/i)).toBeInTheDocument();
  });
});

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
