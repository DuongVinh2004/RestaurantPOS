<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Services\LoyaltyPointsService;
use App\Services\NotificationOutboxService;
use App\Services\ReservationCodeGenerator;
use App\Services\ReservationFinancialSyncService;
use App\Services\ReservationLockService;
use App\Services\ReservationService;
use App\Services\RestaurantTableStateService;
use App\Services\RuntimeSettingService;
use App\Services\Staff\StaffCheckInService;
use App\Services\Staff\StaffOperationalRealtimeService;
use App\Services\Staff\StaffWaitingListService;
use App\Services\TableHoldService;
use App\Services\TableTimeConflictService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffOperationalRealtimeFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();

        config()->set('booking.realtime.enabled', true);
        config()->set('booking.realtime.cache_store', 'array');
        config()->set('booking.realtime.recent_event_limit', 50);
        config()->set('booking.realtime.poll_hint_ms', 1500);
        config()->set('booking.require_redis_for_booking_api', false);

        Cache::store('array')->flush();

        $this->bindRuntime();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function bindRuntime(): void
    {
        $locks = $this->mockReservationLocks();
        $notifications = $this->mockNotificationOutbox();
        $runtime = $this->mockRuntimeSettings();
        $tableState = new RestaurantTableStateService();
        $conflicts = new TableTimeConflictService();
        $financialSync = new ReservationFinancialSyncService();
        $loyalty = new LoyaltyPointsService($financialSync, $runtime);
        $tableHoldService = new TableHoldService($locks, $tableState, $conflicts, $runtime);
        $reservationService = new ReservationService(
            $tableHoldService,
            $locks,
            new ReservationCodeGenerator(),
            $notifications,
            $loyalty,
            $tableState,
            $conflicts,
            $financialSync,
        );
        $checkInService = new StaffCheckInService($locks, $notifications, $tableState, $conflicts, $runtime);
        $waitingListService = new StaffWaitingListService($notifications, $locks, $reservationService, $checkInService, $runtime);

        $this->app->instance(NotificationOutboxService::class, $notifications);
        $this->app->instance(ReservationLockService::class, $locks);
        $this->app->instance(RuntimeSettingService::class, $runtime);
        $this->app->instance(RestaurantTableStateService::class, $tableState);
        $this->app->instance(TableTimeConflictService::class, $conflicts);
        $this->app->instance(StaffWaitingListService::class, $waitingListService);
    }

    public function test_change_feed_trims_to_retention_window_and_discards_other_topic_history(): void
    {
        config()->set('booking.realtime.recent_event_limit', 10);

        $events = [];
        for ($version = 1; $version <= 12; $version++) {
            $events[] = [
                'topic' => StaffOperationalRealtimeService::TOPIC_BOARD,
                'channel' => 'staff.board',
                'version' => $version,
                'type' => 'reservation.updated',
                'occurred_at' => Carbon::parse('2026-04-05 12:00:00', 'UTC')->addSeconds($version)->toIso8601String(),
                'refresh_targets' => ['board'],
                'payload' => ['reservation_id' => $version],
            ];
        }

        $events[] = [
            'topic' => StaffOperationalRealtimeService::TOPIC_WAITING_LIST,
            'channel' => 'staff.waiting_list',
            'version' => 99,
            'type' => 'waiting_list.notified',
            'occurred_at' => Carbon::parse('2026-04-05 12:30:00', 'UTC')->toIso8601String(),
            'refresh_targets' => ['waiting_list'],
            'payload' => ['waiting_id' => 99],
        ];

        Cache::store('array')->forever('booking:realtime:board:events', $events);
        Cache::store('array')->forever('booking:realtime:board:version', 12);

        $snapshot = app(StaffOperationalRealtimeService::class)->readTopic(
            StaffOperationalRealtimeService::TOPIC_BOARD,
            1,
            20,
        );

        self::assertSame(12, (int) $snapshot['current_version']);
        self::assertSame(3, (int) $snapshot['oldest_available_version']);
        self::assertTrue((bool) $snapshot['stale_cursor']);
        self::assertCount(10, (array) $snapshot['events']);
        self::assertSame(range(3, 12), array_map(static fn (array $event): int => (int) ($event['version'] ?? 0), (array) $snapshot['events']));
        self::assertNull(collect($snapshot['events'])->firstWhere('topic', StaffOperationalRealtimeService::TOPIC_WAITING_LIST));
    }

    public function test_publish_reconciles_cached_version_with_existing_history_before_appending_event(): void
    {
        Cache::store('array')->forever('booking:realtime:board:events', [
            [
                'topic' => StaffOperationalRealtimeService::TOPIC_BOARD,
                'channel' => 'staff.board',
                'version' => 4,
                'type' => 'reservation.created',
                'occurred_at' => Carbon::parse('2026-04-05 13:00:00', 'UTC')->toIso8601String(),
                'refresh_targets' => ['board'],
                'payload' => ['reservation_id' => 4],
            ],
            [
                'topic' => StaffOperationalRealtimeService::TOPIC_BOARD,
                'channel' => 'staff.board',
                'version' => 5,
                'type' => 'reservation.checked_in',
                'occurred_at' => Carbon::parse('2026-04-05 13:01:00', 'UTC')->toIso8601String(),
                'refresh_targets' => ['board', 'timeline'],
                'payload' => ['reservation_id' => 5],
            ],
        ]);
        Cache::store('array')->forever('booking:realtime:board:version', 1);

        $service = app(StaffOperationalRealtimeService::class);
        $event = $service->publishBoardEvent('reservation.board_assignment_committed', [
            'reservation_id' => 6,
            'table_id' => 9,
        ], ['board', 'timeline']);

        self::assertNotNull($event);
        self::assertSame(6, (int) ($event['version'] ?? 0));

        $snapshot = $service->readTopic(StaffOperationalRealtimeService::TOPIC_BOARD, 0, 20);

        self::assertSame(6, (int) $snapshot['current_version']);
        self::assertSame([4, 5, 6], array_map(static fn (array $row): int => (int) ($row['version'] ?? 0), (array) $snapshot['events']));
    }

    public function test_board_endpoint_exposes_realtime_meta_and_assignment_emits_board_change_event(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $staffHeaders = $this->staffAuthHeaders($staffId);
        $now = $this->nowUtc()->copy()->setTime(11, 0);
        Carbon::setTestNow($now);

        $tableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'REALTIME-04',
            'zone' => 'Main',
            'status' => 'Available',
        ]);
        $reservationId = $this->createReservation([
            'start_time' => $now->copy()->addMinutes(20),
            'end_time' => $now->copy()->addHours(2),
            'guest_count' => 4,
            'status' => 'Confirmed',
            'row_version' => 1,
        ]);

        $board = $this->withHeaders($staffHeaders)->getJson(sprintf(
            '/api/v1/staff/tables/board?from=%s&to=%s',
            urlencode($now->copy()->addMinutes(20)->toIso8601String()),
            urlencode($now->copy()->addHours(2)->toIso8601String()),
        ));

        $board->assertOk()
            ->assertJsonPath('meta.realtime.enabled', true)
            ->assertJsonPath('meta.realtime.topic', 'board')
            ->assertJsonPath('meta.realtime.channel', 'staff.board')
            ->assertJsonPath('meta.realtime.changes_uri', '/api/v1/staff/tables/board/changes')
            ->assertJsonPath('meta.realtime.polling_compatible', true);

        $beforeVersion = (int) $board->json('meta.realtime.current_version');

        $assign = $this->withHeaders($this->withIdempotencyKey('staff-realtime-board-assign', $staffHeaders))
            ->postJson('/api/v1/staff/reservations/' . $reservationId . '/assign-table', [
                'table_id' => $tableId,
                'row_version' => 1,
            ]);

        $assign->assertOk()->assertJsonPath('data.table_ids.0', $tableId);

        $changes = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $beforeVersion);

        $changes->assertOk()
            ->assertJsonPath('data.topic', 'board')
            ->assertJsonPath('data.channel', 'staff.board')
            ->assertJsonPath('data.has_changes', true);

        $event = collect($changes->json('data.events'))->firstWhere('type', 'reservation.board_assignment_committed');
        self::assertIsArray($event);
        self::assertSame($reservationId, (int) data_get($event, 'payload.reservation_id'));
        self::assertSame($tableId, (int) data_get($event, 'payload.table_id'));
        self::assertSame(['board', 'timeline'], array_values((array) data_get($event, 'refresh_targets', [])));
    }

    public function test_waiting_list_notify_and_customer_arrival_emit_change_feed_events(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'full_name' => 'Realtime Waiting Customer',
            'phone' => '0909444001',
        ]);
        $customer = \App\Models\User::query()->findOrFail($customerId);
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry([
            'user_id' => $customerId,
            'guest_name' => 'Realtime Waiting Customer',
            'phone' => '0909444001',
            'guest_count' => 4,
            'status' => 'Waiting',
            'row_version' => 1,
        ]);

        $queue = $this->withHeaders($staffHeaders)->getJson('/api/v1/staff/waiting-list?active_only=1');
        $queue->assertOk()
            ->assertJsonPath('meta.realtime.enabled', true)
            ->assertJsonPath('meta.realtime.topic', 'waiting_list')
            ->assertJsonPath('meta.realtime.channel', 'staff.waiting_list')
            ->assertJsonPath('meta.realtime.changes_uri', '/api/v1/staff/waiting-list/changes');

        $waitingBeforeVersion = (int) $queue->json('meta.realtime.current_version');

        $boardBefore = $this->withHeaders($staffHeaders)->getJson('/api/v1/staff/tables/board/changes');
        $boardBefore->assertOk();
        $boardBeforeVersion = (int) $boardBefore->json('data.current_version');

        $notify = $this->withHeaders($this->withIdempotencyKey('staff-realtime-waiting-notify', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list/' . $waitingId . '/notify', [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);

        $notify->assertOk()->assertJsonPath('data.status', 'Notified');

        $confirm = $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => 'customer-realtime-confirm-arrival'])
            ->postJson('/api/v1/waiting-list/' . $waitingId . '/confirm-arrival', [
                'row_version' => (int) $notify->json('data.row_version'),
            ]);

        $confirm->assertOk()
            ->assertJsonPath('meta.action', 'await_staff_seating')
            ->assertJsonPath('meta.staff_seat_required', true);

        $waitingChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/waiting-list/changes?after_version=' . $waitingBeforeVersion);

        $waitingChanges->assertOk()->assertJsonPath('data.has_changes', true);
        $waitingEvents = collect($waitingChanges->json('data.events'));

        self::assertNotNull($waitingEvents->firstWhere('type', 'waiting_list.notified'));
        self::assertNotNull($waitingEvents->firstWhere('type', 'waiting_list.customer_arrival_confirmed'));

        $boardChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $boardBeforeVersion);

        $boardChanges->assertOk()->assertJsonPath('data.has_changes', true);
        $boardEvents = collect($boardChanges->json('data.events'));

        self::assertNotNull($boardEvents->firstWhere('type', 'waiting_list.notified'));
        self::assertNotNull($boardEvents->firstWhere('type', 'waiting_list.customer_arrival_confirmed'));
    }

    public function test_waiting_list_advance_route_emits_queue_advanced_change_event(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $sourceCustomerId = $this->createUser([
            'role_name' => 'Customer',
            'full_name' => 'Advance Source',
            'phone' => '0909555001',
        ]);
        $nextCustomerId = $this->createUser([
            'role_name' => 'Customer',
            'full_name' => 'Advance Next',
            'phone' => '0909555002',
        ]);
        $sourceCustomer = \App\Models\User::query()->findOrFail($sourceCustomerId);

        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $sourceWaitingId = $this->createWaitingListEntry([
            'user_id' => $sourceCustomerId,
            'guest_name' => 'Advance Source',
            'phone' => '0909555001',
            'guest_count' => 4,
            'status' => 'Waiting',
            'row_version' => 1,
        ]);
        $nextWaitingId = $this->createWaitingListEntry([
            'user_id' => $nextCustomerId,
            'guest_name' => 'Advance Next',
            'phone' => '0909555002',
            'guest_count' => 2,
            'status' => 'Waiting',
            'row_version' => 1,
            'requested_at' => $this->nowUtc()->copy()->addMinute(),
        ]);

        $waitingBefore = $this->withHeaders($staffHeaders)->getJson('/api/v1/staff/waiting-list/changes');
        $waitingBefore->assertOk();
        $beforeVersion = (int) $waitingBefore->json('data.current_version');

        $notify = $this->withHeaders($this->withIdempotencyKey('staff-realtime-advance-notify', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list/' . $sourceWaitingId . '/notify', [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notify->assertOk();

        $decline = $this->actingAs($sourceCustomer)
            ->withHeaders(['Idempotency-Key' => 'customer-realtime-advance-decline'])
            ->postJson('/api/v1/waiting-list/' . $sourceWaitingId . '/decline', [
                'row_version' => (int) $notify->json('data.row_version'),
            ]);
        $decline->assertOk();

        $advance = $this->withHeaders($this->withIdempotencyKey('staff-realtime-advance-run', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list/' . $sourceWaitingId . '/advance', [
                'row_version' => (int) $decline->json('data.row_version'),
            ]);

        $advance->assertOk()
            ->assertJsonPath('data.source_waiting_list.waiting_id', $sourceWaitingId)
            ->assertJsonPath('data.advanced_waiting_list.waiting_id', $nextWaitingId)
            ->assertJsonPath('data.automation.result', 'notified_next_candidate');

        $changes = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/waiting-list/changes?after_version=' . $beforeVersion);

        $changes->assertOk()->assertJsonPath('data.has_changes', true);
        $event = collect($changes->json('data.events'))->firstWhere('type', 'waiting_list.queue_advanced');
        self::assertIsArray($event);
        self::assertSame($sourceWaitingId, (int) data_get($event, 'payload.source_waiting_id'));
        self::assertSame($nextWaitingId, (int) data_get($event, 'payload.advanced_waiting_id'));
        self::assertSame('notified_next_candidate', (string) data_get($event, 'payload.result'));
    }

    public function test_settlement_finalize_emits_board_change_event_and_clears_live_table_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-22 12:00:00', 'UTC'));

        [$staffId, $tableId, $reservationId, $orderId] = $this->seedRealtimeCheckoutScenario('REALTIME-FINALIZE');
        $staffHeaders = $this->staffAuthHeaders($staffId, 'staff-realtime-finalize');

        $beforeVersion = (int) $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes')
            ->assertOk()
            ->json('data.current_version');

        $response = $this->withHeaders($this->withIdempotencyKey('staff-realtime-finalize-checkout', $staffHeaders))
            ->postJson("/api/v1/staff/orders/{$orderId}/settlement/finalize", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'paid_amount' => 100000,
                'currency' => 'VND',
                'transaction_code' => 'REALTIME-FINALIZE-1',
                'row_version' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.payment_status', 'Success')
            ->assertJsonPath('data.outstanding_amount', 0);

        self::assertSame('Completed', (string) $this->table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('Available', (string) $this->table('restaurant_tables')->where('table_id', $tableId)->value('status'));

        $this->withHeaders($staffHeaders)
            ->getJson("/api/v1/staff/tables/{$tableId}/active-order")
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');

        $changes = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $beforeVersion);

        $changes->assertOk()->assertJsonPath('data.has_changes', true);

        $event = collect($changes->json('data.events'))->firstWhere('type', 'reservation.settlement_completed');
        self::assertIsArray($event);
        self::assertSame($reservationId, (int) data_get($event, 'payload.reservation_id'));
        self::assertSame($orderId, (int) data_get($event, 'payload.order_id'));
        self::assertSame([$tableId], array_values((array) data_get($event, 'payload.table_ids', [])));
        self::assertSame('Completed', (string) data_get($event, 'payload.reservation_status'));
        self::assertSame(['board', 'timeline'], array_values((array) data_get($event, 'refresh_targets', [])));
    }

    public function test_pay_route_emits_board_change_event_when_payment_fully_settles_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-22 12:30:00', 'UTC'));

        [$staffId, $tableId, $reservationId, $orderId] = $this->seedRealtimeCheckoutScenario('REALTIME-PAY');
        $staffHeaders = $this->staffAuthHeaders($staffId, 'staff-realtime-pay');

        $beforeVersion = (int) $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes')
            ->assertOk()
            ->json('data.current_version');

        $response = $this->withHeaders($this->withIdempotencyKey('staff-realtime-pay-order', $staffHeaders))
            ->postJson("/api/v1/staff/orders/{$orderId}/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'paid_amount' => 100000,
                'currency' => 'VND',
                'transaction_code' => 'REALTIME-PAY-1',
                'row_version' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.status', 'Completed')
            ->assertJsonPath('data.payment_status', 'Success');

        self::assertSame('Completed', (string) $this->table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('Available', (string) $this->table('restaurant_tables')->where('table_id', $tableId)->value('status'));

        $changes = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $beforeVersion);

        $changes->assertOk()->assertJsonPath('data.has_changes', true);

        $event = collect($changes->json('data.events'))->firstWhere('type', 'reservation.settlement_completed');
        self::assertIsArray($event);
        self::assertSame($reservationId, (int) data_get($event, 'payload.reservation_id'));
        self::assertSame($orderId, (int) data_get($event, 'payload.order_id'));
        self::assertSame([$tableId], array_values((array) data_get($event, 'payload.table_ids', [])));
    }

    public function test_settlement_finalize_replay_does_not_emit_duplicate_board_change_event(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-22 12:15:00', 'UTC'));

        [$staffId, $tableId, $reservationId, $orderId] = $this->seedRealtimeCheckoutScenario('REALTIME-FINALIZE-REPLAY');
        $staffHeaders = $this->staffAuthHeaders($staffId, 'staff-realtime-finalize-replay');
        $headers = $this->withIdempotencyKey('staff-realtime-finalize-replay', $staffHeaders);

        $beforeVersion = (int) $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes')
            ->assertOk()
            ->json('data.current_version');

        $payload = [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 100000,
            'currency' => 'VND',
            'transaction_code' => 'REALTIME-FINALIZE-REPLAY-1',
            'row_version' => 1,
        ];

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/orders/{$orderId}/settlement/finalize", $payload)
            ->assertOk()
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.payment_status', 'Success')
            ->assertJsonPath('data.outstanding_amount', 0);

        $firstChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $beforeVersion);

        $firstChanges->assertOk()->assertJsonPath('data.has_changes', true);
        $afterFirstVersion = (int) $firstChanges->json('data.current_version');

        $event = collect($firstChanges->json('data.events'))->firstWhere('type', 'reservation.settlement_completed');
        self::assertIsArray($event);
        self::assertSame($reservationId, (int) data_get($event, 'payload.reservation_id'));
        self::assertSame($orderId, (int) data_get($event, 'payload.order_id'));
        self::assertSame([$tableId], array_values((array) data_get($event, 'payload.table_ids', [])));

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/orders/{$orderId}/settlement/finalize", $payload)
            ->assertOk()
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.payment_status', 'Success')
            ->assertJsonPath('data.outstanding_amount', 0);

        $replayChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $afterFirstVersion);

        $replayChanges->assertOk()
            ->assertJsonPath('data.has_changes', false)
            ->assertJsonCount(0, 'data.events');

        self::assertSame('Completed', (string) $this->table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('Available', (string) $this->table('restaurant_tables')->where('table_id', $tableId)->value('status'));
        self::assertSame(
            1,
            (int) $this->table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Final')
                ->where('idempotency_key', 'staff-realtime-finalize-replay')
                ->count()
        );
    }

    public function test_pay_route_replay_does_not_emit_duplicate_board_change_event(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-22 12:45:00', 'UTC'));

        [$staffId, $tableId, $reservationId, $orderId] = $this->seedRealtimeCheckoutScenario('REALTIME-PAY-REPLAY');
        $staffHeaders = $this->staffAuthHeaders($staffId, 'staff-realtime-pay-replay');
        $headers = $this->withIdempotencyKey('staff-realtime-pay-replay', $staffHeaders);

        $beforeVersion = (int) $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes')
            ->assertOk()
            ->json('data.current_version');

        $payload = [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 100000,
            'currency' => 'VND',
            'transaction_code' => 'REALTIME-PAY-REPLAY-1',
            'row_version' => 1,
        ];

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/orders/{$orderId}/pay", $payload)
            ->assertOk()
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.status', 'Completed')
            ->assertJsonPath('data.payment_status', 'Success');

        $firstChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $beforeVersion);

        $firstChanges->assertOk()->assertJsonPath('data.has_changes', true);
        $afterFirstVersion = (int) $firstChanges->json('data.current_version');

        $event = collect($firstChanges->json('data.events'))->firstWhere('type', 'reservation.settlement_completed');
        self::assertIsArray($event);
        self::assertSame($reservationId, (int) data_get($event, 'payload.reservation_id'));
        self::assertSame($orderId, (int) data_get($event, 'payload.order_id'));
        self::assertSame([$tableId], array_values((array) data_get($event, 'payload.table_ids', [])));

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/orders/{$orderId}/pay", $payload)
            ->assertOk()
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.status', 'Completed')
            ->assertJsonPath('data.payment_status', 'Success');

        $replayChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $afterFirstVersion);

        $replayChanges->assertOk()
            ->assertJsonPath('data.has_changes', false)
            ->assertJsonCount(0, 'data.events');

        self::assertSame('Completed', (string) $this->table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('Available', (string) $this->table('restaurant_tables')->where('table_id', $tableId)->value('status'));
        self::assertSame(
            1,
            (int) $this->table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Final')
                ->where('idempotency_key', 'staff-realtime-pay-replay')
                ->count()
        );
    }

    public function test_refund_cancel_emits_board_change_event_and_clears_live_table_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-22 13:00:00', 'UTC'));

        [$staffId, $tableId, $reservationId] = $this->seedRealtimeRefundCancelScenario('REALTIME-RFCANCEL');
        $staffHeaders = $this->staffAuthHeaders($staffId, 'staff-realtime-refund-cancel');

        $beforeVersion = (int) $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes')
            ->assertOk()
            ->json('data.current_version');

        $response = $this->withHeaders($this->withIdempotencyKey('staff-realtime-refund-cancel', $staffHeaders))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/refund-cancel", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'refund_scope' => 'all',
                'currency' => 'VND',
                'transaction_code' => 'REALTIME-RFCANCEL-1',
                'row_version' => 1,
                'reason' => 'customer_request',
                'cancel_reason' => 'customer_request',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.reservation.status', 'Cancelled')
            ->assertJsonPath('data.refund.cancelled', true)
            ->assertJsonPath('data.refund.refund_scope', 'all');

        self::assertSame('Cancelled', (string) $this->table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('Available', (string) $this->table('restaurant_tables')->where('table_id', $tableId)->value('status'));
        self::assertSame(1, (int) $this->table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Refund')->count());

        $this->withHeaders($staffHeaders)
            ->getJson("/api/v1/staff/tables/{$tableId}/active-order")
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');

        $changes = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $beforeVersion);

        $changes->assertOk()->assertJsonPath('data.has_changes', true);

        $event = collect($changes->json('data.events'))->firstWhere('type', 'reservation.refund_cancelled');
        self::assertIsArray($event);
        self::assertSame($reservationId, (int) data_get($event, 'payload.reservation_id'));
        self::assertSame([$tableId], array_values((array) data_get($event, 'payload.table_ids', [])));
        self::assertSame('Cancelled', (string) data_get($event, 'payload.reservation_status'));
        self::assertSame('customer_request', (string) data_get($event, 'payload.cancel_reason'));
        self::assertCount(1, (array) data_get($event, 'payload.refund_payment_ids', []));
        self::assertSame(['board', 'timeline'], array_values((array) data_get($event, 'refresh_targets', [])));
    }

    public function test_refund_cancel_replay_does_not_emit_duplicate_board_change_event(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-22 13:30:00', 'UTC'));

        [$staffId, $tableId, $reservationId] = $this->seedRealtimeRefundCancelScenario('REALTIME-RFCANCEL-REPLAY');
        $staffHeaders = $this->staffAuthHeaders($staffId, 'staff-realtime-refund-cancel-replay');
        $headers = $this->withIdempotencyKey('staff-realtime-refund-cancel-replay', $staffHeaders);

        $beforeVersion = (int) $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes')
            ->assertOk()
            ->json('data.current_version');

        $payload = [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'refund_scope' => 'all',
            'currency' => 'VND',
            'transaction_code' => 'REALTIME-RFCANCEL-REPLAY-1',
            'row_version' => 1,
            'reason' => 'customer_request',
            'cancel_reason' => 'customer_request',
        ];

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/reservations/{$reservationId}/refund-cancel", $payload)
            ->assertOk()
            ->assertJsonPath('data.reservation.status', 'Cancelled');

        $firstChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $beforeVersion);

        $firstChanges->assertOk()->assertJsonPath('data.has_changes', true);
        $afterFirstVersion = (int) $firstChanges->json('data.current_version');
        self::assertNotNull(collect($firstChanges->json('data.events'))->firstWhere('type', 'reservation.refund_cancelled'));

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/reservations/{$reservationId}/refund-cancel", $payload)
            ->assertOk()
            ->assertJsonPath('data.reservation.status', 'Cancelled');

        $replayChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $afterFirstVersion);

        $replayChanges->assertOk()
            ->assertJsonPath('data.has_changes', false)
            ->assertJsonCount(0, 'data.events');

        self::assertSame('Available', (string) $this->table('restaurant_tables')->where('table_id', $tableId)->value('status'));
        self::assertSame(1, (int) $this->table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Refund')->count());
    }

    public function test_deposit_pay_emits_board_change_event_and_refreshes_deposit_operational_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-22 14:00:00', 'UTC'));

        [$staffId, $tableId, $reservationId] = $this->seedRealtimeDepositScenario('REALTIME-DEPOSIT');
        $staffHeaders = $this->staffAuthHeaders($staffId, 'staff-realtime-deposit-pay');

        $beforeVersion = (int) $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes')
            ->assertOk()
            ->json('data.current_version');

        $response = $this->withHeaders($this->withIdempotencyKey('staff-realtime-deposit-pay', $staffHeaders))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'amount' => 60000,
                'currency' => 'VND',
                'transaction_code' => 'REALTIME-DEPOSIT-1',
                'row_version' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('meta.action', 'deposit_pay')
            ->assertJsonPath('data.deposit.status', 'Paid')
            ->assertJsonPath('data.deposit.outstanding_amount', '0.00')
            ->assertJsonPath('data.payment.payment_type', 'Deposit')
            ->assertJsonPath('data.payment.status', 'Success');

        self::assertSame('Paid', (string) $this->table('reservations')->where('reservation_id', $reservationId)->value('deposit_status'));
        self::assertSame('60000.00', number_format((float) $this->table('reservations')->where('reservation_id', $reservationId)->value('deposit_paid_amount'), 2, '.', ''));

        $changes = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $beforeVersion);

        $changes->assertOk()->assertJsonPath('data.has_changes', true);

        $event = collect($changes->json('data.events'))->firstWhere('type', 'reservation.deposit_paid');
        self::assertIsArray($event);
        self::assertSame($reservationId, (int) data_get($event, 'payload.reservation_id'));
        self::assertSame([$tableId], array_values((array) data_get($event, 'payload.table_ids', [])));
        self::assertSame('Paid', (string) data_get($event, 'payload.deposit_status'));
        self::assertSame('Success', (string) data_get($event, 'payload.payment_status'));
        self::assertSame(['board', 'timeline'], array_values((array) data_get($event, 'refresh_targets', [])));
    }

    public function test_deposit_pay_replay_does_not_emit_duplicate_board_change_event(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-22 14:30:00', 'UTC'));

        [$staffId, $tableId, $reservationId] = $this->seedRealtimeDepositScenario('REALTIME-DEPOSIT-REPLAY');
        $staffHeaders = $this->staffAuthHeaders($staffId, 'staff-realtime-deposit-pay-replay');
        $headers = $this->withIdempotencyKey('staff-realtime-deposit-pay-replay', $staffHeaders);

        $beforeVersion = (int) $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes')
            ->assertOk()
            ->json('data.current_version');

        $payload = [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'amount' => 60000,
            'currency' => 'VND',
            'transaction_code' => 'REALTIME-DEPOSIT-REPLAY-1',
            'row_version' => 1,
        ];

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", $payload)
            ->assertOk()
            ->assertJsonPath('data.deposit.status', 'Paid');

        $firstChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $beforeVersion);

        $firstChanges->assertOk()->assertJsonPath('data.has_changes', true);
        $afterFirstVersion = (int) $firstChanges->json('data.current_version');
        self::assertNotNull(collect($firstChanges->json('data.events'))->firstWhere('type', 'reservation.deposit_paid'));

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", $payload)
            ->assertOk()
            ->assertJsonPath('data.deposit.status', 'Paid');

        $replayChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $afterFirstVersion);

        $replayChanges->assertOk()
            ->assertJsonPath('data.has_changes', false)
            ->assertJsonCount(0, 'data.events');

        self::assertSame($tableId, (int) $this->table('reservation_tables')->where('reservation_id', $reservationId)->value('table_id'));
        self::assertSame(1, (int) $this->table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Deposit')->count());
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function seedRealtimeCheckoutScenario(string $reservationCode): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable([
            'status' => 'Occupied',
            'table_code' => $reservationCode . '-T4',
            'zone' => 'Main',
        ]);
        $reservationId = $this->createReservation([
            'reservation_code' => $reservationCode,
            'user_id' => $customerId,
            'status' => 'Reserved',
            'checked_in_at' => Carbon::now('UTC')->copy()->subMinutes(10),
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'bill_currency' => 'VND',
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 2,
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '100000.00',
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        return [$staffId, $tableId, $reservationId, $orderId];
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function seedRealtimeRefundCancelScenario(string $reservationCode): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable([
            'status' => 'Occupied',
            'table_code' => $reservationCode . '-T4',
            'zone' => 'Main',
        ]);
        $reservationId = $this->createReservation([
            'reservation_code' => $reservationCode,
            'user_id' => $customerId,
            'status' => 'Reserved',
            'checked_in_at' => Carbon::now('UTC')->copy()->subMinutes(10),
            'deposit_required_amount' => '60000.00',
            'deposit_paid_amount' => '60000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 1,
            'unit_price' => '120000.00',
            'currency' => 'VND',
            'line_total' => '120000.00',
            'status' => 'Ordered',
            'row_version' => 1,
        ]);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '60000.00',
            'currency' => 'VND',
            'transaction_code' => $reservationCode . '-DEP-1',
        ]);

        return [$staffId, $tableId, $reservationId];
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function seedRealtimeDepositScenario(string $reservationCode): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable([
            'status' => 'Reserved',
            'table_code' => $reservationCode . '-T4',
            'zone' => 'Main',
        ]);
        $reservationId = $this->createReservation([
            'reservation_code' => $reservationCode,
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '60000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        return [$staffId, $tableId, $reservationId];
    }
}
