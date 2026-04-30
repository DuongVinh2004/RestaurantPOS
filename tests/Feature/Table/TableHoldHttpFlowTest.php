<?php

declare(strict_types=1);

namespace Tests\Feature\Table;

use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class TableHoldHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);

        $this->app->instance(ReservationLockService::class, $this->mockReservationLocks());
        $this->app->instance(RestaurantTableStateService::class, new RestaurantTableStateService);
        $this->app->instance(TableTimeConflictService::class, new TableTimeConflictService);
        $this->app->instance(RuntimeSettingService::class, $this->mockRuntimeSettings());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_session_can_create_show_refresh_and_cancel_hold_via_http_flow(): void
    {
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addHours(2);
        $end = $start->copy()->addHours(2);
        $sessionId = 'sess-table-hold-happy';

        $create = $this->postJson('/api/v1/table-holds', [
            'session_id' => $sessionId,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'table_ids' => [$tableId],
            'hold_minutes' => 5,
        ], $this->withIdempotencyKey('table-hold-create-happy'));

        $create->assertCreated()
            ->assertJsonPath('data.hold_status', 'Holding')
            ->assertJsonPath('data.tables.0.table_id', $tableId)
            ->assertJsonPath('data.row_version', 1);

        $holdId = (string) $create->json('data.hold_id');
        $show = $this->getJson('/api/v1/table-holds/'.$holdId.'?session_id='.$sessionId);

        $show->assertOk()
            ->assertJsonPath('data.hold_id', $holdId)
            ->assertJsonPath('data.tables.0.table_id', $tableId)
            ->assertJsonPath('data.hold_status', 'Holding');

        $originalExpireAt = Carbon::parse((string) $show->json('data.expire_at'));

        $refresh = $this->withHeaders($this->withIdempotencyKey('table-hold-refresh-happy'))
            ->patchJson('/api/v1/table-holds/'.$holdId.'/refresh', [
                'session_id' => $sessionId,
                'extend_minutes' => 3,
                'row_version' => 1,
            ]);

        $refresh->assertOk()
            ->assertJsonPath('data.hold_id', $holdId)
            ->assertJsonPath('data.hold_status', 'Holding')
            ->assertJsonPath('data.row_version', 2);

        $refreshedExpireAt = Carbon::parse((string) $refresh->json('data.expire_at'));
        $this->assertTrue($refreshedExpireAt->greaterThanOrEqualTo($originalExpireAt));

        $cancel = $this->withHeaders($this->withIdempotencyKey('table-hold-cancel-happy'))
            ->deleteJson('/api/v1/table-holds/'.$holdId, [
                'session_id' => $sessionId,
                'row_version' => 2,
            ]);

        $cancel->assertOk()
            ->assertJsonPath('data.hold_id', $holdId)
            ->assertJsonPath('data.hold_status', 'Cancelled')
            ->assertJsonPath('data.row_version', 3);

        self::assertSame('Cancelled', DB::table('table_holds')->where('hold_id', $holdId)->value('hold_status'));
    }

    public function test_hold_create_is_idempotent_and_show_rejects_wrong_session(): void
    {
        $tableId = $this->createRestaurantTableWithSeats(2, ['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addHours(3);
        $end = $start->copy()->addHour();
        $sessionId = 'sess-table-hold-idem';
        $payload = [
            'session_id' => $sessionId,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'table_ids' => [$tableId],
            'hold_minutes' => 5,
        ];
        $headers = $this->withIdempotencyKey('table-hold-create-idem');

        $first = $this->postJson('/api/v1/table-holds', $payload, $headers);
        $second = $this->postJson('/api/v1/table-holds', $payload, $headers);

        $first->assertCreated();
        $second->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        $firstHoldId = (string) $first->json('data.hold_id');
        $secondHoldId = (string) $second->json('data.hold_id');
        self::assertSame($firstHoldId, $secondHoldId);
        self::assertSame(1, DB::table('table_holds')->where('session_id', $sessionId)->count());

        $this->getJson('/api/v1/table-holds/'.$firstHoldId.'?session_id=other-session')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session_id']);
    }

    public function test_future_hold_can_use_currently_occupied_table_when_time_window_is_free(): void
    {
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Occupied']);
        $start = $this->nowUtc()->copy()->addHours(5);
        $end = $start->copy()->addHour();

        $response = $this->postJson('/api/v1/table-holds', [
            'session_id' => 'sess-table-hold-future-occupied',
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'table_ids' => [$tableId],
            'hold_minutes' => 5,
        ], $this->withIdempotencyKey('table-hold-future-occupied'));

        $response->assertCreated()
            ->assertJsonPath('data.hold_status', 'Holding')
            ->assertJsonPath('data.tables.0.table_id', $tableId);
    }

    public function test_second_session_cannot_create_conflicting_hold_for_same_table_window_once_first_hold_exists(): void
    {
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addHours(3);
        $end = $start->copy()->addHour();

        $this->postJson('/api/v1/table-holds', [
            'session_id' => 'sess-table-hold-first',
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'table_ids' => [$tableId],
            'hold_minutes' => 5,
        ], $this->withIdempotencyKey('table-hold-first-conflict'))
            ->assertCreated()
            ->assertJsonPath('data.hold_status', 'Holding');

        $response = $this->postJson('/api/v1/table-holds', [
            'session_id' => 'sess-table-hold-second',
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'table_ids' => [$tableId],
            'hold_minutes' => 5,
        ], $this->withIdempotencyKey('table-hold-second-conflict'));

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        $message = (string) data_get($response->json(), 'details.errors.table_ids.0', '');
        self::assertStringContainsString((string) $tableId, $message);
        self::assertSame(1, (int) DB::table('table_holds')->whereIn('hold_status', ['Holding', 'Pending'])->count());
        self::assertSame(1, (int) DB::table('table_hold_details')->where('table_id', $tableId)->count());
    }

    public function test_hold_create_rejects_branch_local_window_outside_business_hours(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'HOLD02',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'business_hours' => collect(range(0, 6))
                ->map(static fn (int $day): array => [
                    'day_of_week' => $day,
                    'periods' => [[
                        'start_time' => '10:00',
                        'end_time' => '22:00',
                    ]],
                ])
                ->all(),
        ]);
        $tableId = $this->createRestaurantTableWithSeats(2, ['status' => 'Available', 'branch_id' => $branchId]);
        $start = now('Asia/Ho_Chi_Minh')->addDay()->setTime(9, 0)->utc();
        $end = $start->copy()->addHour();

        $response = $this->postJson('/api/v1/table-holds', [
            'session_id' => 'sess-hold-business-hours',
            'branch_id' => $branchId,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'table_ids' => [$tableId],
            'hold_minutes' => 5,
        ], $this->withIdempotencyKey('table-hold-business-hours-reject'));

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('details.errors.start_time.0', 'Requested hold window falls outside the configured branch business hours.');
    }

    public function test_refresh_rejects_stale_row_version(): void
    {
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $holdId = $this->createTableHold([
            'session_id' => 'sess-table-hold-stale',
            'start_time' => $this->nowUtc()->copy()->addHours(4),
            'end_time' => $this->nowUtc()->copy()->addHours(6),
            'row_version' => 2,
            'expire_at' => $this->nowUtc()->copy()->addMinutes(10),
        ], [$tableId]);

        $response = $this->withHeaders($this->withIdempotencyKey('table-hold-refresh-stale'))
            ->patchJson('/api/v1/table-holds/'.$holdId.'/refresh', [
                'session_id' => 'sess-table-hold-stale',
                'extend_minutes' => 5,
                'row_version' => 1,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonValidationErrors(['row_version']);

        self::assertStringContainsString('row_version mismatch', (string) data_get($response->json(), 'details.errors.row_version.0', ''));
    }

    public function test_confirmed_hold_artifact_does_not_block_new_live_hold_for_same_session(): void
    {
        $oldTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $newTableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addHours(4);
        $end = $start->copy()->addHour();
        $sessionId = 'sess-confirmed-hold-artifact';

        $this->createTableHold([
            'session_id' => $sessionId,
            'start_time' => $start,
            'end_time' => $end,
            'expire_at' => $this->nowUtc()->copy()->addMinutes(30),
            'hold_status' => 'Confirmed',
            'confirmed_reservation_id' => $this->createReservation([
                'status' => 'Confirmed',
                'start_time' => $start,
                'end_time' => $end,
            ]),
        ], [$oldTableId]);

        $response = $this->postJson('/api/v1/table-holds', [
            'session_id' => $sessionId,
            'start_time' => $start->copy()->addDay()->toIso8601String(),
            'end_time' => $end->copy()->addDay()->toIso8601String(),
            'table_ids' => [$newTableId],
            'hold_minutes' => 5,
        ], $this->withIdempotencyKey('table-hold-ignore-confirmed-artifact'));

        $response->assertCreated()
            ->assertJsonPath('data.hold_status', 'Holding')
            ->assertJsonPath('data.tables.0.table_id', $newTableId);

        self::assertSame(2, DB::table('table_holds')->where('session_id', $sessionId)->count());
    }

    public function test_staff_without_reservation_manage_cannot_override_table_hold_routes_without_session_scope(): void
    {
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $holdId = $this->createTableHold([
            'session_id' => 'sess-table-hold-staff-forbidden',
            'start_time' => $this->nowUtc()->copy()->addHours(2),
            'end_time' => $this->nowUtc()->copy()->addHours(4),
            'expire_at' => $this->nowUtc()->copy()->addMinutes(15),
            'row_version' => 1,
        ], [$tableId]);

        $staffId = $this->createUser(['role_name' => 'Staff']);
        config()->set('staff_capabilities.role_capabilities', [
            'Admin' => ['*'],
            'Staff' => ['table.board.view'],
        ]);

        $headers = $this->staffAuthHeaders($staffId, 'hold-staff-no-manage');
        $showHeaders = array_merge($headers, ['X-Request-Id' => 'req-table-hold-show-forbidden']);
        $refreshHeaders = array_merge($headers, ['X-Request-Id' => 'req-table-hold-refresh-forbidden']);
        $cancelHeaders = array_merge($headers, ['X-Request-Id' => 'req-table-hold-cancel-forbidden']);

        $this->withHeaders($showHeaders)
            ->getJson('/api/v1/table-holds/'.$holdId)
            ->assertStatus(403)
            ->assertHeader('X-Request-Id', 'req-table-hold-show-forbidden')
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('request_id', 'req-table-hold-show-forbidden')
            ->assertJsonPath('required_capability', 'reservation.manage');

        $this->withHeaders($this->withIdempotencyKey($refreshHeaders, 'hold-staff-no-manage-refresh'))
            ->patchJson('/api/v1/table-holds/'.$holdId.'/refresh', [
                'extend_minutes' => 5,
                'row_version' => 1,
            ])
            ->assertStatus(403)
            ->assertHeader('X-Request-Id', 'req-table-hold-refresh-forbidden')
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('request_id', 'req-table-hold-refresh-forbidden')
            ->assertJsonPath('required_capability', 'reservation.manage');

        $this->withHeaders($this->withIdempotencyKey($cancelHeaders, 'hold-staff-no-manage-cancel'))
            ->deleteJson('/api/v1/table-holds/'.$holdId, [
                'row_version' => 1,
            ])
            ->assertStatus(403)
            ->assertHeader('X-Request-Id', 'req-table-hold-cancel-forbidden')
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('request_id', 'req-table-hold-cancel-forbidden')
            ->assertJsonPath('required_capability', 'reservation.manage');

        self::assertSame('Holding', DB::table('table_holds')->where('hold_id', $holdId)->value('hold_status'));
        self::assertSame(1, (int) DB::table('table_holds')->where('hold_id', $holdId)->value('row_version'));
    }

    public function test_staff_with_reservation_manage_can_show_refresh_and_cancel_hold_without_session_scope(): void
    {
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $holdId = $this->createTableHold([
            'session_id' => 'sess-table-hold-staff-manage',
            'start_time' => $this->nowUtc()->copy()->addHours(2),
            'end_time' => $this->nowUtc()->copy()->addHours(4),
            'expire_at' => $this->nowUtc()->copy()->addMinutes(5),
            'row_version' => 1,
        ], [$tableId]);

        $staffId = $this->createUser(['role_name' => 'Staff']);
        config()->set('staff_capabilities.role_capabilities', [
            'Admin' => ['*'],
            'Staff' => ['reservation.manage'],
        ]);

        $headers = $this->staffAuthHeaders($staffId, 'hold-staff-manage');

        $this->withHeaders($headers)
            ->getJson('/api/v1/table-holds/'.$holdId)
            ->assertOk()
            ->assertJsonPath('data.hold_id', $holdId)
            ->assertJsonPath('data.hold_status', 'Holding');

        $refresh = $this->withHeaders($this->withIdempotencyKey($headers, 'hold-staff-manage-refresh'))
            ->patchJson('/api/v1/table-holds/'.$holdId.'/refresh', [
                'extend_minutes' => 5,
                'row_version' => 1,
            ]);

        $refresh->assertOk()
            ->assertJsonPath('data.hold_id', $holdId)
            ->assertJsonPath('data.row_version', 2);

        $this->withHeaders($this->withIdempotencyKey($headers, 'hold-staff-manage-cancel'))
            ->deleteJson('/api/v1/table-holds/'.$holdId, [
                'row_version' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.hold_status', 'Cancelled')
            ->assertJsonPath('data.row_version', 3);
    }
}
