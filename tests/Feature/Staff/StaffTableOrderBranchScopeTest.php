<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\Ordering\Application\UseCases\Orders\StaffTableOrderService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class StaffTableOrderBranchScopeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        foreach (['reservation_orders', 'reservation_tables', 'reservations', 'restaurant_tables', 'branches'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('branches', function (Blueprint $table): void {
            $table->increments('branch_id');
            $table->string('branch_code')->unique();
            $table->string('branch_name');
            $table->string('description')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('currency', 10)->default('VND');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('restaurant_tables', function (Blueprint $table): void {
            $table->increments('table_id');
            $table->unsignedInteger('branch_id')->nullable();
            $table->string('table_code')->nullable();
            $table->unsignedInteger('template_id')->nullable();
            $table->string('zone')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('reservations', function (Blueprint $table): void {
            $table->increments('reservation_id');
            $table->unsignedInteger('branch_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('reservation_code')->nullable();
            $table->dateTime('reserved_at')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->unsignedInteger('guest_count')->default(2);
            $table->string('status')->nullable();
            $table->string('source')->nullable();
            $table->dateTime('billed_at')->nullable();
            $table->decimal('final_bill_amount', 10, 2)->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('reservation_tables', function (Blueprint $table): void {
            $table->increments('reservation_table_id');
            $table->unsignedInteger('reservation_id');
            $table->unsignedInteger('table_id');
        });

        Schema::create('reservation_orders', function (Blueprint $table): void {
            $table->increments('order_id');
            $table->unsignedInteger('reservation_id');
            $table->string('order_type');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        DB::table('branches')->insert([
            [
                'branch_id' => 1,
                'branch_code' => 'MAIN',
                'branch_name' => 'Main',
                'timezone' => 'UTC',
                'currency' => 'VND',
                'is_active' => true,
                'is_default' => true,
                'row_version' => 1,
            ],
            [
                'branch_id' => 2,
                'branch_code' => 'ANNEX',
                'branch_name' => 'Annex',
                'timezone' => 'UTC',
                'currency' => 'VND',
                'is_active' => true,
                'is_default' => false,
                'row_version' => 1,
            ],
        ]);
    }

    public function test_create_on_spot_order_rejects_reservation_table_branch_mismatch(): void
    {
        DB::table('restaurant_tables')->insert([
            'table_id' => 10,
            'branch_id' => 2,
            'status' => RestaurantTableStatus::Occupied->value,
            'is_deleted' => false,
            'row_version' => 1,
        ]);

        DB::table('reservations')->insert([
            'reservation_id' => 100,
            'branch_id' => 1,
            'status' => ReservationStatus::Reserved->value,
            'guest_count' => 2,
            'row_version' => 1,
        ]);

        DB::table('reservation_tables')->insert([
            'reservation_id' => 100,
            'table_id' => 10,
        ]);

        $service = $this->makeService();

        $this->expectException(ValidationException::class);
        $service->createOnSpotOrder(10, 100, []);
    }

    public function test_create_on_spot_order_backfills_missing_reservation_branch_from_table_branch(): void
    {
        DB::table('restaurant_tables')->insert([
            'table_id' => 11,
            'branch_id' => 2,
            'status' => RestaurantTableStatus::Occupied->value,
            'is_deleted' => false,
            'row_version' => 1,
        ]);

        DB::table('reservations')->insert([
            'reservation_id' => 101,
            'branch_id' => null,
            'status' => ReservationStatus::Reserved->value,
            'guest_count' => 2,
            'row_version' => 1,
        ]);

        DB::table('reservation_tables')->insert([
            'reservation_id' => 101,
            'table_id' => 11,
        ]);

        $order = $this->makeService()->createOnSpotOrder(11, 101, []);

        self::assertGreaterThan(0, (int) $order->order_id);
        self::assertSame(2, (int) DB::table('reservations')->where('reservation_id', 101)->value('branch_id'));
    }

    public function test_create_on_spot_order_rejects_branch_outside_staff_operational_scope(): void
    {
        DB::table('restaurant_tables')->insert([
            'table_id' => 12,
            'branch_id' => 2,
            'status' => RestaurantTableStatus::Occupied->value,
            'is_deleted' => false,
            'row_version' => 1,
        ]);

        DB::table('reservations')->insert([
            'reservation_id' => 102,
            'branch_id' => 2,
            'status' => ReservationStatus::Reserved->value,
            'guest_count' => 2,
            'row_version' => 1,
        ]);

        DB::table('reservation_tables')->insert([
            'reservation_id' => 102,
            'table_id' => 12,
        ]);

        $staffBranchContext = Mockery::mock(StaffBranchContextService::class);
        $staffBranchContext->shouldReceive('assertAccessibleBranch')
            ->once()
            ->with(5001, 2)
            ->andThrow(new ModelNotFoundException);

        $this->expectException(ModelNotFoundException::class);

        $this->makeService($staffBranchContext)->createOnSpotOrder(
            tableId: 12,
            reservationId: 102,
            items: [],
            staffUserId: 5001,
        );
    }

    private function makeService(?StaffBranchContextService $staffBranchContextService = null): StaffTableOrderService
    {
        $locks = Mockery::mock(ReservationLockService::class);
        $locks->shouldReceive('withTableLocks')
            ->andReturnUsing(static fn (array $tableIds, callable $callback) => $callback());

        return new StaffTableOrderService($locks, new BranchContextService, $staffBranchContextService);
    }
}

