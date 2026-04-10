import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ReportingPage } from './ReportingPage';
import { renderWithSession } from '../../test/render';
import { buildStaffSession } from '../../test/fixtures';
import { RestaurantPosApiError } from '../../core/api/sdk';
import type { StaffSessionContextValue } from '../../app/session-context';

const apiMocks = vi.hoisted(() => ({
  listDailySalesReporting: vi.fn(),
  listDailyOperationsReporting: vi.fn(),
  listDailyInventoryReporting: vi.fn(),
}));

vi.mock('../../core/api/staff-api', () => apiMocks);

describe('ReportingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    arrangeReportingFixtures();
  });

  it('loads sales, operations, and inventory snapshots with the startup branch scope', async () => {
    renderWithSession(<ReportingPage />, createSessionContext());

    const { startDate, endDate } = expectedReportWindow();

    await waitFor(() =>
      expect(apiMocks.listDailySalesReporting).toHaveBeenCalledWith({
        branch_id: 1,
        start_date: startDate,
        end_date: endDate,
        per_page: 7,
        sort: '-business_date',
      }),
    );
    expect(apiMocks.listDailyOperationsReporting).toHaveBeenCalledWith({
      branch_id: 1,
      start_date: startDate,
      end_date: endDate,
      per_page: 7,
      sort: '-business_date',
    });
    expect(apiMocks.listDailyInventoryReporting).toHaveBeenCalledWith({
      branch_id: 1,
      ingredient_id: undefined,
      start_date: startDate,
      end_date: endDate,
      per_page: 7,
      sort: '-business_date',
    });

    expect(screen.getAllByText(new RegExp(`Rows 1 \\| latest business date ${endDate}`, 'i')).length).toBe(3);
    expect(screen.getAllByText(`MAIN | ${endDate}`).length).toBeGreaterThan(0);
    expect(screen.getByText(/Arabica Beans \| MAIN/i)).toBeInTheDocument();
  });

  it('reloads all reporting slices with the edited filters and ingredient scope', async () => {
    renderWithSession(<ReportingPage />, createSessionContext());

    await waitFor(() => expect(apiMocks.listDailySalesReporting).toHaveBeenCalledTimes(1));

    fireEvent.change(screen.getByLabelText('Branch ID'), { target: { value: '2' } });
    fireEvent.change(screen.getByLabelText('Start date'), { target: { value: '2026-04-01' } });
    fireEvent.change(screen.getByLabelText('End date'), { target: { value: '2026-04-03' } });
    fireEvent.change(screen.getByLabelText('Ingredient ID'), { target: { value: '501' } });
    fireEvent.click(screen.getByRole('button', { name: 'Apply filters' }));

    await waitFor(() =>
      expect(apiMocks.listDailyInventoryReporting).toHaveBeenLastCalledWith({
        branch_id: 2,
        ingredient_id: 501,
        start_date: '2026-04-01',
        end_date: '2026-04-03',
        per_page: 7,
        sort: '-business_date',
      }),
    );
    expect(apiMocks.listDailySalesReporting).toHaveBeenLastCalledWith({
      branch_id: 2,
      start_date: '2026-04-01',
      end_date: '2026-04-03',
      per_page: 7,
      sort: '-business_date',
    });
    expect(screen.getByText(/Da ap dung reporting filters moi/i)).toBeInTheDocument();
  });

  it('expires the staff session when reporting bootstrap returns 401', async () => {
    arrangeReportingFixtures();
    const session = createSessionContext();
    apiMocks.listDailySalesReporting.mockRejectedValueOnce(buildCoreApiError(401, {
      error_code: 'unauthorized',
      message: 'Unauthorized.',
    }));

    renderWithSession(<ReportingPage />, session);

    await waitFor(() =>
      expect(session.expire).toHaveBeenCalledWith('Phien staff da het han. Dang nhap lai de tiep tuc.'),
    );
  });
});

function arrangeReportingFixtures() {
  const { endDate } = expectedReportWindow();

  apiMocks.listDailySalesReporting.mockResolvedValue({
    data: [
      {
        snapshot_id: 1,
        business_date: endDate,
        currency: 'VND',
        branch_id: 1,
        branch: {
          branch_id: 1,
          branch_code: 'MAIN',
          branch_name: 'Chi nhanh chinh',
          is_default: true,
        },
        billed: {
          reservation_count: 4,
          guest_count: 11,
          gross_bill_amount: 240000,
          discount_amount: 15000,
          billed_total_amount: 225000,
        },
        invoices: {
          issued_count: 4,
          issued_total_amount: 225000,
          tax_amount: 12000,
        },
        payments: {
          payment_row_count: 4,
          refund_row_count: 0,
          captured_amount: 225000,
          refunded_amount: 0,
          net_paid_amount: 225000,
          deposit_net_amount: 0,
          final_net_amount: 225000,
        },
        cashier: {
          closed_shift_count: 1,
          cash_discrepancy_amount: 0,
        },
        freshness: {
          refreshed_at: '2026-04-08T10:10:00Z',
        },
      },
    ],
    meta: createSnapshotMeta('sales'),
  });
  apiMocks.listDailyOperationsReporting.mockResolvedValue({
    data: [
      {
        snapshot_id: 2,
        business_date: endDate,
        branch_id: 1,
        branch: {
          branch_id: 1,
          branch_code: 'MAIN',
          branch_name: 'Chi nhanh chinh',
          is_default: true,
        },
        reservations: {
          scheduled_count: 6,
          scheduled_guest_count: 18,
          scheduled_minutes_total: 660,
          checked_in_count: 5,
          completed_count: 4,
          cancelled_count: 1,
          no_show_count: 0,
        },
        turn_time: {
          turn_count: 4,
          turn_minutes_total: 280,
          avg_turn_minutes: 70,
        },
        waiting_list: {
          created_count: 3,
          notified_count: 2,
          seated_count: 2,
          cancelled_count: 0,
          confirmed_arrival_count: 2,
          seated_conversion_rate: 0.66,
          arrival_confirmation_rate: 1,
        },
        freshness: {
          refreshed_at: '2026-04-08T10:10:00Z',
        },
      },
    ],
    meta: createSnapshotMeta('operations'),
  });
  apiMocks.listDailyInventoryReporting.mockResolvedValue({
    data: [
      {
        snapshot_id: 3,
        business_date: endDate,
        branch_id: 1,
        branch: {
          branch_id: 1,
          branch_code: 'MAIN',
          branch_name: 'Chi nhanh chinh',
          is_default: true,
        },
        ingredient_id: 501,
        ingredient: {
          ingredient_id: 501,
          code: 'BEANS',
          name: 'Arabica Beans',
          unit_code: 'kg',
          is_active: true,
        },
        unit_code: 'kg',
        movement_summary: {
          movement_count: 2,
          purchase_receipt_movement_count: 1,
          stock_in_quantity: 5,
          stock_out_quantity: 2,
          adjustment_increase_quantity: 0,
          adjustment_decrease_quantity: 0,
          wastage_quantity: 0.2,
          net_quantity_delta: 3,
          last_movement_at: '2026-04-08T09:30:00Z',
        },
        freshness: {
          refreshed_at: '2026-04-08T10:10:00Z',
        },
      },
    ],
    meta: createSnapshotMeta('inventory'),
  });
}

function createSnapshotMeta(family: string) {
  const { startDate, endDate } = expectedReportWindow();

  return {
    action: `staff_reporting_${family}_index`,
    filters: {},
    sort: {
      supported: true,
      value: '-business_date',
      by: 'business_date',
      dir: 'desc',
    },
    pagination: {
      mode: 'paged',
      current_page: 1,
      per_page: 7,
      from: 1,
      to: 1,
      total: 1,
      last_page: 1,
      has_more_pages: false,
    },
    current_page: 1,
    per_page: 7,
    from: 1,
    to: 1,
    total: 1,
    last_page: 1,
    has_more_pages: false,
    query_contract: {},
    snapshot_health: {
      family,
      row_count: 1,
      date_range: {
        start_date: startDate,
        end_date: endDate,
      },
      latest_business_date: endDate,
      latest_refreshed_at_utc: '2026-04-08T10:10:00Z',
      latest_refresh_age_seconds: 10,
      stale_threshold_seconds: 300,
      is_empty: false,
      is_stale: false,
      status: 'ok',
      reasons: [],
    },
  };
}

function expectedReportWindow() {
  const end = new Date();
  end.setHours(0, 0, 0, 0);
  const start = new Date(end);
  start.setDate(start.getDate() - 6);

  return {
    startDate: start.toISOString().slice(0, 10),
    endDate: end.toISOString().slice(0, 10),
  };
}

function buildCoreApiError<T>(status: number, payload: T, message = 'API request failed') {
  return new RestaurantPosApiError(message, status, payload);
}

function createSessionContext(overrides: Partial<StaffSessionContextValue['session']> = {}): StaffSessionContextValue {
  return {
    session: buildStaffSession({
      capabilities: ['settlement.manage'],
      known_capabilities: ['settlement.manage'],
      ...overrides,
    }),
    booting: false,
    notice: null,
    noticeTone: 'success',
    setAuthenticatedSession: vi.fn(),
    setNotice: vi.fn(),
    clearNotice: vi.fn(),
    refresh: vi.fn(),
    logout: vi.fn(),
    expire: vi.fn(),
  };
}
