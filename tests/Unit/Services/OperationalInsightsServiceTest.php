<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DatabaseContractInspector;
use App\Services\Inventory\PurchaseOrderReconciliationService;
use App\Services\Kitchen\KitchenTicketReconciliationService;
use App\Services\OperationalInsightsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class OperationalInsightsServiceTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('booking.ops.reporting_snapshot_stale_hours', 48);
        $this->requireBookingSchema();
        $this->clearReportingSnapshotTables();
    }

    private function clearReportingSnapshotTables(): void
    {
        foreach ([
            'reporting_daily_sales_snapshots',
            'reporting_daily_operation_snapshots',
            'reporting_daily_inventory_movement_snapshots',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    #[Group('booking-ops')]
    public function test_snapshot_exposes_reporting_and_branch_sections_when_live_foundations_are_seeded(): void
    {
        $now = Carbon::parse('2026-04-02T09:00:00Z')->utc();
        $ingredientId = $this->createIngredient();

        DB::table('reporting_daily_sales_snapshots')->insert([
            'branch_id' => 1,
            'business_date' => '2026-04-01',
            'currency' => 'VND',
            'refreshed_at' => $now->copy()->subHour(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('reporting_daily_operation_snapshots')->insert([
            'branch_id' => 1,
            'business_date' => '2026-04-01',
            'refreshed_at' => $now->copy()->subHours(2),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('reporting_daily_inventory_movement_snapshots')->insert([
            'branch_id' => 1,
            'business_date' => '2026-04-01',
            'ingredient_id' => $ingredientId,
            'unit_code' => 'g',
            'refreshed_at' => $now->copy()->subHours(3),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $snapshot = app(OperationalInsightsService::class)->snapshot($now);

        $this->assertArrayHasKey('reporting_snapshots', $snapshot);
        $this->assertArrayHasKey('branch_defaults', $snapshot);
        $this->assertSame('ok', $snapshot['reporting_snapshots']['status']);
        $this->assertSame(3, $snapshot['reporting_snapshots']['populated_family_count']);
        $this->assertSame(3, $snapshot['reporting_snapshots']['healthy_family_count']);
        $this->assertSame(0, $snapshot['reporting_snapshots']['stale_scope_count_total']);
        $this->assertSame(3, $snapshot['reporting_snapshots']['family_count']);
        $this->assertGreaterThanOrEqual(3 * 3600, (int) $snapshot['reporting_snapshots']['latest_refresh_age_seconds_max']);
        $this->assertSame('ok', $snapshot['branch_defaults']['status']);
        $this->assertSame(1, $snapshot['branch_defaults']['default_count']);
        $this->assertSame('MAIN', $snapshot['branch_defaults']['default_branch_code']);
    }

    #[Group('booking-ops')]
    public function test_database_contract_snapshot_reads_live_metadata_without_inspector_exception(): void
    {
        $snapshot = app(DatabaseContractInspector::class)->snapshot();
        $issues = (array) ($snapshot['issues'] ?? []);

        $this->assertFalse(
            collect($issues)->contains(static fn ($issue) => is_string($issue) && str_contains($issue, 'Failed to inspect database contract metadata:'))
        );
    }

    #[Group('booking-ops')]
    public function test_snapshot_returns_fail_section_when_a_dependency_throws(): void
    {
        $this->app->instance(DatabaseContractInspector::class, new class extends DatabaseContractInspector
        {
            public function __construct() {}

            public function snapshot(): array
            {
                throw new \RuntimeException('database contract inspector unavailable');
            }
        });

        $snapshot = app(OperationalInsightsService::class)->snapshot(Carbon::parse('2026-04-02T09:00:00Z')->utc());

        $this->assertSame('fail', $snapshot['database_contract']['status']);
        $this->assertContains('runtime_dependency_unavailable', $snapshot['database_contract']['reasons']);
        $this->assertStringContainsString('database contract inspector unavailable', (string) ($snapshot['database_contract']['error'] ?? ''));
    }

    #[Group('booking-ops')]
    public function test_reporting_snapshot_health_degrades_when_latest_refresh_is_stale(): void
    {
        config()->set('booking.ops.reporting_snapshot_stale_hours', 24);

        $now = Carbon::parse('2026-04-02T09:00:00Z')->utc();

        DB::table('reporting_daily_sales_snapshots')->insert([
            'branch_id' => 1,
            'business_date' => '2026-03-29',
            'currency' => 'VND',
            'refreshed_at' => $now->copy()->subHours(72),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $snapshot = app(OperationalInsightsService::class)->reportingSnapshotsSnapshot($now);

        $this->assertSame('degraded', $snapshot['status']);
        $this->assertContains('reporting_snapshot_stale', $snapshot['reasons']);
        $this->assertSame(0, $snapshot['healthy_family_count']);
        $this->assertSame(1, $snapshot['stale_scope_count_total']);
        $this->assertGreaterThanOrEqual(72 * 3600, (int) $snapshot['latest_refresh_age_seconds_max']);
        $this->assertSame(1, $snapshot['families']['sales']['stale_scope_count']);
        $this->assertGreaterThanOrEqual(72 * 3600, (int) $snapshot['families']['sales']['latest_refresh_age_seconds']);
    }

    #[Group('booking-ops')]
    public function test_reporting_snapshot_health_stays_ok_when_snapshot_tables_are_empty_and_source_domains_are_empty(): void
    {
        $now = Carbon::parse('2026-04-02T09:00:00Z')->utc();

        $snapshot = app(OperationalInsightsService::class)->reportingSnapshotsSnapshot($now);

        $this->assertSame('ok', $snapshot['status']);
        $this->assertSame([], $snapshot['reasons']);
        $this->assertSame(0, $snapshot['populated_family_count']);
        $this->assertSame(0, $snapshot['source_activity_count_total']);
        $this->assertSame(0, $snapshot['families']['sales']['source_activity_count']);
        $this->assertSame(0, $snapshot['families']['operations']['source_activity_count']);
        $this->assertSame(0, $snapshot['families']['inventory']['source_activity_count']);
    }

    #[Group('booking-ops')]
    public function test_reporting_snapshot_health_degrades_when_snapshot_family_is_missing_and_foundation_activity_exists(): void
    {
        $now = Carbon::parse('2026-04-02T09:00:00Z')->utc();
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $ingredientId = $this->createIngredient();

        $this->createReservation([
            'branch_id' => 1,
            'user_id' => $customerId,
            'status' => 'Completed',
            'guest_count' => 2,
            'start_time' => $now->copy()->subDay()->setTime(12, 0),
            'end_time' => $now->copy()->subDay()->setTime(13, 0),
            'checked_in_at' => $now->copy()->subDay()->setTime(12, 5),
            'checked_out_at' => $now->copy()->subDay()->setTime(12, 55),
            'billed_at' => $now->copy()->subDay()->setTime(13, 0),
            'bill_currency' => 'VND',
            'final_bill_amount' => '90000.00',
            'discount_amount' => '10000.00',
        ]);

        $this->createWaitingListEntry([
            'branch_id' => 1,
            'requested_at' => $now->copy()->subDay()->setTime(18, 0),
            'status' => 'Waiting',
        ]);

        $this->createIngredientStockMovement([
            'branch_id' => 1,
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => '5.000',
            'unit_code' => 'g',
            'created_at' => $now->copy()->subDay()->setTime(6, 0),
        ]);

        DB::table('reporting_daily_sales_snapshots')->insert([
            'branch_id' => 1,
            'business_date' => '2026-04-01',
            'currency' => 'VND',
            'refreshed_at' => $now->copy()->subHour(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $snapshot = app(OperationalInsightsService::class)->reportingSnapshotsSnapshot($now);

        $this->assertSame('degraded', $snapshot['status']);
        $this->assertContains('reporting_snapshot_incomplete', $snapshot['reasons']);
        $this->assertSame(1, $snapshot['populated_family_count']);
        $this->assertSame(2, $snapshot['empty_family_count']);
        $this->assertSame(['operations', 'inventory'], $snapshot['empty_families']);
        $this->assertGreaterThan(0, $snapshot['families']['operations']['source_activity_count']);
        $this->assertGreaterThan(0, $snapshot['families']['inventory']['source_activity_count']);
    }

    #[Group('booking-ops')]
    public function test_branch_defaults_health_fails_when_multiple_default_branches_exist(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'NORTH',
            'branch_name' => 'North Branch',
        ]);

        DB::table('branches')->where('branch_id', $branchId)->update([
            'is_default' => true,
            'updated_at' => now('UTC'),
        ]);

        $snapshot = app(OperationalInsightsService::class)->branchDefaultsSnapshot();

        $this->assertSame('fail', $snapshot['status']);
        $this->assertContains('branch_default_ambiguous', $snapshot['reasons']);
        $this->assertSame(2, $snapshot['default_count']);
    }

    #[Group('booking-ops')]
    public function test_staff_api_key_health_ignores_expiring_auth_sessions_when_governance_keys_are_healthy(): void
    {
        config()->set('staff_auth.database_store_enabled', true);
        config()->set('staff_auth.allow_env_fallback', false);
        config()->set('booking.ops.staff_api_keys_missing_active_fail_count', 1);
        config()->set('booking.ops.staff_api_keys_never_used_warn_count', 5);
        config()->set('booking.ops.staff_api_keys_expiring_soon_days', 14);

        $staffId = $this->createUser(['role_name' => 'Staff']);
        DB::table('staff_api_keys')->delete();

        $now = Carbon::parse('2026-04-02T09:00:00Z')->utc();
        DB::table('staff_api_keys')->insert([
            [
                'staff_api_key_id' => 9001,
                'user_id' => $staffId,
                'label' => 'Bootstrap POS Key',
                'key_hash' => str_repeat('a', 64),
                'last_used_at' => $now->copy()->subDay(),
                'expires_at' => $now->copy()->addDays(30),
                'revoked_at' => null,
                'created_at' => $now->copy()->subDays(5),
                'updated_at' => $now->copy()->subDays(5),
            ],
            [
                'staff_api_key_id' => 9002,
                'user_id' => $staffId,
                'label' => 'Auth Session - POS Tablet',
                'key_hash' => str_repeat('b', 64),
                'last_used_at' => $now->copy()->subMinutes(30),
                'expires_at' => $now->copy()->addHours(4),
                'revoked_at' => null,
                'created_at' => $now->copy()->subHour(),
                'updated_at' => $now->copy()->subHour(),
            ],
            [
                'staff_api_key_id' => 9003,
                'user_id' => $staffId,
                'label' => 'Auth Session',
                'key_hash' => str_repeat('c', 64),
                'last_used_at' => null,
                'expires_at' => $now->copy()->addHours(2),
                'revoked_at' => null,
                'created_at' => $now->copy()->subHour(),
                'updated_at' => $now->copy()->subHour(),
            ],
        ]);

        $snapshot = app(OperationalInsightsService::class)->staffApiKeySnapshot($now);

        $this->assertSame('ok', $snapshot['status']);
        $this->assertSame([], $snapshot['reasons']);
        $this->assertSame(3, $snapshot['active_count']);
        $this->assertSame(1, $snapshot['active_governance_count']);
        $this->assertSame(2, $snapshot['active_session_count']);
        $this->assertSame(2, $snapshot['expiring_soon_count']);
        $this->assertSame(0, $snapshot['expiring_soon_governance_count']);
        $this->assertSame(2, $snapshot['expiring_soon_session_count']);
    }

    #[Group('booking-ops')]
    public function test_staff_api_key_health_degrades_when_governance_key_is_expiring_soon(): void
    {
        config()->set('staff_auth.database_store_enabled', true);
        config()->set('staff_auth.allow_env_fallback', false);
        config()->set('booking.ops.staff_api_keys_missing_active_fail_count', 1);
        config()->set('booking.ops.staff_api_keys_never_used_warn_count', 5);
        config()->set('booking.ops.staff_api_keys_expiring_soon_days', 14);

        $staffId = $this->createUser(['role_name' => 'Staff']);
        DB::table('staff_api_keys')->delete();

        $now = Carbon::parse('2026-04-02T09:00:00Z')->utc();
        DB::table('staff_api_keys')->insert([
            'staff_api_key_id' => 9010,
            'user_id' => $staffId,
            'label' => 'Bootstrap POS Key',
            'key_hash' => str_repeat('d', 64),
            'last_used_at' => $now->copy()->subDay(),
            'expires_at' => $now->copy()->addDays(3),
            'revoked_at' => null,
            'created_at' => $now->copy()->subDays(5),
            'updated_at' => $now->copy()->subDays(5),
        ]);

        $snapshot = app(OperationalInsightsService::class)->staffApiKeySnapshot($now);

        $this->assertSame('degraded', $snapshot['status']);
        $this->assertContains('staff_api_keys_expiring_soon', $snapshot['reasons']);
        $this->assertSame(1, $snapshot['expiring_soon_governance_count']);
        $this->assertSame(0, $snapshot['expiring_soon_session_count']);
    }

    #[Group('booking-ops')]
    public function test_snapshot_exposes_non_core_domain_sections_when_kitchen_inventory_and_conversation_foundations_are_healthy(): void
    {
        $now = Carbon::parse('2026-04-02T09:00:00Z')->utc();
        $this->resetNonCoreOpsTables();

        $categoryId = $this->ensureMenuCategory('Ops Snapshot Kitchen');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'OPS-KDS-01',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'OPS-KDS',
            'name' => 'Ops Snapshot KDS',
        ]);
        $routeId = $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
        ]);
        $orderItemId = $this->createOrderItem([
            'item_id' => $itemId,
            'status' => 'Ordered',
        ]);
        $this->createKitchenOrderTicket([
            'station_id' => $stationId,
            'route_id' => $routeId,
            'order_item_id' => $orderItemId,
            'item_id' => $itemId,
            'category_id' => $categoryId,
            'ticket_status' => 'Queued',
            'first_dispatched_at' => $now->copy()->subMinutes(4),
            'created_at' => $now->copy()->subMinutes(4),
            'updated_at' => $now->copy()->subMinutes(4),
        ]);

        $this->seedPurchaseOrderReceiptLineage([
            'now' => $now,
            'purchase_order_status' => 'Received',
            'ordered_quantity' => '5.000',
            'received_quantity' => '5.000',
        ]);

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $conversationId = $this->createConversation([
            'workflow_state' => 'Assigned',
            'workflow_state_reason' => 'assigned',
            'workflow_state_changed_at' => $now->copy()->subMinutes(3),
            'created_at' => $now->copy()->subMinutes(6),
        ]);
        DB::table('conversation_messages')->insert([
            'conversation_id' => $conversationId,
            'sender' => 'user',
            'sender_id' => null,
            'message_text' => 'Recent follow-up message',
            'message_type' => 'text',
            'is_internal_note' => 0,
            'attachment_url' => null,
            'created_at' => $now->copy()->subMinutes(2),
            'is_processed' => 0,
            'processing_status' => null,
            'confidence' => null,
            'related_reservation_id' => null,
            'related_order_id' => null,
        ]);
        DB::table('agent_assignments')->insert([
            'conversation_id' => $conversationId,
            'agent_user_id' => $staffId,
            'assigned_at' => $now->copy()->subMinutes(3),
            'released_at' => null,
            'is_active' => 1,
            'notes' => null,
        ]);

        $snapshot = app(OperationalInsightsService::class)->snapshot($now);

        $this->assertArrayHasKey('kitchen_kds', $snapshot);
        $this->assertArrayHasKey('inventory_purchasing', $snapshot);
        $this->assertArrayHasKey('conversation_inbox', $snapshot);
        $this->assertSame('ok', $snapshot['kitchen_kds']['status']);
        $this->assertSame(1, $snapshot['kitchen_kds']['active_ticket_count']);
        $this->assertSame(0, $snapshot['kitchen_kds']['drift_count']);
        $this->assertSame('ok', $snapshot['inventory_purchasing']['status']);
        $this->assertSame(1, $snapshot['inventory_purchasing']['checked_order_count']);
        $this->assertSame(0, $snapshot['inventory_purchasing']['issue_order_count']);
        $this->assertSame(0, $snapshot['inventory_purchasing']['duplicate_purchase_receipt_reference_count']);
        $this->assertSame([], $snapshot['inventory_purchasing']['duplicate_purchase_receipt_reference_examples']);
        $this->assertSame([], $snapshot['inventory_purchasing']['issue_examples']);
        $this->assertSame('ok', $snapshot['conversation_inbox']['status']);
        $this->assertSame(1, $snapshot['conversation_inbox']['active_conversation_count']);
        $this->assertSame(0, $snapshot['conversation_inbox']['unassigned_count']);
        $this->assertSame(0, $snapshot['conversation_inbox']['terminal_with_active_assignment_count']);
    }

    #[Group('booking-ops')]
    public function test_non_core_domain_snapshots_fail_when_kitchen_inventory_and_conversation_drift_is_detected(): void
    {
        $now = Carbon::parse('2026-04-02T09:00:00Z')->utc();
        $this->resetNonCoreOpsTables();

        $categoryId = $this->ensureMenuCategory('Ops Drift Kitchen');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'OPS-KDS-DRIFT',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'OPS-KDS-DRIFT',
            'name' => 'Ops Drift KDS',
        ]);
        $routeId = $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
        ]);
        $orderItemId = $this->createOrderItem([
            'item_id' => $itemId,
            'status' => 'Ordered',
        ]);
        $this->createKitchenOrderTicket([
            'station_id' => $stationId,
            'route_id' => $routeId,
            'order_item_id' => $orderItemId,
            'item_id' => $itemId,
            'category_id' => $categoryId,
            'ticket_status' => 'Fired',
            'first_dispatched_at' => $now->copy()->subMinutes(6),
            'fired_at' => $now->copy()->subMinutes(5),
            'created_at' => $now->copy()->subMinutes(6),
            'updated_at' => $now->copy()->subMinutes(5),
        ]);

        $this->seedPurchaseOrderReceiptLineage([
            'now' => $now,
            'purchase_order_status' => 'PartiallyReceived',
            'ordered_quantity' => '5.000',
            'received_quantity' => '5.000',
            'line_received_quantity' => '5.000',
            'with_stock_movement' => false,
        ]);

        $conversationId = $this->createConversation([
            'workflow_state' => 'Closed',
            'workflow_state_reason' => 'closed',
            'status' => 'Closed',
            'closed_at' => $now->copy()->subMinutes(2),
            'workflow_state_changed_at' => $now->copy()->subMinutes(2),
            'created_at' => $now->copy()->subMinutes(10),
        ]);
        DB::table('agent_assignments')->insert([
            'conversation_id' => $conversationId,
            'agent_user_id' => $this->createUser(['role_name' => 'Staff']),
            'assigned_at' => $now->copy()->subMinute(),
            'released_at' => null,
            'is_active' => 1,
            'notes' => null,
        ]);

        $kitchenSnapshot = app(OperationalInsightsService::class)->kitchenKdsSnapshot($now);
        $inventorySnapshot = app(OperationalInsightsService::class)->inventoryPurchasingSnapshot($now);
        $conversationSnapshot = app(OperationalInsightsService::class)->conversationInboxSnapshot($now);

        $this->assertSame('fail', $kitchenSnapshot['status']);
        $this->assertContains('kitchen_ticket_status_drift_detected', $kitchenSnapshot['reasons']);
        $this->assertSame(1, $kitchenSnapshot['status_drift_count']);
        $this->assertSame('fail', $inventorySnapshot['status']);
        $this->assertContains('inventory_purchase_receiving_drift_detected', $inventorySnapshot['reasons']);
        $this->assertSame(1, $inventorySnapshot['issue_order_count']);
        $this->assertSame('fail', $conversationSnapshot['status']);
        $this->assertContains('conversation_terminal_assignment_drift', $conversationSnapshot['reasons']);
        $this->assertSame(1, $conversationSnapshot['terminal_with_active_assignment_count']);
    }

    #[Group('booking-ops')]
    public function test_inventory_snapshot_fails_when_duplicate_purchase_receipt_references_are_reported(): void
    {
        $service = new OperationalInsightsService(
            app(DatabaseContractInspector::class),
            app(KitchenTicketReconciliationService::class),
            new class extends PurchaseOrderReconciliationService
            {
                public function duplicatePurchaseReceiptReferenceSummary(int $limit = 3): array
                {
                    return [
                        'duplicate_reference_count' => 1,
                        'duplicate_movement_count' => 2,
                        'examples' => [
                            [
                                'reference_id' => 'GRN-OPS-DUP:501',
                                'duplicate_count' => 2,
                                'movement_ids' => [4001, 4002],
                            ],
                        ],
                    ];
                }
            },
        );

        $snapshot = $service->inventoryPurchasingSnapshot(Carbon::parse('2026-04-02T09:00:00Z')->utc());

        $this->assertSame('fail', $snapshot['status']);
        $this->assertContains('inventory_purchase_receipt_lineage_duplicate_detected', $snapshot['reasons']);
        $this->assertSame(1, $snapshot['duplicate_purchase_receipt_reference_count']);
        $this->assertSame(2, $snapshot['duplicate_purchase_receipt_movement_count']);
        $this->assertSame('GRN-OPS-DUP:501', $snapshot['duplicate_purchase_receipt_reference_examples'][0]['reference_id'] ?? null);
    }

    #[Group('booking-ops')]
    public function test_non_core_domain_snapshots_degrade_when_backlogs_are_stale_without_domain_drift(): void
    {
        config()->set('booking.ops.kitchen_ready_backlog_warn_seconds', 300);
        config()->set('booking.ops.inventory_purchase_overdue_warn_count', 1);
        config()->set('booking.ops.inventory_purchase_overdue_warn_seconds', 3600);
        config()->set('booking.ops.conversation_unassigned_warn_count', 1);
        config()->set('booking.ops.conversation_overdue_warn_count', 1);
        config()->set('booking.ops.conversation_oldest_overdue_warn_seconds', 900);

        $now = Carbon::parse('2026-04-02T09:00:00Z')->utc();
        $this->resetNonCoreOpsTables();

        $categoryId = $this->ensureMenuCategory('Ops Backlog Kitchen');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'OPS-KDS-BACKLOG',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'OPS-KDS-BACKLOG',
            'name' => 'Ops Backlog KDS',
        ]);
        $routeId = $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
        ]);
        $orderItemId = $this->createOrderItem([
            'item_id' => $itemId,
            'status' => 'InProgress',
        ]);
        $this->createKitchenOrderTicket([
            'station_id' => $stationId,
            'route_id' => $routeId,
            'order_item_id' => $orderItemId,
            'item_id' => $itemId,
            'category_id' => $categoryId,
            'ticket_status' => 'Ready',
            'first_dispatched_at' => $now->copy()->subMinutes(40),
            'fired_at' => $now->copy()->subMinutes(35),
            'ready_at' => $now->copy()->subMinutes(20),
            'created_at' => $now->copy()->subMinutes(40),
            'updated_at' => $now->copy()->subMinutes(20),
        ]);

        $this->seedPurchaseOrderReceiptLineage([
            'now' => $now,
            'purchase_order_status' => 'Ordered',
            'ordered_quantity' => '5.000',
            'with_receipt' => false,
            'expected_at' => $now->copy()->subDays(2),
        ]);

        $this->createConversation([
            'workflow_state' => 'Open',
            'workflow_state_reason' => 'open',
            'status' => 'Open',
            'created_at' => $now->copy()->subHours(2),
            'workflow_state_changed_at' => $now->copy()->subHours(2),
        ]);

        $kitchenSnapshot = app(OperationalInsightsService::class)->kitchenKdsSnapshot($now);
        $inventorySnapshot = app(OperationalInsightsService::class)->inventoryPurchasingSnapshot($now);
        $conversationSnapshot = app(OperationalInsightsService::class)->conversationInboxSnapshot($now);

        $this->assertSame('degraded', $kitchenSnapshot['status']);
        $this->assertContains('kitchen_ticket_ready_backlog_stale', $kitchenSnapshot['reasons']);
        $this->assertSame('degraded', $inventorySnapshot['status']);
        $this->assertContains('inventory_purchase_order_overdue_backlog', $inventorySnapshot['reasons']);
        $this->assertSame(1, $inventorySnapshot['overdue_open_order_count']);
        $this->assertSame('degraded', $conversationSnapshot['status']);
        $this->assertContains('conversation_unassigned_backlog', $conversationSnapshot['reasons']);
        $this->assertContains('conversation_overdue_backlog', $conversationSnapshot['reasons']);
        $this->assertSame(1, $conversationSnapshot['unassigned_count']);
        $this->assertSame(1, $conversationSnapshot['overdue_count']);
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array{purchase_order_id:int,po_line_id:int,receipt_id:?int,receipt_line_id:?int,stock_movement_id:?int}
     */
    private function seedPurchaseOrderReceiptLineage(array $overrides = []): array
    {
        $seedNow = $overrides['now'] ?? $this->nowUtc();
        $now = $seedNow instanceof Carbon
            ? $seedNow->copy()->utc()
            : Carbon::parse((string) $seedNow)->utc();
        $supplierId = (int) ($overrides['supplier_id'] ?? $this->createSupplier());
        $ingredientId = (int) ($overrides['ingredient_id'] ?? $this->createIngredient());
        $orderedQuantity = (string) ($overrides['ordered_quantity'] ?? '5.000');
        $receivedQuantity = (string) ($overrides['received_quantity'] ?? $orderedQuantity);
        $unitCode = (string) ($overrides['unit_code'] ?? (DB::table('ingredients')->where('ingredient_id', $ingredientId)->value('unit_code') ?? 'g'));
        $purchaseOrderStatus = (string) ($overrides['purchase_order_status'] ?? 'Received');
        $withReceipt = (bool) ($overrides['with_receipt'] ?? true);
        $withStockMovement = (bool) ($overrides['with_stock_movement'] ?? true);
        $lineReceivedQuantity = array_key_exists('line_received_quantity', $overrides)
            ? (string) $overrides['line_received_quantity']
            : ($withReceipt ? $receivedQuantity : '0.000');

        $purchaseOrderId = (int) DB::table('purchase_orders')->insertGetId([
            'branch_id' => (int) ($overrides['branch_id'] ?? 1),
            'supplier_id' => $supplierId,
            'order_code' => (string) ($overrides['order_code'] ?? ('PO-OPS-' . strtoupper(bin2hex(random_bytes(3))))),
            'purchase_order_status' => $purchaseOrderStatus,
            'ordered_at' => $overrides['ordered_at'] ?? $now->copy()->subDay(),
            'expected_at' => $overrides['expected_at'] ?? $now->copy()->addDay(),
            'received_at' => $purchaseOrderStatus === 'Received'
                ? ($overrides['received_at'] ?? $now->copy()->subHour())
                : ($overrides['received_at'] ?? null),
            'supplier_reference' => 'SUP-OPS',
            'notes' => 'Ops snapshot fixture',
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $poLineId = (int) DB::table('purchase_order_lines')->insertGetId([
            'purchase_order_id' => $purchaseOrderId,
            'ingredient_id' => $ingredientId,
            'ordered_quantity' => $orderedQuantity,
            'received_quantity' => $lineReceivedQuantity,
            'unit_code' => $unitCode,
            'unit_cost' => '10.000',
            'notes' => null,
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $receiptId = null;
        $receiptLineId = null;
        $stockMovementId = null;

        if ($withReceipt) {
            $receiptId = (int) DB::table('purchase_receipts')->insertGetId([
                'branch_id' => (int) ($overrides['branch_id'] ?? 1),
                'purchase_order_id' => $purchaseOrderId,
                'receipt_code' => (string) ($overrides['receipt_code'] ?? ('GRN-OPS-' . strtoupper(bin2hex(random_bytes(3))))),
                'receipt_status' => 'Posted',
                'received_at' => $overrides['receipt_received_at'] ?? $now->copy()->subMinutes(30),
                'supplier_document_no' => 'SUP-DOC-OPS',
                'notes' => 'Ops snapshot fixture',
                'created_by' => null,
                'created_at' => $now,
            ]);

            if ($withStockMovement) {
                $stockMovementId = $this->createIngredientStockMovement([
                    'branch_id' => (int) ($overrides['branch_id'] ?? 1),
                    'ingredient_id' => $ingredientId,
                    'movement_type' => 'StockIn',
                    'quantity_delta' => $receivedQuantity,
                    'unit_code' => $unitCode,
                    'reference_type' => 'PurchaseReceipt',
                    'reference_id' => (($overrides['receipt_code'] ?? null) ?: DB::table('purchase_receipts')->where('receipt_id', $receiptId)->value('receipt_code')).':'.$poLineId,
                    'created_at' => $now,
                ]);
            }

            $receiptLineId = (int) DB::table('purchase_receipt_lines')->insertGetId([
                'receipt_id' => $receiptId,
                'purchase_order_line_id' => $poLineId,
                'ingredient_id' => $ingredientId,
                'received_quantity' => $receivedQuantity,
                'unit_code' => $unitCode,
                'unit_cost' => '10.000',
                'stock_movement_id' => $stockMovementId,
                'notes' => null,
                'created_at' => $now,
            ]);
        }

        return [
            'purchase_order_id' => $purchaseOrderId,
            'po_line_id' => $poLineId,
            'receipt_id' => $receiptId,
            'receipt_line_id' => $receiptLineId,
            'stock_movement_id' => $stockMovementId,
        ];
    }

    private function resetNonCoreOpsTables(): void
    {
        foreach ([
            'message_entities',
            'conversation_files',
            'conversation_events',
            'conversation_analyses',
            'conversation_messages',
            'agent_assignments',
            'conversations',
            'kitchen_order_item_tickets',
            'purchase_receipt_lines',
            'purchase_receipts',
            'purchase_order_lines',
            'purchase_orders',
            'ingredient_stock_movements',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }
}
