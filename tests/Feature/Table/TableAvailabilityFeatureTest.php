<?php

declare(strict_types=1);

namespace Tests\Feature\Table;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class TableAvailabilityFeatureTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
    }

    public function test_available_endpoint_excludes_overlaps_and_keeps_own_session_hold_visible_for_same_session(): void
    {
        $start = $this->nowUtc()->copy()->addHours(3)->startOfMinute();
        $end = $start->copy()->addHours(2);

        $tableA = $this->createRestaurantTableWithSeats(2, ['zone' => 'A', 'table_code' => 'A-02']);
        $tableB = $this->createRestaurantTableWithSeats(2, ['zone' => 'A', 'table_code' => 'A-03']);
        $reservedTable = $this->createRestaurantTableWithSeats(4, ['zone' => 'A', 'table_code' => 'A-04']);
        $heldByOther = $this->createRestaurantTableWithSeats(4, ['zone' => 'A', 'table_code' => 'A-05']);

        $reservationId = $this->createReservation([
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $reservedTable);

        $this->createTableHold([
            'session_id' => 'other-session',
            'start_time' => $start,
            'end_time' => $end,
            'expire_at' => $this->nowUtc()->copy()->addMinutes(30),
        ], [$heldByOther]);

        $this->createTableHold([
            'session_id' => 'self-session',
            'start_time' => $start,
            'end_time' => $end,
            'expire_at' => $this->nowUtc()->copy()->addMinutes(30),
        ], [$tableB]);

        $response = $this->getJson(sprintf(
            '/api/v1/tables/available?from=%s&to=%s&zone=A&guest_count=4&suggest=1&session_id=self-session',
            urlencode($start->toIso8601String()),
            urlencode($end->toIso8601String()),
        ));

        $response->assertOk();

        $tableIds = array_map('intval', array_column($response->json('data'), 'table_id'));
        sort($tableIds);

        self::assertSame([$tableA, $tableB], $tableIds);

        $suggestions = $response->json('meta.suggestions');
        self::assertIsArray($suggestions);
        self::assertNotEmpty($suggestions);
        self::assertSame([$tableA, $tableB], $suggestions[0]['table_ids']);
        self::assertSame(4, $suggestions[0]['total_seats']);
    }

    public function test_available_endpoint_hides_same_hold_without_matching_session_context(): void
    {
        $start = $this->nowUtc()->copy()->addHours(4)->startOfMinute();
        $end = $start->copy()->addHours(2);

        $visibleTableId = $this->createRestaurantTableWithSeats(2, ['zone' => 'A', 'table_code' => 'A-10']);
        $ownHeldTableId = $this->createRestaurantTableWithSeats(2, ['zone' => 'A', 'table_code' => 'A-11']);

        $this->createTableHold([
            'session_id' => 'self-session',
            'start_time' => $start,
            'end_time' => $end,
            'expire_at' => $this->nowUtc()->copy()->addMinutes(45),
        ], [$ownHeldTableId]);

        $response = $this->getJson(sprintf(
            '/api/v1/tables/available?from=%s&to=%s&zone=A&guest_count=2',
            urlencode($start->toIso8601String()),
            urlencode($end->toIso8601String()),
        ));

        $response->assertOk();

        $tableIds = array_map('intval', array_column($response->json('data'), 'table_id'));
        sort($tableIds);

        self::assertSame([$visibleTableId], $tableIds);
    }

    public function test_available_endpoint_applies_turnover_buffer_and_filters_blocked_and_maintenance_tables(): void
    {
        config()->set('booking.service_buffer_minutes', 15);

        $start = $this->nowUtc()->copy()->addHours(6)->startOfMinute();
        $end = $start->copy()->addHours(2);

        $freeTableId = $this->createRestaurantTableWithSeats(4, ['zone' => 'B', 'table_code' => 'B-01', 'status' => 'Available']);
        $bufferConflictTableId = $this->createRestaurantTableWithSeats(4, ['zone' => 'B', 'table_code' => 'B-02', 'status' => 'Available']);
        $blockedTableId = $this->createRestaurantTableWithSeats(4, ['zone' => 'B', 'table_code' => 'B-03', 'status' => 'Blocked']);
        $maintenanceTableId = $this->createRestaurantTableWithSeats(4, ['zone' => 'B', 'table_code' => 'B-04', 'status' => 'Maintenance']);

        $bufferReservationId = $this->createReservation([
            'start_time' => $start->copy()->subHours(2),
            'end_time' => $start->copy()->subMinutes(10),
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($bufferReservationId, $bufferConflictTableId);

        $response = $this->getJson(sprintf(
            '/api/v1/tables/available?from=%s&to=%s&zone=B&guest_count=4',
            urlencode($start->toIso8601String()),
            urlencode($end->toIso8601String()),
        ));

        $response->assertOk();

        $tableIds = array_map('intval', array_column($response->json('data'), 'table_id'));
        sort($tableIds);

        self::assertSame([$freeTableId], $tableIds);
        self::assertNotContains($bufferConflictTableId, $tableIds);
        self::assertNotContains($blockedTableId, $tableIds);
        self::assertNotContains($maintenanceTableId, $tableIds);
    }

    public function test_available_endpoint_real_time_window_only_returns_tables_currently_marked_available(): void
    {
        $start = $this->nowUtc()->copy()->subMinute();
        $end = $this->nowUtc()->copy()->addMinutes(30);

        $availableTableId = $this->createRestaurantTableWithSeats(4, ['zone' => 'C', 'table_code' => 'C-01', 'status' => 'Available']);
        $occupiedTableId = $this->createRestaurantTableWithSeats(4, ['zone' => 'C', 'table_code' => 'C-02', 'status' => 'Occupied']);

        $response = $this->getJson(sprintf(
            '/api/v1/tables/available?from=%s&to=%s&zone=C&guest_count=4',
            urlencode($start->toIso8601String()),
            urlencode($end->toIso8601String()),
        ));

        $response->assertOk();

        $tableIds = array_map('intval', array_column($response->json('data'), 'table_id'));
        sort($tableIds);

        self::assertSame([$availableTableId], $tableIds);
        self::assertNotContains($occupiedTableId, $tableIds);
    }

    public function test_available_endpoint_uses_table_branch_even_when_reservation_and_hold_branch_copies_drift(): void
    {
        $annexBranchId = $this->createBranch([
            'branch_code' => 'AVDRIFT',
            'branch_name' => 'Availability Drift',
        ]);
        $start = $this->nowUtc()->copy()->addHours(5)->startOfMinute();
        $end = $start->copy()->addHours(2);

        $freeTableId = $this->createRestaurantTableWithSeats(4, ['branch_id' => 1, 'zone' => 'DRIFT', 'table_code' => 'D-01']);
        $reservedDriftTableId = $this->createRestaurantTableWithSeats(4, ['branch_id' => 1, 'zone' => 'DRIFT', 'table_code' => 'D-02']);
        $heldDriftTableId = $this->createRestaurantTableWithSeats(4, ['branch_id' => 1, 'zone' => 'DRIFT', 'table_code' => 'D-03']);

        $reservationId = $this->createReservation([
            'branch_id' => $annexBranchId,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $reservedDriftTableId);

        $this->createTableHold([
            'branch_id' => $annexBranchId,
            'session_id' => 'availability-drift-hold',
            'start_time' => $start,
            'end_time' => $end,
            'expire_at' => $this->nowUtc()->copy()->addMinutes(30),
            'hold_status' => 'Holding',
        ], [$heldDriftTableId]);

        $response = $this->getJson(sprintf(
            '/api/v1/tables/available?branch_id=1&from=%s&to=%s&zone=DRIFT&guest_count=2',
            urlencode($start->toIso8601String()),
            urlencode($end->toIso8601String()),
        ));

        $response->assertOk();

        $tableIds = array_map('intval', array_column($response->json('data'), 'table_id'));
        sort($tableIds);

        self::assertSame([$freeTableId], $tableIds);
    }

    public function test_available_endpoint_reports_branch_policy_rejection_for_closure_window(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'HCM01',
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
            'closure_windows' => [
                [
                    'start_local' => '2026-09-10 18:00:00',
                    'end_local' => '2026-09-10 20:00:00',
                    'type' => 'blackout',
                    'reason' => 'Su kien rieng',
                ],
            ],
        ]);
        $this->createRestaurantTableWithSeats(4, ['branch_id' => $branchId, 'zone' => 'VIP']);

        $start = now('Asia/Ho_Chi_Minh')->setDate(2026, 9, 10)->setTime(18, 30)->utc();
        $end = $start->copy()->addHour();

        $response = $this->getJson(sprintf(
            '/api/v1/tables/available?branch_id=%d&from=%s&to=%s&zone=VIP&guest_count=2',
            $branchId,
            urlencode($start->toIso8601String()),
            urlencode($end->toIso8601String()),
        ));

        $response->assertOk()
            ->assertJsonPath('meta.branch_id', $branchId)
            ->assertJsonPath('meta.branch_timezone', 'Asia/Ho_Chi_Minh')
            ->assertJsonPath('meta.availability_policy.allowed', false)
            ->assertJsonPath('meta.availability_policy.reason', 'closure_window');

        self::assertSame([], $response->json('data'));
    }
}
