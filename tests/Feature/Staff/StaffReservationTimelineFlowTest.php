<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffReservationTimelineFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        config()->set('app.timezone', 'UTC');
        config()->set('booking.multi_branch.default_branch_timezone', 'Asia/Ho_Chi_Minh');
    }

    public function test_staff_can_view_timeline_grouped_by_slots_with_operational_flags(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        [$staffId, $zoneTableId, $dueSoonReservationId, $lateReservationId, $overdueReservationId, $checkedInReservationId] = $this->seedTimelineScenario();

        $response = $this->withHeaders($this->staffHeaders($staffId, 'staff-timeline-main'))
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&slot_minutes=30');

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'reservation_timeline')
            ->assertJsonPath('data.slot_minutes', 30)
            ->assertJsonPath('data.summary.total_reservations', 4)
            ->assertJsonPath('data.summary.flag_counts.due_soon', 1)
            ->assertJsonPath('data.summary.flag_counts.late', 1)
            ->assertJsonPath('data.summary.flag_counts.overdue', 1)
            ->assertJsonPath('data.summary.flag_counts.checked_in', 1)
            ->assertJsonPath('data.summary.flag_counts.has_active_order', 1)
            ->assertJsonPath('data.summary.flag_counts.needs_assignment', 0)
            ->assertJsonPath('data.calendar.lane_mode', 'slot')
            ->assertJsonPath('data.calendar.has_lane_grouping', false)
            ->assertJsonPath('data.items.0.deposit.currency', 'VND');

        $items = collect($response->json('data.items'))->keyBy(fn (array $item): int => (int) $item['reservation']['reservation_id']);

        self::assertSame('due_soon', $items[$dueSoonReservationId]['operational_state']);
        self::assertSame('late', $items[$lateReservationId]['operational_state']);
        self::assertSame('overdue', $items[$overdueReservationId]['operational_state']);
        self::assertSame('checked_in', $items[$checkedInReservationId]['operational_state']);
        self::assertTrue((bool) $items[$checkedInReservationId]['flags']['has_active_order']);
        self::assertSame($zoneTableId, (int) $items[$checkedInReservationId]['reservation']['table_ids'][0]);
        self::assertSame('zone:Main', $items[$checkedInReservationId]['calendar']['primary_zone_lane_key']);
        self::assertSame('assigned', $items[$checkedInReservationId]['orchestration']['assignment_state']);
    }

    public function test_staff_timeline_uses_runtime_grace_windows_for_operational_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));
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

        [$staffId, , $dueSoonReservationId, $lateReservationId, $overdueReservationId] = $this->seedTimelineScenario();

        $response = $this->withHeaders($this->staffHeaders($staffId, 'staff-timeline-runtime-grace'))
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&slot_minutes=30');

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.flag_counts.due_soon', 1)
            ->assertJsonPath('data.summary.flag_counts.late', 1)
            ->assertJsonPath('data.summary.flag_counts.overdue', 1);

        $items = collect($response->json('data.items'))->keyBy(fn (array $item): int => (int) $item['reservation']['reservation_id']);
        self::assertSame('due_soon', $items[$dueSoonReservationId]['operational_state']);
        self::assertSame('late', $items[$lateReservationId]['operational_state']);
        self::assertSame('overdue', $items[$overdueReservationId]['operational_state']);
    }

    public function test_staff_can_filter_timeline_by_status_and_table(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        [$staffId, $zoneTableId, , , , $checkedInReservationId] = $this->seedTimelineScenario();

        $response = $this->withHeaders($this->staffHeaders($staffId, 'staff-timeline-filter-status'))
            ->getJson(sprintf('/api/v1/staff/reservations/timeline?date=2026-03-21&status=Reserved&table_id=%d', $zoneTableId));

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.total_reservations', 1)
            ->assertJsonPath('data.items.0.reservation.reservation_id', $checkedInReservationId)
            ->assertJsonPath('data.items.0.flags.checked_in', true);
    }

    public function test_staff_can_filter_timeline_by_zone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        [$staffId] = $this->seedTimelineScenario();

        $response = $this->withHeaders($this->staffHeaders($staffId, 'staff-timeline-zone'))
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&zone=Patio&status=Cancelled');

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.total_reservations', 1)
            ->assertJsonPath('data.items.0.reservation.tables.0.zone', 'Patio');
    }

    public function test_staff_can_filter_timeline_by_branch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        [$staffId] = $this->seedTimelineScenario();
        $annexBranchId = $this->createBranch([
            'branch_code' => 'TLANNEX',
            'branch_name' => 'Timeline Annex',
        ]);
        $annexTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'TL-ANNEX-01',
            'zone' => 'Annex',
            'branch_id' => $annexBranchId,
        ]);
        $customerId = $this->createUser(['role_name' => 'Customer', 'full_name' => 'Annex Timeline Guest']);
        $annexReservationId = $this->createReservation([
            'user_id' => $customerId,
            'branch_id' => $annexBranchId,
            'reservation_code' => 'TL-ANNEX-ACTIVE',
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 10:20:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:20:00', 'UTC'),
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($annexReservationId, $annexTableId);

        $response = $this->withHeaders($this->staffHeaders($staffId, 'staff-timeline-branch'))
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&branch_id='.$annexBranchId);

        $response
            ->assertOk()
            ->assertJsonPath('meta.filters.branch_id', $annexBranchId)
            ->assertJsonPath('data.summary.total_reservations', 1)
            ->assertJsonPath('data.items.0.reservation.reservation_id', $annexReservationId);
    }

    public function test_staff_can_request_zone_lane_grouping_without_breaking_legacy_timeline_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        [$staffId] = $this->seedTimelineScenario();
        $patioTableId = (int) RestaurantTable::query()->where('table_code', 'AA-TL-PATIO-01')->value('table_id');
        $customerId = $this->createUser(['role_name' => 'Customer', 'full_name' => 'Patio Lane Guest']);

        $patioReservationId = $this->createReservation([
            'user_id' => $customerId,
            'reservation_code' => 'TL-PATIO-ACTIVE',
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 10:20:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:20:00', 'UTC'),
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($patioReservationId, $patioTableId);

        $response = $this->withHeaders($this->staffHeaders($staffId, 'staff-timeline-lanes-zone'))
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&lane_by=zone');

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.total_reservations', 5)
            ->assertJsonPath('data.calendar.lane_mode', 'zone')
            ->assertJsonPath('data.calendar.has_lane_grouping', true)
            ->assertJsonPath('data.calendar.lane_count', 2)
            ->assertJsonPath('meta.filters.lane_by', 'zone');

        $lanes = collect($response->json('data.calendar.lanes'))->keyBy('lane_key');
        self::assertSame(4, (int) data_get($lanes['zone:Main'], 'reservation_count'));
        self::assertSame(1, (int) data_get($lanes['zone:Patio'], 'reservation_count'));

        $items = collect($response->json('data.items'))->keyBy(fn (array $item): int => (int) $item['reservation']['reservation_id']);
        self::assertSame('zone:Patio', data_get($items[$patioReservationId], 'calendar.primary_zone_lane_key'));
    }

    public function test_staff_can_request_table_lane_grouping_with_unassigned_candidate_preview(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        [$staffId, $mainTableId] = $this->seedTimelineScenario();
        $customerId = $this->createUser(['role_name' => 'Customer', 'full_name' => 'Unassigned Timeline Guest']);

        $unassignedReservationId = $this->createReservation([
            'user_id' => $customerId,
            'reservation_code' => 'TL-UNASSIGNED',
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 10:15:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:15:00', 'UTC'),
            'guest_count' => 2,
            'bill_currency' => 'VND',
        ]);

        $response = $this->withHeaders($this->staffHeaders($staffId, 'staff-timeline-lanes-table'))
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&lane_by=table&include_candidate_tables=1');

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.total_reservations', 5)
            ->assertJsonPath('data.summary.flag_counts.needs_assignment', 1)
            ->assertJsonPath('data.calendar.lane_mode', 'table')
            ->assertJsonPath('data.calendar.has_lane_grouping', true)
            ->assertJsonPath('meta.filters.include_candidate_tables', true);

        $items = collect($response->json('data.items'))->keyBy(fn (array $item): int => (int) $item['reservation']['reservation_id']);
        self::assertSame('unassigned', data_get($items[$unassignedReservationId], 'calendar.primary_table_lane_key'));
        self::assertTrue((bool) data_get($items[$unassignedReservationId], 'orchestration.needs_assignment'));
        self::assertTrue((bool) data_get($items[$unassignedReservationId], 'orchestration.ready_for_assignment'));
        self::assertTrue((bool) data_get($items[$unassignedReservationId], 'orchestration.candidate_table_preview_loaded'));
        self::assertGreaterThan(0, (int) data_get($items[$unassignedReservationId], 'orchestration.candidate_table_count'));
        self::assertSame('AA-TL-PATIO-01', (string) data_get($items[$unassignedReservationId], 'orchestration.best_fit_table.table_code'));

        $lanes = collect($response->json('data.calendar.lanes'))->keyBy('lane_key');
        self::assertSame(4, (int) data_get($lanes['table:'.$mainTableId], 'reservation_count'));
        self::assertSame(1, (int) data_get($lanes['unassigned'], 'reservation_count'));
    }

    public function test_staff_can_filter_table_lane_timeline_by_zone_without_losing_unassigned_candidates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        [$staffId] = $this->seedTimelineScenario();
        $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'TL-MAIN-AVAILABLE',
            'zone' => 'Main',
            'status' => 'Available',
        ]);
        $customerId = $this->createUser(['role_name' => 'Customer', 'full_name' => 'Main Zone Candidate Guest']);

        $mainZoneReservationId = $this->createReservation([
            'user_id' => $customerId,
            'reservation_code' => 'TL-UNASSIGNED-MAIN',
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 10:10:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:10:00', 'UTC'),
            'guest_count' => 4,
            'bill_currency' => 'VND',
        ]);

        $response = $this->withHeaders($this->staffHeaders($staffId, 'staff-timeline-zone-unassigned'))
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&lane_by=table&include_candidate_tables=1&zone=Main');

        $response
            ->assertOk()
            ->assertJsonPath('meta.filters.zone', 'Main');

        $items = collect($response->json('data.items'))->keyBy(fn (array $item): int => (int) $item['reservation']['reservation_id']);
        self::assertArrayHasKey($mainZoneReservationId, $items->all());
        self::assertSame('unassigned', data_get($items[$mainZoneReservationId], 'calendar.primary_table_lane_key'));
        self::assertTrue((bool) data_get($items[$mainZoneReservationId], 'orchestration.ready_for_assignment'));
        self::assertGreaterThan(0, (int) data_get($items[$mainZoneReservationId], 'orchestration.candidate_table_count'));
        self::assertSame('Main', data_get($items[$mainZoneReservationId], 'orchestration.assignment_request_context.zone'));
    }

    public function test_terminal_reservations_are_excluded_by_default_but_can_be_selected_explicitly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        [$staffId, , , , , , $cancelledReservationId] = $this->seedTimelineScenario();

        $defaultResponse = $this->withHeaders($this->staffHeaders($staffId, 'staff-timeline-default-terminal'))
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21');

        $defaultIds = collect($defaultResponse->json('data.items'))->pluck('reservation.reservation_id')->all();
        self::assertNotContains($cancelledReservationId, $defaultIds);

        $filteredResponse = $this->withHeaders($this->staffHeaders($staffId, 'staff-timeline-filter-terminal'))
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&status=Cancelled');

        $filteredResponse
            ->assertOk()
            ->assertJsonPath('data.summary.total_reservations', 1)
            ->assertJsonPath('data.items.0.reservation.reservation_id', $cancelledReservationId)
            ->assertJsonPath('data.items.0.flags.cancelled', true);
    }

    public function test_non_staff_requests_are_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));
        $this->seedTimelineScenario();

        $response = $this->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21');

        $response->assertUnauthorized();
    }

    /**
     * @return array{0:int,1:int,2:int,3:int,4:int,5:int,6:int}
     */
    private function seedTimelineScenario(): array
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer', 'full_name' => 'Timeline Guest']);
        $mainTableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'TL-MAIN-01', 'zone' => 'Main']);
        $patioTableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'AA-TL-PATIO-01', 'zone' => 'Patio']);

        $dueSoonReservationId = $this->createReservation([
            'user_id' => $customerId,
            'reservation_code' => 'TL-DUE-SOON',
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 10:10:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:10:00', 'UTC'),
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '20000.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($dueSoonReservationId, $mainTableId);

        $lateReservationId = $this->createReservation([
            'user_id' => $customerId,
            'reservation_code' => 'TL-LATE',
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 09:50:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 10:50:00', 'UTC'),
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($lateReservationId, $mainTableId);

        $overdueReservationId = $this->createReservation([
            'user_id' => $customerId,
            'reservation_code' => 'TL-OVERDUE',
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 09:40:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 10:40:00', 'UTC'),
            'deposit_required_amount' => '80000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($overdueReservationId, $mainTableId);

        $checkedInReservationId = $this->createReservation([
            'user_id' => $customerId,
            'reservation_code' => 'TL-CHECKED-IN',
            'status' => 'Reserved',
            'start_time' => Carbon::parse('2026-03-21 09:30:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:00:00', 'UTC'),
            'checked_in_at' => Carbon::parse('2026-03-21 09:35:00', 'UTC'),
            'deposit_required_amount' => '120000.00',
            'deposit_paid_amount' => '120000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($checkedInReservationId, $mainTableId);
        $orderId = $this->createOrder([
            'reservation_id' => $checkedInReservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $this->createMenuItem(),
            'quantity' => 1,
            'unit_price' => '35000.00',
            'currency' => 'VND',
            'line_total' => '35000.00',
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        $cancelledReservationId = $this->createReservation([
            'user_id' => $customerId,
            'reservation_code' => 'TL-CANCELLED',
            'status' => 'Cancelled',
            'start_time' => Carbon::parse('2026-03-21 10:30:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:30:00', 'UTC'),
            'cancelled_at' => Carbon::parse('2026-03-21 08:00:00', 'UTC'),
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($cancelledReservationId, $patioTableId);

        return [$staffId, $mainTableId, $dueSoonReservationId, $lateReservationId, $overdueReservationId, $checkedInReservationId, $cancelledReservationId];
    }

    /**
     * @return array<string,string>
     */
    private function staffHeaders(int $staffId, string $apiKey): array
    {
        return $this->staffAuthHeaders($staffId, $apiKey);
    }
}
