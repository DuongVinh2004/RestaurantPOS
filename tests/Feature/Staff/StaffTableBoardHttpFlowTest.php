<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffTableBoardHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.api_keys', []);
        $this->resetBoardFixtures();
    }

    public function test_staff_table_board_preserves_legacy_shape_and_adds_actionable_hints(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'board-key');

        $tableId = $this->createRestaurantTable(['zone' => 'Main']);
        $now = Carbon::now('UTC')->startOfMinute();
        $reservationId = $this->createReservation([
            'status' => 'Confirmed',
            'start_time' => $now->copy()->subMinutes(5),
            'end_time' => $now->copy()->addHour(),
            'guest_count' => 4,
            'row_version' => 7,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/tables/board?from=' . urlencode($now->copy()->subHour()->toIso8601String()) . '&to=' . urlencode($now->copy()->addHour()->toIso8601String()));

        $response->assertOk();
        $response->assertJsonPath('meta.supported_actions.check_in.endpoint_template', '/api/v1/staff/reservations/{reservation_id}/check-in');

        $row = collect($response->json('data'))->firstWhere('table_id', $tableId);
        self::assertIsArray($row);
        self::assertSame('reserved_in_range', $row['board_state']);
        self::assertArrayHasKey('reservations', $row);
        self::assertArrayHasKey('holds', $row);
        self::assertArrayHasKey('reservation', $row);
        self::assertArrayHasKey('hold', $row);

        self::assertSame(4, (int) $row['capacity']['seats']);
        self::assertFalse((bool) $row['availability']['accepts_new_assignment']);
        self::assertSame('check_in', $row['operational_hints']['preferred_action']);
        self::assertTrue((bool) $row['actions']['check_in']['available']);
        self::assertSame('/api/v1/staff/reservations/' . $reservationId . '/check-in', $row['actions']['check_in']['endpoint']);
        self::assertSame(7, (int) $row['actions']['check_in']['preferred_payload']['row_version']);
        self::assertSame([$tableId], $row['actions']['check_in']['preferred_payload']['table_ids']);
    }

    public function test_staff_table_board_legacy_alias_still_works_and_zone_filter_is_preserved(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'legacy-board-key');

        $mainTableId = $this->createRestaurantTable(['zone' => 'Main']);
        $this->createRestaurantTable(['zone' => 'Patio']);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/table-board?zone=Main');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('meta.filters.zone', 'Main');

        $row = $response->json('data.0');
        self::assertSame($mainTableId, (int) $row['table_id']);
        self::assertSame('available', $row['board_state']);
        self::assertTrue((bool) $row['availability']['accepts_new_assignment']);
        self::assertSame('assignment_candidate', $row['operational_hints']['preferred_action']);
    }

    public function test_staff_table_board_surfaces_move_table_action_for_checked_in_table(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'move-key');

        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $now = Carbon::now('UTC')->startOfMinute();
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => $now->copy()->subMinutes(10),
            'start_time' => $now->copy()->subMinutes(30),
            'end_time' => $now->copy()->addHour(),
            'row_version' => 3,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/tables/board?from=' . urlencode($now->copy()->subHour()->toIso8601String()) . '&to=' . urlencode($now->copy()->addHour()->toIso8601String()));

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('table_id', $tableId);
        self::assertIsArray($row);
        self::assertSame('occupied_now', $row['board_state']);
        self::assertTrue((bool) $row['actions']['move_table']['available']);
        self::assertSame('/api/v1/staff/reservations/' . $reservationId . '/move-table', $row['actions']['move_table']['endpoint']);
        self::assertSame($tableId, (int) $row['actions']['move_table']['preferred_payload']['from_table_id']);
        self::assertSame(3, (int) $row['actions']['move_table']['preferred_payload']['row_version']);
        self::assertFalse((bool) $row['actions']['check_in']['available']);
    }

    public function test_staff_table_board_hides_check_in_when_multi_table_reservation_has_non_ready_assigned_table(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'multi-table-checkin-key');

        $availableTableId = $this->createRestaurantTableWithSeats(2, ['zone' => 'Main', 'status' => 'Available']);
        $occupiedTableId = $this->createRestaurantTableWithSeats(2, ['zone' => 'Main', 'status' => 'Occupied']);
        $now = Carbon::now('UTC')->startOfMinute();
        $reservationId = $this->createReservation([
            'status' => 'Confirmed',
            'start_time' => $now->copy()->subMinutes(5),
            'end_time' => $now->copy()->addHour(),
            'guest_count' => 4,
            'row_version' => 9,
        ]);
        $this->attachReservationTable($reservationId, $availableTableId);
        $this->attachReservationTable($reservationId, $occupiedTableId);

        $response = $this->withHeaders($headers)->getJson(
            '/api/v1/staff/tables/board?from=' . urlencode($now->copy()->subHour()->toIso8601String()) . '&to=' . urlencode($now->copy()->addHour()->toIso8601String())
        );

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('table_id', $availableTableId);
        self::assertIsArray($row);
        self::assertSame($reservationId, (int) data_get($row, 'reservation.reservation_id'));
        self::assertFalse((bool) data_get($row, 'actions.check_in.available'));
        self::assertFalse((bool) data_get($row, 'actions.check_in.checks.all_assigned_tables_available'));
        self::assertSame('none', data_get($row, 'operational_hints.preferred_action'));
    }

    public function test_staff_table_board_hides_check_in_when_assigned_table_has_active_hold_conflict(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'board-hold-conflict-key');

        $tableId = $this->createRestaurantTableWithSeats(4, ['zone' => 'Main', 'status' => 'Available']);
        $now = Carbon::now('UTC')->startOfMinute();
        $reservationId = $this->createReservation([
            'status' => 'Confirmed',
            'start_time' => $now->copy()->subMinutes(5),
            'end_time' => $now->copy()->addHour(),
            'guest_count' => 4,
            'row_version' => 5,
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createTableHold([
            'start_time' => $now->copy()->subMinutes(10),
            'end_time' => $now->copy()->addMinutes(15),
            'expire_at' => $now->copy()->addMinutes(30),
            'hold_status' => 'Holding',
        ], [$tableId]);

        $response = $this->withHeaders($headers)->getJson(
            '/api/v1/staff/tables/board?from=' . urlencode($now->copy()->subHour()->toIso8601String()) . '&to=' . urlencode($now->copy()->addHour()->toIso8601String())
        );

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('table_id', $tableId);
        self::assertIsArray($row);
        self::assertFalse((bool) data_get($row, 'actions.check_in.available'));
        self::assertSame('assigned_table_hold_conflict', data_get($row, 'actions.check_in.blocked_reason_code'));
        self::assertTrue((bool) data_get($row, 'actions.check_in.checks.has_assigned_table_hold_conflict'));
        self::assertSame('none', data_get($row, 'operational_hints.preferred_action'));
    }

    public function test_staff_table_board_keeps_same_reservation_confirmed_hold_visible_without_blocking_check_in(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'board-own-confirmed-hold-key');

        $tableId = $this->createRestaurantTableWithSeats(4, ['zone' => 'Main', 'status' => 'Available']);
        $now = Carbon::now('UTC')->startOfMinute();
        $reservationId = $this->createReservation([
            'status' => 'Confirmed',
            'start_time' => $now->copy()->subMinutes(5),
            'end_time' => $now->copy()->addHour(),
            'guest_count' => 4,
            'row_version' => 8,
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createTableHold([
            'session_id' => 'board-own-confirmed-hold-session',
            'hold_status' => 'Confirmed',
            'confirmed_reservation_id' => $reservationId,
            'start_time' => $now->copy()->subMinutes(10),
            'end_time' => $now->copy()->addMinutes(15),
            'expire_at' => $now->copy()->subMinutes(30),
        ], [$tableId]);

        $response = $this->withHeaders($headers)->getJson(
            '/api/v1/staff/tables/board?from=' . urlencode($now->copy()->subHour()->toIso8601String()) . '&to=' . urlencode($now->copy()->addHour()->toIso8601String())
        );

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('table_id', $tableId);
        self::assertIsArray($row);
        self::assertSame('Confirmed', data_get($row, 'hold.hold_status'));
        self::assertTrue((bool) data_get($row, 'actions.check_in.available'));
        self::assertSame(null, data_get($row, 'actions.check_in.blocked_reason_code'));
        self::assertFalse((bool) data_get($row, 'actions.check_in.checks.has_assigned_table_hold_conflict'));
        self::assertFalse((bool) data_get($row, 'actions.check_in.checks.has_hold_conflict'));
        self::assertSame('check_in', data_get($row, 'operational_hints.preferred_action'));
    }

    public function test_staff_table_board_hides_check_in_when_assigned_table_branch_drifts_from_reservation(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'board-branch-drift-key');
        $annexBranchId = $this->createBranch([
            'branch_code' => 'BOARDRIFT',
            'branch_name' => 'Board Drift Annex',
        ]);

        $tableId = $this->createRestaurantTableWithSeats(4, [
            'zone' => 'Main',
            'status' => 'Available',
            'branch_id' => $annexBranchId,
        ]);
        $now = Carbon::now('UTC')->startOfMinute();
        $reservationId = $this->createReservation([
            'status' => 'Confirmed',
            'branch_id' => 1,
            'start_time' => $now->copy()->subMinutes(5),
            'end_time' => $now->copy()->addHour(),
            'guest_count' => 4,
            'row_version' => 6,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->getJson(
            '/api/v1/staff/tables/board?from=' . urlencode($now->copy()->subHour()->toIso8601String()) . '&to=' . urlencode($now->copy()->addHour()->toIso8601String())
        );

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('table_id', $tableId);
        self::assertIsArray($row);
        self::assertFalse((bool) data_get($row, 'actions.check_in.available'));
        self::assertSame('branch_mismatch', data_get($row, 'actions.check_in.blocked_reason_code'));
        self::assertFalse((bool) data_get($row, 'actions.check_in.checks.branch_consistent'));
        self::assertSame('none', data_get($row, 'operational_hints.preferred_action'));
    }

    public function test_staff_table_board_surfaces_move_table_action_for_historical_checked_in_status_without_timestamp(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'historical-move-key');

        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $now = Carbon::now('UTC')->startOfMinute();
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => null,
            'start_time' => $now->copy()->subMinutes(30),
            'end_time' => $now->copy()->addHour(),
            'row_version' => 4,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->getJson(
            '/api/v1/staff/tables/board?from=' . urlencode($now->copy()->subHour()->toIso8601String()) . '&to=' . urlencode($now->copy()->addHour()->toIso8601String())
        );

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('table_id', $tableId);
        self::assertIsArray($row);
        self::assertTrue((bool) data_get($row, 'actions.move_table.available'));
        self::assertSame('/api/v1/staff/reservations/' . $reservationId . '/move-table', data_get($row, 'actions.move_table.endpoint'));
        self::assertSame('move_table', data_get($row, 'operational_hints.preferred_action'));
    }

    public function test_non_staff_cannot_view_table_board(): void
    {
        $response = $this->getJson('/api/v1/staff/tables/board');

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'unauthorized');
    }


    private function resetBoardFixtures(): void
    {
        DB::table('reservation_order_items')->delete();
        DB::table('reservation_orders')->delete();
        DB::table('reservation_tables')->delete();
        DB::table('table_hold_details')->delete();
        DB::table('table_holds')->delete();
        DB::table('payments')->delete();
        DB::table('reservations')->delete();
        DB::table('restaurant_tables')->delete();
        DB::table('table_templates')->delete();
    }
}
