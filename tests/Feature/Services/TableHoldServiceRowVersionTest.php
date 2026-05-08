<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableHoldService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class TableHoldServiceRowVersionTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_expire_stale_holds_bumps_row_version_and_marks_hold_expired(): void
    {
        $staleHoldCountBefore = (int) DB::table('table_holds')
            ->where('hold_status', 'Holding')
            ->where('expire_at', '<=', Carbon::now('UTC'))
            ->count();
        $tableId = $this->createRestaurantTable(['status' => 'Available']);
        $holdId = $this->createTableHold([
            'session_id' => 'sess-hold-expire-rv',
            'hold_status' => 'Holding',
            'row_version' => 1,
            'expire_at' => Carbon::now('UTC')->subMinute(),
        ], [$tableId]);

        $service = $this->makeTableHoldService();
        $expired = $service->expireStaleHolds();

        self::assertSame($staleHoldCountBefore + 1, $expired);
        self::assertSame('Expired', (string) DB::table('table_holds')->where('hold_id', $holdId)->value('hold_status'));
        self::assertSame(2, (int) DB::table('table_holds')->where('hold_id', $holdId)->value('row_version'));
        self::assertNotNull(DB::table('table_holds')->where('hold_id', $holdId)->value('updated_at'));
    }

    public function test_cancel_hold_after_scheduler_expiry_rejects_stale_row_version_before_status_check(): void
    {
        $staleHoldCountBefore = (int) DB::table('table_holds')
            ->where('hold_status', 'Holding')
            ->where('expire_at', '<=', Carbon::now('UTC'))
            ->count();
        $tableId = $this->createRestaurantTable(['status' => 'Available']);
        $holdId = $this->createTableHold([
            'session_id' => 'sess-hold-expire-stale-rv',
            'hold_status' => 'Holding',
            'row_version' => 1,
            'expire_at' => Carbon::now('UTC')->subMinute(),
        ], [$tableId]);

        $service = $this->makeTableHoldService();
        self::assertSame($staleHoldCountBefore + 1, $service->expireStaleHolds());

        try {
            $service->cancelHold($holdId, 'sess-hold-expire-stale-rv', false, 1, 55);
            self::fail('Expected stale row_version validation error.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('row_version', $e->errors());
        }
    }

    public function test_cancel_hold_bumps_row_version_and_returns_fresh_snapshot(): void
    {
        $tableId = $this->createRestaurantTable(['status' => 'Available']);
        $holdId = $this->createTableHold([
            'session_id' => 'sess-hold-cancel-rv',
            'hold_status' => 'Holding',
            'row_version' => 1,
        ], [$tableId]);

        $service = $this->makeTableHoldService();
        $result = $service->cancelHold($holdId, 'sess-hold-cancel-rv', false, 1, 99);

        self::assertSame('Cancelled', $result['hold_status']);
        self::assertSame(2, (int) $result['row_version']);
        self::assertSame(2, (int) DB::table('table_holds')->where('hold_id', $holdId)->value('row_version'));
    }

    public function test_refresh_hold_bumps_row_version_and_rejects_stale_follow_up_request(): void
    {
        $tableId = $this->createRestaurantTable(['status' => 'Available']);
        $holdId = $this->createTableHold([
            'session_id' => 'sess-hold-refresh-rv',
            'hold_status' => 'Holding',
            'row_version' => 1,
            'expire_at' => Carbon::now('UTC')->addMinutes(5),
        ], [$tableId]);

        $service = $this->makeTableHoldService();
        $result = $service->refreshHold($holdId, 'sess-hold-refresh-rv', 3, false, 1, 77);

        self::assertSame(2, (int) $result['row_version']);
        self::assertSame(2, (int) DB::table('table_holds')->where('hold_id', $holdId)->value('row_version'));

        try {
            $service->cancelHold($holdId, 'sess-hold-refresh-rv', false, 1, 77);
            self::fail('Expected stale row_version validation error.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('row_version', $e->errors());
        }
    }

    private function makeTableHoldService(): TableHoldService
    {
        return new TableHoldService(
            $this->mockReservationLocks(),
            new RestaurantTableStateService,
            new TableTimeConflictService,
            $this->mockRuntimeSettings(),
        );
    }
}
