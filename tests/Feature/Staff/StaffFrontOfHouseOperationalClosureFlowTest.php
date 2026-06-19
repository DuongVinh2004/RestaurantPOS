<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffFrontOfHouseOperationalClosureFlowTest extends TestCase
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
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);

        app('cache')->forgetDriver('redis');
        Cache::store('redis')->getStore()->flush();

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

    public function test_front_of_house_chain_keeps_assignment_service_order_and_release_guard_consistent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $serviceTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'A-FOH-SERVICE-04',
            'zone' => 'Main',
            'status' => 'Available',
        ]);
        $moveTargetTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'Z-FOH-MOVE-04',
            'zone' => 'Main',
            'status' => 'Available',
        ]);

        $reservationId = $this->createReservation([
            'reservation_code' => 'FOH-CLOSURE',
            'status' => 'Confirmed',
            'guest_count' => 4,
            'start_time' => Carbon::parse('2026-03-21 12:00:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 13:00:00', 'UTC'),
            'row_version' => 1,
            'bill_currency' => 'VND',
        ]);

        $rescheduleResponse = $this->withHeaders($this->withIdempotencyKey('foh-closure-reschedule', $headers))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/reschedule", [
                'row_version' => 1,
                'start_time' => '2026-03-21T10:10:00+00:00',
                'end_time' => '2026-03-21T11:10:00+00:00',
                'reason' => 'ops_alignment',
            ]);

        $rescheduleResponse->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.row_version', 2);
        self::assertSame([], DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->pluck('table_id')
            ->all());

        $timelineResponse = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&lane_by=table&include_candidate_tables=1&zone=Main');

        $timelineResponse->assertOk();

        $timelineItem = collect($timelineResponse->json('data.items'))
            ->firstWhere('reservation.reservation_id', $reservationId);

        self::assertIsArray($timelineItem);
        self::assertSame('unassigned', data_get($timelineItem, 'calendar.primary_table_lane_key'));
        self::assertSame('assign_suggested', data_get($timelineItem, 'workbench.summary.next_recommended_action'));
        self::assertGreaterThan(0, (int) data_get($timelineItem, 'orchestration.candidate_table_count'));
        self::assertSame($serviceTableId, (int) data_get($timelineItem, 'orchestration.best_fit_table.table_id'));
        self::assertSame('Main', data_get($timelineItem, 'orchestration.assignment_request_context.zone'));
        self::assertSame(false, data_get($timelineItem, 'orchestration.assignment_request_context.include_slot_only_candidates'));

        $assignBestFitPayload = (array) collect((array) data_get($timelineItem, 'workbench.actions', []))
            ->firstWhere('key', 'assign_best_fit')['payload_defaults'];

        $assignResponse = $this->withHeaders($this->withIdempotencyKey('foh-closure-assign-best-fit', $headers))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/timeline/actions/assign-best-fit", $assignBestFitPayload);

        $assignResponse->assertOk()
            ->assertJsonPath('meta.action', 'timeline_assign_best_fit')
            ->assertJsonPath('assignment.mode', 'best_fit')
            ->assertJsonPath('assignment.assigned_table.table_id', $serviceTableId)
            ->assertJsonPath('assignment.assignment_request_context.zone', 'Main')
            ->assertJsonPath('assignment.assignment_request_context.include_slot_only_candidates', false)
            ->assertJsonPath('data.table_ids.0', $serviceTableId);

        $assignedRowVersion = (int) $assignResponse->json('data.row_version');
        self::assertGreaterThanOrEqual(2, $assignedRowVersion);

        $checkInResponse = $this->withHeaders($this->withIdempotencyKey('foh-closure-check-in', $headers))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/timeline/actions/check-in", [
                'row_version' => $assignedRowVersion,
                'checked_in_at' => '2026-03-21T10:10:00+00:00',
            ]);

        $checkInResponse->assertOk()
            ->assertJsonPath('meta.action', 'timeline_check_in')
            ->assertJsonPath('data.status', 'Reserved')
            ->assertJsonPath('data.table_ids.0', $serviceTableId);

        $checkedInRowVersion = (int) $checkInResponse->json('data.row_version');
        self::assertGreaterThan($assignedRowVersion, $checkedInRowVersion);

        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $serviceTableId)->value('status'));

        $menuItemId = $this->createMenuItem();
        $this->createMenuItemPrice([
            'item_id' => $menuItemId,
            'price' => '120000',
            'currency' => 'VND',
        ]);

        $createOrderResponse = $this->withHeaders($this->withIdempotencyKey('foh-closure-create-order', $headers))
            ->postJson("/api/v1/staff/tables/{$serviceTableId}/orders", [
                'reservation_id' => $reservationId,
                'row_version' => $checkedInRowVersion,
                'items' => [
                    [
                        'menu_item_id' => $menuItemId,
                        'qty' => 1,
                    ],
                ],
            ]);

        $createOrderResponse->assertStatus(201)
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.status', 'Active')
            ->assertJsonPath('data.items.0.item_id', $menuItemId)
            ->assertJsonPath('data.items.0.quantity', 1);

        $orderId = (int) $createOrderResponse->json('data.order_id');

        $moveResponse = $this->withHeaders($this->withIdempotencyKey('foh-closure-move-table', $headers))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/move-table", [
                'from_table_id' => $serviceTableId,
                'to_table_id' => $moveTargetTableId,
                'row_version' => $checkedInRowVersion,
            ]);

        $moveResponse->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.table_ids.0', $moveTargetTableId);

        self::assertSame([$moveTargetTableId], DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(fn ($id) => (int) $id)
            ->all());
        self::assertSame('Available', DB::table('restaurant_tables')->where('table_id', $serviceTableId)->value('status'));
        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $moveTargetTableId)->value('status'));

        $activeOrderByNewTable = $this->withHeaders($headers)
            ->getJson("/api/v1/staff/tables/{$moveTargetTableId}/active-order");

        $activeOrderByNewTable->assertOk()
            ->assertJsonPath('meta.action', 'active_order_by_table')
            ->assertJsonPath('data.order.order_id', $orderId)
            ->assertJsonPath('data.table.table_id', $moveTargetTableId)
            ->assertJsonPath('data.order.reservation_id', $reservationId);

        $activeOrderByReservation = $this->withHeaders($headers)
            ->getJson("/api/v1/staff/reservations/{$reservationId}/active-order");

        $activeOrderByReservation->assertOk()
            ->assertJsonPath('meta.action', 'active_order_by_reservation')
            ->assertJsonPath('data.order.order_id', $orderId)
            ->assertJsonPath('data.table.table_id', $moveTargetTableId);

        $this->withHeaders($headers)
            ->getJson("/api/v1/staff/tables/{$serviceTableId}/active-order")
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');

        $currentTableRowVersion = (int) DB::table('restaurant_tables')
            ->where('table_id', $moveTargetTableId)
            ->value('row_version');

        $this->withHeaders($this->withIdempotencyKey('foh-closure-release-blocked', $headers))
            ->postJson("/api/v1/staff/tables/{$moveTargetTableId}/release", [
                'row_version' => $currentTableRowVersion,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);

        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $moveTargetTableId)->value('status'));
    }
}
