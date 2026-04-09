<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Services\ReservationLockService;
use App\Services\RestaurantTableStateService;
use App\Services\TableTimeConflictService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffMoveTableFlowTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();

        $this->app->instance(ReservationLockService::class, $this->mockReservationLocks());
        $this->app->instance(RestaurantTableStateService::class, new RestaurantTableStateService());
        $this->app->instance(TableTimeConflictService::class, new TableTimeConflictService());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_staff_can_move_checked_in_reservation_to_available_target_table(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('move-table-success', $this->staffAuthHeaders($staffId));

        $fromTableId = $this->createRestaurantTableWithSeats(2, ['status' => 'Occupied']);
        $toTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc(),
        ]);
        $this->attachReservationTable($reservationId, $fromTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/move-table", [
            'from_table_id' => $fromTableId,
            'to_table_id' => $toTableId,
            'row_version' => 1,
        ]);

        $response->assertOk();
        self::assertSame([$toTableId], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->map(fn ($id) => (int) $id)->all());
        self::assertSame('Available', DB::table('restaurant_tables')->where('table_id', $fromTableId)->value('status'));
        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $toTableId)->value('status'));

        $log = $this->assertAuditLogRecorded('reservation.table_moved', 'reservation', $reservationId);
        self::assertSame($staffId, $log->actor_user_id);
        self::assertSame('staff_user', $log->actor_type);
        self::assertSame((string) $fromTableId, (string) data_get($log->before_json, 'from_table_id'));
        self::assertSame((string) $toTableId, (string) data_get($log->after_json, 'to_table_id'));
        $this->assertAuditSubjectRecorded($log, 'restaurant_table', $fromTableId, 'from_table');
        $this->assertAuditSubjectRecorded($log, 'restaurant_table', $toTableId, 'to_table');
    }

    public function test_move_table_rejects_non_available_target_table(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('move-table-busy-target', $this->staffAuthHeaders($staffId));

        $fromTableId = $this->createRestaurantTableWithSeats(2, ['status' => 'Occupied']);
        $busyTargetId = $this->createRestaurantTableWithSeats(4, ['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc(),
        ]);
        $this->attachReservationTable($reservationId, $fromTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/move-table", [
            'from_table_id' => $fromTableId,
            'to_table_id' => $busyTargetId,
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to_table_id']);
    }

    public function test_move_table_rejects_target_table_from_different_branch(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('move-table-branch-mismatch', $this->staffAuthHeaders($staffId));
        $annexBranchId = $this->createBranch([
            'branch_code' => 'ANNEXMOV',
            'branch_name' => 'Annex Move',
        ]);

        $fromTableId = $this->createRestaurantTableWithSeats(2, ['status' => 'Occupied', 'branch_id' => 1]);
        $toTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available', 'branch_id' => $annexBranchId]);
        $reservationId = $this->createReservation([
            'branch_id' => 1,
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc(),
        ]);
        $this->attachReservationTable($reservationId, $fromTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/move-table", [
            'from_table_id' => $fromTableId,
            'to_table_id' => $toTableId,
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reservation_id']);
        self::assertSame([$fromTableId], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->map(fn ($id) => (int) $id)->all());
        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $fromTableId)->value('status'));
        self::assertSame('Available', DB::table('restaurant_tables')->where('table_id', $toTableId)->value('status'));
    }

    public function test_move_table_rejects_when_from_table_is_not_assigned_to_reservation(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('move-table-invalid-source', $this->staffAuthHeaders($staffId));

        $assignedTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Occupied']);
        $unassignedTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $targetTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc(),
        ]);
        $this->attachReservationTable($reservationId, $assignedTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/move-table", [
            'from_table_id' => $unassignedTableId,
            'to_table_id' => $targetTableId,
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from_table_id']);
        self::assertSame([$assignedTableId], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_move_table_rejects_target_table_with_overlapping_hold(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('move-table-hold-conflict', $this->staffAuthHeaders($staffId));

        $fromTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Occupied']);
        $targetTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addMinutes(15);
        $end = $start->copy()->addHours(2);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc(),
            'start_time' => $start,
            'end_time' => $end,
        ]);
        $this->attachReservationTable($reservationId, $fromTableId);
        $this->createTableHold([
            'start_time' => $start->copy()->subMinutes(10),
            'end_time' => $end->copy()->addMinutes(10),
            'expire_at' => $this->nowUtc()->copy()->addMinutes(30),
            'hold_status' => 'Holding',
        ], [$targetTableId]);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/move-table", [
            'from_table_id' => $fromTableId,
            'to_table_id' => $targetTableId,
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to_table_id']);
        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $fromTableId)->value('status'));
        self::assertSame('Available', DB::table('restaurant_tables')->where('table_id', $targetTableId)->value('status'));
    }

    public function test_move_table_rejects_stale_row_version(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('move-table-row-version', $this->staffAuthHeaders($staffId));

        $fromTableId = $this->createRestaurantTableWithSeats(2, ['status' => 'Occupied']);
        $toTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc(),
            'row_version' => 2,
        ]);
        $this->attachReservationTable($reservationId, $fromTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/move-table", [
            'from_table_id' => $fromTableId,
            'to_table_id' => $toTableId,
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['row_version']);
        self::assertSame([$fromTableId], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_move_table_rejects_blocked_target_table(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('move-table-blocked-target', $this->staffAuthHeaders($staffId));

        $fromTableId = $this->createRestaurantTableWithSeats(2, ['status' => 'Occupied']);
        $blockedTargetId = $this->createRestaurantTableWithSeats(4, ['status' => 'Blocked']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc(),
        ]);
        $this->attachReservationTable($reservationId, $fromTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/move-table", [
            'from_table_id' => $fromTableId,
            'to_table_id' => $blockedTargetId,
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to_table_id']);
    }

    public function test_move_table_rejects_when_reservation_is_not_checked_in(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('move-table-wrong-status', $this->staffAuthHeaders($staffId));

        $fromTableId = $this->createRestaurantTableWithSeats(2, ['status' => 'Available']);
        $toTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $reservationId = $this->createReservation([
            'status' => 'Confirmed',
            'checked_in_at' => null,
        ]);
        $this->attachReservationTable($reservationId, $fromTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/move-table", [
            'from_table_id' => $fromTableId,
            'to_table_id' => $toTableId,
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
        self::assertSame([$fromTableId], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->map(fn ($id) => (int) $id)->all());
    }
}
