<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class AdminReportingReadModelsFoundationHttpFlowTest extends TestCase
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

    public function test_admin_can_rebuild_reporting_snapshots_from_foundation_domains(): void
    {
        [$adminId, $headers] = $this->adminHeaders('admin-reporting-manage-key');
        $branchId = $this->createBranch([
            'branch_code' => 'HCM',
            'branch_name' => 'Ho Chi Minh',
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
        ]);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $ingredientId = $this->createIngredient(['name' => 'Coffee Beans', 'unit_code' => 'kg']);

        $businessDay = Carbon::parse('2026-03-28 00:00:00', 'UTC');

        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Completed',
            'guest_count' => 4,
            'start_time' => $businessDay->copy()->setTime(10, 0),
            'end_time' => $businessDay->copy()->setTime(11, 30),
            'checked_in_at' => $businessDay->copy()->setTime(10, 5),
            'checked_out_at' => $businessDay->copy()->setTime(11, 20),
            'discount_amount' => '20000.00',
            'final_bill_amount' => '180000.00',
            'bill_currency' => 'VND',
            'billed_at' => $businessDay->copy()->setTime(11, 25),
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Paid',
        ]);

        $depositPaymentId = $this->createPayment([
            'branch_id' => $branchId,
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000.00',
            'currency' => 'VND',
            'payment_method' => 'Card',
            'payment_provider' => 'simulated',
            'created_by' => $staffId,
            'paid_at' => $businessDay->copy()->setTime(9, 45),
            'created_at' => $businessDay->copy()->setTime(9, 45),
            'updated_at' => $businessDay->copy()->setTime(9, 45),
        ]);

        $this->createPayment([
            'branch_id' => $branchId,
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '130000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'paid_at' => $businessDay->copy()->setTime(11, 30),
            'created_at' => $businessDay->copy()->setTime(11, 30),
            'updated_at' => $businessDay->copy()->setTime(11, 30),
        ]);

        $this->createPayment([
            'branch_id' => $branchId,
            'reservation_id' => $reservationId,
            'payment_type' => 'Refund',
            'status' => 'Refunded',
            'amount' => '5000.00',
            'currency' => 'VND',
            'payment_method' => 'Card',
            'payment_provider' => 'simulated',
            'refund_of_payment_id' => $depositPaymentId,
            'created_by' => $staffId,
            'paid_at' => $businessDay->copy()->setTime(12, 0),
            'created_at' => $businessDay->copy()->setTime(12, 0),
            'updated_at' => $businessDay->copy()->setTime(12, 0),
            'provider_response_json' => [
                'refund_target_payment_type' => 'Deposit',
            ],
        ]);

        DB::table('billing_invoices')->insert([
            'reservation_id' => $reservationId,
            'invoice_number' => 'INV-RPT-0001',
            'invoice_status' => 'Issued',
            'subtotal_amount' => '200000.00',
            'discount_amount' => '20000.00',
            'total_amount' => '180000.00',
            'currency' => 'VND',
            'tax_code' => 'VAT10',
            'tax_name' => 'VAT 10%',
            'tax_rate_percentage' => '10.000',
            'prices_include_tax' => 1,
            'taxable_amount' => '163636.36',
            'tax_amount' => '16363.64',
            'seller_name' => 'Restaurant POS',
            'seller_tax_id' => '0301234567',
            'seller_address' => '123 Nguyen Hue',
            'issued_at' => $businessDay->copy()->setTime(11, 35),
            'issued_by' => $adminId,
            'voided_at' => null,
            'voided_by' => null,
            'metadata_json' => null,
            'row_version' => 1,
            'created_at' => $businessDay->copy()->setTime(11, 35),
            'updated_at' => $businessDay->copy()->setTime(11, 35),
        ]);

        $this->createCashierShift([
            'branch_id' => $branchId,
            'shift_code' => 'CSH-RPT-001',
            'cashier_user_id' => $staffId,
            'status' => 'Closed',
            'currency' => 'VND',
            'terminal_code' => 'POS-01',
            'opening_float_amount' => '100000.00',
            'expected_cash_amount' => '230000.00',
            'actual_cash_amount' => '228000.00',
            'cash_discrepancy_amount' => '-2000.00',
            'opened_at' => $businessDay->copy()->setTime(8, 0),
            'closed_at' => $businessDay->copy()->setTime(22, 0),
            'opened_by' => $staffId,
            'closed_by' => $staffId,
            'opening_note' => 'Morning shift',
            'closing_note' => 'Closed',
            'row_version' => 2,
            'created_at' => $businessDay->copy()->setTime(8, 0),
            'updated_at' => $businessDay->copy()->setTime(22, 0),
        ]);

        $waitingId = $this->createWaitingListEntry([
            'branch_id' => $branchId,
            'requested_at' => $businessDay->copy()->setTime(18, 0),
            'notified_at' => $businessDay->copy()->setTime(18, 10),
            'seated_at' => $businessDay->copy()->setTime(18, 20),
            'customer_confirmed_arrival_at' => $businessDay->copy()->setTime(18, 15),
            'status' => 'Seated',
        ]);

        $this->createWaitingListEntry([
            'branch_id' => $branchId,
            'requested_at' => $businessDay->copy()->setTime(19, 0),
            'cancelled_at' => $businessDay->copy()->setTime(19, 10),
            'status' => 'Cancelled',
            'cancel_reason' => 'Left',
        ]);

        $this->createIngredientStockMovement([
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => '10.000',
            'unit_code' => 'kg',
            'reference_type' => 'PurchaseReceipt',
            'reference_id' => 'PR-001',
            'created_at' => $businessDay->copy()->setTime(7, 0),
        ]);

        $this->createIngredientStockMovement([
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'movement_type' => 'Wastage',
            'quantity_delta' => '-1.500',
            'unit_code' => 'kg',
            'created_at' => $businessDay->copy()->setTime(21, 0),
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-reporting-rebuild'))
            ->postJson('/api/v1/admin/settings/reporting/snapshots/rebuild', [
                'branch_id' => $branchId,
                'start_date' => $businessDay->toDateString(),
                'end_date' => $businessDay->toDateString(),
            ]);

        $response->assertOk()
            ->assertJsonPath('meta.action', 'admin_reporting_snapshots_rebuilt')
            ->assertJsonPath('data.branch_id', $branchId)
            ->assertJsonPath('data.rebuild.sales.row_count', 1)
            ->assertJsonPath('data.rebuild.sales.is_empty', false)
            ->assertJsonPath('data.rebuild.operations.row_count', 1)
            ->assertJsonPath('data.rebuild.operations.is_empty', false)
            ->assertJsonPath('data.rebuild.inventory.row_count', 1)
            ->assertJsonPath('data.rebuild.inventory.is_empty', false)
            ->assertJsonCount(0, 'data.warnings');

        $this->assertDatabaseHas('reporting_daily_sales_snapshots', [
            'branch_id' => $branchId,
            'business_date' => $businessDay->toDateString(),
            'currency' => 'VND',
            'billed_reservation_count' => 1,
            'invoice_issued_count' => 1,
        ]);

        $this->assertDatabaseHas('reporting_daily_operation_snapshots', [
            'branch_id' => $branchId,
            'business_date' => $businessDay->toDateString(),
            'scheduled_reservation_count' => 1,
            'waiting_list_created_count' => 2,
            'waiting_list_seated_count' => 1,
            'waiting_list_cancelled_count' => 1,
        ]);

        $this->assertDatabaseHas('reporting_daily_inventory_movement_snapshots', [
            'branch_id' => $branchId,
            'business_date' => $businessDay->toDateString(),
            'ingredient_id' => $ingredientId,
            'unit_code' => 'kg',
            'movement_count' => 2,
            'purchase_receipt_movement_count' => 1,
        ]);

        $this->assertGreaterThan(0, $waitingId);
    }

    public function test_inventory_reporting_rebuild_reconciles_purchase_receipts_with_served_order_consumption_for_branch_scope(): void
    {
        $adminRoleId = $this->ensureRole('Admin');
        $staffRoleId = $this->ensureRole('Staff');
        config()->set('staff_auth.allowed_role_ids', [$adminRoleId, $staffRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $adminRoleId => ['*'],
            $staffRoleId => ['*'],
        ]);

        $adminId = $this->createUser(['role_id' => $adminRoleId, 'role_name' => 'Admin']);
        $staffId = $this->createUser(['role_id' => $staffRoleId, 'role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $adminHeaders = $this->staffAuthHeaders($adminId, 'admin-reporting-inventory-reconcile-key');
        $staffHeaders = $this->staffAuthHeaders($staffId, 'staff-reporting-inventory-reconcile-key');
        $branchId = $this->createBranch([
            'branch_code' => 'DAD',
            'branch_name' => 'Da Nang',
        ]);
        $otherBranchId = $this->createBranch([
            'branch_code' => 'CTO',
            'branch_name' => 'Can Tho',
        ]);
        $supplierId = $this->createSupplier([
            'code' => 'SUP-RPT-RECON',
            'name' => 'Reporting Reconcile Supply',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-RPT-RECON',
            'name' => 'Reporting Rice',
            'unit_code' => 'kg',
        ]);
        $menuItemId = $this->createMenuItem([
            'code' => 'ITEM-RPT-RECON',
            'name' => 'Reporting Bowl',
        ]);

        $businessDay = Carbon::parse('2026-03-29 08:00:00', 'UTC');
        Carbon::setTestNow($businessDay);

        try {
            $this->createMenuItemRecipeLine([
                'item_id' => $menuItemId,
                'ingredient_id' => $ingredientId,
                'quantity' => '1.500',
                'unit_code' => 'kg',
                'sort_order' => 10,
            ]);

            $createPurchaseOrder = $this->withHeaders($this->withIdempotencyKey($adminHeaders, 'idem-admin-reporting-chain-po'))
                ->postJson('/api/v1/admin/inventory/purchase-orders', [
                    'branch_id' => $branchId,
                    'supplier_id' => $supplierId,
                    'order_code' => 'PO-RPT-RECON-0001',
                    'lines' => [
                        [
                            'ingredient_id' => $ingredientId,
                            'ordered_quantity' => '10.000',
                            'unit_code' => 'kg',
                            'unit_cost' => '18.500',
                        ],
                    ],
                ]);

            $createPurchaseOrder->assertCreated()
                ->assertJsonPath('data.purchase_order_status', 'Ordered')
                ->assertJsonPath('data.branch.branch_id', $branchId);

            $purchaseOrderId = (int) $createPurchaseOrder->json('data.purchase_order_id');
            $poLineId = (int) $createPurchaseOrder->json('data.lines.0.po_line_id');

            $receiptResponse = $this->withHeaders($this->withIdempotencyKey($adminHeaders, 'idem-admin-reporting-chain-receipt'))
                ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', [
                    'receipt_code' => 'GRN-RPT-RECON-0001',
                    'received_at' => $businessDay->copy()->setTime(9, 0)->toIso8601String(),
                    'lines' => [
                        [
                            'purchase_order_line_id' => $poLineId,
                            'received_quantity' => '10.000',
                            'unit_cost' => '18.500',
                        ],
                    ],
                ]);

            $receiptResponse->assertCreated()
                ->assertJsonPath('data.summary.received_total_quantity', '10.000')
                ->assertJsonPath('meta.purchase_order.purchase_order_status', 'Received');

            $reservationId = $this->createReservation([
                'branch_id' => $branchId,
                'user_id' => $customerId,
                'status' => 'Reserved',
                'reserved_at' => $businessDay->copy()->setTime(11, 0),
                'start_time' => $businessDay->copy()->setTime(11, 0),
                'end_time' => $businessDay->copy()->setTime(12, 0),
                'checked_in_at' => $businessDay->copy()->setTime(11, 0),
            ]);
            $tableId = $this->createRestaurantTable([
                'branch_id' => $branchId,
                'status' => 'Occupied',
            ]);
            $this->attachReservationTable($reservationId, $tableId);

            $orderId = $this->createOrder([
                'reservation_id' => $reservationId,
                'order_type' => 'OnSpot',
                'status' => 'Active',
                'row_version' => 1,
                'created_at' => $businessDay->copy()->setTime(11, 5),
                'updated_at' => $businessDay->copy()->setTime(11, 5),
            ]);
            $orderItemId = $this->createOrderItem([
                'order_id' => $orderId,
                'item_id' => $menuItemId,
                'quantity' => 2,
                'unit_price' => '95000.00',
                'currency' => 'VND',
                'line_total' => '190000.00',
                'status' => 'Ordered',
                'row_version' => 1,
                'created_at' => $businessDay->copy()->setTime(11, 10),
                'updated_at' => $businessDay->copy()->setTime(11, 10),
            ]);

            Carbon::setTestNow($businessDay->copy()->setTime(11, 30));

            $serveResponse = $this->withHeaders($this->withIdempotencyKey($staffHeaders, 'idem-staff-reporting-chain-serve'))
                ->postJson('/api/v1/staff/orders/'.$orderId.'/items/'.$orderItemId.'/status', [
                    'order_row_version' => 1,
                    'row_version' => 1,
                    'status' => 'Served',
                ]);

            $serveResponse->assertOk()
                ->assertJsonPath('meta.status', 'Served')
                ->assertJsonPath('data.items.0.status', 'Served');

            $this->createIngredientStockMovement([
                'branch_id' => $otherBranchId,
                'ingredient_id' => $ingredientId,
                'movement_type' => 'StockIn',
                'quantity_delta' => '99.000',
                'unit_code' => 'kg',
                'reference_type' => 'PurchaseReceipt',
                'reference_id' => 'GRN-OTHER-BRANCH-0001',
                'created_at' => $businessDay->copy()->setTime(15, 0),
            ]);

            self::assertSame(
                2,
                (int) DB::table('ingredient_stock_movements')
                    ->where('branch_id', $branchId)
                    ->where('ingredient_id', $ingredientId)
                    ->count()
            );
            self::assertSame(
                '-3.000',
                number_format(
                    (float) DB::table('ingredient_stock_movements')
                        ->where('branch_id', $branchId)
                        ->where('ingredient_id', $ingredientId)
                        ->where('reference_type', 'ReservationOrderItemConsumption')
                        ->value('quantity_delta'),
                    3,
                    '.',
                    ''
                )
            );

            $rebuildResponse = $this->withHeaders($this->withIdempotencyKey($adminHeaders, 'idem-admin-reporting-chain-rebuild'))
                ->postJson('/api/v1/admin/settings/reporting/snapshots/rebuild', [
                    'branch_id' => $branchId,
                    'start_date' => $businessDay->toDateString(),
                    'end_date' => $businessDay->toDateString(),
                ]);

            $rebuildResponse->assertOk()
                ->assertJsonPath('meta.action', 'admin_reporting_snapshots_rebuilt')
                ->assertJsonPath('data.branch_id', $branchId)
                ->assertJsonPath('data.rebuild.inventory.row_count', 1)
                ->assertJsonPath('data.rebuild.inventory.ingredient_count', 1)
                ->assertJsonPath('data.rebuild.inventory.is_empty', false);

            $this->assertDatabaseHas('reporting_daily_inventory_movement_snapshots', [
                'branch_id' => $branchId,
                'business_date' => $businessDay->toDateString(),
                'ingredient_id' => $ingredientId,
                'unit_code' => 'kg',
                'movement_count' => 2,
                'purchase_receipt_movement_count' => 1,
                'stock_in_quantity' => '10.000',
                'stock_out_quantity' => '3.000',
                'net_quantity_delta' => '7.000',
            ]);

            self::assertSame(
                1,
                (int) DB::table('reporting_daily_inventory_movement_snapshots')
                    ->where('business_date', $businessDay->toDateString())
                    ->where('ingredient_id', $ingredientId)
                    ->count()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_rebuild_reporting_surfaces_empty_scope_warnings_instead_of_silent_success(): void
    {
        [, $headers] = $this->adminHeaders('admin-reporting-empty-scope-key');
        $branchId = $this->createBranch([
            'branch_code' => 'HN',
            'branch_name' => 'Ha Noi',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-reporting-empty-scope'))
            ->postJson('/api/v1/admin/settings/reporting/snapshots/rebuild', [
                'branch_id' => $branchId,
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-01',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.rebuild.sales.is_empty', true)
            ->assertJsonPath('data.rebuild.operations.is_empty', true)
            ->assertJsonPath('data.rebuild.inventory.is_empty', true)
            ->assertJsonPath('data.warnings.0', 'sales_snapshot_empty_for_requested_scope')
            ->assertJsonPath('data.warnings.1', 'operations_snapshot_empty_for_requested_scope')
            ->assertJsonPath('data.warnings.2', 'inventory_snapshot_empty_for_requested_scope')
            ->assertJsonPath('data.warnings.3', 'requested_reporting_scope_empty');
    }

    public function test_non_admin_staff_cannot_rebuild_reporting_snapshots(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-reporting-forbidden'), 'idem-staff-reporting-forbidden'))
            ->postJson('/api/v1/admin/settings/reporting/snapshots/rebuild', [
                'start_date' => '2026-03-28',
                'end_date' => '2026-03-28',
            ]);

        $response->assertForbidden();
    }

    /**
     * @return array{0:int,1:array<string,string>}
     */
    private function adminHeaders(string $apiKey): array
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);

        return [$adminId, $this->staffAuthHeaders($adminId, $apiKey)];
    }
}
