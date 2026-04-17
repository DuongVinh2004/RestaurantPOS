import { App as AntdApp } from 'antd';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { buildStaffSession } from '../../../../test/fixtures';
import { AccessGatePage } from './AccessGatePage';

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();

describe('AccessGatePage', () => {
  beforeAll(() => {
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: (query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: () => undefined,
        removeListener: () => undefined,
        addEventListener: () => undefined,
        removeEventListener: () => undefined,
        dispatchEvent: () => false,
      }),
    });

    class ResizeObserverMock {
      observe() {}
      unobserve() {}
      disconnect() {}
    }

    Object.defineProperty(globalThis, 'ResizeObserver', {
      writable: true,
      value: ResizeObserverMock,
    });
  });

  beforeEach(() => {
    sessionStorage.clear();
    useFlowStore.setState(initialFlowState, true);
    useAuthStore.setState({
      ...initialAuthState,
      status: 'authenticated',
      session: null,
      notice: null,
    });
  });

  it('recommends the dashboard when the session is fully ready', () => {
    useAuthStore.setState({
      ...useAuthStore.getState(),
      session: buildStaffSession({
        capabilities: ['table.board.view', 'conversation.manage'],
        known_capabilities: ['table.board.view', 'conversation.manage'],
      }),
    });

    renderWithProviders();

    expect(screen.getByRole('button', { name: /Mở Tổng quan/i })).toBeInTheDocument();
    expect(screen.getByText(/điều hướng theo bước công việc an toàn/i)).toBeInTheDocument();
  });

  it('pushes finance-blocked sessions toward cashier shift first', () => {
    const baseSession = buildStaffSession({
      capabilities: ['cashier.shift.manage', 'settlement.manage'],
      known_capabilities: ['cashier.shift.manage', 'settlement.manage'],
    });

    useAuthStore.setState({
      ...useAuthStore.getState(),
      session: buildStaffSession({
        ...baseSession,
        startup: {
          ...baseSession.startup,
          active_cashier_shift: null,
          readiness: {
            ...baseSession.startup.readiness,
            cashier_shift: 'action_required',
            requires_cashier_shift: true,
          },
        },
      }),
    });

    renderWithProviders();

    expect(screen.getByRole('button', { name: /Mở Ca thu ngân/i })).toBeInTheDocument();
    expect(screen.getByText(/thanh toán và đối soát vẫn khóa/i)).toBeInTheDocument();
  });
});

function renderWithProviders() {
  return render(
    <AntdApp>
      <MemoryRouter initialEntries={['/access']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <Routes>
          <Route path="/access" element={<AccessGatePage />} />
        </Routes>
      </MemoryRouter>
    </AntdApp>,
  );
}
