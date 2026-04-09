<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Services\NotificationOutboxService;
use App\Services\ReservationLockService;
use App\Services\RestaurantTableStateService;
use App\Services\RuntimeSettingService;
use App\Services\TableTimeConflictService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffCheckInFlowTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();

        $this->app->instance(NotificationOutboxService::class, $this->mockNotificationOutbox());
        $this->app->instance(ReservationLockService::class, $this->mockReservationLocks());
        $this->app->instance(RuntimeSettingService::class, $this->mockRuntimeSettings());
        $this->app->instance(RestaurantTableStateService::class, new RestaurantTableStateService());
        $this->app->instance(TableTimeConflictService::class, new TableTimeConflictService());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_staff_can_check_in_confirmed_reservation_via_http_flow(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('checkin-success', $this->staffAuthHeaders($staffId));

        $tableId = $this->createRestaurantTable(['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addMinutes(5);
        $reservationId = $this->createReservation([
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/check-in", [
            'table_ids' => [$tableId],
            'checked_in_at' => $start->toIso8601String(),
            'row_version' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'Reserved');

        self::assertSame('Reserved', DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
        self::assertSame(2, (int) DB::table('restaurant_tables')->where('table_id', $tableId)->value('row_version'));

        $log = $this->assertAuditLogRecorded('reservation.checked_in', 'reservation', $reservationId);
        self::assertSame($staffId, $log->actor_user_id);
        self::assertSame('staff_user', $log->actor_type);
        self::assertSame('Reserved', (string) data_get($log->after_json, 'status'));
        self::assertSame(1, (int) data_get($log->summary_json, 'table_count'));
        $this->assertAuditSubjectRecorded($log, 'restaurant_table', $tableId, 'table');
    }

    public function test_check_in_rejects_request_outside_grace_window(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('checkin-outside-grace', $this->staffAuthHeaders($staffId));

        $tableId = $this->createRestaurantTable(['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addMinutes(5);
        $reservationId = $this->createReservation([
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/check-in", [
            'table_ids' => [$tableId],
            'checked_in_at' => $start->copy()->addHours(2)->toIso8601String(),
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['checked_in_at']);
        self::assertSame('Confirmed', DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
    }

    public function test_check_in_rejects_assigned_tables_with_overlapping_active_hold(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('checkin-hold-conflict', $this->staffAuthHeaders($staffId));

        $tableId = $this->createRestaurantTable(['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addMinutes(20);
        $end = $start->copy()->addHours(2);
        $reservationId = $this->createReservation([
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createTableHold([
            'start_time' => $start->copy()->subMinutes(5),
            'end_time' => $end->copy()->addMinutes(5),
            'expire_at' => $this->nowUtc()->copy()->addMinutes(30),
            'hold_status' => 'Holding',
        ], [$tableId]);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/check-in", [
            'table_ids' => [$tableId],
            'checked_in_at' => $start->toIso8601String(),
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['table_ids']);
        self::assertSame('Confirmed', DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('Available', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_check_in_ignores_confirmed_hold_linked_to_same_reservation(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('checkin-own-confirmed-hold', $this->staffAuthHeaders($staffId));

        $tableId = $this->createRestaurantTable(['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addMinutes(10);
        $end = $start->copy()->addHours(2);
        $reservationId = $this->createReservation([
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createTableHold([
            'session_id' => 'checkin-own-confirmed-hold',
            'start_time' => $start->copy()->subMinutes(5),
            'end_time' => $end->copy()->addMinutes(5),
            'expire_at' => $this->nowUtc()->copy()->subMinutes(10),
            'hold_status' => 'Confirmed',
            'confirmed_reservation_id' => $reservationId,
        ], [$tableId]);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/check-in", [
            'table_ids' => [$tableId],
            'checked_in_at' => $start->toIso8601String(),
            'row_version' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'Reserved')
            ->assertJsonPath('data.table_ids.0', $tableId);

        self::assertSame('Reserved', DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_check_in_rejects_assigned_tables_with_branch_mismatch(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('checkin-branch-mismatch', $this->staffAuthHeaders($staffId));
        $annexBranchId = $this->createBranch([
            'branch_code' => 'ANNEXCHK',
            'branch_name' => 'Annex Checkin',
        ]);

        $tableId = $this->createRestaurantTable([
            'status' => 'Available',
            'branch_id' => $annexBranchId,
        ]);
        $start = $this->nowUtc()->copy()->addMinutes(10);
        $reservationId = $this->createReservation([
            'branch_id' => 1,
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/check-in", [
            'table_ids' => [$tableId],
            'checked_in_at' => $start->toIso8601String(),
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reservation_id']);
        self::assertSame('Confirmed', DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('Available', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_check_in_rejects_attempt_to_change_assigned_tables(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('checkin-change-assigned-table', $this->staffAuthHeaders($staffId));

        $assignedTableId = $this->createRestaurantTable(['status' => 'Available']);
        $otherTableId = $this->createRestaurantTable(['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addMinutes(10);
        $reservationId = $this->createReservation([
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $assignedTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/check-in", [
            'table_ids' => [$otherTableId],
            'checked_in_at' => $start->toIso8601String(),
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['table_ids']);
        self::assertSame([$assignedTableId], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_check_in_rejects_stale_row_version(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('checkin-row-version', $this->staffAuthHeaders($staffId));

        $tableId = $this->createRestaurantTable(['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addMinutes(5);
        $reservationId = $this->createReservation([
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'status' => 'Confirmed',
            'row_version' => 2,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/check-in", [
            'table_ids' => [$tableId],
            'checked_in_at' => $start->toIso8601String(),
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['row_version']);
        self::assertSame('Confirmed', DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('Available', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_check_in_is_idempotent_when_reservation_is_already_checked_in(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $firstHeaders = $this->withIdempotencyKey('checkin-first', $this->staffAuthHeaders($staffId));
        $secondHeaders = $this->withIdempotencyKey('checkin-second', $this->staffAuthHeaders($staffId, 'test-staff-key-2'));

        $tableId = $this->createRestaurantTable(['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addMinutes(5);
        $reservationId = $this->createReservation([
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $firstCheckedInAt = $start->copy();
        $first = $this->withHeaders($firstHeaders)->postJson("/api/v1/staff/reservations/{$reservationId}/check-in", [
            'table_ids' => [$tableId],
            'checked_in_at' => $firstCheckedInAt->toIso8601String(),
            'row_version' => 1,
        ]);
        $first->assertOk()->assertJsonPath('data.status', 'Reserved');

        $second = $this->withHeaders($secondHeaders)->postJson("/api/v1/staff/reservations/{$reservationId}/check-in", [
            'table_ids' => [$tableId],
            'checked_in_at' => $start->copy()->addMinutes(10)->toIso8601String(),
            'row_version' => 1,
        ]);

        $second->assertOk();
        $second->assertJsonPath('data.status', 'Reserved');
        self::assertSame('Reserved', DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertTrue(Carbon::parse((string) DB::table('reservations')->where('reservation_id', $reservationId)->value('checked_in_at'))->utc()->equalTo($firstCheckedInAt));
        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }
}
