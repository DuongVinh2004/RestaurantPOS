import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { buildStaffSession } from '../../../../test/fixtures';
import { DashboardPage } from './DashboardPage';

const staffApiMocks = vi.hoisted(() => ({
  buildBoardWindow: vi.fn(),
  getCurrentCashierShift: vi.fn(),
  getTableBoard: vi.fn(),
  listBranches: vi.fn(),
  listConversations: vi.fn(),
  listDailyInventoryReporting: vi.fn(),
  listDailyOperationsReporting: vi.fn(),
  listDailySalesReporting: vi.fn(),
  listFinancialReconciliation: vi.fn(),
  listKitchenStations: vi.fn(),
  listReservations: vi.fn(),
  listWaitingList: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', () => staffApiMocks);

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();
const allCapabilities = [
  'table.board.view',
  'reservation.manage',
  'waiting_list.manage',
  'kitchen.manage',
  'settlement.manage',
  'cashier.shift.manage',
  'conversation.manage',
  'reporting.view',
];

describe('DashboardPage', () => {
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
    useFlowStore.setState(initialFlowState, true);
    useAuthStore.setState({
      ...initialAuthState,
      status: 'authenticated',
      session: buildStaffSession({
        capabilities: allCapabilities,
        known_capabilities: allCapabilities,
      }),
      notice: null,
    });
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 1,
    });

    staffApiMocks.buildBoardWindow.mockReturnValue({
      from: '2026-04-10T09:00:00Z',
      to: '2026-04-10T13:00:00Z',
    });
    staffApiMocks.listBranches.mockResolvedValue({
      data: [
        {
          branch_id: 1,
          branch_code: 'MAIN',
          branch_name: 'Chi nhanh chinh',
        },
      ],
    });
    staffApiMocks.getTableBoard.mockResolvedValue({
      data: [
        {
          table_id: 1,
          table_code: 'A1',
          zone: 'Main hall',
          capacity: { seats: 4 },
          board_state: 'Occupied',
          availability: { accepts_new_assignment: false },
          operational_hints: { preferred_action: 'open_order' },
          active_order: { order_id: 810 },
          reservation: { reservation_code: 'RSV-101' },
        },
        {
          table_id: 2,
          table_code: 'A2',
          zone: 'Main hall',
          capacity: { seats: 2 },
          board_state: 'Available',
          availability: { accepts_new_assignment: true },
          operational_hints: { preferred_action: 'seat_waiting' },
          active_order: null,
          reservation: null,
        },
      ],
      summary: {
        active_order_count: 3,
        unassigned_reservation_count: 2,
      },
      unassigned_reservations: [{ reservation_id: 1 }, { reservation_id: 2 }],
      meta: {},
    });
    staffApiMocks.listReservations.mockResolvedValue({
      data: [
        {
          reservation_id: 101,
          reservation_code: 'RSV-101',
          user: { full_name: 'Nguyễn An' },
          start_time: '2026-04-10T11:30:00Z',
          guest_count: 4,
          status: 'Confirmed',
        },
      ],
      meta: {},
    });
    staffApiMocks.listWaitingList.mockResolvedValue({
      data: [
        {
          waiting_id: 55,
          guest_name: 'Trần Bình',
          guest_count: 2,
          status: 'Waiting',
          current_response_state: 'pending',
          orchestration: { recommended_action: 'notify' },
        },
      ],
      meta: {
        summary: {
          ready_to_seat_count: 1,
          awaiting_customer_follow_up_count: 1,
        },
      },
    });
    staffApiMocks.listKitchenStations.mockResolvedValue({
      data: [
        {
          station_id: 1,
          name: 'Bếp nóng',
          code: 'HOT',
          output_mode: 'queue',
          ticket_counts: {
            queued: 4,
            fired: 2,
            ready: 1,
          },
        },
      ],
      meta: {},
    });
    staffApiMocks.listFinancialReconciliation.mockResolvedValue({
      data: [
        {
          reservation: {
            reservation_id: 101,
            reservation_code: 'RSV-101',
            customer: { full_name: 'Nguyễn An' },
            bill_currency: 'VND',
            status: 'Completed',
          },
          payment_summary: {
            net_paid_amount: 1200000,
            over_refunded_amount: 0,
            currency: { currency: 'VND' },
          },
          reconciliation: {
            bill_outstanding_amount: 200000,
          },
          flags: {
            has_discrepancy: true,
            has_over_refund: false,
            has_bill_outstanding: true,
            has_bill_overpaid: false,
            has_mixed_payment_currencies: false,
            is_fully_settled: false,
          },
        },
      ],
      meta: {},
    });
    staffApiMocks.getCurrentCashierShift.mockResolvedValue({
      data: {
        shift_code: 'SHIFT-01',
        status: 'Open',
        currency: 'VND',
        expected_cash_amount: 500000,
        summary: {
          payments: { payment_count: 6 },
          cash: { expected_cash_amount: 500000 },
          methods: [
            {
              payment_method: 'Cash',
              net_amount: 800000,
              currency: 'VND',
            },
          ],
        },
      },
    });
    staffApiMocks.listConversations.mockResolvedValue({
      data: [
        {
          conversation_id: 'conv_1',
          latest_message: { message_text: 'Khách hỏi giờ giữ bàn' },
          latest_activity_at: '2026-04-10T11:20:00Z',
          created_at: '2026-04-10T10:00:00Z',
          branch: { branch_code: 'MAIN' },
          branch_id: 1,
          status: 'Open',
          assignment_state: { is_unassigned: true, is_mine: false },
          user: { full_name: 'Khách An' },
        },
      ],
      meta: {
        summary: {
          total: 3,
          assigned: 1,
          unassigned: 2,
          mine: 1,
        },
      },
    });
    staffApiMocks.listDailySalesReporting.mockResolvedValue({
      data: [
        {
          currency: 'VND',
          payments: { net_paid_amount: 4200000 },
          billed: { gross_bill_amount: 5000000, reservation_count: 8 },
          invoices: { issued_count: 6 },
        },
      ],
      meta: {
        snapshot_health: {
          status: 'healthy',
          is_empty: false,
          reasons: [],
          row_count: 1,
          latest_business_date: '2026-04-10',
          latest_refresh_age_seconds: 60,
        },
      },
    });
    staffApiMocks.listDailyOperationsReporting.mockResolvedValue({
      data: [
        {
          reservations: { completed_count: 7 },
          waiting_list: { seated_count: 3 },
          turn_time: { turn_count: 2, turn_minutes_total: 150 },
        },
      ],
      meta: {
        snapshot_health: {
          status: 'healthy',
          is_empty: false,
          reasons: [],
          row_count: 1,
          latest_business_date: '2026-04-10',
          latest_refresh_age_seconds: 60,
        },
      },
    });
    staffApiMocks.listDailyInventoryReporting.mockResolvedValue({
      data: [
        {
          movement_summary: {
            movement_count: 9,
            net_quantity_delta: 2.5,
            wastage_quantity: 0.4,
          },
        },
      ],
      meta: {
        snapshot_health: {
          status: 'healthy',
          is_empty: false,
          reasons: [],
          row_count: 1,
          latest_business_date: '2026-04-10',
          latest_refresh_age_seconds: 60,
        },
      },
    });
  });

  it('renders dashboard blocks from live query data', async () => {
    renderDashboard();

    expect(await screen.findByRole('heading', { level: 2, name: 'Cockpit vận hành Mộc Sen' })).toBeInTheDocument();
    expect(screen.getByText('Tình trạng ca')).toBeInTheDocument();
    expect(await screen.findAllByText('SHIFT-01')).not.toHaveLength(0);
  });

  it('deep-links from a KPI card into the relevant workspace', async () => {
    renderDashboard();

    const kpi = await screen.findByRole('button', { name: /Khách đang chờ/i });
    fireEvent.click(kpi);

    await waitFor(() => expect(screen.getByText('Waiting list route')).toBeInTheDocument());
  });
});

function renderDashboard() {
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
        <MemoryRouter initialEntries={[staffRoutePaths.ops.dashboard]} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <Routes>
            <Route path={staffRoutePaths.ops.dashboard} element={<DashboardPage />} />
            <Route path={staffRoutePaths.ops.waitingList} element={<div>Waiting list route</div>} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}
