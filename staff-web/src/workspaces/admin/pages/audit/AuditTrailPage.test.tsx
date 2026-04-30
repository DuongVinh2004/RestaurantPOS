import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { buildStaffSession } from '../../../../test/fixtures';
import { AuditTrailPage } from './AuditTrailPage';

const staffApiMocks = vi.hoisted(() => ({
  listAuditTrail: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', () => ({
  listAuditTrail: staffApiMocks.listAuditTrail,
}));

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();

describe('AuditTrailPage', () => {
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
    staffApiMocks.listAuditTrail.mockReset();
    staffApiMocks.listAuditTrail.mockImplementation(async (query: Record<string, unknown>) => ({
      data: [
        {
          audit_id: 91,
          action: 'payment.refunded',
          occurred_at: '2026-04-10T09:00:00Z',
          primary_subject: {
            type: 'payment',
            id: '7001',
          },
          subjects: [
            { type: 'payment', id: '7001', role: 'primary' },
            { type: 'reservation', id: '301', role: 'reservation' },
          ],
          actor: {
            user_id: 8,
            type: 'staff_user',
            key: 'staff_api_key:11',
            user: {
              user_id: 8,
              full_name: 'Audit Lead',
            },
          },
          request: {
            request_id: 'req-branch-3',
            branch_id: 3,
            method: 'POST',
            path: '/api/v1/staff/reservations/301/refund',
          },
          before: null,
          after: null,
          summary: { refund_amount: '100000.00' },
          meta: { filters: query },
        },
      ],
      meta: {
        action: 'staff_audit_trail_index',
        page: query.page ?? 1,
        per_page: query.per_page ?? 20,
        total: 1,
        last_page: 1,
        filters: query,
      },
    }));

    useFlowStore.setState(initialFlowState, true);
    useAuthStore.setState({
      ...initialAuthState,
      status: 'authenticated',
      session: buildStaffSession({
        capabilities: ['reservation.manage', 'order.manage'],
        known_capabilities: ['reservation.manage', 'order.manage'],
      }),
      notice: null,
    });
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 3,
    });
  });

  it('defaults audit reads to the shell branch context and shows request linkage', async () => {
    renderAuditTrailPage();

    await waitFor(() => expect(staffApiMocks.listAuditTrail).toHaveBeenCalled());
    expect(staffApiMocks.listAuditTrail).toHaveBeenCalledWith(expect.objectContaining({
      branch_id: 3,
      page: 1,
      per_page: 20,
    }));

    expect((await screen.findAllByText('Chi nhánh #3')).length).toBeGreaterThan(0);
    await waitFor(() => expect(document.body.textContent ?? '').toContain('req-branch-3'));
  });

  it('resets request and search filters without losing shell branch scope', async () => {
    renderAuditTrailPage();

    const searchInput = await screen.findByLabelText('Tìm kiếm trong nhật ký audit');
    const requestIdInput = screen.getByLabelText('Lọc theo mã truy vết');

    fireEvent.change(searchInput, { target: { value: 'refund' } });
    fireEvent.change(requestIdInput, { target: { value: 'req-999' } });

    await waitFor(() => expect(staffApiMocks.listAuditTrail).toHaveBeenLastCalledWith(expect.objectContaining({
      branch_id: 3,
      q: 'refund',
      request_id: 'req-999',
    })));

    fireEvent.click(screen.getByRole('button', { name: /Đặt lại bộ lọc/i }));

    await waitFor(() => {
      expect(searchInput).toHaveValue('');
      expect(requestIdInput).toHaveValue('');
    });
    expect(staffApiMocks.listAuditTrail).toHaveBeenLastCalledWith(expect.objectContaining({
      branch_id: 3,
      request_id: undefined,
      q: undefined,
    }));
  });
});

function renderAuditTrailPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <AntdApp>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <AuditTrailPage />
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}
