import ReactDOM from 'react-dom/client';
import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import '../src/index.css';
import '../src/styles/design-bundle-overrides.css';
import '../src/styles/tokens.css';
import '../src/styles/ui-overrides.css';
import { StaffAppShell } from '../src/app/layout/StaffAppShell';
import { DashboardPage } from '../src/workspaces/ops/pages/dashboard/DashboardPage';
import { useAuthStore } from '../src/app/store/auth-store';
import { useFlowStore } from '../src/app/store/flow-store';
import { buildStaffSession } from '../src/test/fixtures';
import { writeStoredStaffToken } from '../src/shared/auth/storage';

const mode = new URLSearchParams(window.location.search).get('mode') ?? 'default';
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: false,
      staleTime: 30_000,
    },
  },
});

const allCapabilities = [
  'table.board.view',
  'reservation.manage',
  'waiting_list.manage',
  'order.manage',
  'kitchen.manage',
  'settlement.manage',
  'cashier.shift.manage',
  'conversation.manage',
  'audit.view',
  'reporting.view',
];

const session = buildStaffSession({
  capabilities: allCapabilities,
  known_capabilities: allCapabilities,
  startup: {
    default_branch: {
      branch_id: 1,
      branch_code: 'MAIN',
      branch_name: 'Chi nhanh Trung tam',
      timezone: 'Asia/Ho_Chi_Minh',
      currency: 'VND',
      is_default: true,
      is_active: true,
    },
    active_cashier_shift: {
      cashier_shift_id: 44,
      branch_id: 1,
      branch: {
        branch_id: 1,
        branch_code: 'MAIN',
        branch_name: 'Chi nhanh Trung tam',
        timezone: 'Asia/Ho_Chi_Minh',
        currency: 'VND',
        is_default: true,
        is_active: true,
      },
      shift_code: 'SHIFT-01',
      status: 'open',
      currency: 'VND',
      terminal_code: 'POS-01',
      row_version: 7,
      opened_at: '2026-04-10T08:00:00Z',
    },
    readiness: {
      access: 'ready',
      branch: 'ready',
      cashier_shift: 'ready',
      operator_ready: true,
      requires_cashier_shift: true,
      granted_capability_count: allCapabilities.length,
      known_capability_count: allCapabilities.length,
    },
  },
});

useAuthStore.setState({
  ...useAuthStore.getState(),
  status: 'authenticated',
  session,
  notice: mode === 'warning'
    ? {
      tone: 'warning',
      message: 'Đây là bản preview warning để kiểm tra wording staff-facing khi dashboard có nguồn dữ liệu lỗi.',
    }
    : null,
});
useFlowStore.setState({
  ...useFlowStore.getState(),
  branchId: 1,
}, true);
writeStoredStaffToken('staff-preview-token');

installFetchStub(mode, session);

ReactDOM.createRoot(document.getElementById('root')!).render(
  <AntdApp>
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/ops/dashboard']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <Routes>
          <Route element={<StaffAppShell />}>
            <Route path="/ops/dashboard" element={<DashboardPage />} />
            <Route path="/ops/tables" element={<div />} />
            <Route path="/ops/waiting-list" element={<div />} />
            <Route path="/kitchen/board" element={<div />} />
            <Route path="/ops/finance-review" element={<div />} />
            <Route path="/ops/cashier-shift" element={<div />} />
            <Route path="/ops/conversations" element={<div />} />
            <Route path="/admin/reporting" element={<div />} />
            <Route path="/ops/reservations" element={<div />} />
            <Route path="/access" element={<div />} />
          </Route>
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  </AntdApp>,
);

function installFetchStub(previewMode: string, previewSession: ReturnType<typeof buildStaffSession>) {
  const branchList = {
    data: [
      {
        branch_id: 1,
        branch_code: 'MAIN',
        branch_name: 'Chi nhanh Trung tam',
        timezone: 'Asia/Ho_Chi_Minh',
        currency: 'VND',
        is_default: true,
        is_active: true,
      },
      {
        branch_id: 2,
        branch_code: 'RIVERSIDE',
        branch_name: 'Chi nhanh Riverside',
        timezone: 'Asia/Ho_Chi_Minh',
        currency: 'VND',
        is_default: false,
        is_active: true,
      },
    ],
  };

  const tableBoard = {
    data: [
      buildTableRow(1, 'A1', 'Occupied', 'Main hall', { activeOrderId: 810, preferredAction: 'open_order', seats: 4 }),
      buildTableRow(2, 'A2', 'Available', 'Main hall', { preferredAction: 'seat_waiting', acceptsNewAssignment: true, seats: 2 }),
      buildTableRow(3, 'B1', 'Occupied', 'Terrace', { activeOrderId: 811, reservationCode: 'RSV-202', seats: 4 }),
      buildTableRow(4, 'B2', 'Reserved', 'Terrace', { reservationCode: 'RSV-203', seats: 6 }),
      buildTableRow(5, 'C1', 'Occupied', 'VIP', { activeOrderId: 812, seats: 6 }),
      buildTableRow(6, 'C2', 'Available', 'VIP', { acceptsNewAssignment: true, seats: 4 }),
      buildTableRow(7, 'D1', 'Occupied', 'Window', { activeOrderId: 813, seats: 2 }),
      buildTableRow(8, 'D2', 'Available', 'Window', { acceptsNewAssignment: true, seats: 2 }),
    ],
    summary: {
      active_order_count: 5,
      unassigned_reservation_count: 2,
    },
    unassigned_reservations: [{ reservation_id: 1 }, { reservation_id: 2 }],
    meta: {},
  };

  const reservations = {
    data: [
      {
        reservation_id: 101,
        reservation_code: 'RSV-101',
        user: { full_name: 'Nguyen An' },
        start_time: '2026-04-10T11:30:00Z',
        guest_count: 4,
        status: 'Confirmed',
      },
      {
        reservation_id: 102,
        reservation_code: 'RSV-102',
        user: { full_name: 'Le Minh' },
        start_time: '2026-04-10T11:45:00Z',
        guest_count: 2,
        status: 'Confirmed',
      },
    ],
    meta: {},
  };

  const waitingList = {
    data: [
      {
        waiting_id: 55,
        guest_name: 'Tran Binh',
        guest_count: 2,
        status: 'Waiting',
        current_response_state: 'pending',
        orchestration: { recommended_action: 'notify' },
      },
      {
        waiting_id: 56,
        guest_name: 'Pham Linh',
        guest_count: 4,
        status: 'Waiting',
        current_response_state: 'pending',
        orchestration: { recommended_action: 'seat' },
      },
    ],
    meta: {
      summary: {
        ready_to_seat_count: 3,
        awaiting_customer_follow_up_count: 2,
      },
    },
  };

  const kitchenStations = {
    data: [
      {
        station_id: 1,
        name: 'Bep nong',
        code: 'HOT',
        output_mode: 'queue',
        ticket_counts: {
          queued: 6,
          fired: 4,
          ready: 2,
        },
      },
      {
        station_id: 2,
        name: 'Bep nguoi',
        code: 'COLD',
        output_mode: 'queue',
        ticket_counts: {
          queued: 2,
          fired: 1,
          ready: 3,
        },
      },
    ],
    meta: {},
  };

  const financeRows = {
    data: [
      buildFinanceRow(101, 'RSV-101', 'Nguyen An', 1200000, 350000, true, true, false),
      buildFinanceRow(102, 'RSV-102', 'Le Minh', 900000, 0, false, false, true),
      buildFinanceRow(103, 'RSV-103', 'Pham Linh', 700000, 150000, false, true, false),
    ],
    meta: {},
  };

  const currentShift = {
    data: {
      shift_code: 'SHIFT-01',
      status: 'Open',
      currency: 'VND',
      expected_cash_amount: 1200000,
      opened_at: '2026-04-10T08:00:00Z',
      summary: {
        payments: { payment_count: 18 },
        cash: { expected_cash_amount: 1200000 },
        methods: [
          { payment_method: 'Cash', net_amount: 2400000, currency: 'VND' },
          { payment_method: 'Card', net_amount: 3600000, currency: 'VND' },
          { payment_method: 'BankTransfer', net_amount: 1800000, currency: 'VND' },
        ],
      },
    },
  };

  const conversations = {
    data: [
      buildConversation('conv_1', 'Khach hoi doi ban 4 nguoi', true, false, '2026-04-10T11:20:00Z'),
      buildConversation('conv_2', 'Khach xin doi gio den', true, false, '2026-04-10T11:12:00Z'),
      buildConversation('conv_3', 'Khach hoi giu cho them 10 phut', false, true, '2026-04-10T11:02:00Z'),
    ],
    meta: {
      summary: {
        total: 5,
        assigned: 2,
        unassigned: 3,
        mine: 1,
      },
    },
  };

  const reportingMeta = {
    snapshot_health: {
      status: previewMode === 'warning' ? 'degraded' : 'healthy',
      is_empty: false,
      reasons: [],
      row_count: 1,
      latest_business_date: '2026-04-10',
      latest_refresh_age_seconds: previewMode === 'warning' ? 540 : 75,
    },
  };

  const salesReporting = {
    data: [
      {
        currency: 'VND',
        payments: { net_paid_amount: 8400000 },
        billed: { gross_bill_amount: 9200000, reservation_count: 14 },
        invoices: { issued_count: 11 },
      },
    ],
    meta: reportingMeta,
  };

  const operationsReporting = {
    data: [
      {
        reservations: { completed_count: 12 },
        waiting_list: { seated_count: 5 },
        turn_time: { turn_count: 4, turn_minutes_total: 230 },
      },
    ],
    meta: reportingMeta,
  };

  const inventoryReporting = {
    data: [
      {
        movement_summary: {
          movement_count: 17,
          net_quantity_delta: 6.5,
          wastage_quantity: 1.2,
        },
      },
    ],
    meta: reportingMeta,
  };

  window.fetch = async (input) => {
    const url = new URL(typeof input === 'string' ? input : input.url, window.location.origin);
    const path = url.pathname;

    if (path.endsWith('/auth/staff/refresh')) {
      return jsonResponse({ data: previewSession });
    }

    if (path.endsWith('/staff/branches')) {
      return jsonResponse(branchList);
    }

    if (path.endsWith('/staff/tables/board')) {
      return jsonResponse(tableBoard);
    }

    if (path.endsWith('/staff/reservations')) {
      return jsonResponse(reservations);
    }

    if (path.endsWith('/staff/waiting-list')) {
      return jsonResponse(waitingList);
    }

    if (path.endsWith('/staff/kitchen/stations')) {
      return jsonResponse(kitchenStations);
    }

    if (path.endsWith('/staff/finance/reconciliation')) {
      if (previewMode === 'warning') {
        return jsonResponse({
          message: 'SQLSTATE[HY000] preview finance error',
          error_code: 'server_error',
          request_id: 'preview-finance-001',
        }, 500);
      }

      return jsonResponse(financeRows);
    }

    if (path.endsWith('/staff/cashier/shifts/current')) {
      return jsonResponse(currentShift);
    }

    if (path.endsWith('/staff/conversations')) {
      if (previewMode === 'warning') {
        return jsonResponse({
          message: 'Timeout while talking to inbox backend',
          error_code: 'upstream_timeout',
          request_id: 'preview-conversation-001',
        }, 503);
      }

      return jsonResponse(conversations);
    }

    if (path.endsWith('/staff/reporting/daily-sales')) {
      return jsonResponse(salesReporting);
    }

    if (path.endsWith('/staff/reporting/daily-operations')) {
      return jsonResponse(operationsReporting);
    }

    if (path.endsWith('/staff/reporting/daily-inventory')) {
      return jsonResponse(inventoryReporting);
    }

    return jsonResponse({
      message: `Preview route not stubbed: ${path}`,
    }, 404);
  };
}

function jsonResponse(body: unknown, status = 200) {
  return Promise.resolve(new Response(JSON.stringify(body), {
    status,
    headers: {
      'Content-Type': 'application/json',
      'X-Request-Id': 'preview-request',
    },
  }));
}

function buildTableRow(
  tableId: number,
  tableCode: string,
  boardState: string,
  zone: string,
  options: {
    activeOrderId?: number;
    reservationCode?: string;
    preferredAction?: string;
    acceptsNewAssignment?: boolean;
    seats: number;
  },
) {
  return {
    table_id: tableId,
    table_code: tableCode,
    zone,
    capacity: { seats: options.seats },
    board_state: boardState,
    availability: { accepts_new_assignment: options.acceptsNewAssignment ?? false },
    operational_hints: { preferred_action: options.preferredAction ?? 'none' },
    active_order: options.activeOrderId ? { order_id: options.activeOrderId } : null,
    reservation: options.reservationCode ? { reservation_code: options.reservationCode } : null,
  };
}

function buildFinanceRow(
  reservationId: number,
  reservationCode: string,
  customerName: string,
  paidAmount: number,
  outstandingAmount: number,
  hasDiscrepancy: boolean,
  hasOutstanding: boolean,
  fullySettled: boolean,
) {
  return {
    reservation: {
      reservation_id: reservationId,
      reservation_code: reservationCode,
      customer: { full_name: customerName },
      bill_currency: 'VND',
      status: fullySettled ? 'Completed' : 'Open',
    },
    payment_summary: {
      net_paid_amount: paidAmount,
      over_refunded_amount: 0,
      currency: { currency: 'VND' },
    },
    reconciliation: {
      bill_outstanding_amount: outstandingAmount,
    },
    flags: {
      has_discrepancy: hasDiscrepancy,
      has_over_refund: false,
      has_bill_outstanding: hasOutstanding,
      has_bill_overpaid: false,
      has_mixed_payment_currencies: false,
      is_fully_settled: fullySettled,
    },
  };
}

function buildConversation(
  id: string,
  messageText: string,
  isUnassigned: boolean,
  isMine: boolean,
  latestActivityAt: string,
) {
  return {
    conversation_id: id,
    latest_message: { message_text: messageText },
    latest_activity_at: latestActivityAt,
    created_at: '2026-04-10T10:00:00Z',
    branch: { branch_code: 'MAIN' },
    branch_id: 1,
    status: 'Open',
    assignment_state: {
      is_unassigned: isUnassigned,
      is_mine: isMine,
    },
    user: { full_name: 'Khach preview' },
  };
}
