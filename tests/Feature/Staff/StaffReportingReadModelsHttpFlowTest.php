<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Services\Reporting\ReportingSnapshotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffReportingReadModelsHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', true);
        config()->set('staff_auth.env_fallback_allowed_environments', ['testing']);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_staff_can_read_daily_sales_operations_and_inventory_snapshots(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $ingredientId = $this->createIngredient(['code' => 'BEANS', 'name' => 'Coffee Beans', 'unit_code' => 'kg']);
        $branchId = 1;
        $businessDay = Carbon::parse('2026-03-29 00:00:00', 'UTC');

        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Completed',
            'guest_count' => 3,
            'start_time' => $businessDay->copy()->setTime(12, 0),
            'end_time' => $businessDay->copy()->setTime(13, 30),
            'checked_in_at' => $businessDay->copy()->setTime(12, 10),
            'checked_out_at' => $businessDay->copy()->setTime(13, 20),
            'discount_amount' => '10000.00',
            'final_bill_amount' => '90000.00',
            'bill_currency' => 'VND',
            'billed_at' => $businessDay->copy()->setTime(13, 25),
            'deposit_required_amount' => '30000.00',
            'deposit_paid_amount' => '30000.00',
            'deposit_status' => 'Paid',
        ]);

        $depositPaymentId = $this->createPayment([
            'branch_id' => $branchId,
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '30000.00',
            'currency' => 'VND',
            'created_by' => $staffId,
            'paid_at' => $businessDay->copy()->setTime(11, 45),
            'created_at' => $businessDay->copy()->setTime(11, 45),
            'updated_at' => $businessDay->copy()->setTime(11, 45),
        ]);

        $this->createPayment([
            'branch_id' => $branchId,
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '60000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'paid_at' => $businessDay->copy()->setTime(13, 30),
            'created_at' => $businessDay->copy()->setTime(13, 30),
            'updated_at' => $businessDay->copy()->setTime(13, 30),
        ]);

        $this->createPayment([
            'branch_id' => $branchId,
            'reservation_id' => $reservationId,
            'payment_type' => 'Refund',
            'status' => 'Refunded',
            'amount' => '5000.00',
            'currency' => 'VND',
            'refund_of_payment_id' => $depositPaymentId,
            'created_by' => $staffId,
            'paid_at' => $businessDay->copy()->setTime(14, 0),
            'created_at' => $businessDay->copy()->setTime(14, 0),
            'updated_at' => $businessDay->copy()->setTime(14, 0),
            'provider_response_json' => [
                'refund_target_payment_type' => 'Deposit',
            ],
        ]);

        DB::table('billing_invoices')->insert([
            'reservation_id' => $reservationId,
            'invoice_number' => 'INV-RPT-0002',
            'invoice_status' => 'Issued',
            'subtotal_amount' => '100000.00',
            'discount_amount' => '10000.00',
            'total_amount' => '90000.00',
            'currency' => 'VND',
            'tax_code' => 'VAT10',
            'tax_name' => 'VAT 10%',
            'tax_rate_percentage' => '10.000',
            'prices_include_tax' => 1,
            'taxable_amount' => '81818.18',
            'tax_amount' => '8181.82',
            'seller_name' => 'Restaurant POS',
            'seller_tax_id' => '0301234567',
            'seller_address' => '123 Nguyen Hue',
            'issued_at' => $businessDay->copy()->setTime(13, 35),
            'issued_by' => $staffId,
            'voided_at' => null,
            'voided_by' => null,
            'metadata_json' => null,
            'row_version' => 1,
            'created_at' => $businessDay->copy()->setTime(13, 35),
            'updated_at' => $businessDay->copy()->setTime(13, 35),
        ]);

        $this->createWaitingListEntry([
            'branch_id' => $branchId,
            'requested_at' => $businessDay->copy()->setTime(17, 0),
            'notified_at' => $businessDay->copy()->setTime(17, 5),
            'customer_confirmed_arrival_at' => $businessDay->copy()->setTime(17, 8),
            'seated_at' => $businessDay->copy()->setTime(17, 12),
            'status' => 'Seated',
        ]);

        $this->createIngredientStockMovement([
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => '12.000',
            'unit_code' => 'kg',
            'reference_type' => 'PurchaseReceipt',
            'reference_id' => 'PR-002',
            'created_at' => $businessDay->copy()->setTime(6, 0),
        ]);

        $this->createIngredientStockMovement([
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'movement_type' => 'Wastage',
            'quantity_delta' => '-2.000',
            'unit_code' => 'kg',
            'created_at' => $businessDay->copy()->setTime(21, 0),
        ]);

        app(ReportingSnapshotService::class)->rebuild([
            'branch_id' => $branchId,
            'start_date' => $businessDay->toDateString(),
            'end_date' => $businessDay->toDateString(),
        ], $staffId);

        $headers = $this->staffAuthHeaders($staffId, 'staff-reporting-view-key');

        $sales = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reporting/daily-sales?start_date=' . $businessDay->toDateString() . '&end_date=' . $businessDay->toDateString());

        $sales->assertOk()
            ->assertJsonPath('meta.action', 'staff_reporting_daily_sales_index')
            ->assertJsonPath('meta.snapshot_health.family', 'sales')
            ->assertJsonPath('meta.snapshot_health.status', 'ok')
            ->assertJsonPath('meta.snapshot_health.is_empty', false)
            ->assertJsonPath('data.0.business_date', $businessDay->toDateString())
            ->assertJsonPath('data.0.branch.branch_code', 'MAIN')
            ->assertJsonPath('data.0.billed.reservation_count', 1)
            ->assertJsonPath('data.0.billed.gross_bill_amount', 100000.0)
            ->assertJsonPath('data.0.payments.net_paid_amount', 85000.0)
            ->assertJsonPath('data.0.invoices.tax_amount', 8181.82);

        $operations = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reporting/daily-operations?start_date=' . $businessDay->toDateString() . '&end_date=' . $businessDay->toDateString());

        $operations->assertOk()
            ->assertJsonPath('meta.action', 'staff_reporting_daily_operations_index')
            ->assertJsonPath('meta.snapshot_health.family', 'operations')
            ->assertJsonPath('meta.snapshot_health.status', 'ok')
            ->assertJsonPath('data.0.reservations.scheduled_count', 1)
            ->assertJsonPath('data.0.reservations.completed_count', 1)
            ->assertJsonPath('data.0.turn_time.avg_turn_minutes', 70.0)
            ->assertJsonPath('data.0.waiting_list.created_count', 1)
            ->assertJsonPath('data.0.waiting_list.seated_count', 1)
            ->assertJsonPath('data.0.waiting_list.arrival_confirmation_rate', 100.0);

        $inventory = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reporting/daily-inventory?start_date=' . $businessDay->toDateString() . '&end_date=' . $businessDay->toDateString() . '&ingredient_id=' . $ingredientId);

        $inventory->assertOk()
            ->assertJsonPath('meta.action', 'staff_reporting_daily_inventory_index')
            ->assertJsonPath('meta.snapshot_health.family', 'inventory')
            ->assertJsonPath('meta.snapshot_health.status', 'ok')
            ->assertJsonPath('data.0.ingredient.code', 'BEANS')
            ->assertJsonPath('data.0.movement_summary.movement_count', 2)
            ->assertJsonPath('data.0.movement_summary.purchase_receipt_movement_count', 1)
            ->assertJsonPath('data.0.movement_summary.stock_in_quantity', 12.0)
            ->assertJsonPath('data.0.movement_summary.wastage_quantity', 2.0)
            ->assertJsonPath('data.0.movement_summary.net_quantity_delta', 10.0);
    }


    public function test_staff_reporting_meta_marks_empty_snapshot_scope_as_degraded(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-reporting-empty-scope');

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reporting/daily-sales?start_date=2026-03-01&end_date=2026-03-01');

        $response->assertOk()
            ->assertJsonPath('meta.snapshot_health.family', 'sales')
            ->assertJsonPath('meta.snapshot_health.status', 'degraded')
            ->assertJsonPath('meta.snapshot_health.is_empty', true)
            ->assertJsonPath('meta.snapshot_health.reasons.0', 'reporting_snapshot_empty');
    }

    public function test_non_staff_requests_are_rejected_for_reporting_read_models(): void
    {
        $response = $this->getJson('/api/v1/staff/reporting/daily-sales');
        $response->assertUnauthorized();
    }
}
