<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Services\NotificationOutboxService;
use App\Services\ReservationLockService;
use App\Services\RestaurantTableStateService;
use App\Services\RuntimeSettingService;
use App\Services\TableTimeConflictService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffReservationTimelineWorkbenchFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        config()->set('app.timezone', 'UTC');
        config()->set('booking.multi_branch.default_branch_timezone', 'Asia/Ho_Chi_Minh');
        config()->set('booking.check_in_grace_minutes', 15);

        $this->app->instance(NotificationOutboxService::class, $this->mockNotificationOutbox());
        $this->app->instance(ReservationLockService::class, $this->mockReservationLocks());
        $this->app->instance(RuntimeSettingService::class, $this->mockRuntimeSettings());
        $this->app->instance(RestaurantTableStateService::class, new RestaurantTableStateService);
        $this->app->instance(TableTimeConflictService::class, new TableTimeConflictService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_timeline_surfaces_workbench_actions_and_summary_without_breaking_existing_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $checkInTableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'TL-WB-CHECKIN', 'zone' => 'Main', 'status' => 'Available']);
        $candidateTableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'TL-WB-CANDIDATE', 'zone' => 'Main', 'status' => 'Available']);
        $moveTargetTableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'TL-WB-MOVE', 'zone' => 'Main', 'status' => 'Available']);

        $unassignedReservationId = $this->createReservation([
            'reservation_code' => 'TL-WB-UNASSIGNED',
            'status' => 'Confirmed',
            'guest_count' => 4,
            'start_time' => Carbon::parse('2026-03-21 10:10:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:10:00', 'UTC'),
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'deposit_requirement_acknowledged_at' => $this->nowUtc(),
            'deposit_intent_status' => 'Submitted',
            'deposit_intent_submitted_at' => $this->nowUtc(),
        ]);

        $assignedReservationId = $this->createReservation([
            'reservation_code' => 'TL-WB-ASSIGNED',
            'status' => 'Confirmed',
            'guest_count' => 4,
            'start_time' => Carbon::parse('2026-03-21 10:10:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:10:00', 'UTC'),
        ]);
        $this->attachReservationTable($assignedReservationId, $checkInTableId);

        $checkedInReservationId = $this->createReservation([
            'reservation_code' => 'TL-WB-CHECKEDIN',
            'status' => 'Reserved',
            'guest_count' => 4,
            'start_time' => Carbon::parse('2026-03-21 09:50:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:30:00', 'UTC'),
            'checked_in_at' => Carbon::parse('2026-03-21 09:55:00', 'UTC'),
        ]);
        $this->attachReservationTable($checkedInReservationId, $moveTargetTableId);
        DB::table('restaurant_tables')->where('table_id', $moveTargetTableId)->update(['status' => 'Occupied']);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&lane_by=table&include_candidate_tables=1');

        $response->assertOk()
            ->assertJsonPath('meta.workbench.supported', true)
            ->assertJsonPath('meta.workbench.drag_drop_backend_supported', false);

        $items = collect($response->json('data.items'))->keyBy(fn (array $item): int => (int) $item['reservation']['reservation_id']);
        $workbenchActionCounts = (array) $response->json('data.summary.workbench_action_counts');
        $derivedActionCounts = collect($response->json('data.items'))
            ->flatMap(static function (array $item): array {
                return collect((array) data_get($item, 'workbench.actions', []))
                    ->filter(static fn (array $action): bool => (bool) ($action['available'] ?? false))
                    ->pluck('key')
                    ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '')
                    ->values()
                    ->all();
            })
            ->countBy()
            ->all();

        self::assertSame((int) ($derivedActionCounts['assign_best_fit'] ?? 0), (int) ($workbenchActionCounts['assign_best_fit'] ?? 0));
        self::assertSame((int) ($derivedActionCounts['assign_suggested'] ?? 0), (int) ($workbenchActionCounts['assign_suggested'] ?? 0));
        self::assertSame((int) ($derivedActionCounts['check_in'] ?? 0), (int) ($workbenchActionCounts['check_in'] ?? 0));
        self::assertSame((int) ($derivedActionCounts['move_table'] ?? 0), (int) ($workbenchActionCounts['move_table'] ?? 0));

        self::assertSame('assign_suggested', data_get($items[$unassignedReservationId], 'workbench.summary.next_recommended_action'));
        self::assertSame(
            "/api/v1/staff/reservations/{$unassignedReservationId}/timeline/actions/assign-suggested",
            data_get($items[$unassignedReservationId], 'workbench.actions.1.uri')
        );
        self::assertSame($candidateTableId, (int) data_get($items[$unassignedReservationId], 'workbench.actions.1.suggested_table.table_id'));
        self::assertSame('2026-03-20T17:00:00+00:00', data_get($items[$unassignedReservationId], 'workbench.actions.0.payload_defaults.board_from'));
        self::assertSame('2026-03-21T17:00:00+00:00', data_get($items[$unassignedReservationId], 'workbench.actions.0.payload_defaults.board_to'));
        self::assertSame(false, data_get($items[$unassignedReservationId], 'workbench.actions.0.payload_defaults.include_slot_only_candidates'));
        self::assertSame(false, data_get($items[$unassignedReservationId], 'orchestration.assignment_request_context.include_slot_only_candidates'));
        self::assertTrue((bool) data_get($items[$unassignedReservationId], 'workbench.actions.0.available'));
        self::assertTrue((bool) data_get($items[$unassignedReservationId], 'workbench.actions.1.available'));
        self::assertTrue((bool) data_get($items[$unassignedReservationId], 'workbench.summary.requires_deposit_follow_up'));

        self::assertSame('check_in', data_get($items[$assignedReservationId], 'workbench.summary.next_recommended_action'));
        self::assertTrue((bool) data_get($items[$assignedReservationId], 'workbench.actions.2.available'));
        self::assertTrue((bool) data_get($items[$assignedReservationId], 'workbench.summary.check_in_window.open'));

        self::assertSame('move_table', data_get($items[$checkedInReservationId], 'workbench.summary.next_recommended_action'));
        self::assertTrue((bool) data_get($items[$checkedInReservationId], 'workbench.actions.3.available'));
    }

    public function test_timeline_workbench_uses_runtime_check_in_grace_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));
        config()->set('booking.check_in_grace_minutes', 5);
        $this->app->instance(RuntimeSettingService::class, new class extends RuntimeSettingService
        {
            public function int(string $settingKey, int $fallback): int
            {
                return match ($settingKey) {
                    'checkin.grace_minutes',
                    'booking.check_in_grace_minutes' => 20,
                    default => $fallback,
                };
            }
        });

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $checkInTableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'TL-WB-RUNTIME-CHECKIN', 'zone' => 'Main', 'status' => 'Available']);
        $reservationId = $this->createReservation([
            'reservation_code' => 'TL-WB-RUNTIME',
            'status' => 'Confirmed',
            'guest_count' => 4,
            'start_time' => Carbon::parse('2026-03-21 10:10:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:10:00', 'UTC'),
        ]);
        $this->attachReservationTable($reservationId, $checkInTableId);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&lane_by=table&include_candidate_tables=1');

        $response->assertOk();

        $items = collect($response->json('data.items'))->keyBy(fn (array $item): int => (int) $item['reservation']['reservation_id']);
        self::assertSame('check_in', data_get($items[$reservationId], 'workbench.summary.next_recommended_action'));
        self::assertTrue((bool) data_get($items[$reservationId], 'workbench.summary.check_in_window.open'));
        self::assertTrue((bool) data_get($items[$reservationId], 'workbench.actions.2.available'));
    }

    public function test_timeline_workbench_hides_check_in_when_assigned_table_has_active_hold_conflict(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'TL-WB-HOLD-01', 'zone' => 'Main', 'status' => 'Available']);
        $reservationId = $this->createReservation([
            'reservation_code' => 'TL-WB-HOLD',
            'status' => 'Confirmed',
            'guest_count' => 4,
            'start_time' => Carbon::parse('2026-03-21 10:10:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:10:00', 'UTC'),
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createTableHold([
            'start_time' => Carbon::parse('2026-03-21 10:05:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 10:40:00', 'UTC'),
            'expire_at' => Carbon::parse('2026-03-21 10:30:00', 'UTC'),
            'hold_status' => 'Holding',
        ], [$tableId]);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&lane_by=table&include_candidate_tables=1');

        $response->assertOk();

        $items = collect($response->json('data.items'))->keyBy(fn (array $item): int => (int) $item['reservation']['reservation_id']);
        self::assertFalse((bool) data_get($items[$reservationId], 'workbench.actions.2.available'));
        self::assertSame('assigned_table_hold_conflict', data_get($items[$reservationId], 'workbench.actions.2.blocked_reason_code'));
        self::assertTrue((bool) data_get($items[$reservationId], 'workbench.actions.2.context.checks.has_assigned_table_hold_conflict'));
        self::assertFalse((bool) data_get($items[$reservationId], 'workbench.summary.check_in_window.open'));
    }

    public function test_timeline_workbench_hides_check_in_when_assigned_table_branch_drifts_from_reservation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);
        $annexBranchId = $this->createBranch([
            'branch_code' => 'TLWBANNEX',
            'branch_name' => 'Timeline Workbench Annex',
        ]);

        $tableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'TL-WB-BRANCH-01',
            'zone' => 'Main',
            'status' => 'Available',
            'branch_id' => $annexBranchId,
        ]);
        $reservationId = $this->createReservation([
            'reservation_code' => 'TL-WB-BRANCH',
            'status' => 'Confirmed',
            'branch_id' => 1,
            'guest_count' => 4,
            'start_time' => Carbon::parse('2026-03-21 10:10:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:10:00', 'UTC'),
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&lane_by=table&include_candidate_tables=1');

        $response->assertOk();

        $items = collect($response->json('data.items'))->keyBy(fn (array $item): int => (int) $item['reservation']['reservation_id']);
        self::assertFalse((bool) data_get($items[$reservationId], 'workbench.actions.2.available'));
        self::assertSame('branch_mismatch', data_get($items[$reservationId], 'workbench.actions.2.blocked_reason_code'));
        self::assertFalse((bool) data_get($items[$reservationId], 'workbench.actions.2.context.checks.branch_consistent'));
        self::assertFalse((bool) data_get($items[$reservationId], 'workbench.summary.check_in_window.open'));
    }

    public function test_timeline_assign_best_fit_alias_reuses_canonical_assignment_service(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('timeline-workbench-assign-best-fit', $this->staffAuthHeaders($staffId));

        $bestFitTableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'TL-WB-BESTFIT-04', 'zone' => 'Main', 'status' => 'Available']);
        $this->createRestaurantTableWithSeats(6, ['table_code' => 'TL-WB-BESTFIT-06', 'zone' => 'Main', 'status' => 'Available']);
        $reservationId = $this->createReservation([
            'reservation_code' => 'TL-WB-ASSIGN-BESTFIT',
            'status' => 'Confirmed',
            'guest_count' => 4,
            'start_time' => Carbon::parse('2026-03-21 10:20:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:20:00', 'UTC'),
            'row_version' => 1,
        ]);

        $response = $this->withHeaders($headers)
            ->postJson("/api/v1/staff/reservations/{$reservationId}/timeline/actions/assign-best-fit", [
                'row_version' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('meta.action', 'timeline_assign_best_fit')
            ->assertJsonPath('assignment.mode', 'best_fit')
            ->assertJsonPath('assignment.assigned_table.table_id', $bestFitTableId)
            ->assertJsonPath('data.row_version', 2)
            ->assertJsonPath('data.table_ids.0', $bestFitTableId);

        self::assertSame([$bestFitTableId], DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    public function test_timeline_assign_best_fit_alias_uses_current_timeline_candidate_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('timeline-workbench-assign-best-fit-window-context', $this->staffAuthHeaders($staffId));

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
            'reservation_code' => 'TL-WB-WINDOW-BLOCK',
            'status' => 'Confirmed',
            'guest_count' => 4,
            'start_time' => Carbon::parse('2026-03-21 10:30:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:30:00', 'UTC'),
        ]);
        $this->attachReservationTable($blockingReservationId, $slotOnlyTableId);

        $reservationId = $this->createReservation([
            'reservation_code' => 'TL-WB-WINDOW-TARGET',
            'status' => 'Confirmed',
            'guest_count' => 4,
            'start_time' => Carbon::parse('2026-03-21 13:00:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 14:00:00', 'UTC'),
            'row_version' => 1,
        ]);

        $response = $this->withHeaders($headers)
            ->postJson("/api/v1/staff/reservations/{$reservationId}/timeline/actions/assign-best-fit", [
                'row_version' => 1,
                'board_from' => '2026-03-20T17:00:00+00:00',
                'board_to' => '2026-03-21T17:00:00+00:00',
                'zone' => 'Main',
            ]);

        $response->assertOk()
            ->assertJsonPath('meta.action', 'timeline_assign_best_fit')
            ->assertJsonPath('assignment.mode', 'best_fit')
            ->assertJsonPath('assignment.assigned_table.table_id', $openWindowTableId)
            ->assertJsonPath('assignment.assignment_request_context.include_slot_only_candidates', false)
            ->assertJsonPath('assignment.assignment_window.availability_mode', 'open_for_board_window');

        self::assertSame([$openWindowTableId], DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    public function test_timeline_check_in_alias_reuses_existing_guardrails(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('timeline-workbench-checkin-guard', $this->staffAuthHeaders($staffId));

        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'TL-WB-GUARD-01', 'zone' => 'Main', 'status' => 'Available']);
        $reservationId = $this->createReservation([
            'reservation_code' => 'TL-WB-CHECKIN-GUARD',
            'status' => 'Confirmed',
            'guest_count' => 4,
            'start_time' => Carbon::parse('2026-03-21 11:00:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 12:00:00', 'UTC'),
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)
            ->postJson("/api/v1/staff/reservations/{$reservationId}/timeline/actions/check-in", [
                'row_version' => 1,
                'checked_in_at' => Carbon::parse('2026-03-21 10:00:00', 'UTC')->toIso8601String(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['checked_in_at']);

        self::assertSame('Confirmed', DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('Available', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }
}
