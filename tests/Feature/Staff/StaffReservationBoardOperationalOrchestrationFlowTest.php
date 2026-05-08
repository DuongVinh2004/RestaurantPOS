<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffReservationBoardOperationalOrchestrationFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        config()->set('booking.staff_table_board_candidate_preview_limit', 5);
        config()->set('booking.staff_table_board_close_fit_max_extra_seats', 2);
    }

    public function test_board_surfaces_slot_only_candidates_and_ranks_open_window_candidate_first(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $now = $this->nowUtc()->copy()->setTime(9, 0);
        Carbon::setTestNow($now);
        $zone = 'Board Orchestration Slot';

        $slotOnlyTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'AA-04',
            'zone' => $zone,
            'status' => 'Available',
        ]);
        $openWindowTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'ZZ-04',
            'zone' => $zone,
            'status' => 'Available',
        ]);

        $earlierAssignedReservationId = $this->createReservation([
            'start_time' => $now->copy()->addMinutes(30),
            'end_time' => $now->copy()->addHour()->addMinutes(30),
            'guest_count' => 4,
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($earlierAssignedReservationId, $slotOnlyTableId);

        $targetReservationId = $this->createReservation([
            'start_time' => $now->copy()->addHours(3),
            'end_time' => $now->copy()->addHours(4),
            'guest_count' => 4,
            'status' => 'Confirmed',
        ]);

        $response = $this->withHeaders($headers)->getJson(sprintf(
            '/api/v1/staff/tables/board?from=%s&to=%s&include_holds=0&zone=%s',
            urlencode($now->copy()->toIso8601String()),
            urlencode($now->copy()->addHours(5)->toIso8601String()),
            urlencode($zone),
        ));

        $response->assertOk()
            ->assertJsonPath('summary.unassigned_reservation_count', 1)
            ->assertJsonPath('summary.unassigned_with_slot_only_candidate_count', 1)
            ->assertJsonPath('orchestration.write_side.assign_suggested_table_requires_current_candidate', true)
            ->assertJsonPath('orchestration.capacity_policy.close_fit_max_extra_seats', 2);

        $unassigned = collect($response->json('unassigned_reservations'))->keyBy('reservation_id');
        $target = $unassigned[$targetReservationId];
        self::assertSame(2, (int) data_get($target, 'orchestration.candidate_table_count'));
        self::assertSame('ZZ-04', (string) data_get($target, 'orchestration.candidate_tables.0.table_code'));
        self::assertSame('AA-04', (string) data_get($target, 'orchestration.candidate_tables.1.table_code'));
        self::assertSame(1, (int) data_get($target, 'orchestration.candidate_tables.0.rank'));
        self::assertSame(2, (int) data_get($target, 'orchestration.candidate_tables.1.rank'));
        self::assertFalse((bool) data_get($target, 'orchestration.candidate_tables.0.policy_flags.slot_only_candidate'));
        self::assertTrue((bool) data_get($target, 'orchestration.candidate_tables.1.policy_flags.slot_only_candidate'));
        self::assertSame('open_for_board_window', (string) data_get($target, 'orchestration.candidate_tables.0.assignment_window.availability_mode'));
        self::assertSame('slot_available_busy_elsewhere_in_board_window', (string) data_get($target, 'orchestration.candidate_tables.1.assignment_window.availability_mode'));
        self::assertContains('primary_recommendation', (array) data_get($target, 'orchestration.candidate_tables.0.reason_codes', []));
        self::assertContains('table_busy_elsewhere_in_board_window_but_free_for_reservation_window', (array) data_get($target, 'orchestration.candidate_tables.1.reason_codes', []));

        $tables = collect($response->json('data'))->keyBy('table_id');
        self::assertSame('reserved_in_range', (string) $tables[$slotOnlyTableId]['board_state']);
        self::assertSame($targetReservationId, (int) data_get($tables[$slotOnlyTableId], 'candidate_reservations.0.reservation_id'));
        self::assertTrue((bool) data_get($tables[$slotOnlyTableId], 'candidate_reservations.0.policy_flags.slot_only_candidate'));
        self::assertSame('ZZ-04', (string) data_get($target, 'orchestration.best_fit_table.table_code'));
        self::assertSame($openWindowTableId, (int) data_get($target, 'orchestration.best_fit_table.table_id'));
    }

    public function test_board_candidate_preview_excludes_tables_from_other_branches(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);
        $annexBranchId = $this->createBranch([
            'branch_code' => 'CANDANNX',
            'branch_name' => 'Candidate Annex',
        ]);

        $now = $this->nowUtc()->copy()->setTime(9, 0);
        Carbon::setTestNow($now);
        $zone = 'Board Candidate Branch';

        $sameBranchTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'MAIN-04',
            'zone' => $zone,
            'status' => 'Available',
            'branch_id' => 1,
        ]);
        $otherBranchTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'ANNEX-04',
            'zone' => $zone,
            'status' => 'Available',
            'branch_id' => $annexBranchId,
        ]);

        $targetReservationId = $this->createReservation([
            'branch_id' => 1,
            'start_time' => $now->copy()->addHours(2),
            'end_time' => $now->copy()->addHours(3),
            'guest_count' => 4,
            'status' => 'Confirmed',
        ]);

        $response = $this->withHeaders($headers)->getJson(sprintf(
            '/api/v1/staff/tables/board?from=%s&to=%s&include_holds=0&zone=%s',
            urlencode($now->copy()->toIso8601String()),
            urlencode($now->copy()->addHours(4)->toIso8601String()),
            urlencode($zone),
        ));

        $response->assertOk();

        $unassigned = collect($response->json('unassigned_reservations'))->keyBy('reservation_id');
        self::assertSame(1, (int) data_get($unassigned[$targetReservationId], 'orchestration.candidate_table_count'));
        self::assertSame($sameBranchTableId, (int) data_get($unassigned[$targetReservationId], 'orchestration.candidate_tables.0.table_id'));
        self::assertNotContains($otherBranchTableId, collect((array) data_get($unassigned[$targetReservationId], 'orchestration.candidate_tables', []))->pluck('table_id')->all());
    }
}
