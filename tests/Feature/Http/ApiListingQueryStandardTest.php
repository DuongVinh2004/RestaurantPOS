<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Modules\Reporting\Application\Workflows\ReportingSnapshotWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class ApiListingQueryStandardTest extends TestCase
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
        config()->set('staff_auth.allow_role_name_fallback', true);
        config()->set('staff_auth.allowed_role_names', ['Admin', 'Staff']);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_staff_reservation_listing_accepts_canonical_filters_and_legacy_sort_aliases(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-query-reservations');
        $tableId = $this->createRestaurantTableWithSeats(6, ['table_code' => 'Q-01', 'zone' => 'Main']);

        $smallReservationId = $this->createReservation([
            'reservation_code' => 'Q-RSV-001',
            'status' => 'Confirmed',
            'guest_count' => 2,
        ]);
        $this->attachReservationTable($smallReservationId, $tableId);

        $largeReservationId = $this->createReservation([
            'reservation_code' => 'Q-RSV-002',
            'status' => 'Confirmed',
            'guest_count' => 6,
        ]);
        $this->attachReservationTable($largeReservationId, $tableId);

        $canonical = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reservations?filter[bucket]=all&filter[status]=Confirmed&sort=-guest_count&page=1&per_page=1');

        $canonical->assertOk()
            ->assertJsonPath('meta.pagination.mode', 'paged')
            ->assertJsonPath('meta.filters.bucket', 'all')
            ->assertJsonPath('meta.filters.status', 'Confirmed')
            ->assertJsonPath('meta.sort.value', '-guest_count')
            ->assertJsonPath('meta.query_contract.filter_keys.0', 'bucket')
            ->assertJsonPath('data.0.reservation_id', $largeReservationId);

        $legacy = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reservations?bucket=all&status=Confirmed&sort_by=guest_count&sort_dir=desc&page=1&per_page=1');

        $legacy->assertOk()
            ->assertJsonPath('meta.sort.by', 'guest_count')
            ->assertJsonPath('meta.sort.dir', 'desc')
            ->assertJsonPath('data.0.reservation_id', $largeReservationId);
    }

    public function test_staff_waiting_list_supports_legacy_unbounded_and_paged_modes(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-query-waiting');

        $this->createWaitingListEntry([
            'guest_name' => 'Beta Guest',
            'status' => 'Waiting',
            'priority' => 1,
        ]);
        $this->createWaitingListEntry([
            'guest_name' => 'Alpha Guest',
            'status' => 'Waiting',
            'priority' => 1,
        ]);

        $legacy = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/waiting-list?filter[active_only]=1');

        $legacy->assertOk()
            ->assertJsonPath('meta.pagination.mode', 'legacy_unbounded')
            ->assertJsonPath('meta.total', 2);

        $paged = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/waiting-list?filter[active_only]=1&sort=guest_name&page=1&per_page=1');

        $paged->assertOk()
            ->assertJsonPath('meta.pagination.mode', 'paged')
            ->assertJsonPath('meta.sort.value', 'guest_name')
            ->assertJsonPath('meta.query_contract.sort_fields.0', 'priority')
            ->assertJsonPath('data.0.guest_name', 'Alpha Guest');
    }

    public function test_timeline_and_board_publish_non_paginated_query_contracts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-05 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-query-board');
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'TB-01', 'zone' => 'Main', 'status' => 'Available']);
        $reservationId = $this->createReservation([
            'reservation_code' => 'TB-RSV-001',
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-04-05 10:15:00', 'UTC'),
            'end_time' => Carbon::parse('2026-04-05 11:15:00', 'UTC'),
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $timeline = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reservations/timeline?filter[date]=2026-04-05&filter[lane_by]=zone');

        $timeline->assertOk()
            ->assertJsonPath('meta.pagination.mode', 'none')
            ->assertJsonPath('meta.query_contract.pagination.supported', false)
            ->assertJsonPath('meta.filters.date', '2026-04-05')
            ->assertJsonPath('meta.filters.lane_by', 'zone');

        $board = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/tables/board?filter[zone]=Main');

        $board->assertOk()
            ->assertJsonPath('meta.pagination.mode', 'none')
            ->assertJsonPath('meta.query_contract.pagination.supported', false)
            ->assertJsonPath('meta.filters.zone', 'Main')
            ->assertJsonPath('data.0.table_id', $tableId);
    }

    public function test_admin_menu_listing_supports_canonical_paged_queries_and_legacy_unbounded_mode(): void
    {
        [$adminId, $headers] = $this->adminHeaders('admin-query-menu');
        $categoryId = $this->ensureMenuCategory('Query Menu');
        $itemA = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'Q-MENU-B',
            'name' => 'Query Item B',
            'is_available' => 1,
        ]);
        $itemB = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'Q-MENU-A',
            'name' => 'Query Item A',
            'is_available' => 1,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $itemA,
            'price' => '120000.00',
            'currency' => 'VND',
            'effective_from' => now('UTC')->subDay(),
        ]);
        $this->createMenuItemPrice([
            'item_id' => $itemB,
            'price' => '110000.00',
            'currency' => 'VND',
            'effective_from' => now('UTC')->subHours(2),
        ]);

        $legacy = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/menu/items?filter[category_id]='.$categoryId);

        $legacy->assertOk()
            ->assertJsonPath('meta.pagination.mode', 'legacy_unbounded')
            ->assertJsonPath('meta.total', 2);

        $paged = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/menu/items?filter[category_id]='.$categoryId.'&sort=code&page=1&per_page=1');

        $paged->assertOk()
            ->assertJsonPath('meta.pagination.mode', 'paged')
            ->assertJsonPath('meta.sort.value', 'code')
            ->assertJsonPath('meta.query_contract.sort_fields.1', 'code')
            ->assertJsonPath('data.0.code', 'Q-MENU-A');

        $prices = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/menu/items/'.$itemA.'/prices?sort=-effective_from&page=1&per_page=1');

        $prices->assertOk()
            ->assertJsonPath('meta.pagination.mode', 'paged')
            ->assertJsonPath('meta.sort.value', '-effective_from')
            ->assertJsonPath('meta.item_id', $itemA);

        self::assertSame($adminId, $adminId);
    }

    public function test_admin_inventory_and_purchasing_lists_expose_standardized_meta(): void
    {
        [, $headers] = $this->adminHeaders('admin-query-inventory');
        $ingredientId = $this->createIngredient([
            'code' => 'ING-Q-01',
            'name' => 'Query Rice',
            'unit_code' => 'kg',
        ]);
        $this->createIngredientStockMovement([
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => '5.000',
            'unit_code' => 'kg',
        ]);

        $supplierId = $this->createSupplier([
            'code' => 'SUP-Q-01',
            'name' => 'Query Supplier',
        ]);
        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-query-po-create'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-Q-0001',
                'purchase_order_status' => 'Ordered',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '2.000',
                        'unit_code' => 'kg',
                    ],
                ],
            ])->assertCreated();

        $ingredients = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/ingredients?filter[q]=Query&sort=-stock_on_hand_quantity&page=1&per_page=1');

        $ingredients->assertOk()
            ->assertJsonPath('meta.sort.value', '-stock_on_hand_quantity')
            ->assertJsonPath('meta.query_contract.filter_keys.0', 'is_active')
            ->assertJsonPath('data.0.ingredient_id', $ingredientId);

        $movements = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements?sort=-created_at&page=1&per_page=1');

        $movements->assertOk()
            ->assertJsonPath('meta.sort.value', '-created_at')
            ->assertJsonPath('meta.ingredient.ingredient_id', $ingredientId);

        $suppliers = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/suppliers?filter[q]=Supplier&sort=name&page=1&per_page=1');

        $suppliers->assertOk()
            ->assertJsonPath('meta.sort.value', 'name')
            ->assertJsonPath('data.0.supplier_id', $supplierId);

        $orders = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/purchase-orders?filter[supplier_id]='.$supplierId.'&sort=-created_at&page=1&per_page=1');

        $orders->assertOk()
            ->assertJsonPath('meta.sort.value', '-created_at')
            ->assertJsonPath('meta.filters.supplier_id', $supplierId);
    }

    public function test_staff_finance_and_reporting_lists_expose_standardized_query_meta(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-05 09:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-query-finance');
        $customerId = $this->createUser(['role_name' => 'Customer', 'full_name' => 'Report Guest']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'reservation_code' => 'Q-FIN-001',
            'status' => 'Completed',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Paid',
            'final_bill_amount' => '150000.00',
            'bill_currency' => 'VND',
            'billed_at' => Carbon::parse('2026-04-05 08:30:00', 'UTC'),
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000.00',
            'currency' => 'VND',
            'created_by' => $staffId,
            'paid_at' => Carbon::parse('2026-04-05 08:00:00', 'UTC'),
        ]);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '100000.00',
            'currency' => 'VND',
            'created_by' => $staffId,
            'paid_at' => Carbon::parse('2026-04-05 08:35:00', 'UTC'),
        ]);

        app(ReportingSnapshotWorkflow::class)->rebuild([
            'branch_id' => 1,
            'start_date' => '2026-04-05',
            'end_date' => '2026-04-05',
        ], $staffId);

        $finance = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/finance/reconciliation?filter[reservation_code]=Q-FIN&sort=-last_payment_activity_at&page=1&per_page=1');

        $finance->assertOk()
            ->assertJsonPath('meta.sort.value', '-last_payment_activity_at')
            ->assertJsonPath('meta.filters.reservation_code', 'Q-FIN')
            ->assertJsonPath('data.0.reservation.reservation_id', $reservationId);

        $reporting = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reporting/daily-sales?filter[start_date]=2026-04-05&filter[end_date]=2026-04-05&sort=-gross_bill_amount&page=1&per_page=10');

        $reporting->assertOk()
            ->assertJsonPath('meta.sort.value', '-gross_bill_amount')
            ->assertJsonPath('meta.query_contract.filter_keys.0', 'branch_id')
            ->assertJsonPath('meta.snapshot_health.family', 'sales');
    }

    /**
     * @return array{0:int,1:array<string,string>}
     */
    private function adminHeaders(string $apiKey): array
    {
        $adminRoleId = $this->ensureRole('Admin');
        $adminId = $this->createUser([
            'role_id' => $adminRoleId,
            'role_name' => 'Admin',
        ]);

        config()->set('staff_auth.allowed_role_ids', [$adminRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $adminRoleId => ['*'],
        ]);

        return [$adminId, $this->staffAuthHeaders($adminId, $apiKey)];
    }
}
