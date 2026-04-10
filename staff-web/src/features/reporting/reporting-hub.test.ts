import { describe, expect, it } from 'vitest';
import type {
  ReportingDailyInventoryMovementSnapshot,
  ReportingDailyOperationSnapshot,
  ReportingDailySalesSnapshot,
  StaffReportingCollectionMeta,
} from '../../core/api/sdk';
import {
  buildInventoryQuery,
  buildOperationsQuery,
  buildSalesQuery,
  snapshotHealthDescription,
  snapshotHealthLabel,
  summarizeInventory,
  summarizeOperations,
  summarizeSales,
} from './reporting-hub';

const filters = {
  dateFrom: '2026-04-01',
  dateTo: '2026-04-10',
  currency: 'VND',
  ingredientId: '88',
};

describe('reporting hub helpers', () => {
  it('builds sales query from shell branch context and filters', () => {
    expect(buildSalesQuery(filters, 3, 2, 12)).toEqual({
      branch_id: 3,
      currency: 'VND',
      start_date: '2026-04-01',
      end_date: '2026-04-10',
      per_page: 12,
      page: 2,
      sort: '-business_date',
    });
  });

  it('builds inventory query with optional ingredient filter', () => {
    expect(buildInventoryQuery(filters, null, 1, 12)).toEqual({
      ingredient_id: 88,
      start_date: '2026-04-01',
      end_date: '2026-04-10',
      per_page: 12,
      page: 1,
      sort: '-business_date',
    });
  });

  it('builds operations query without sales-only or inventory-only filters', () => {
    expect(buildOperationsQuery(filters, 5, 3, 12)).toEqual({
      branch_id: 5,
      start_date: '2026-04-01',
      end_date: '2026-04-10',
      per_page: 12,
      page: 3,
      sort: '-business_date',
    });
  });

  it('summarizes sales, operations and inventory rows', () => {
    const sales = summarizeSales([{
      snapshot_id: 1,
      business_date: '2026-04-10',
      currency: 'VND',
      branch_id: 3,
      billed: {
        reservation_count: 5,
        guest_count: 10,
        gross_bill_amount: 2000000,
        discount_amount: 100000,
        billed_total_amount: 1900000,
      },
      invoices: {
        issued_count: 4,
        issued_total_amount: 1900000,
        tax_amount: 80000,
      },
      payments: {
        payment_row_count: 4,
        refund_row_count: 1,
        captured_amount: 1950000,
        refunded_amount: 50000,
        net_paid_amount: 1900000,
        deposit_net_amount: 200000,
        final_net_amount: 1700000,
      },
      cashier: {
        closed_shift_count: 1,
        cash_discrepancy_amount: 0,
      },
      freshness: {
        refreshed_at: '2026-04-10T00:00:00Z',
      },
    } satisfies ReportingDailySalesSnapshot]);
    const operations = summarizeOperations([{
      snapshot_id: 2,
      business_date: '2026-04-10',
      branch_id: 3,
      reservations: {
        scheduled_count: 12,
        scheduled_guest_count: 30,
        scheduled_minutes_total: 720,
        checked_in_count: 10,
        completed_count: 9,
        cancelled_count: 1,
        no_show_count: 1,
      },
      turn_time: {
        turn_count: 3,
        turn_minutes_total: 270,
        avg_turn_minutes: 90,
      },
      waiting_list: {
        created_count: 8,
        notified_count: 6,
        seated_count: 5,
        cancelled_count: 1,
        confirmed_arrival_count: 4,
        seated_conversion_rate: 0.625,
        arrival_confirmation_rate: 0.5,
      },
      freshness: {
        refreshed_at: '2026-04-10T00:00:00Z',
      },
    } satisfies ReportingDailyOperationSnapshot]);
    const inventory = summarizeInventory([{
      snapshot_id: 3,
      business_date: '2026-04-10',
      branch_id: 3,
      ingredient_id: 88,
      unit_code: 'kg',
      movement_summary: {
        movement_count: 6,
        purchase_receipt_movement_count: 2,
        stock_in_quantity: 8,
        stock_out_quantity: 4,
        adjustment_increase_quantity: 1,
        adjustment_decrease_quantity: 0.5,
        wastage_quantity: 0.25,
        net_quantity_delta: 4.25,
        last_movement_at: '2026-04-10T02:00:00Z',
      },
      freshness: {
        refreshed_at: '2026-04-10T00:00:00Z',
      },
    } satisfies ReportingDailyInventoryMovementSnapshot]);

    expect(sales).toEqual({ netPaidAmount: 1900000, grossBillAmount: 2000000, invoiceCount: 4 });
    expect(operations).toEqual({ completedCount: 9, waitingSeatedCount: 5, avgTurnMinutes: 90 });
    expect(inventory).toEqual({ movementCount: 6, netQuantityDelta: 4.25, wastageQuantity: 0.25 });
  });

  it('describes degraded snapshot health explicitly', () => {
    const meta = {
      filters: {},
      sort: { supported: true, value: '-business_date', by: 'business_date', dir: 'desc' },
      pagination: {
        mode: 'paged',
        current_page: 1,
        per_page: 12,
        from: 1,
        to: 1,
        total: 1,
        last_page: 1,
        has_more_pages: false,
      },
      current_page: 1,
      per_page: 12,
      from: 1,
      to: 1,
      total: 1,
      last_page: 1,
      has_more_pages: false,
      query_contract: {
        parameters: {
          filter: 'filter',
          sort: 'sort',
          page: 'page',
          per_page: 'per_page',
        },
        filter_keys: [],
        sort_fields: [],
        default_sort: '-business_date',
        pagination: {
          supported: true,
          max_per_page: 100,
        },
        legacy_aliases: {},
      },
      action: 'staff_reporting_daily_sales_index',
      snapshot_health: {
        family: 'sales',
        row_count: 0,
        date_range: { start_date: '2026-04-01', end_date: '2026-04-10' },
        latest_business_date: null,
        latest_refreshed_at_utc: null,
        latest_refresh_age_seconds: null,
        stale_threshold_seconds: 86400,
        is_empty: true,
        is_stale: false,
        status: 'degraded',
        reasons: ['empty_scope'],
      },
    } satisfies StaffReportingCollectionMeta;

    expect(snapshotHealthLabel(meta)).toBe('Phạm vi trống');
    expect(snapshotHealthDescription(meta)).toContain('Nguyên nhân Phạm vi trống');
  });
});
