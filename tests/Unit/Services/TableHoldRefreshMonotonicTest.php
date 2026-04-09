<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ReservationLockService;
use App\Services\RestaurantTableStateService;
use App\Services\RuntimeSettingService;
use App\Services\TableHoldService;
use App\Services\TableTimeConflictService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class TableHoldRefreshMonotonicTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');

        $this->ensureSchema();
        DB::table('table_hold_details')->delete();
        DB::table('table_holds')->delete();
        DB::table('restaurant_tables')->delete();
        DB::table('table_templates')->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_refresh_never_moves_expire_at_backwards(): void
    {
        config()->set('booking.hold_max_total_minutes', 15);

        Carbon::setTestNow(Carbon::parse('2026-03-22 10:00:00', 'UTC'));
        $now = Carbon::now('UTC');

        $templateId = DB::table('table_templates')->insertGetId([
            'template_code' => 'TPL-REFRESH',
            'seats' => 4,
            'description' => 'Test template',
        ]);

        $tableId = DB::table('restaurant_tables')->insertGetId([
            'table_code' => 'TB-REFRESH',
            'template_id' => $templateId,
            'zone' => 'A',
            'status' => 'Available',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'price' => null,
        ]);

        DB::table('table_holds')->insert([
            'hold_id' => 'hold-refresh-1',
            'session_id' => 'sess-refresh',
            'user_id' => null,
            'confirmed_reservation_id' => null,
            'start_time' => $now,
            'end_time' => $now->copy()->addHour(),
            'duration_minutes' => 60,
            'hold_status' => 'Holding',
            'row_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'expire_at' => $now->copy()->addMinutes(10),
            'updated_by' => null,
        ]);

        DB::table('table_hold_details')->insert([
            'hold_id' => 'hold-refresh-1',
            'table_id' => $tableId,
        ]);

        Carbon::setTestNow($now->copy()->addMinute());

        $service = new TableHoldService(
            $this->mockLockService(),
            Mockery::mock(RestaurantTableStateService::class),
            Mockery::mock(TableTimeConflictService::class),
            Mockery::mock(RuntimeSettingService::class),
        );

        $result = $service->refreshHold('hold-refresh-1', 'sess-refresh', 5, false, null, null);

        $this->assertSame('hold-refresh-1', $result['hold_id']);
        $this->assertNotNull($result['expire_at']);
        $this->assertTrue($result['expire_at']->greaterThanOrEqualTo($now->copy()->addMinutes(10)));
    }

    private function mockLockService(): ReservationLockService
    {
        $mock = Mockery::mock(ReservationLockService::class);
        $mock->shouldReceive('withTableLocks')->andReturnUsing(static fn (array $tableIds, callable $callback) => $callback());

        return $mock;
    }

    private function ensureSchema(): void
    {
        if (! Schema::hasTable('table_templates')) {
            Schema::create('table_templates', function (Blueprint $table): void {
                $table->increments('template_id');
                $table->string('template_code')->unique();
                $table->integer('seats')->default(4);
                $table->string('description')->nullable();
            });
        }

        if (! Schema::hasTable('restaurant_tables')) {
            Schema::create('restaurant_tables', function (Blueprint $table): void {
                $table->increments('table_id');
                $table->string('table_code')->unique();
                $table->unsignedInteger('template_id')->nullable();
                $table->string('zone')->nullable();
                $table->string('status', 30)->default('Available');
                $table->boolean('is_deleted')->default(false);
                $table->unsignedInteger('row_version')->default(1);
                $table->decimal('price', 12, 2)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('table_holds')) {
            Schema::create('table_holds', function (Blueprint $table): void {
                $table->string('hold_id')->primary();
                $table->string('session_id');
                $table->unsignedInteger('user_id')->nullable();
                $table->unsignedInteger('confirmed_reservation_id')->nullable();
                $table->dateTime('start_time');
                $table->dateTime('end_time');
                $table->integer('duration_minutes')->default(0);
                $table->string('hold_status', 30)->default('Holding');
                $table->unsignedInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('expire_at')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
            });
        }

        if (! Schema::hasTable('table_hold_details')) {
            Schema::create('table_hold_details', function (Blueprint $table): void {
                $table->increments('hold_detail_id');
                $table->string('hold_id');
                $table->unsignedInteger('table_id');
            });
        }
    }
}
