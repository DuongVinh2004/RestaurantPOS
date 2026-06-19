<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffServiceSessionHttpFlowTest extends TestCase
{
    use AssertsAuditTrail;
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
        Cache::store('array')->flush();

        $this->app->instance(ReservationLockService::class, $this->mockReservationLocks());
        $this->app->instance(RestaurantTableStateService::class, new RestaurantTableStateService);
        $this->app->instance(TableTimeConflictService::class, new TableTimeConflictService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_walk_in_create_successfully_creates_checked_in_reservation_backed_service_session(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'full_name' => 'Walk In Session Customer',
            'email' => 'walkin.session.customer@example.test',
        ]);
        $branchId = 1;
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'table_code' => 'WALKIN-01',
            'status' => 'Available',
        ]);
        $startedAt = $this->nowUtc()->copy()->addMinutes(45);

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-service-session-create'), 'staff-service-session-create-1'))
            ->postJson('/api/v1/staff/service-sessions/walk-in', [
                'branch_id' => $branchId,
                'user_id' => $customerId,
                'table_ids' => [$tableId],
                'guest_count' => 3,
                'started_at' => $startedAt->toIso8601String(),
                'service_minutes' => 90,
                'notes' => 'Window table requested',
            ]);

        $response->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.branch_id', $branchId)
            ->assertJsonPath('data.user.user_id', $customerId)
            ->assertJsonPath('data.table_ids.0', $tableId)
            ->assertJsonPath('data.status', 'Reserved')
            ->assertJsonPath('data.source', 'WalkIn')
            ->assertJsonPath('data.status_flags.is_checked_in', true);

        $reservationId = (int) $response->json('data.reservation_id');

        self::assertSame('Reserved', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('WalkIn', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('source'));
        self::assertSame('Occupied', (string) DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
        self::assertSame(1, (int) DB::table('notification_outbox')
            ->where('related_reservation_id', $reservationId)
            ->where('template_key', 'reservation.checked_in')
            ->count());

        $changes = app(OperationalRealtimeService::class)->readTopic(OperationalRealtimeService::TOPIC_BOARD, 0, 10);
        $event = collect((array) ($changes['events'] ?? []))->firstWhere('type', 'service_session.walk_in_created');
        self::assertIsArray($event);
        self::assertSame($reservationId, (int) data_get($event, 'payload.reservation_id'));
        self::assertSame($branchId, (int) data_get($event, 'payload.branch_id'));
        self::assertSame([$tableId], array_values((array) data_get($event, 'payload.table_ids', [])));

        $audit = $this->assertAuditLogRecorded('service_session.walk_in_created', 'reservation', $reservationId);
        $this->assertAuditSubjectRecorded($audit, 'restaurant_table', $tableId, 'table');
        $this->assertAuditSubjectRecorded($audit, 'user', $customerId, 'customer');
    }

    public function test_walk_in_create_reuses_existing_customer_by_phone(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'full_name' => 'Known Walk In Customer',
            'email' => 'walkin.phone.customer@example.test',
            'phone' => '0900000123',
        ]);
        $branchId = 1;
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'table_code' => 'WALKIN-PHONE-01',
            'status' => 'Available',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId, 'staff-service-session-phone'),
            'staff-service-session-phone-1',
        ))->postJson('/api/v1/staff/service-sessions/walk-in', [
            'branch_id' => $branchId,
            'guest_name' => 'Same Phone Guest',
            'phone' => '0900000123',
            'table_ids' => [$tableId],
            'guest_count' => 2,
            'started_at' => $this->nowUtc()->copy()->addMinutes(30)->toIso8601String(),
            'service_minutes' => 60,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.user_id', $customerId)
            ->assertJsonPath('data.table_ids.0', $tableId)
            ->assertJsonPath('data.source', 'WalkIn');

        self::assertSame(1, (int) DB::table('users')->where('phone', '0900000123')->count());
    }

    public function test_walk_in_create_rejects_phone_owned_by_non_customer(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createUser([
            'role_name' => 'Staff',
            'full_name' => 'Staff Phone Owner',
            'email' => 'walkin.phone.staff@example.test',
            'phone' => '0900000456',
        ]);
        $branchId = 1;
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'status' => 'Available',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId, 'staff-service-session-phone-guard'),
            'staff-service-session-phone-guard-1',
        ))->postJson('/api/v1/staff/service-sessions/walk-in', [
            'branch_id' => $branchId,
            'guest_name' => 'Blocked Guest',
            'phone' => '0900000456',
            'table_ids' => [$tableId],
            'guest_count' => 2,
            'started_at' => $this->nowUtc()->copy()->addMinutes(30)->toIso8601String(),
            'service_minutes' => 60,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['phone']);

        self::assertSame(0, (int) DB::table('reservations')->where('source', 'WalkIn')->count());
        self::assertSame('Available', (string) DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_active_service_session_lookup_returns_current_checked_in_reservation_for_table(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = 1;
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'table_code' => 'ACTIVE-01',
            'status' => 'Occupied',
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'reservation_code' => 'RSV-WALKIN-ACTIVE-001',
            'status' => 'Reserved',
            'source' => 'WalkIn',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(20),
            'checked_out_at' => null,
            'cancelled_at' => null,
            'no_show_at' => null,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-service-session-show'))
            ->getJson('/api/v1/staff/tables/'.$tableId.'/active-service-session')
            ->assertOk()
            ->assertJsonPath('meta.action', 'active_service_session_by_table')
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.status', 'Reserved')
            ->assertJsonPath('data.table_ids.0', $tableId);
    }

    public function test_walk_in_create_rejects_requested_branch_mismatch_for_selected_tables(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableBranchId = 1;
        $otherBranchId = $this->createBranch([
            'branch_code' => 'WALKIN-D',
            'branch_name' => 'Requested Branch',
        ]);
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $tableBranchId,
            'status' => 'Available',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-service-session-branch'), 'staff-service-session-branch-1'))
            ->postJson('/api/v1/staff/service-sessions/walk-in', [
                'branch_id' => $otherBranchId,
                'user_id' => $customerId,
                'table_ids' => [$tableId],
                'guest_count' => 2,
                'started_at' => $this->nowUtc()->copy()->addMinutes(30)->toIso8601String(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_walk_in_create_rejects_conflicting_table_window(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = 1;
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'status' => 'Available',
        ]);
        $start = $this->nowUtc()->copy()->addMinutes(40);
        $end = $start->copy()->addHours(2);
        $conflictingReservationId = $this->createReservation([
            'branch_id' => $branchId,
            'reservation_code' => 'RSV-WALKIN-CONFLICT-001',
            'status' => 'Confirmed',
            'start_time' => $start,
            'end_time' => $end,
        ]);
        $this->attachReservationTable($conflictingReservationId, $tableId);

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-service-session-conflict'), 'staff-service-session-conflict-1'))
            ->postJson('/api/v1/staff/service-sessions/walk-in', [
                'branch_id' => $branchId,
                'user_id' => $customerId,
                'table_ids' => [$tableId],
                'guest_count' => 2,
                'started_at' => $start->copy()->addMinutes(15)->toIso8601String(),
                'service_minutes' => 60,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['table_ids']);

        self::assertSame(0, (int) DB::table('reservations')->where('source', 'WalkIn')->count());
    }

    public function test_walk_in_create_rejects_unavailable_table_status(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = 1;
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'status' => 'Occupied',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-service-session-unavailable'), 'staff-service-session-unavailable-1'))
            ->postJson('/api/v1/staff/service-sessions/walk-in', [
                'branch_id' => $branchId,
                'user_id' => $customerId,
                'table_ids' => [$tableId],
                'guest_count' => 2,
                'started_at' => $this->nowUtc()->copy()->addMinutes(30)->toIso8601String(),
                'service_minutes' => 60,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['table_ids']);

        self::assertSame(0, (int) DB::table('reservations')->where('source', 'WalkIn')->count());
    }

    public function test_walk_in_create_rejects_invalid_payload(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-service-session-invalid'), 'staff-service-session-invalid-1'))
            ->postJson('/api/v1/staff/service-sessions/walk-in', [
                'table_ids' => [],
                'guest_count' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['guest_name', 'table_ids', 'guest_count']);
    }

    public function test_walk_in_create_rejects_existing_non_customer_user_binding(): void
    {
        $actorStaffId = $this->createUser(['role_name' => 'Staff']);
        $targetStaffId = $this->createUser([
            'role_name' => 'Staff',
            'full_name' => 'Not A Customer',
            'email' => 'walkin.staff.target@example.test',
        ]);
        $branchId = 1;
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'status' => 'Available',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey(
            $this->staffAuthHeaders($actorStaffId, 'staff-service-session-customer-guard'),
            'staff-service-session-customer-guard-1'
        ))->postJson('/api/v1/staff/service-sessions/walk-in', [
            'branch_id' => $branchId,
            'user_id' => $targetStaffId,
            'table_ids' => [$tableId],
            'guest_count' => 2,
            'started_at' => $this->nowUtc()->copy()->addMinutes(30)->toIso8601String(),
            'service_minutes' => 60,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['user_id']);

        self::assertSame(0, (int) DB::table('reservations')->where('source', 'WalkIn')->count());
        self::assertSame('Available', (string) DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
        self::assertSame(0, (int) DB::table('notification_outbox')->count());
    }

    public function test_walk_in_create_rejects_branch_closure_window(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch([
            'branch_code' => 'WICLOSE',
            'branch_name' => 'Walk In Closure Branch',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'business_hours' => collect(range(0, 6))
                ->map(static fn (int $day): array => [
                    'day_of_week' => $day,
                    'periods' => [[
                        'start_time' => '09:00',
                        'end_time' => '22:00',
                    ]],
                ])
                ->all(),
            'closure_windows' => [[
                'start_local' => '2026-09-10 18:00:00',
                'end_local' => '2026-09-10 20:00:00',
                'type' => 'closure',
                'reason' => 'Private event',
            ]],
        ]);
        config()->set('staff_capabilities.role_branch_scopes.Staff', ['default', (string) $branchId]);
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'status' => 'Available',
        ]);
        $startedAt = Carbon::parse('2026-09-10 18:30:00', 'Asia/Ho_Chi_Minh')->utc();

        $response = $this->withHeaders($this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId, 'staff-service-session-closure'),
            'staff-service-session-closure-1',
        ))->postJson('/api/v1/staff/service-sessions/walk-in', [
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'table_ids' => [$tableId],
            'guest_count' => 2,
            'started_at' => $startedAt->toIso8601String(),
            'service_minutes' => 60,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('details.errors.branch_id.0', 'Walk-in service sessions are unavailable because the branch is closed: Private event.');

        self::assertSame(0, (int) DB::table('reservations')->where('source', 'WalkIn')->count());
        self::assertSame('Available', (string) DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_walk_in_create_uses_branch_policy_default_service_minutes_when_service_minutes_is_omitted(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch([
            'branch_code' => 'WIPOLI',
            'branch_name' => 'Walk In Policy Branch',
            'booking_policy' => [
                'reservation' => [],
                'waiting_list' => [
                    'enabled' => true,
                    'default_service_minutes' => 150,
                ],
                'availability' => [],
            ],
        ]);
        config()->set('staff_capabilities.role_branch_scopes.Staff', ['default', (string) $branchId]);
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'status' => 'Available',
        ]);
        $startedAt = $this->nowUtc()->copy()->addMinutes(45)->startOfMinute();

        $response = $this->withHeaders($this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId, 'staff-service-session-policy'),
            'staff-service-session-policy-1',
        ))->postJson('/api/v1/staff/service-sessions/walk-in', [
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'table_ids' => [$tableId],
            'guest_count' => 2,
            'started_at' => $startedAt->toIso8601String(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.branch_id', $branchId)
            ->assertJsonPath('data.table_ids.0', $tableId);

        $reservationId = (int) $response->json('data.reservation_id');
        $endAt = Carbon::parse((string) DB::table('reservations')->where('reservation_id', $reservationId)->value('end_time'))->utc();

        self::assertSame(150, (int) $startedAt->diffInMinutes($endAt));
    }

    public function test_walk_in_create_replays_idempotently_without_duplicate_reservations(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'email' => 'walkin.idempotent.customer@example.test',
        ]);
        $branchId = 1;
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'status' => 'Available',
        ]);
        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-service-session-replay'), 'staff-service-session-replay-1');
        $payload = [
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'table_ids' => [$tableId],
            'guest_count' => 2,
            'started_at' => $this->nowUtc()->copy()->addMinutes(35)->toIso8601String(),
            'service_minutes' => 75,
        ];

        $first = $this->withHeaders($headers)->postJson('/api/v1/staff/service-sessions/walk-in', $payload);
        $second = $this->withHeaders($headers)->postJson('/api/v1/staff/service-sessions/walk-in', $payload);

        $first->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false');
        $second->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.reservation_id', $first->json('data.reservation_id'));

        $reservationId = (int) $first->json('data.reservation_id');
        self::assertSame(1, (int) DB::table('reservations')->where('reservation_id', $reservationId)->count());
        self::assertSame(1, (int) DB::table('notification_outbox')
            ->where('related_reservation_id', $reservationId)
            ->where('template_key', 'reservation.checked_in')
            ->count());
    }

    public function test_walk_in_create_rejects_same_idempotency_key_with_different_payload_without_duplicate_reservation(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'email' => 'walkin.idempotent.conflict.customer@example.test',
        ]);
        $branchId = 1;
        $firstTableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'status' => 'Available',
        ]);
        $secondTableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'status' => 'Available',
        ]);
        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-service-session-replay-conflict'), 'staff-service-session-replay-conflict-1');
        $payload = [
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'table_ids' => [$firstTableId],
            'guest_count' => 2,
            'started_at' => $this->nowUtc()->copy()->addMinutes(35)->toIso8601String(),
            'service_minutes' => 75,
        ];

        $first = $this->withHeaders($headers)->postJson('/api/v1/staff/service-sessions/walk-in', $payload);
        $second = $this->withHeaders($headers)->postJson('/api/v1/staff/service-sessions/walk-in', array_merge($payload, [
            'table_ids' => [$secondTableId],
        ]));

        $first->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false');
        $second->assertStatus(409)
            ->assertJsonPath('error_code', 'idempotency_conflict')
            ->assertJsonPath('conflict_type', 'idempotency_payload_mismatch');

        $reservationId = (int) $first->json('data.reservation_id');
        self::assertSame(1, (int) DB::table('reservations')
            ->where('source', 'WalkIn')
            ->where('user_id', $customerId)
            ->count());
        self::assertSame(1, (int) DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->where('table_id', $firstTableId)
            ->count());
        self::assertSame(0, (int) DB::table('reservation_tables')
            ->where('table_id', $secondTableId)
            ->count());
    }

    public function test_active_service_session_lookup_returns_not_found_for_historical_checked_out_reservation(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch([
            'branch_code' => 'WALKIN-G',
            'branch_name' => 'Historical Branch',
        ]);
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'status' => 'Occupied',
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'reservation_code' => 'RSV-WALKIN-HISTORY-001',
            'status' => 'Completed',
            'source' => 'WalkIn',
            'checked_in_at' => $this->nowUtc()->copy()->subHours(3),
            'checked_out_at' => $this->nowUtc()->copy()->subHour(),
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $this->withHeaders(array_merge(
            $this->staffAuthHeaders($staffId, 'staff-service-session-history'),
            ['X-Request-Id' => 'req-staff-service-session-404']
        ))
            ->getJson('/api/v1/staff/tables/'.$tableId.'/active-service-session')
            ->assertStatus(404)
            ->assertHeader('X-Request-Id', 'req-staff-service-session-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('request_id', 'req-staff-service-session-404');
    }

    public function test_walk_in_happy_path_supports_board_and_order_replays_without_duplicate_mutation(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'full_name' => 'Replay Happy Path Customer',
            'email' => 'replay.happy.path.customer@example.test',
        ]);
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => 1,
            'table_code' => 'WALKIN-HAPPY-01',
            'status' => 'Available',
            'zone' => 'Main',
        ]);
        $menuItemId = $this->createMenuItem();
        $this->createMenuItemPrice([
            'item_id' => $menuItemId,
            'price' => '90000',
            'currency' => 'VND',
        ]);

        $startedAt = $this->nowUtc()->copy()->addMinutes(25)->startOfMinute();
        $headers = $this->staffAuthHeaders($staffId, 'staff-dine-in-happy-path');

        $walkIn = $this->withHeaders($this->withIdempotencyKey($headers, 'staff-dine-in-walk-in-1'))
            ->postJson('/api/v1/staff/service-sessions/walk-in', [
                'branch_id' => 1,
                'user_id' => $customerId,
                'table_ids' => [$tableId],
                'guest_count' => 2,
                'started_at' => $startedAt->toIso8601String(),
                'service_minutes' => 90,
                'notes' => 'Replay happy path',
            ]);

        $walkIn->assertCreated()
            ->assertJsonPath('data.status', 'Reserved')
            ->assertJsonPath('data.table_ids.0', $tableId);

        $reservationId = (int) $walkIn->json('data.reservation_id');
        $reservationRowVersion = (int) $walkIn->json('data.row_version');

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/tables/'.$tableId.'/active-service-session')
            ->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId);

        $boardBeforeOrder = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/tables/board?from='.urlencode($startedAt->copy()->subHour()->toIso8601String()).'&to='.urlencode($startedAt->copy()->addHours(2)->toIso8601String()).'&branch_id=1');

        $boardBeforeOrder->assertOk();
        $boardRowBeforeOrder = collect($boardBeforeOrder->json('data'))->firstWhere('table_id', $tableId);
        self::assertIsArray($boardRowBeforeOrder);
        self::assertSame('occupied_now', $boardRowBeforeOrder['board_state']);
        self::assertSame($reservationId, (int) data_get($boardRowBeforeOrder, 'reservation.reservation_id'));
        self::assertNull(data_get($boardRowBeforeOrder, 'active_order'));

        $createPayload = [
            'reservation_id' => $reservationId,
            'row_version' => $reservationRowVersion,
            'notes' => 'Start dine-in order',
        ];

        $createOrderFirst = $this->withHeaders($this->withIdempotencyKey($headers, 'staff-dine-in-order-create-1'))
            ->postJson("/api/v1/staff/tables/{$tableId}/orders", $createPayload);

        $createOrderFirst->assertStatus(201)
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.status', 'Active')
            ->assertJsonCount(0, 'data.items');

        $orderId = (int) $createOrderFirst->json('data.order_id');
        $orderRowVersion = (int) $createOrderFirst->json('data.row_version');

        $createOrderReplay = $this->withHeaders($this->withIdempotencyKey($headers, 'staff-dine-in-order-create-1'))
            ->postJson("/api/v1/staff/tables/{$tableId}/orders", $createPayload);

        $createOrderReplay->assertStatus(201)
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.order_id', $orderId);

        $addItemsPayload = [
            'row_version' => $orderRowVersion,
            'items' => [[
                'menu_item_id' => $menuItemId,
                'qty' => 1,
                'note' => 'no onions',
            ]],
        ];

        $addItemsFirst = $this->withHeaders($this->withIdempotencyKey($headers, 'staff-dine-in-order-items-1'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items", $addItemsPayload);

        $addItemsFirst->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.items.0.item_id', $menuItemId)
            ->assertJsonPath('data.items.0.quantity', 1);

        $addItemsReplay = $this->withHeaders($this->withIdempotencyKey($headers, 'staff-dine-in-order-items-1'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items", $addItemsPayload);

        $addItemsReplay->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.items.0.item_id', $menuItemId);

        self::assertSame(
            1,
            (int) DB::table('reservation_order_items')
                ->where('order_id', $orderId)
                ->where('item_id', $menuItemId)
                ->count()
        );

        $this->withHeaders($headers)
            ->getJson("/api/v1/staff/tables/{$tableId}/active-order")
            ->assertOk()
            ->assertJsonPath('meta.action', 'active_order_by_table')
            ->assertJsonPath('data.order.order_id', $orderId)
            ->assertJsonPath('data.order.items.0.item_id', $menuItemId)
            ->assertJsonPath('data.order.items.0.quantity', 1)
            ->assertJsonPath('data.table.table_id', $tableId);

        $boardAfterOrder = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/tables/board?from='.urlencode($startedAt->copy()->subHour()->toIso8601String()).'&to='.urlencode($startedAt->copy()->addHours(2)->toIso8601String()).'&branch_id=1');

        $boardAfterOrder->assertOk();
        $boardRowAfterOrder = collect($boardAfterOrder->json('data'))->firstWhere('table_id', $tableId);
        self::assertIsArray($boardRowAfterOrder);
        self::assertSame($orderId, (int) data_get($boardRowAfterOrder, 'active_order.order_id'));
        self::assertSame('Active', data_get($boardRowAfterOrder, 'active_order.status'));
    }

    public function test_walk_in_create_rejects_branch_outside_staff_operational_scope(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $annexBranchId = $this->createBranch([
            'branch_code' => 'WALKIN-SCOPE',
            'branch_name' => 'Walk In Scope Branch',
        ]);
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $annexBranchId,
            'status' => 'Available',
        ]);

        $this->withHeaders($this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId, 'staff-service-session-branch-scope'),
            'staff-service-session-branch-scope-1',
        ))->postJson('/api/v1/staff/service-sessions/walk-in', [
            'branch_id' => $annexBranchId,
            'user_id' => $customerId,
            'table_ids' => [$tableId],
            'guest_count' => 2,
            'started_at' => $this->nowUtc()->copy()->addMinutes(30)->toIso8601String(),
            'service_minutes' => 60,
        ])->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');

        self::assertSame(0, (int) DB::table('reservations')->where('source', 'WalkIn')->count());
        self::assertSame('Available', (string) DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_active_service_session_lookup_hides_sessions_outside_staff_operational_branch_scope(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $annexBranchId = $this->createBranch([
            'branch_code' => 'WALKIN-LOOKUP-SCOPE',
            'branch_name' => 'Walk In Lookup Scope Branch',
        ]);
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $annexBranchId,
            'table_code' => 'ACTIVE-SCOPE-01',
            'status' => 'Occupied',
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => $annexBranchId,
            'user_id' => $customerId,
            'status' => 'Reserved',
            'source' => 'WalkIn',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(10),
            'checked_out_at' => null,
            'cancelled_at' => null,
            'no_show_at' => null,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-service-session-scope-read'))
            ->getJson('/api/v1/staff/tables/'.$tableId.'/active-service-session')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }
}
