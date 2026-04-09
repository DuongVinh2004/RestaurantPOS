<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Services\ReservationLockService;
use App\Services\RestaurantTableStateService;
use App\Services\Staff\StaffOperationalRealtimeService;
use App\Services\TableTimeConflictService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
        $branchId = $this->createBranch([
            'branch_code' => 'WALKIN-A',
            'branch_name' => 'Walk In Branch',
        ]);
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

        $changes = app(StaffOperationalRealtimeService::class)->readTopic(StaffOperationalRealtimeService::TOPIC_BOARD, 0, 10);
        $event = collect((array) ($changes['events'] ?? []))->firstWhere('type', 'service_session.walk_in_created');
        self::assertIsArray($event);
        self::assertSame($reservationId, (int) data_get($event, 'payload.reservation_id'));
        self::assertSame($branchId, (int) data_get($event, 'payload.branch_id'));
        self::assertSame([$tableId], array_values((array) data_get($event, 'payload.table_ids', [])));

        $audit = $this->assertAuditLogRecorded('service_session.walk_in_created', 'reservation', $reservationId);
        $this->assertAuditSubjectRecorded($audit, 'restaurant_table', $tableId, 'table');
        $this->assertAuditSubjectRecorded($audit, 'user', $customerId, 'customer');
    }

    public function test_active_service_session_lookup_returns_current_checked_in_reservation_for_table(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch([
            'branch_code' => 'WALKIN-B',
            'branch_name' => 'Active Session Branch',
        ]);
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
        $tableBranchId = $this->createBranch([
            'branch_code' => 'WALKIN-C',
            'branch_name' => 'Table Branch',
        ]);
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
        $branchId = $this->createBranch([
            'branch_code' => 'WALKIN-E',
            'branch_name' => 'Conflict Branch',
        ]);
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
        $branchId = $this->createBranch([
            'branch_code' => 'WALKIN-U',
            'branch_name' => 'Unavailable Branch',
        ]);
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

    public function test_walk_in_create_replays_idempotently_without_duplicate_reservations(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'email' => 'walkin.idempotent.customer@example.test',
        ]);
        $branchId = $this->createBranch([
            'branch_code' => 'WALKIN-F',
            'branch_name' => 'Replay Branch',
        ]);
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
}
