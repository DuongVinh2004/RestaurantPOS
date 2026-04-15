<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
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
        $this->app->instance(RestaurantTableStateService::class, new RestaurantTableStateService);
        $this->app->instance(TableTimeConflictService::class, new TableTimeConflictService);
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

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'stale_row_version');
        $response->assertJsonValidationErrors(['row_version']);
        self::assertSame([$fromTableId], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_move_table_frees_original_table_from_confirmed_hold_conflicts(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('move-table-confirmed-hold-shadow', $this->staffAuthHeaders($staffId));

        $start = $this->nowUtc()->copy()->addHours(2)->startOfMinute();
        $end = $start->copy()->addHours(2);
        $fromTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Occupied', 'zone' => 'MOVE', 'table_code' => 'MV-01']);
        $toTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available', 'zone' => 'MOVE', 'table_code' => 'MV-02']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc(),
            'start_time' => $start,
            'end_time' => $end,
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $fromTableId);
        $this->createTableHold([
            'session_id' => 'move-confirmed-shadow',
            'start_time' => $start,
            'end_time' => $end,
            'expire_at' => $this->nowUtc()->copy()->subMinutes(5),
            'hold_status' => 'Confirmed',
            'confirmed_reservation_id' => $reservationId,
        ], [$fromTableId]);

        $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/move-table", [
            'from_table_id' => $fromTableId,
            'to_table_id' => $toTableId,
            'row_version' => 1,
        ])->assertOk();

        $availability = $this->getJson(sprintf(
            '/api/v1/tables/available?from=%s&to=%s&zone=MOVE&guest_count=2',
            urlencode($start->toIso8601String()),
            urlencode($end->toIso8601String()),
        ));

        $availability->assertOk();

        $tableIds = array_map('intval', array_column($availability->json('data'), 'table_id'));
        sort($tableIds);

        self::assertSame([$fromTableId], $tableIds);
    }

    public function test_move_table_rejects_when_source_table_still_has_another_active_service_context(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('move-table-source-still-busy', $this->staffAuthHeaders($staffId));

        $fromTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Occupied']);
        $toTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $start = $this->nowUtc()->copy()->subMinutes(10);
        $end = $this->nowUtc()->copy()->addHour();

        $movingReservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(5),
            'start_time' => $start,
            'end_time' => $end,
            'row_version' => 1,
        ]);
        $blockingReservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(2),
            'start_time' => $start,
            'end_time' => $end,
            'row_version' => 1,
        ]);

        $this->attachReservationTable($movingReservationId, $fromTableId);
        $this->attachReservationTable($blockingReservationId, $fromTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$movingReservationId}/move-table", [
            'from_table_id' => $fromTableId,
            'to_table_id' => $toTableId,
            'row_version' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['from_table_id'])
            ->assertJsonPath(
                'details.errors.from_table_id.0',
                'Original table still has another active service context: '
                .$blockingReservationId
                .'. Resolve that reservation before retrying the move.'
            );

        self::assertSame(
            [$fromTableId],
            DB::table('reservation_tables')
                ->where('reservation_id', $movingReservationId)
                ->pluck('table_id')
                ->map(fn ($id) => (int) $id)
                ->all()
        );
        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $fromTableId)->value('status'));
        self::assertSame('Available', DB::table('restaurant_tables')->where('table_id', $toTableId)->value('status'));
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
