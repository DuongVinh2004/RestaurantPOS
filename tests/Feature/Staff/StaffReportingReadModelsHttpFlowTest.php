<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\Reporting\Application\Workflows\ReportingSnapshotWorkflow;
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
        $staffId = $this->createUser(['role_name' => 'Manager']);
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
            'discount_amount' => '10000',
            'final_bill_amount' => '90000',
            'bill_currency' => 'VND',
            'billed_at' => $businessDay->copy()->setTime(13, 25),
            'deposit_required_amount' => '30000',
            'deposit_paid_amount' => '30000',
            'deposit_status' => 'Paid',
        ]);

        $depositPaymentId = $this->createPayment([
            'branch_id' => $branchId,
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '30000',
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
            'amount' => '60000',
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
            'amount' => '5000',
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
            'subtotal_amount' => '100000',
            'discount_amount' => '10000',
            'total_amount' => '90000',
            'currency' => 'VND',
            'tax_code' => 'VAT10',
            'tax_name' => 'VAT 10%',
            'tax_rate_percentage' => '10.000',
            'prices_include_tax' => 1,
            'taxable_amount' => '81818',
            'tax_amount' => '8182',
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

        app(ReportingSnapshotWorkflow::class)->rebuild([
            'branch_id' => $branchId,
            'start_date' => $businessDay->toDateString(),
            'end_date' => $businessDay->toDateString(),
        ], $staffId);

        $headers = $this->staffAuthHeaders($staffId, 'staff-reporting-view-key');

        $sales = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reporting/daily-sales?start_date='.$businessDay->toDateString().'&end_date='.$businessDay->toDateString());

        $sales->assertOk()
            ->assertJsonPath('meta.action', 'staff_reporting_daily_sales_index')
            ->assertJsonPath('meta.snapshot_health.family', 'sales')
            ->assertJsonPath('meta.snapshot_health.classification', 'launch_critical')
            ->assertJsonPath('meta.snapshot_health.certified_accounting', false)
            ->assertJsonPath('meta.snapshot_health.status', 'ok')
            ->assertJsonPath('meta.snapshot_health.is_empty', false)
            ->assertJsonPath('meta.snapshot_health.scope_count', 1)
            ->assertJsonPath('meta.snapshot_health.stale_scope_count', 0)
            ->assertJsonPath('data.0.business_date', $businessDay->toDateString())
            ->assertJsonPath('data.0.branch.branch_code', 'MAIN')
            ->assertJsonPath('data.0.billed.reservation_count', 1)
            ->assertJsonPath('data.0.billed.gross_bill_amount', 100000.0)
            ->assertJsonPath('data.0.payments.net_paid_amount', 85000.0)
            ->assertJsonPath('data.0.invoices.tax_amount', 8182.0);

        $operations = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reporting/daily-operations?start_date='.$businessDay->toDateString().'&end_date='.$businessDay->toDateString());

        $operations->assertOk()
            ->assertJsonPath('meta.action', 'staff_reporting_daily_operations_index')
            ->assertJsonPath('meta.snapshot_health.family', 'operations')
            ->assertJsonPath('meta.snapshot_health.classification', 'launch_critical')
            ->assertJsonPath('meta.snapshot_health.status', 'ok')
            ->assertJsonPath('meta.snapshot_health.scope_count', 1)
            ->assertJsonPath('data.0.reservations.scheduled_count', 1)
            ->assertJsonPath('data.0.reservations.completed_count', 1)
            ->assertJsonPath('data.0.turn_time.avg_turn_minutes', 70.0)
            ->assertJsonPath('data.0.waiting_list.created_count', 1)
            ->assertJsonPath('data.0.waiting_list.seated_count', 1)
            ->assertJsonPath('data.0.waiting_list.arrival_confirmation_rate', 100.0);

        $inventory = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reporting/daily-inventory?start_date='.$businessDay->toDateString().'&end_date='.$businessDay->toDateString().'&ingredient_id='.$ingredientId);

        $inventory->assertOk()
            ->assertJsonPath('meta.action', 'staff_reporting_daily_inventory_index')
            ->assertJsonPath('meta.snapshot_health.family', 'inventory')
            ->assertJsonPath('meta.snapshot_health.classification', 'experimental')
            ->assertJsonPath('meta.snapshot_health.certified_accounting', false)
            ->assertJsonPath('meta.snapshot_health.certification_warnings.0', 'experimental_reporting_not_certified_accounting')
            ->assertJsonPath('meta.snapshot_health.status', 'ok')
            ->assertJsonPath('meta.snapshot_health.scope_count', 1)
            ->assertJsonPath('data.0.ingredient.code', 'BEANS')
            ->assertJsonPath('data.0.movement_summary.movement_count', 2)
            ->assertJsonPath('data.0.movement_summary.purchase_receipt_movement_count', 1)
            ->assertJsonPath('data.0.movement_summary.stock_in_quantity', 12.0)
            ->assertJsonPath('data.0.movement_summary.wastage_quantity', 2.0)
            ->assertJsonPath('data.0.movement_summary.net_quantity_delta', 10.0);
    }

    public function test_staff_reporting_meta_marks_empty_snapshot_scope_as_degraded(): void
    {
        $staffId = $this->createUser(['role_name' => 'Manager']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-reporting-empty-scope');

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reporting/daily-sales?start_date=2026-03-01&end_date=2026-03-01');

        $response->assertOk()
            ->assertJsonPath('meta.snapshot_health.family', 'sales')
            ->assertJsonPath('meta.snapshot_health.status', 'degraded')
            ->assertJsonPath('meta.snapshot_health.is_empty', true)
            ->assertJsonPath('meta.snapshot_health.reasons.0', 'reporting_snapshot_empty');
    }

    public function test_staff_reporting_meta_marks_partially_stale_inventory_scope_as_degraded(): void
    {
        $staffId = $this->createUser(['role_name' => 'Manager']);
        $ingredientFreshId = $this->createIngredient(['code' => 'FRESH-RPT', 'name' => 'Fresh Scope', 'unit_code' => 'kg']);
        $ingredientStaleId = $this->createIngredient(['code' => 'STALE-RPT', 'name' => 'Stale Scope', 'unit_code' => 'kg']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-reporting-stale-scope');

        config()->set('booking.ops.reporting_snapshot_stale_hours', 24);

        $freshRefreshedAt = Carbon::parse('2026-04-03 12:00:00', 'UTC');
        $staleRefreshedAt = Carbon::parse('2026-04-01 08:00:00', 'UTC');

        Carbon::setTestNow(Carbon::parse('2026-04-04 08:00:00', 'UTC'));

        try {
            DB::table('reporting_daily_inventory_movement_snapshots')->insert([
                [
                    'branch_id' => 1,
                    'business_date' => '2026-04-03',
                    'ingredient_id' => $ingredientFreshId,
                    'unit_code' => 'kg',
                    'movement_count' => 2,
                    'purchase_receipt_movement_count' => 1,
                    'stock_in_quantity' => '5.000',
                    'stock_out_quantity' => '1.000',
                    'adjustment_increase_quantity' => '0.000',
                    'adjustment_decrease_quantity' => '0.000',
                    'wastage_quantity' => '0.250',
                    'net_quantity_delta' => '4.000',
                    'last_movement_at' => $freshRefreshedAt->copy()->subHour(),
                    'refreshed_at' => $freshRefreshedAt,
                    'created_at' => $freshRefreshedAt,
                    'updated_at' => $freshRefreshedAt,
                ],
                [
                    'branch_id' => 1,
                    'business_date' => '2026-04-03',
                    'ingredient_id' => $ingredientStaleId,
                    'unit_code' => 'kg',
                    'movement_count' => 1,
                    'purchase_receipt_movement_count' => 0,
                    'stock_in_quantity' => '0.000',
                    'stock_out_quantity' => '2.000',
                    'adjustment_increase_quantity' => '0.000',
                    'adjustment_decrease_quantity' => '0.000',
                    'wastage_quantity' => '0.500',
                    'net_quantity_delta' => '-2.000',
                    'last_movement_at' => $staleRefreshedAt->copy()->subHour(),
                    'refreshed_at' => $staleRefreshedAt,
                    'created_at' => $staleRefreshedAt,
                    'updated_at' => $staleRefreshedAt,
                ],
            ]);

            $response = $this->withHeaders($headers)
                ->getJson('/api/v1/staff/reporting/daily-inventory?start_date=2026-04-03&end_date=2026-04-03');

            $response->assertOk()
                ->assertJsonPath('meta.snapshot_health.family', 'inventory')
                ->assertJsonPath('meta.snapshot_health.status', 'degraded')
                ->assertJsonPath('meta.snapshot_health.is_stale', true)
                ->assertJsonPath('meta.snapshot_health.scope_count', 2)
                ->assertJsonPath('meta.snapshot_health.stale_scope_count', 1)
                ->assertJsonPath('meta.snapshot_health.stale_scope_examples.0.ingredient_id', $ingredientStaleId)
                ->assertJsonPath('meta.snapshot_health.reasons.0', 'reporting_snapshot_stale')
                ->assertJsonPath('meta.snapshot_health.reasons.1', 'reporting_snapshot_scope_partial');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_staff_daily_sales_reports_are_scoped_to_accessible_branches(): void
    {
        $scope = $this->makeReportingBranchScopeFixture('SALES');
        $now = $this->nowUtc();

        DB::table('reporting_daily_sales_snapshots')->insert([
            $this->salesSnapshotRow(1, $scope['business_date'], 110000, 99000, $now),
            $this->salesSnapshotRow($scope['allowed_branch_id'], $scope['business_date'], 220000, 210000, $now),
            $this->salesSnapshotRow($scope['denied_branch_id'], $scope['business_date'], 330000, 300000, $now),
        ]);

        $this->withHeaders($scope['headers'])
            ->getJson('/api/v1/staff/reporting/daily-sales?start_date='.$scope['business_date'].'&end_date='.$scope['business_date'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.snapshot_health.scope_count', 2)
            ->assertJsonMissing(['branch_id' => $scope['denied_branch_id']]);

        $this->withHeaders($scope['headers'])
            ->getJson('/api/v1/staff/reporting/daily-sales?branch_id='.$scope['allowed_branch_id'].'&start_date='.$scope['business_date'].'&end_date='.$scope['business_date'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.branch_id', $scope['allowed_branch_id'])
            ->assertJsonPath('meta.snapshot_health.scope_count', 1);

        $this->withHeaders($scope['headers'])
            ->getJson('/api/v1/staff/reporting/daily-sales?branch_id='.$scope['denied_branch_id'].'&start_date='.$scope['business_date'].'&end_date='.$scope['business_date'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_staff_daily_operations_reports_are_scoped_to_accessible_branches(): void
    {
        $scope = $this->makeReportingBranchScopeFixture('OPS');
        $now = $this->nowUtc();

        DB::table('reporting_daily_operation_snapshots')->insert([
            $this->operationSnapshotRow(1, $scope['business_date'], 3, 2, $now),
            $this->operationSnapshotRow($scope['allowed_branch_id'], $scope['business_date'], 5, 4, $now),
            $this->operationSnapshotRow($scope['denied_branch_id'], $scope['business_date'], 7, 6, $now),
        ]);

        $this->withHeaders($scope['headers'])
            ->getJson('/api/v1/staff/reporting/daily-operations?start_date='.$scope['business_date'].'&end_date='.$scope['business_date'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.snapshot_health.scope_count', 2)
            ->assertJsonMissing(['branch_id' => $scope['denied_branch_id']]);

        $this->withHeaders($scope['headers'])
            ->getJson('/api/v1/staff/reporting/daily-operations?branch_id='.$scope['allowed_branch_id'].'&start_date='.$scope['business_date'].'&end_date='.$scope['business_date'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.branch_id', $scope['allowed_branch_id'])
            ->assertJsonPath('meta.snapshot_health.scope_count', 1);

        $this->withHeaders($scope['headers'])
            ->getJson('/api/v1/staff/reporting/daily-operations?branch_id='.$scope['denied_branch_id'].'&start_date='.$scope['business_date'].'&end_date='.$scope['business_date'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_staff_daily_inventory_reports_are_scoped_to_accessible_branches(): void
    {
        $scope = $this->makeReportingBranchScopeFixture('INV');
        $ingredientId = $this->createIngredient(['code' => 'RPT-SCOPE-INV', 'name' => 'Scoped Inventory', 'unit_code' => 'kg']);
        $now = $this->nowUtc();

        DB::table('reporting_daily_inventory_movement_snapshots')->insert([
            $this->inventorySnapshotRow(1, $scope['business_date'], $ingredientId, 2, 5, $now),
            $this->inventorySnapshotRow($scope['allowed_branch_id'], $scope['business_date'], $ingredientId, 4, 9, $now),
            $this->inventorySnapshotRow($scope['denied_branch_id'], $scope['business_date'], $ingredientId, 6, 13, $now),
        ]);

        $this->withHeaders($scope['headers'])
            ->getJson('/api/v1/staff/reporting/daily-inventory?start_date='.$scope['business_date'].'&end_date='.$scope['business_date'].'&ingredient_id='.$ingredientId)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.snapshot_health.scope_count', 2)
            ->assertJsonMissing(['branch_id' => $scope['denied_branch_id']]);

        $this->withHeaders($scope['headers'])
            ->getJson('/api/v1/staff/reporting/daily-inventory?branch_id='.$scope['allowed_branch_id'].'&start_date='.$scope['business_date'].'&end_date='.$scope['business_date'].'&ingredient_id='.$ingredientId)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.branch_id', $scope['allowed_branch_id'])
            ->assertJsonPath('meta.snapshot_health.scope_count', 1);

        $this->withHeaders($scope['headers'])
            ->getJson('/api/v1/staff/reporting/daily-inventory?branch_id='.$scope['denied_branch_id'].'&start_date='.$scope['business_date'].'&end_date='.$scope['business_date'].'&ingredient_id='.$ingredientId)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_non_staff_requests_are_rejected_for_reporting_read_models(): void
    {
        $response = $this->getJson('/api/v1/staff/reporting/daily-sales');
        $response->assertUnauthorized();
    }

    /**
     * @return array{headers:array<string,string>,allowed_branch_id:int,denied_branch_id:int,business_date:string}
     */
    private function makeReportingBranchScopeFixture(string $suffix): array
    {
        $reportingRoleId = $this->ensureRole('Branch Reporter '.$suffix);
        $staffId = $this->createUser([
            'role_id' => $reportingRoleId,
            'role_name' => 'Branch Reporter '.$suffix,
        ]);
        $allowedBranchId = $this->createBranch([
            'branch_code' => 'RPT-'.$suffix.'-ALLOW',
            'branch_name' => 'Reporting '.$suffix.' Allow',
        ]);
        $deniedBranchId = $this->createBranch([
            'branch_code' => 'RPT-'.$suffix.'-DENY',
            'branch_name' => 'Reporting '.$suffix.' Deny',
        ]);

        $roleIdCapabilities = (array) config('staff_capabilities.role_id_capabilities', []);
        $roleIdCapabilities[$reportingRoleId] = ['reporting.view'];
        config()->set('staff_capabilities.role_id_capabilities', $roleIdCapabilities);

        $roleIdBranchScopes = (array) config('staff_capabilities.role_id_branch_scopes', []);
        $roleIdBranchScopes[$reportingRoleId] = ['default', (string) $allowedBranchId];
        config()->set('staff_capabilities.role_id_branch_scopes', $roleIdBranchScopes);

        return [
            'headers' => $this->staffAuthHeaders($staffId, 'staff-reporting-'.$suffix.'-scope'),
            'allowed_branch_id' => $allowedBranchId,
            'denied_branch_id' => $deniedBranchId,
            'business_date' => '2026-04-07',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function salesSnapshotRow(int $branchId, string $businessDate, int $grossAmount, int $netAmount, Carbon $now): array
    {
        return [
            'branch_id' => $branchId,
            'business_date' => $businessDate,
            'currency' => 'VND',
            'billed_reservation_count' => 1,
            'billed_guest_count' => 2,
            'gross_bill_amount' => $grossAmount,
            'billed_total_amount' => $grossAmount,
            'payment_row_count' => 1,
            'captured_amount' => $netAmount,
            'net_paid_amount' => $netAmount,
            'final_net_amount' => $netAmount,
            'refreshed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function operationSnapshotRow(int $branchId, string $businessDate, int $scheduledCount, int $completedCount, Carbon $now): array
    {
        return [
            'branch_id' => $branchId,
            'business_date' => $businessDate,
            'scheduled_reservation_count' => $scheduledCount,
            'scheduled_guest_count' => $scheduledCount * 2,
            'completed_count' => $completedCount,
            'turn_count' => $completedCount,
            'turn_minutes_total' => $completedCount * 45,
            'waiting_list_created_count' => $scheduledCount,
            'waiting_list_seated_count' => $completedCount,
            'refreshed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function inventorySnapshotRow(int $branchId, string $businessDate, int $ingredientId, int $movementCount, int $netQuantity, Carbon $now): array
    {
        return [
            'branch_id' => $branchId,
            'business_date' => $businessDate,
            'ingredient_id' => $ingredientId,
            'unit_code' => 'kg',
            'movement_count' => $movementCount,
            'purchase_receipt_movement_count' => 1,
            'stock_in_quantity' => $netQuantity,
            'net_quantity_delta' => $netQuantity,
            'last_movement_at' => $now,
            'refreshed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
