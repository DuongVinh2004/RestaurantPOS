<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\KitchenDispatch\Application\Workflows\KitchenRoutingService;
use App\Modules\KitchenDispatch\Application\Workflows\KitchenTicketReconciliationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffKitchenDispatchFoundationFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('booking.realtime.enabled', true);
        config()->set('booking.realtime.cache_store', 'array');
        config()->set('booking.realtime.recent_event_limit', 50);
        config()->set('booking.realtime.poll_hint_ms', 1500);
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
        Cache::store('array')->flush();
        Cache::store('redis')->getStore()->flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_staff_can_dispatch_fire_bump_recall_and_complete_kitchen_ticket_without_breaking_order_lifecycle(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-dispatch-key');

        $categoryId = $this->ensureMenuCategory('Kitchen Mains');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'KDS-BOWL-01',
            'name' => 'Kitchen Bowl',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'HOT-PASS',
            'name' => 'Hot Pass',
            'output_mode' => 'Both',
            'printer_target' => 'printer://hot-pass',
        ]);
        $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
            'sort_order' => 10,
        ]);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $orderItemId = $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 2,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        $stations = $this->withHeaders($headers)->getJson('/api/v1/staff/kitchen/stations');

        $stations->assertOk()
            ->assertJsonPath('meta.realtime.topic', 'kitchen')
            ->assertJsonPath('meta.realtime.channel', 'staff.kitchen')
            ->assertJsonPath('meta.realtime.changes_uri', '/api/v1/staff/kitchen/changes');

        $beforeVersion = (int) $stations->json('meta.realtime.current_version');

        $dispatch = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-dispatch-1'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ]);

        $dispatch->assertOk()
            ->assertJsonPath('meta.action', 'kitchen_order_dispatched')
            ->assertJsonPath('meta.created_count', 1)
            ->assertJsonPath('meta.reused_count', 0)
            ->assertJsonPath('meta.unrouted_count', 0)
            ->assertJsonPath('meta.pinned_route_count', 0)
            ->assertJsonPath('data.0.order.order_id', $orderId)
            ->assertJsonPath('data.0.ticket_status', 'Queued')
            ->assertJsonPath('data.0.lifecycle.state_reason', 'awaiting_fire')
            ->assertJsonPath('data.0.lifecycle.allowed_actions.0', 'fire')
            ->assertJsonPath('data.0.reconciliation.sync_status', 'in_sync')
            ->assertJsonPath('data.0.reconciliation.routing_status', 'active_route')
            ->assertJsonPath('data.0.output_mode', 'Both')
            ->assertJsonPath('data.0.printer_target', 'printer://hot-pass');

        $ticketId = (int) $dispatch->json('data.0.ticket_id');

        self::assertSame('Active', (string) $this->table('reservation_orders')->where('order_id', $orderId)->value('status'));

        $changes = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/kitchen/changes?after_version='.$beforeVersion);

        $changes->assertOk()
            ->assertJsonPath('data.topic', 'kitchen')
            ->assertJsonPath('data.channel', 'staff.kitchen')
            ->assertJsonPath('data.has_changes', true);

        $fire = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-fire-1'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/fire');

        $fire->assertOk()
            ->assertJsonPath('meta.action', 'kitchen_ticket_fired')
            ->assertJsonPath('data.ticket_status', 'Fired')
            ->assertJsonPath('data.lifecycle.state_reason', 'in_preparation')
            ->assertJsonPath('data.order_item.status', 'InProgress');

        $orderRowVersion = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('row_version');
        $itemRowVersion = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('row_version');

        $bump = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-bump-1'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/bump');

        $bump->assertOk()
            ->assertJsonPath('meta.action', 'kitchen_ticket_bumped')
            ->assertJsonPath('data.ticket_status', 'Ready')
            ->assertJsonPath('data.lifecycle.allowed_actions.0', 'recall');

        $recall = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-recall-1'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/recall');

        $recall->assertOk()
            ->assertJsonPath('meta.action', 'kitchen_ticket_recalled')
            ->assertJsonPath('data.ticket_status', 'Fired')
            ->assertJsonPath('data.recall_count', 1);

        $served = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-order-item-served-via-kitchen'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/items/'.$orderItemId.'/status', [
                'order_row_version' => $orderRowVersion,
                'row_version' => $itemRowVersion,
                'status' => 'Served',
            ]);

        $served->assertOk()
            ->assertJsonPath('meta.status', 'Served')
            ->assertJsonPath('data.items.0.status', 'Served');

        $tickets = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/kitchen/stations/'.$stationId.'/tickets?include_terminal=1');

        $tickets->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.ticket_id', $ticketId)
            ->assertJsonPath('data.0.ticket_status', 'Completed')
            ->assertJsonPath('data.0.lifecycle.is_terminal', true)
            ->assertJsonPath('data.0.reconciliation.sync_status', 'in_sync')
            ->assertJsonPath('data.0.routing.route_present', true)
            ->assertJsonPath('data.0.route.is_active', true)
            ->assertJsonPath('data.0.order_item.status', 'Served');
    }

    public function test_ticket_must_be_fired_before_it_can_be_bumped(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-bump-guard-key');

        $categoryId = $this->ensureMenuCategory('Kitchen Bump Guard');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'KDS-BUMP-01',
            'name' => 'Bump Guard Bowl',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'BUMP-GUARD',
            'name' => 'Bump Guard',
            'output_mode' => 'KDS',
        ]);
        $routeId = $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
            'sort_order' => 10,
        ]);

        $orderItemId = $this->createOrderItem([
            'item_id' => $itemId,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);
        $ticketId = $this->createKitchenOrderTicket([
            'station_id' => $stationId,
            'route_id' => $routeId,
            'order_item_id' => $orderItemId,
            'item_id' => $itemId,
            'category_id' => $categoryId,
            'ticket_status' => 'Queued',
        ]);

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-bump-before-fire'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/bump')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ticket_id']);

        self::assertSame('Queued', (string) $this->table('kitchen_order_item_tickets')->where('ticket_id', $ticketId)->value('ticket_status'));
        self::assertSame('Ordered', (string) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('status'));
    }

    public function test_dispatch_skips_unrouted_items_without_breaking_order(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-unrouted-key');

        $mappedCategoryId = $this->ensureMenuCategory('Mapped Kitchen');
        $unroutedCategoryId = $this->ensureMenuCategory('Unrouted Kitchen');
        $stationId = $this->createKitchenStation();
        $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $mappedCategoryId,
        ]);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);

        $mappedItemId = $this->createMenuItem(['category_id' => $mappedCategoryId]);
        $unroutedItemId = $this->createMenuItem(['category_id' => $unroutedCategoryId]);

        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $mappedItemId,
            'status' => 'Ordered',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $unroutedItemId,
            'status' => 'Ordered',
        ]);

        $dispatch = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-dispatch-unrouted'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ]);

        $dispatch->assertOk()
            ->assertJsonPath('meta.created_count', 1)
            ->assertJsonPath('meta.unrouted_count', 1);

        self::assertSame(1, (int) $this->table('kitchen_order_item_tickets')->where('order_id', $orderId)->count());
        self::assertSame(2, (int) $this->table('reservation_order_items')->where('order_id', $orderId)->count());
        self::assertSame('Active', (string) $this->table('reservation_orders')->where('order_id', $orderId)->value('status'));
    }

    public function test_dispatch_with_only_unrouted_items_emits_change_feed_signal_without_creating_tickets(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-all-unrouted-key');

        $unroutedCategoryId = $this->ensureMenuCategory('All Unrouted Kitchen');
        $unroutedItemId = $this->createMenuItem([
            'category_id' => $unroutedCategoryId,
            'code' => 'KDS-UNROUTED-ONLY',
            'name' => 'Unrouted Only Bowl',
        ]);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $unroutedItemId,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        $beforeVersion = (int) $this->withHeaders($headers)
            ->getJson('/api/v1/staff/kitchen/changes')
            ->assertOk()
            ->json('data.current_version');

        $dispatch = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-dispatch-unrouted-only'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ]);

        $dispatch->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.created_count', 0)
            ->assertJsonPath('meta.reused_count', 0)
            ->assertJsonPath('meta.unrouted_count', 1)
            ->assertJsonPath('meta.pinned_route_count', 0);

        $changes = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/kitchen/changes?after_version='.$beforeVersion);

        $changes->assertOk()
            ->assertJsonPath('data.topic', 'kitchen')
            ->assertJsonPath('data.has_changes', true);

        $event = collect($changes->json('data.events'))->firstWhere('type', 'kitchen.order_dispatched');
        self::assertIsArray($event);
        self::assertSame($orderId, (int) data_get($event, 'payload.order_id'));
        self::assertSame($reservationId, (int) data_get($event, 'payload.reservation_id'));
        self::assertSame([], array_values((array) data_get($event, 'payload.ticket_ids', [])));
        self::assertSame(0, (int) data_get($event, 'payload.created_count'));
        self::assertSame(1, (int) data_get($event, 'payload.unrouted_count'));
        self::assertSame(['kitchen'], array_values((array) data_get($event, 'refresh_targets', [])));

        self::assertSame(0, (int) $this->table('kitchen_order_item_tickets')->where('order_id', $orderId)->count());
        self::assertSame('Active', (string) $this->table('reservation_orders')->where('order_id', $orderId)->value('status'));
    }

    public function test_cancelled_order_item_after_dispatch_marks_ticket_cancelled_without_silent_drift(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-cancelled-after-dispatch-key');

        $categoryId = $this->ensureMenuCategory('Kitchen Cancel Sync');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'KDS-CANCEL-01',
            'name' => 'Cancel Sync Bowl',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'CANCEL-SYNC',
            'name' => 'Cancel Sync',
            'output_mode' => 'KDS',
        ]);
        $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
            'sort_order' => 10,
        ]);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $orderItemId = $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        $dispatch = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-cancel-sync-dispatch'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ]);

        $dispatch->assertOk()
            ->assertJsonPath('data.0.ticket_status', 'Queued');

        $ticketId = (int) $dispatch->json('data.0.ticket_id');
        $orderRowVersion = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('row_version');
        $itemRowVersion = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('row_version');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-cancel-sync-status'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/items/'.$orderItemId.'/status', [
                'order_row_version' => $orderRowVersion,
                'row_version' => $itemRowVersion,
                'status' => 'Cancelled',
            ])
            ->assertOk()
            ->assertJsonPath('meta.status', 'Cancelled');

        $tickets = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/kitchen/stations/'.$stationId.'/tickets?include_terminal=1');

        $tickets->assertOk()
            ->assertJsonPath('data.0.ticket_id', $ticketId)
            ->assertJsonPath('data.0.ticket_status', 'Cancelled')
            ->assertJsonPath('data.0.lifecycle.state_reason', 'order_item_cancelled')
            ->assertJsonPath('data.0.reconciliation.sync_status', 'in_sync')
            ->assertJsonPath('data.0.reconciliation.order_item_expected_status', 'Cancelled')
            ->assertJsonPath('data.0.reconciliation.order_item_matches_ticket', true);
    }

    public function test_kitchen_station_reads_can_be_scoped_to_branch_workload(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-branch-scope-key');
        $branchA = $this->createBranch(['branch_code' => 'KITCHA', 'branch_name' => 'Kitchen A']);
        $branchB = $this->createBranch(['branch_code' => 'KITCHB', 'branch_name' => 'Kitchen B']);
        config()->set('staff_capabilities.role_branch_scopes.Staff', ['default', (string) $branchA]);

        $categoryId = $this->ensureMenuCategory('Kitchen Branch Scope');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'KDS-BRANCH-01',
            'name' => 'Kitchen Branch Bowl',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'BRANCH-PASS',
            'name' => 'Branch Pass',
            'output_mode' => 'Both',
        ]);
        $routeId = $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
            'sort_order' => 10,
        ]);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationA = $this->createReservation([
            'branch_id' => $branchA,
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $reservationB = $this->createReservation([
            'branch_id' => $branchB,
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $orderA = $this->createOrder([
            'reservation_id' => $reservationA,
            'status' => 'Active',
        ]);
        $orderB = $this->createOrder([
            'reservation_id' => $reservationB,
            'status' => 'Active',
        ]);
        $orderItemA = $this->createOrderItem([
            'order_id' => $orderA,
            'item_id' => $itemId,
            'status' => 'Ordered',
        ]);
        $orderItemB = $this->createOrderItem([
            'order_id' => $orderB,
            'item_id' => $itemId,
            'status' => 'Ordered',
        ]);

        $ticketA = $this->createKitchenOrderTicket([
            'station_id' => $stationId,
            'order_id' => $orderA,
            'reservation_id' => $reservationA,
            'order_item_id' => $orderItemA,
            'item_id' => $itemId,
            'category_id' => $categoryId,
            'route_id' => $routeId,
            'ticket_status' => 'Queued',
            'first_dispatched_at' => $this->nowUtc(),
        ]);
        $ticketB = $this->createKitchenOrderTicket([
            'station_id' => $stationId,
            'order_id' => $orderB,
            'reservation_id' => $reservationB,
            'order_item_id' => $orderItemB,
            'item_id' => $itemId,
            'category_id' => $categoryId,
            'route_id' => $routeId,
            'ticket_status' => 'Ready',
            'first_dispatched_at' => $this->nowUtc(),
            'ready_at' => $this->nowUtc(),
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/kitchen/stations?branch_id='.$branchA)
            ->assertOk()
            ->assertJsonPath('meta.branch_id', $branchA)
            ->assertJsonPath('meta.branch_scope.requested_branch_id', $branchA)
            ->assertJsonPath('meta.branch_scope.uses_explicit_entitlement', true)
            ->assertJsonPath('data.0.ticket_counts.queued', 1)
            ->assertJsonPath('data.0.ticket_counts.ready', 0);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/kitchen/stations/'.$stationId.'/tickets?branch_id='.$branchA.'&include_terminal=1')
            ->assertOk()
            ->assertJsonPath('meta.branch_id', $branchA)
            ->assertJsonPath('meta.branch_scope.requested_branch_id', $branchA)
            ->assertJsonPath('meta.branch_scope.uses_explicit_entitlement', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ticket_id', $ticketA);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/kitchen/stations?branch_id='.$branchB)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('message', 'Branch not found.');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-branch-scope-deny'))
            ->postJson('/api/v1/staff/orders/'.$orderB.'/kitchen/dispatch', [
                'row_version' => 1,
            ])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('message', 'Order not found.');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-ticket-branch-scope-deny'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketB.'/fire')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('message', 'Kitchen ticket not found.');
    }

    public function test_branch_flag_can_disable_kitchen_dispatch_mutations(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'KITCHOFF',
            'branch_name' => 'Kitchen Dispatch Off',
        ]);
        $this->upsertFeatureFlagOverride(
            'staff.kitchen_dispatch',
            false,
            'testing',
            $branchId,
            ['reason' => 'branch still on manual expo'],
        );

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-feature-flag-key');
        config()->set('staff_capabilities.role_branch_scopes.Staff', ['default', (string) $branchId]);
        $categoryId = $this->ensureMenuCategory('Kitchen Disabled');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'KDS-OFF-01',
            'name' => 'Kitchen Disabled Bowl',
        ]);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable([
            'branch_id' => $branchId,
            'status' => 'Occupied',
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-dispatch-disabled'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['feature_flag']);
    }

    public function test_branch_flag_blocks_new_dispatch_but_existing_tickets_can_finish_service(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'KITCHFIREOFF',
            'branch_name' => 'Kitchen Fire Off',
        ]);
        $this->upsertFeatureFlagOverride(
            'staff.kitchen_dispatch',
            false,
            'testing',
            $branchId,
            ['reason' => 'kitchen terminal rollout paused'],
        );

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-fire-flag-off-key');
        config()->set('staff_capabilities.role_branch_scopes.Staff', ['default', (string) $branchId]);

        $categoryId = $this->ensureMenuCategory('Kitchen Fire Flagged');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'KDS-FLAG-FIRE-01',
            'name' => 'Kitchen Fire Flag Bowl',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'FLAG-FIRE',
            'name' => 'Flag Fire',
            'output_mode' => 'KDS',
        ]);
        $routeId = $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
            'sort_order' => 10,
        ]);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable([
            'branch_id' => $branchId,
            'status' => 'Occupied',
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $orderItemId = $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);
        $ticketId = $this->createKitchenOrderTicket([
            'station_id' => $stationId,
            'order_id' => $orderId,
            'reservation_id' => $reservationId,
            'order_item_id' => $orderItemId,
            'item_id' => $itemId,
            'category_id' => $categoryId,
            'route_id' => $routeId,
            'ticket_status' => 'Queued',
            'first_dispatched_at' => $this->nowUtc(),
        ]);

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-fire-flag-disabled'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/fire')
            ->assertOk()
            ->assertJsonPath('data.ticket_status', 'Fired');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-bump-flag-disabled'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/bump')
            ->assertOk()
            ->assertJsonPath('data.ticket_status', 'Ready');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-recall-flag-disabled'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/recall')
            ->assertOk()
            ->assertJsonPath('data.ticket_status', 'Fired');

        $blockedOrderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $blockedOrderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-dispatch-flag-disabled-new-order'))
            ->postJson('/api/v1/staff/orders/'.$blockedOrderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['feature_flag']);

        self::assertSame('Fired', (string) $this->table('kitchen_order_item_tickets')->where('ticket_id', $ticketId)->value('ticket_status'));
        self::assertSame('InProgress', (string) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('status'));
        self::assertSame(0, (int) $this->table('kitchen_order_item_tickets')->where('order_id', $blockedOrderId)->count());
    }

    public function test_redispatch_preserves_ready_ticket_state_and_fire_requires_a_queued_ticket(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-ready-guard-key');

        $categoryId = $this->ensureMenuCategory('Kitchen Ready Guard');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'KDS-READY-01',
            'name' => 'Ready Guard Bowl',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'READY-PASS',
            'name' => 'Ready Pass',
            'output_mode' => 'KDS',
        ]);
        $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
            'sort_order' => 10,
        ]);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        $dispatch = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-ready-dispatch-a'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ]);

        $dispatch->assertOk()
            ->assertJsonPath('meta.created_count', 1)
            ->assertJsonPath('data.0.ticket_status', 'Queued');

        $ticketId = (int) $dispatch->json('data.0.ticket_id');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-ready-fire-a'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/fire')
            ->assertOk()
            ->assertJsonPath('data.ticket_status', 'Fired');

        $bump = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-ready-bump-a'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/bump');

        $bump->assertOk()
            ->assertJsonPath('data.ticket_status', 'Ready');

        $readyAt = (string) $this->table('kitchen_order_item_tickets')->where('ticket_id', $ticketId)->value('ready_at');

        $redispatch = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-ready-dispatch-b'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ]);

        $redispatch->assertOk()
            ->assertJsonPath('meta.created_count', 0)
            ->assertJsonPath('meta.reused_count', 1)
            ->assertJsonPath('meta.pinned_route_count', 1)
            ->assertJsonPath('data.0.ticket_id', $ticketId)
            ->assertJsonPath('data.0.ticket_status', 'Ready');

        self::assertSame('Ready', (string) $this->table('kitchen_order_item_tickets')->where('ticket_id', $ticketId)->value('ticket_status'));
        self::assertSame($readyAt, (string) $this->table('kitchen_order_item_tickets')->where('ticket_id', $ticketId)->value('ready_at'));

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-ready-fire-b'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/fire')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ticket_id']);

        self::assertSame('Ready', (string) $this->table('kitchen_order_item_tickets')->where('ticket_id', $ticketId)->value('ticket_status'));
    }

    public function test_active_ticket_route_cannot_be_removed_and_repeat_dispatch_keeps_existing_ticket_routing_snapshot(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-route-guard-key');

        $categoryId = $this->ensureMenuCategory('Kitchen Guard');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'KDS-GUARD-01',
            'name' => 'Kitchen Guard Bowl',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'GUARD-OLD',
            'name' => 'Guard Old',
            'output_mode' => 'Printer',
            'printer_target' => 'printer://guard-old',
        ]);
        $routeId = $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
            'sort_order' => 10,
        ]);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        $dispatch = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-dispatch-guard-a'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ]);

        $dispatch->assertOk()
            ->assertJsonPath('meta.created_count', 1)
            ->assertJsonPath('data.0.station.station_id', $stationId)
            ->assertJsonPath('data.0.route.route_id', $routeId)
            ->assertJsonPath('data.0.printer_target', 'printer://guard-old');

        $ticketId = (int) $dispatch->json('data.0.ticket_id');

        $this->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/fire', [], $this->withIdempotencyKey($headers, 'idem-staff-kitchen-fire-guard-a'))
            ->assertOk();

        $service = app(KitchenRoutingService::class);

        try {
            $service->syncStationRoutes($stationId, []);
            $this->fail('Expected validation exception when removing a kitchen route with active tickets.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('routes', $exception->errors());
        }

        $this->assertSame($routeId, (int) $this->table('kitchen_order_item_tickets')->where('ticket_id', $ticketId)->value('route_id'));
        $this->assertSame(1, (int) $this->table('kitchen_station_category_routes')->where('route_id', $routeId)->count());

        $redispatch = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-dispatch-guard-b'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ]);

        $redispatch->assertOk()
            ->assertJsonPath('meta.created_count', 0)
            ->assertJsonPath('meta.reused_count', 1)
            ->assertJsonPath('meta.pinned_route_count', 1)
            ->assertJsonPath('data.0.ticket_id', $ticketId)
            ->assertJsonPath('data.0.station.station_id', $stationId)
            ->assertJsonPath('data.0.route.route_id', $routeId)
            ->assertJsonPath('data.0.output_mode', 'Printer')
            ->assertJsonPath('data.0.printer_target', 'printer://guard-old');
    }

    public function test_repeat_dispatch_rejects_active_ticket_with_drifted_route_snapshot(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-drifted-redispatch-key');

        $categoryId = $this->ensureMenuCategory('Kitchen Drifted Route');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'KDS-DRIFT-01',
            'name' => 'Drifted Route Bowl',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'DRIFT-PASS',
            'name' => 'Drift Pass',
            'output_mode' => 'KDS',
        ]);
        $routeId = $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
            'sort_order' => 10,
        ]);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        $dispatch = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-drift-dispatch-a'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ]);

        $dispatch->assertOk()
            ->assertJsonPath('data.0.route.route_id', $routeId);

        DB::table('kitchen_station_category_routes')
            ->where('route_id', $routeId)
            ->update([
                'is_active' => 0,
                'updated_at' => $this->nowUtc(),
            ]);

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-drift-dispatch-b'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ticket_id']);

        self::assertSame(0, (int) $this->table('kitchen_station_category_routes')->where('route_id', $routeId)->value('is_active'));
    }

    public function test_generic_ticket_sync_keeps_ready_state_until_explicit_recall(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-sync-ready-key');

        $categoryId = $this->ensureMenuCategory('Kitchen Sync Ready');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'KDS-SYNC-READY-01',
            'name' => 'Sync Ready Bowl',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'SYNC-READY',
            'name' => 'Sync Ready',
            'output_mode' => 'Both',
            'printer_target' => 'printer://sync-ready',
        ]);
        $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
            'sort_order' => 10,
        ]);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $orderItemId = $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        $dispatch = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-sync-ready-dispatch'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ]);
        $ticketId = (int) $dispatch->json('data.0.ticket_id');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-sync-ready-fire'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/fire')
            ->assertOk()
            ->assertJsonPath('data.ticket_status', 'Fired')
            ->assertJsonPath('data.order_item.status', 'InProgress');

        $bump = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-sync-ready-bump'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/bump');

        $bump->assertOk()
            ->assertJsonPath('data.ticket_status', 'Ready')
            ->assertJsonPath('data.order_item.status', 'InProgress');

        $readyAt = (string) $this->table('kitchen_order_item_tickets')->where('ticket_id', $ticketId)->value('ready_at');
        $beforeVersion = (int) $this->withHeaders($headers)
            ->getJson('/api/v1/staff/kitchen/changes')
            ->assertOk()
            ->json('data.current_version');

        $ticket = app(KitchenRoutingService::class)->syncTicketForOrderItem($orderItemId, $staffId);

        self::assertNotNull($ticket);
        self::assertSame('Ready', (string) ($ticket->ticket_status?->value ?? $ticket->ticket_status));
        self::assertNotNull($ticket->ready_at);
        self::assertSame($readyAt, (string) $this->table('kitchen_order_item_tickets')->where('ticket_id', $ticketId)->value('ready_at'));
        self::assertSame('InProgress', (string) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('status'));
        self::assertSame('Ready', (string) $this->table('kitchen_order_item_tickets')->where('ticket_id', $ticketId)->value('ticket_status'));

        $changes = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/kitchen/changes?after_version='.$beforeVersion);

        $changes->assertOk()
            ->assertJsonPath('data.has_changes', false)
            ->assertJsonCount(0, 'data.events');
    }

    public function test_completed_ticket_cannot_be_recalled_and_order_item_state_stays_served(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-terminal-recall-key');

        $categoryId = $this->ensureMenuCategory('Kitchen Terminal Recall');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'KDS-TERM-RECALL-01',
            'name' => 'Terminal Recall Bowl',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'TERM-RECALL',
            'name' => 'Terminal Recall',
            'output_mode' => 'KDS',
        ]);
        $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
            'sort_order' => 10,
        ]);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $orderItemId = $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        $dispatch = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-terminal-dispatch'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/kitchen/dispatch', [
                'row_version' => 1,
            ]);
        $ticketId = (int) $dispatch->json('data.0.ticket_id');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-terminal-fire'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/fire')
            ->assertOk()
            ->assertJsonPath('data.order_item.status', 'InProgress');

        $orderRowVersion = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('row_version');
        $itemRowVersion = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('row_version');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-terminal-serve'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/items/'.$orderItemId.'/status', [
                'order_row_version' => $orderRowVersion,
                'row_version' => $itemRowVersion,
                'status' => 'Served',
            ])
            ->assertOk()
            ->assertJsonPath('meta.status', 'Served');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-kitchen-terminal-recall'))
            ->postJson('/api/v1/staff/kitchen/tickets/'.$ticketId.'/recall')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ticket_id']);

        self::assertSame('Completed', (string) $this->table('kitchen_order_item_tickets')->where('ticket_id', $ticketId)->value('ticket_status'));
        self::assertSame('Served', (string) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('status'));
    }

    public function test_reconciliation_scan_reports_status_and_routing_drift_with_actionable_output(): void
    {
        $categoryId = $this->ensureMenuCategory('Kitchen Reconcile Drift');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'KDS-RECON-01',
            'name' => 'Reconcile Drift Bowl',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'RECON-PASS',
            'name' => 'Recon Pass',
            'output_mode' => 'KDS',
        ]);
        $routeId = $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
            'sort_order' => 10,
        ]);

        $orderItemId = $this->createOrderItem([
            'item_id' => $itemId,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);
        $ticketId = $this->createKitchenOrderTicket([
            'station_id' => $stationId,
            'route_id' => $routeId,
            'order_item_id' => $orderItemId,
            'item_id' => $itemId,
            'category_id' => $categoryId,
            'ticket_status' => 'Fired',
            'fired_at' => $this->nowUtc(),
        ]);

        DB::table('kitchen_station_category_routes')
            ->where('route_id', $routeId)
            ->update([
                'is_active' => 0,
                'updated_at' => $this->nowUtc(),
            ]);

        $report = app(KitchenTicketReconciliationService::class)->scan([
            'station_id' => $stationId,
            'include_terminal' => true,
        ]);

        self::assertSame(1, (int) $report['checked_count']);
        self::assertSame(1, (int) $report['drift_count']);
        self::assertSame(1, (int) $report['status_drift_count']);
        self::assertSame(1, (int) $report['routing_drift_count']);
        self::assertSame($ticketId, (int) data_get($report, 'tickets.0.ticket_id'));
        self::assertSame('drift_detected', data_get($report, 'tickets.0.sync_status'));
        self::assertSame('route_inactive', data_get($report, 'tickets.0.routing_status'));
        self::assertContains('order_item_ticket_mismatch', (array) data_get($report, 'tickets.0.drift_reasons', []));
        self::assertContains('review_station_routes', (array) data_get($report, 'tickets.0.next_actions', []));
    }

    public function test_missing_kitchen_resources_return_standardized_not_found_envelope(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-kitchen-missing-resource-key');

        $this->withHeaders(array_merge($headers, [
            'X-Request-Id' => 'req-staff-kitchen-station-404',
        ]))
            ->getJson('/api/v1/staff/kitchen/stations/999999/tickets')
            ->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-staff-kitchen-station-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('request_id', 'req-staff-kitchen-station-404');

        $dispatchHeaders = $this->withIdempotencyKey(array_merge($headers, [
            'X-Request-Id' => 'req-staff-kitchen-order-404',
        ]), 'idem-staff-kitchen-order-404');

        $this->withHeaders($dispatchHeaders)
            ->postJson('/api/v1/staff/orders/999999/kitchen/dispatch', [
                'row_version' => 1,
            ])
            ->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-staff-kitchen-order-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('request_id', 'req-staff-kitchen-order-404');

        $fireHeaders = $this->withIdempotencyKey(array_merge($headers, [
            'X-Request-Id' => 'req-staff-kitchen-ticket-404',
        ]), 'idem-staff-kitchen-ticket-404');

        $this->withHeaders($fireHeaders)
            ->postJson('/api/v1/staff/kitchen/tickets/999999/fire')
            ->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-staff-kitchen-ticket-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('request_id', 'req-staff-kitchen-ticket-404');
    }

    private function table(string $table)
    {
        return DB::table($table);
    }
}

