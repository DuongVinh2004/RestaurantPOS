<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\RestaurantTableStateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class RestaurantTableStateServiceRowVersionTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    public function test_occupy_and_release_table_states_bump_row_version(): void
    {
        $tableId = $this->createRestaurantTable([
            'status' => 'Available',
            'row_version' => 1,
        ]);

        $service = new RestaurantTableStateService();
        $service->occupyTables([$tableId], Carbon::now('UTC'), 11, [
            'source' => 'row_version_test',
            'reason' => 'occupy',
        ]);

        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
        self::assertSame(2, (int) DB::table('restaurant_tables')->where('table_id', $tableId)->value('row_version'));

        $service->releaseTablesSafely([$tableId], Carbon::now('UTC')->addMinute(), 11, [
            'source' => 'row_version_test',
            'reason' => 'release',
        ]);

        self::assertSame('Available', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
        self::assertSame(3, (int) DB::table('restaurant_tables')->where('table_id', $tableId)->value('row_version'));
    }
}
