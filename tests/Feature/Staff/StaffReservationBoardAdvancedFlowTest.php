<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Services\RuntimeSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffReservationBoardAdvancedFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        config()->set('booking.check_in_grace_minutes', 15);
        config()->set('booking.no_show_grace_minutes', 15);
    }

    public function test_staff_table_board_includes_zone_groups_capacity_and_orchestration_context(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $now = $this->nowUtc()->copy()->setTime(10, 0);
        Carbon::setTestNow($now);

        $windowStart = $now->copy();
        $windowEnd = $now->copy()->addHours(3);

        $mainExactTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'MAIN-04',
            'zone' => 'Main',
            'status' => 'Available',
        ]);
        $mainOccupiedTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'MAIN-06',
            'zone' => 'Main',
            'status' => 'Available',
        ]);
        $patioTableId = $this->createRestaurantTableWithSeats(2, [
            'table_code' => 'PATIO-02',
            'zone' => 'Patio',
            'status' => 'Occupied',
        ]);

        $assignedReservationId = $this->createReservation([
            'start_time' => $now->copy()->addMinutes(10),
            'end_time' => $now->copy()->addHours(2),
            'guest_count' => 6,
            'status' => 'Reserved',
            'checked_in_at' => $now->copy()->subMinutes(5),
            'deposit_required_amount' => '200000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Pending',
        ]);
        $this->attachReservationTable($assignedReservationId, $mainOccupiedTableId);
        $activeOrderId = $this->createOrder([
            'reservation_id' => $assignedReservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);

        $dueSoonReservationId = $this->createReservation([
            'start_time' => $now->copy()->addMinutes(10),
            'end_time' => $now->copy()->addHours(1),
            'guest_count' => 4,
            'status' => 'Confirmed',
        ]);

        $lateReservationId = $this->createReservation([
            'start_time' => $now->copy()->subMinutes(5),
            'end_time' => $now->copy()->addHour(),
            'guest_count' => 2,
            'status' => 'Confirmed',
        ]);

        $overdueReservationId = $this->createReservation([
            'start_time' => $now->copy()->subMinutes(25),
            'end_time' => $now->copy()->addHour(),
            'guest_count' => 8,
            'status' => 'Confirmed',
        ]);

        $terminalReservationId = $this->createReservation([
            'start_time' => $now->copy()->addMinutes(15),
            'end_time' => $now->copy()->addHours(2),
            'guest_count' => 2,
            'status' => 'Cancelled',
        ]);
        $this->attachReservationTable($terminalReservationId, $patioTableId);

        $response = $this->withHeaders($headers)->getJson(sprintf(
            '/api/v1/staff/tables/board?from=%s&to=%s&include_holds=0',
            urlencode($windowStart->toIso8601String()),
            urlencode($windowEnd->toIso8601String()),
        ));

        $response->assertOk()
            ->assertJsonPath('summary.zone_count', 2)
            ->assertJsonPath('summary.active_order_count', 1)
            ->assertJsonPath('orchestration.mode', 'zone_capacity_candidate_matching');

        self::assertGreaterThanOrEqual(3, (int) data_get($response->json(), 'summary.unassigned_reservation_count', 0));

        $tables = collect($response->json('data'))->keyBy('table_id');
        self::assertSame(4, (int) $tables[$mainExactTableId]['capacity']['seats']);
        self::assertTrue((bool) $tables[$mainExactTableId]['availability']['accepts_new_assignment']);
        self::assertSame('reserved_in_range', $tables[$mainOccupiedTableId]['board_state']);
        self::assertSame($activeOrderId, (int) $tables[$mainOccupiedTableId]['active_order']['order_id']);
        self::assertSame('Pending', $tables[$mainOccupiedTableId]['reservation']['deposit']['status']);
        self::assertSame(150000.0, (float) $tables[$mainOccupiedTableId]['reservation']['deposit']['outstanding_amount']);
        self::assertSame('insufficient_capacity', $tables[$mainOccupiedTableId]['current_fit']['status']);

        $candidateReservationIds = collect($tables[$mainExactTableId]['candidate_reservations'])->pluck('reservation_id')->all();
        self::assertContains($dueSoonReservationId, $candidateReservationIds);
        self::assertContains($lateReservationId, $candidateReservationIds);
        self::assertNotContains($overdueReservationId, $candidateReservationIds);

        $zones = collect($response->json('zones'))->keyBy('zone');
        self::assertSame(2, (int) $zones['Main']['summary']['table_count']);
        self::assertSame(1, (int) $zones['Patio']['summary']['occupied_now_count']);

        $unassigned = collect($response->json('unassigned_reservations'))->keyBy('reservation_id');
        self::assertTrue((bool) $unassigned[$dueSoonReservationId]['flags']['due_soon']);
        self::assertTrue((bool) $unassigned[$lateReservationId]['flags']['late']);
        self::assertTrue((bool) $unassigned[$overdueReservationId]['flags']['overdue']);
        self::assertSame('exact_fit', data_get($unassigned[$dueSoonReservationId], 'orchestration.candidate_tables.0.fit.status'));
        self::assertSame(0, count((array) data_get($unassigned[$overdueReservationId], 'orchestration.candidate_tables', [])));
        self::assertFalse($unassigned->has($terminalReservationId));
    }

    public function test_staff_table_board_uses_runtime_grace_windows_for_unassigned_flags(): void
    {
        config()->set('booking.check_in_grace_minutes', 5);
        config()->set('booking.no_show_grace_minutes', 5);
        $this->app->instance(RuntimeSettingService::class, new class extends RuntimeSettingService
        {
            public function int(string $settingKey, int $fallback): int
            {
                return match ($settingKey) {
                    'checkin.grace_minutes',
                    'booking.check_in_grace_minutes',
                    'no_show.grace_minutes',
                    'booking.no_show_grace_minutes' => 20,
                    default => $fallback,
                };
            }
        });

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $now = $this->nowUtc()->copy()->setTime(10, 0);
        Carbon::setTestNow($now);

        $windowStart = $now->copy();
        $windowEnd = $now->copy()->addHours(2);

        $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'RUNTIME-MAIN-04',
            'zone' => 'Main',
            'status' => 'Available',
        ]);

        $dueSoonReservationId = $this->createReservation([
            'reservation_code' => 'RUNTIME-DUE-SOON',
            'status' => 'Confirmed',
            'guest_count' => 4,
            'start_time' => $now->copy()->addMinutes(10),
            'end_time' => $now->copy()->addHour(),
        ]);

        $lateReservationId = $this->createReservation([
            'reservation_code' => 'RUNTIME-LATE',
            'status' => 'Confirmed',
            'guest_count' => 4,
            'start_time' => $now->copy()->subMinutes(10),
            'end_time' => $now->copy()->addMinutes(50),
        ]);

        $response = $this->withHeaders($headers)->getJson(sprintf(
            '/api/v1/staff/tables/board?from=%s&to=%s&include_holds=0',
            urlencode($windowStart->toIso8601String()),
            urlencode($windowEnd->toIso8601String()),
        ));

        $response->assertOk();

        $unassigned = collect($response->json('unassigned_reservations'))->keyBy('reservation_id');
        self::assertTrue((bool) data_get($unassigned[$dueSoonReservationId], 'flags.due_soon'));
        self::assertTrue((bool) data_get($unassigned[$lateReservationId], 'flags.late'));
        self::assertFalse((bool) data_get($unassigned[$lateReservationId], 'flags.overdue'));
    }

    public function test_staff_can_filter_board_by_zone(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $start = $this->nowUtc()->copy()->addHour();
        $end = $start->copy()->addHours(2);
        $this->createRestaurantTableWithSeats(4, ['table_code' => 'ZONE-M-01', 'zone' => 'Main']);
        $this->createRestaurantTableWithSeats(4, ['table_code' => 'ZONE-P-01', 'zone' => 'Patio']);

        $response = $this->withHeaders($headers)->getJson(sprintf(
            '/api/v1/staff/tables/board?from=%s&to=%s&zone=Patio',
            urlencode($start->toIso8601String()),
            urlencode($end->toIso8601String()),
        ));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.zone', 'Patio')
            ->assertJsonCount(1, 'zones')
            ->assertJsonPath('zones.0.zone', 'Patio');
    }

    public function test_non_staff_cannot_view_board(): void
    {
        $customer = \App\Models\User::query()->findOrFail($this->createUser(['role_name' => 'Customer']));

        $response = $this->actingAs($customer)->getJson('/api/v1/staff/tables/board');

        $response->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');
    }
}
