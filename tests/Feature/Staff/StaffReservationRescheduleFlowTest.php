<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffReservationRescheduleFlowTest extends TestCase
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
        $this->app->instance(TableTimeConflictService::class, new TableTimeConflictService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_staff_can_reschedule_and_reassign_tables_via_http_flow(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('reschedule-success', $this->staffAuthHeaders($staffId));

        $oldTableId = $this->createRestaurantTableWithSeats(2, ['status' => 'Available']);
        $newTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $reservationId = $this->createReservation();
        $this->attachReservationTable($reservationId, $oldTableId);

        $newStart = $this->nowUtc()->copy()->addDays(1)->setHour(19)->setMinute(0)->setSecond(0);
        $newEnd = $newStart->copy()->addHours(2);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/reschedule", [
            'row_version' => 1,
            'start_time' => $newStart->toIso8601String(),
            'end_time' => $newEnd->toIso8601String(),
            'guest_count' => 3,
            'notes' => 'VIP reschedule',
            'table_ids' => [$newTableId],
            'reason' => 'customer_called',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.guest_count', 3);
        $response->assertJsonPath('data.notes', 'VIP reschedule');

        $reservation = DB::table('reservations')->where('reservation_id', $reservationId)->first();
        self::assertNotNull($reservation);
        self::assertSame(3, (int) $reservation->guest_count);
        self::assertSame('VIP reschedule', (string) $reservation->notes);
        self::assertTrue(Carbon::parse((string) $reservation->start_time)->utc()->equalTo($newStart));
        self::assertSame([$newTableId], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->map(fn ($id) => (int) $id)->all());

        $log = $this->assertAuditLogRecorded('reservation.rescheduled', 'reservation', $reservationId);
        self::assertSame($staffId, $log->actor_user_id);
        self::assertSame('staff_user', $log->actor_type);
        self::assertSame('customer_called', (string) data_get($log->summary_json, 'reason'));
        self::assertContains('notes', (array) data_get($log->summary_json, 'changed_fields', []));
        self::assertContains('tables', (array) data_get($log->summary_json, 'changed_fields', []));
        $this->assertAuditSubjectRecorded($log, 'restaurant_table', $oldTableId, 'table');
        $this->assertAuditSubjectRecorded($log, 'restaurant_table', $newTableId, 'table');
    }

    public function test_staff_can_reschedule_unassigned_confirmed_reservation_without_forcing_table_assignment(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('reschedule-unassigned-success', $this->staffAuthHeaders($staffId));

        $reservationId = $this->createReservation([
            'status' => 'Confirmed',
            'guest_count' => 2,
            'row_version' => 1,
            'start_time' => $this->nowUtc()->copy()->addHours(2),
            'end_time' => $this->nowUtc()->copy()->addHours(3),
        ]);

        $newStart = $this->nowUtc()->copy()->addDay()->setHour(18)->setMinute(0)->setSecond(0);
        $newEnd = $newStart->copy()->addHour();

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/reschedule", [
            'row_version' => 1,
            'start_time' => $newStart->toIso8601String(),
            'end_time' => $newEnd->toIso8601String(),
            'guest_count' => 3,
            'notes' => 'Unassigned reschedule',
            'reason' => 'ops_alignment',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.row_version', 2)
            ->assertJsonPath('data.guest_count', 3)
            ->assertJsonPath('data.notes', 'Unassigned reschedule');

        $reservation = DB::table('reservations')->where('reservation_id', $reservationId)->first();
        self::assertNotNull($reservation);
        self::assertTrue(Carbon::parse((string) $reservation->start_time)->utc()->equalTo($newStart));
        self::assertTrue(Carbon::parse((string) $reservation->end_time)->utc()->equalTo($newEnd));
        self::assertSame([], DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->pluck('table_id')
            ->all());
    }

    public function test_reschedule_rejects_blocked_target_table(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('reschedule-blocked-target', $this->staffAuthHeaders($staffId));

        $oldTableId = $this->createRestaurantTableWithSeats(2, ['status' => 'Available']);
        $blockedTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Blocked']);
        $reservationId = $this->createReservation();
        $this->attachReservationTable($reservationId, $oldTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/reschedule", [
            'row_version' => 1,
            'table_ids' => [$blockedTableId],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['table_ids']);
    }

    public function test_reschedule_rejects_target_tables_with_overlapping_reservation(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('reschedule-overlap-reservation', $this->staffAuthHeaders($staffId));

        $oldTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $targetTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $reservationId = $this->createReservation();
        $this->attachReservationTable($reservationId, $oldTableId);

        $newStart = $this->nowUtc()->copy()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $newEnd = $newStart->copy()->addHours(2);

        $otherReservationId = $this->createReservation([
            'start_time' => $newStart->copy()->addMinutes(30),
            'end_time' => $newEnd->copy()->addMinutes(30),
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($otherReservationId, $targetTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/reschedule", [
            'row_version' => 1,
            'start_time' => $newStart->toIso8601String(),
            'end_time' => $newEnd->toIso8601String(),
            'table_ids' => [$targetTableId],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['table_ids']);
    }

    public function test_reschedule_rejects_target_tables_without_enough_capacity(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('reschedule-capacity', $this->staffAuthHeaders($staffId));

        $oldTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $smallTableId = $this->createRestaurantTableWithSeats(2, ['status' => 'Available']);
        $reservationId = $this->createReservation([
            'guest_count' => 4,
        ]);
        $this->attachReservationTable($reservationId, $oldTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/reschedule", [
            'row_version' => 1,
            'guest_count' => 4,
            'table_ids' => [$smallTableId],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['table_ids']);
        self::assertSame([$oldTableId], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_reschedule_rejects_target_tables_with_branch_mismatch(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('reschedule-branch-mismatch', $this->staffAuthHeaders($staffId));
        $annexBranchId = $this->createBranch([
            'branch_code' => 'RESANNEX',
            'branch_name' => 'Reschedule Annex',
        ]);

        $oldTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available', 'branch_id' => 1]);
        $otherBranchTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available', 'branch_id' => $annexBranchId]);
        $reservationId = $this->createReservation([
            'branch_id' => 1,
            'guest_count' => 4,
        ]);
        $this->attachReservationTable($reservationId, $oldTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/reschedule", [
            'row_version' => 1,
            'table_ids' => [$otherBranchTableId],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['table_ids']);
        self::assertSame([$oldTableId], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_reschedule_rejects_branch_local_window_outside_business_hours(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('reschedule-business-hours', $this->staffAuthHeaders($staffId));
        $branchId = $this->createBranch([
            'branch_code' => 'RSHCM',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'business_hours' => collect(range(0, 6))
                ->map(static fn (int $day): array => [
                    'day_of_week' => $day,
                    'periods' => [[
                        'start_time' => '10:00',
                        'end_time' => '22:00',
                    ]],
                ])
                ->all(),
        ]);

        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available', 'branch_id' => $branchId]);
        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'start_time' => now('Asia/Ho_Chi_Minh')->addDay()->setTime(18, 0)->utc(),
            'end_time' => now('Asia/Ho_Chi_Minh')->addDay()->setTime(20, 0)->utc(),
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $newStart = now('Asia/Ho_Chi_Minh')->addDay()->setTime(9, 0)->utc();
        $newEnd = $newStart->copy()->addHour();

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/reschedule", [
            'row_version' => 1,
            'start_time' => $newStart->toIso8601String(),
            'end_time' => $newEnd->toIso8601String(),
            'table_ids' => [$tableId],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('details.errors.start_time.0', 'Requested reservation window falls outside the configured branch business hours.');
    }

    public function test_reschedule_rejects_stale_row_version(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('reschedule-row-version', $this->staffAuthHeaders($staffId));

        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $reservationId = $this->createReservation([
            'row_version' => 2,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/reschedule", [
            'row_version' => 1,
            'notes' => 'Should fail because stale version',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'stale_row_version');
        $response->assertJsonValidationErrors(['row_version']);
        self::assertNull(DB::table('reservations')->where('reservation_id', $reservationId)->value('notes'));
    }

    public function test_reschedule_rejects_checked_in_reservation(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('reschedule-checked-in', $this->staffAuthHeaders($staffId));

        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc(),
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/reschedule", [
            'row_version' => 1,
            'notes' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_reschedule_request_requires_at_least_one_mutable_field(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('reschedule-empty-payload', $this->staffAuthHeaders($staffId));

        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $reservationId = $this->createReservation();
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/reschedule", [
            'row_version' => 1,
            'reason' => 'empty-change-set',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payload']);
    }
}
