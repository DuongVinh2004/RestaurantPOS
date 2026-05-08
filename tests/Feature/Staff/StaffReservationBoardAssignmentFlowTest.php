<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffReservationBoardAssignmentFlowTest extends TestCase
{
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

    public function test_staff_can_assign_suggested_table_and_board_updates_after_mutation(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('board-assign-suggested-success', $this->staffAuthHeaders($staffId));

        $now = $this->nowUtc()->copy()->setTime(11, 0);
        Carbon::setTestNow($now);

        $zone = 'Board Assign Suggested';
        $exactTableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'BOARD-04', 'zone' => $zone, 'status' => 'Available']);
        $oversizedTableId = $this->createRestaurantTableWithSeats(6, ['table_code' => 'BOARD-06', 'zone' => $zone, 'status' => 'Available']);
        $reservationId = $this->createReservation([
            'start_time' => $now->copy()->addMinutes(20),
            'end_time' => $now->copy()->addHours(2),
            'guest_count' => 4,
            'status' => 'Confirmed',
            'row_version' => 1,
        ]);

        $boardBefore = $this->withHeaders($this->staffAuthHeaders($staffId))->getJson(sprintf(
            '/api/v1/staff/tables/board?from=%s&to=%s&zone=%s',
            urlencode($now->copy()->addMinutes(20)->toIso8601String()),
            urlencode($now->copy()->addHours(2)->toIso8601String()),
            urlencode($zone),
        ));

        $boardBefore->assertOk()
            ->assertJsonPath('orchestration.write_side.assign_suggested_table_supported', true)
            ->assertJsonPath('orchestration.write_side.assign_best_fit_supported', true);
        self::assertSame(2, count((array) data_get(collect($boardBefore->json('unassigned_reservations'))->firstWhere('reservation_id', $reservationId), 'orchestration.candidate_tables', [])));
        self::assertSame(1, (int) data_get(collect($boardBefore->json('unassigned_reservations'))->firstWhere('reservation_id', $reservationId), 'row_version'));

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/assign-table", [
            'table_id' => $exactTableId,
            'row_version' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('assignment.mode', 'suggested_table')
            ->assertJsonPath('assignment.assigned_table.table_id', $exactTableId)
            ->assertJsonPath('assignment.rank', 1)
            ->assertJsonPath('assignment.fit.status', 'exact_fit')
            ->assertJsonPath('assignment.policy_flags.board_window_open', true)
            ->assertJsonPath('assignment.assignment_window.availability_mode', 'open_for_board_window')
            ->assertJsonPath('data.row_version', 2)
            ->assertJsonPath('data.table_ids.0', $exactTableId);
        self::assertContains('exact_capacity_match', (array) $response->json('assignment.reason_codes'));
        self::assertContains('primary_recommendation', (array) $response->json('assignment.reason_codes'));

        self::assertSame([$exactTableId], DB::table('reservation_tables')->where('reservation_id', $reservationId)->orderBy('table_id')->pluck('table_id')->map(fn ($id) => (int) $id)->all());
        self::assertSame(2, (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'));

        $boardAfter = $this->withHeaders($this->staffAuthHeaders($staffId))->getJson(sprintf(
            '/api/v1/staff/tables/board?from=%s&to=%s&zone=%s',
            urlencode($now->copy()->addMinutes(20)->toIso8601String()),
            urlencode($now->copy()->addHours(2)->toIso8601String()),
            urlencode($zone),
        ));

        $boardAfter->assertOk()->assertJsonPath('summary.unassigned_reservation_count', 0);
        self::assertSame([$reservationId], collect($boardAfter->json('data'))->where('table_id', $exactTableId)->pluck('reservation.reservation_id')->filter()->values()->all());
        self::assertTrue((bool) collect($boardAfter->json('data'))->firstWhere('table_id', $oversizedTableId)['availability']['accepts_new_assignment']);
    }

    public function test_staff_can_assign_best_fit_and_then_check_in(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $assignHeaders = $this->withIdempotencyKey('board-assign-best-fit-success', $this->staffAuthHeaders($staffId));
        $checkInHeaders = $this->withIdempotencyKey('board-assign-best-fit-checkin', $this->staffAuthHeaders($staffId));

        $now = $this->nowUtc()->copy()->setTime(18, 0);
        Carbon::setTestNow($now);
        config()->set('booking.check_in_grace_minutes', 15);

        $bestFitTableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'FIT-04', 'zone' => 'Main', 'status' => 'Available']);
        $this->createRestaurantTableWithSeats(6, ['table_code' => 'FIT-06', 'zone' => 'Main', 'status' => 'Available']);
        $reservationId = $this->createReservation([
            'start_time' => $now->copy()->addMinutes(10),
            'end_time' => $now->copy()->addHours(2),
            'guest_count' => 4,
            'status' => 'Confirmed',
        ]);

        $assignResponse = $this->withHeaders($assignHeaders)->postJson("/api/v1/staff/reservations/{$reservationId}/assign-best-fit", [
            'row_version' => 1,
        ]);

        $assignResponse->assertOk()
            ->assertJsonPath('assignment.mode', 'best_fit')
            ->assertJsonPath('assignment.assigned_table.table_id', $bestFitTableId)
            ->assertJsonPath('assignment.rank', 1)
            ->assertJsonPath('assignment.fit.status', 'exact_fit')
            ->assertJsonPath('assignment.assignment_window.availability_mode', 'open_for_board_window')
            ->assertJsonPath('data.row_version', 2);
        self::assertContains('exact_capacity_match', (array) $assignResponse->json('assignment.reason_codes'));

        $checkInResponse = $this->withHeaders($checkInHeaders)->postJson("/api/v1/staff/reservations/{$reservationId}/check-in", [
            'row_version' => 2,
            'checked_in_at' => $now->copy()->addMinutes(10)->toIso8601String(),
        ]);

        $checkInResponse->assertOk()
            ->assertJsonPath('data.status', 'Reserved')
            ->assertJsonPath('data.table_ids.0', $bestFitTableId);
        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $bestFitTableId)->value('status'));
    }

    public function test_assign_best_fit_uses_current_board_window_context(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('board-assign-best-fit-window-context', $this->staffAuthHeaders($staffId));

        $now = $this->nowUtc()->copy()->setTime(9, 0);
        Carbon::setTestNow($now);

        $slotOnlyTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'AA-04',
            'zone' => 'Main',
            'status' => 'Available',
        ]);
        $openWindowTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'ZZ-04',
            'zone' => 'Main',
            'status' => 'Available',
        ]);

        $blockingReservationId = $this->createReservation([
            'start_time' => $now->copy()->addMinutes(30),
            'end_time' => $now->copy()->addHour()->addMinutes(30),
            'guest_count' => 4,
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($blockingReservationId, $slotOnlyTableId);

        $reservationId = $this->createReservation([
            'start_time' => $now->copy()->addHours(3),
            'end_time' => $now->copy()->addHours(4),
            'guest_count' => 4,
            'status' => 'Confirmed',
            'row_version' => 1,
        ]);

        $boardFrom = $now->copy()->toIso8601String();
        $boardTo = $now->copy()->addHours(5)->toIso8601String();

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/assign-best-fit", [
            'row_version' => 1,
            'board_from' => $boardFrom,
            'board_to' => $boardTo,
            'zone' => 'Main',
        ]);

        $response->assertOk()
            ->assertJsonPath('assignment.mode', 'best_fit')
            ->assertJsonPath('assignment.assigned_table.table_id', $openWindowTableId)
            ->assertJsonPath('assignment.assignment_request_context.board_from', $boardFrom)
            ->assertJsonPath('assignment.assignment_request_context.board_to', $boardTo)
            ->assertJsonPath('assignment.assignment_request_context.zone', 'Main')
            ->assertJsonPath('assignment.assignment_request_context.include_slot_only_candidates', true)
            ->assertJsonPath('assignment.assignment_window.availability_mode', 'open_for_board_window');

        self::assertSame([$openWindowTableId], DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    public function test_assign_table_rejects_non_available_target_table(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('board-assign-busy-target', $this->staffAuthHeaders($staffId));

        $busyTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'guest_count' => 4,
            'status' => 'Confirmed',
        ]);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/assign-table", [
            'table_id' => $busyTableId,
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['table_id']);
        self::assertSame([], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->all());
    }

    public function test_assign_table_rejects_when_capacity_does_not_fit(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('board-assign-capacity', $this->staffAuthHeaders($staffId));

        $smallTableId = $this->createRestaurantTableWithSeats(2, ['status' => 'Available']);
        $reservationId = $this->createReservation([
            'guest_count' => 4,
            'status' => 'Confirmed',
        ]);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/assign-table", [
            'table_id' => $smallTableId,
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['table_id']);
        self::assertSame([], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->all());
    }

    public function test_assign_table_rejects_target_table_with_branch_mismatch(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('board-assign-branch-mismatch', $this->staffAuthHeaders($staffId));
        $annexBranchId = $this->createBranch([
            'branch_code' => 'BRDANNEX',
            'branch_name' => 'Board Annex',
        ]);

        $tableId = $this->createRestaurantTableWithSeats(4, [
            'status' => 'Available',
            'branch_id' => $annexBranchId,
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => 1,
            'guest_count' => 4,
            'status' => 'Confirmed',
        ]);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/assign-table", [
            'table_id' => $tableId,
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['table_id']);
        self::assertSame([], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->all());
    }

    public function test_assign_table_rejects_target_table_outside_current_board_zone_context(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('board-assign-zone-context', $this->staffAuthHeaders($staffId));

        $mainTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'MAIN-04',
            'zone' => 'Main',
            'status' => 'Available',
        ]);
        $otherZoneTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'PATIO-04',
            'zone' => 'Patio',
            'status' => 'Available',
        ]);
        $reservationId = $this->createReservation([
            'guest_count' => 4,
            'status' => 'Confirmed',
            'row_version' => 1,
        ]);

        $boardFrom = $this->nowUtc()->copy()->toIso8601String();
        $boardTo = $this->nowUtc()->copy()->addHours(2)->toIso8601String();

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/assign-table", [
            'table_id' => $otherZoneTableId,
            'row_version' => 1,
            'board_from' => $boardFrom,
            'board_to' => $boardTo,
            'zone' => 'Main',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);

        self::assertSame([], DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->pluck('table_id')
            ->all());
        self::assertSame('Available', DB::table('restaurant_tables')->where('table_id', $mainTableId)->value('status'));
    }

    public function test_assign_table_is_idempotent_for_same_already_assigned_target(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('board-assign-replay', $this->staffAuthHeaders($staffId));

        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $reservationId = $this->createReservation([
            'guest_count' => 4,
            'status' => 'Confirmed',
            'row_version' => 2,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/assign-table", [
            'table_id' => $tableId,
            'row_version' => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.table_ids.0', $tableId)
            ->assertJsonPath('data.row_version', 2);
        self::assertSame(1, DB::table('reservation_tables')->where('reservation_id', $reservationId)->count());
    }

    public function test_assign_table_rejects_stale_row_version(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('board-assign-stale-row-version', $this->staffAuthHeaders($staffId));

        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $reservationId = $this->createReservation([
            'guest_count' => 4,
            'status' => 'Confirmed',
            'row_version' => 2,
        ]);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/assign-table", [
            'table_id' => $tableId,
            'row_version' => 1,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonValidationErrors(['row_version']);

        self::assertStringContainsString('row_version mismatch', (string) data_get($response->json(), 'details.errors.row_version.0', ''));
        self::assertSame([], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->all());
    }

    public function test_assign_table_rejects_when_reservation_already_has_other_assigned_table(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('board-assign-already-assigned', $this->staffAuthHeaders($staffId));

        $currentTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $otherTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $reservationId = $this->createReservation([
            'guest_count' => 4,
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $currentTableId);

        $response = $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/assign-table", [
            'table_id' => $otherTableId,
            'row_version' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reservation_id']);
        self::assertSame([$currentTableId], DB::table('reservation_tables')->where('reservation_id', $reservationId)->pluck('table_id')->map(fn ($id) => (int) $id)->all());
    }
}
