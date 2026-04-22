<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffTableBoardFeatureTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        $this->resetBoardFixtures();
    }

    public function test_staff_table_board_exposes_reservation_and_hold_state_on_canonical_and_alias_routes(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $start = $this->nowUtc()->copy()->addHours(2)->startOfMinute();
        $end = $start->copy()->addHours(2);

        $reservedTableId = $this->createRestaurantTable(['zone' => 'A', 'table_code' => 'BOARD-A1']);
        $heldTableId = $this->createRestaurantTable(['zone' => 'A', 'table_code' => 'BOARD-A2']);

        $reservationId = $this->createReservation([
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $reservedTableId);

        $this->createTableHold([
            'session_id' => 'board-hold-session',
            'start_time' => $start,
            'end_time' => $end,
            'expire_at' => $this->nowUtc()->copy()->addMinutes(30),
        ], [$heldTableId]);

        $query = sprintf('?from=%s&to=%s&zone=A&include_holds=1', urlencode($start->toIso8601String()), urlencode($end->toIso8601String()));

        $canonical = $this->withHeaders($headers)->getJson('/api/v1/staff/tables/board'.$query);
        $alias = $this->withHeaders($headers)->getJson('/api/v1/staff/table-board'.$query);

        $canonical->assertOk();
        $alias->assertOk();

        $canonicalData = collect($canonical->json('data'))->keyBy('table_id');
        $aliasData = collect($alias->json('data'))->keyBy('table_id');

        self::assertSame('reserved_in_range', $canonicalData[$reservedTableId]['board_state']);
        self::assertSame('held_in_range', $canonicalData[$heldTableId]['board_state']);
        self::assertSame($canonicalData[$reservedTableId]['board_state'], $aliasData[$reservedTableId]['board_state']);
        self::assertSame($canonicalData[$heldTableId]['board_state'], $aliasData[$heldTableId]['board_state']);
    }

    public function test_staff_table_board_hides_confirmed_hold_artifact_when_reservation_no_longer_uses_that_table(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $start = $this->nowUtc()->copy()->addHour()->startOfMinute();
        $end = $start->copy()->addHours(2);
        $heldTableId = $this->createRestaurantTable(['zone' => 'A', 'table_code' => 'BOARD-C1']);
        $currentTableId = $this->createRestaurantTable(['zone' => 'A', 'table_code' => 'BOARD-C2', 'status' => 'Occupied']);

        $reservationId = $this->createReservation([
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(5),
        ]);
        $this->attachReservationTable($reservationId, $currentTableId);

        $this->createTableHold([
            'session_id' => 'board-confirmed-hold-session',
            'hold_status' => 'Confirmed',
            'confirmed_reservation_id' => $reservationId,
            'start_time' => $start,
            'end_time' => $end,
            'expire_at' => $this->nowUtc()->copy()->subMinutes(10),
        ], [$heldTableId]);

        $response = $this->withHeaders($headers)->getJson(sprintf(
            '/api/v1/staff/tables/board?from=%s&to=%s&zone=A&include_holds=1',
            urlencode($start->toIso8601String()),
            urlencode($end->toIso8601String()),
        ));

        $response->assertOk();

        $heldTable = collect($response->json('data'))->firstWhere('table_id', $heldTableId);
        $currentTable = collect($response->json('data'))->firstWhere('table_id', $currentTableId);

        self::assertNotNull($heldTable);
        self::assertNotNull($currentTable);
        self::assertSame('available', data_get($heldTable, 'board_state'));
        self::assertNull(data_get($heldTable, 'hold'));
        self::assertSame('occupied_now', data_get($currentTable, 'board_state'));
        self::assertSame($reservationId, data_get($currentTable, 'reservation.reservation_id'));
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
