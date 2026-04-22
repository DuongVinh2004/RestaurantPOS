<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableHoldService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\Catalog\Application\UseCases\PolicyPreview\MenuPreorderPolicyService;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Reservations\Application\Services\ReservationCodeGenerator;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Reservations\Application\Services\ReservationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use Tests\TestCase;

class ReservationServicePreorderPricingGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('cache.default', 'array');
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        config()->set('booking.require_redis_for_booking_api', false);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        foreach ([
            'branches',
            'payments',
            'reservation_order_items',
            'reservation_orders',
            'reservation_tables',
            'reservations',
            'menu_item_prices',
            'menu_items',
            'restaurant_tables',
            'table_templates',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('branches', function (Blueprint $table): void {
            $table->increments('branch_id');
            $table->string('branch_code');
            $table->string('branch_name');
            $table->string('timezone')->default('UTC');
            $table->string('currency', 10)->default('VND');
            $table->tinyInteger('is_active')->default(1);
            $table->tinyInteger('is_default')->default(0);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->string('full_name')->nullable();
            $table->tinyInteger('is_deleted')->default(0);
            $table->timestamps();
        });

        Schema::create('table_templates', function (Blueprint $table): void {
            $table->increments('template_id');
            $table->unsignedInteger('seats');
        });

        Schema::create('restaurant_tables', function (Blueprint $table): void {
            $table->increments('table_id');
            $table->unsignedInteger('template_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->string('status');
            $table->tinyInteger('is_deleted')->default(0);
            $table->unsignedBigInteger('row_version')->default(1);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->increments('item_id');
            $table->string('name');
            $table->tinyInteger('is_available')->default(1);
            $table->tinyInteger('is_preorder_enabled')->default(1);
            $table->unsignedInteger('preorder_quota_per_day')->nullable();
            $table->unsignedInteger('preorder_cutoff_minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('menu_item_prices', function (Blueprint $table): void {
            $table->increments('price_id');
            $table->unsignedInteger('item_id');
            $table->decimal('price', 14, 2);
            $table->string('currency', 10)->default('VND');
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
        });

        Schema::create('reservations', function (Blueprint $table): void {
            $table->increments('reservation_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('branch_id')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('reservation_code');
            $table->dateTime('reserved_at')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->unsignedInteger('guest_count');
            $table->string('status');
            $table->string('source')->default('Online');
            $table->string('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
            $table->timestamps();
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
            $table->string('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
            $table->timestamps();
        });

        Schema::create('reservation_order_items', function (Blueprint $table): void {
            $table->increments('order_item_id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('item_id');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->string('currency', 10)->default('VND');
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('item_name_snapshot')->nullable();
            $table->string('status');
            $table->string('notes')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->increments('payment_id');
            $table->unsignedInteger('reservation_id')->nullable();
        });

        DB::table('users')->insert([
            'user_id' => 7,
            'full_name' => 'Test Customer',
            'is_deleted' => 0,
            'created_at' => Carbon::now('UTC'),
            'updated_at' => Carbon::now('UTC'),
        ]);

        DB::table('branches')->insert([
            'branch_id' => 1,
            'branch_code' => 'MAIN',
            'branch_name' => 'Main Branch',
            'timezone' => 'UTC',
            'currency' => 'VND',
            'is_active' => 1,
            'is_default' => 1,
            'created_at' => Carbon::now('UTC'),
            'updated_at' => Carbon::now('UTC'),
        ]);

        DB::table('table_templates')->insert([
            'template_id' => 21,
            'seats' => 4,
        ]);

        DB::table('restaurant_tables')->insert([
            'table_id' => 11,
            'template_id' => 21,
            'branch_id' => 1,
            'status' => 'Available',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => Carbon::now('UTC'),
            'updated_at' => Carbon::now('UTC'),
        ]);

        $ref = new ReflectionClass(MenuItem::class);
        $property = $ref->getProperty('supportsPreorderColumns');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    public function test_create_reservation_rejects_preorder_items_without_an_effective_price(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 10:00:00', 'UTC'));

        DB::table('menu_items')->insert([
            'item_id' => 501,
            'name' => 'Missing Price Dish',
            'is_available' => 1,
            'is_preorder_enabled' => 1,
            'preorder_quota_per_day' => null,
            'preorder_cutoff_minutes' => 0,
            'created_at' => Carbon::now('UTC'),
            'updated_at' => Carbon::now('UTC'),
        ]);

        $service = $this->makeReservationService();

        try {
            $service->createReservation([
                'user_id' => 7,
                'start_time' => '2026-04-03 12:00:00',
                'end_time' => '2026-04-03 14:00:00',
                'guest_count' => 2,
                'table_ids' => [11],
                'pre_order_items' => [
                    ['item_id' => 501, 'quantity' => 2],
                ],
            ], 7, ['skip_locking' => true]);

            self::fail('Expected preorder validation to reject missing effective price rows.');
        } catch (ValidationException $e) {
            self::assertSame(['Có món chưa có giá hiệu lực tại thời điểm phục vụ.'], $e->errors()['pre_order_items'] ?? []);
        }

        self::assertSame(0, DB::table('reservations')->count());
        self::assertSame(0, DB::table('reservation_orders')->count());
        self::assertSame(0, DB::table('reservation_order_items')->count());
        Carbon::setTestNow();
    }

    public function test_create_reservation_persists_non_zero_preorder_prices_from_the_canonical_policy_service(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 10:00:00', 'UTC'));

        DB::table('menu_items')->insert([
            'item_id' => 601,
            'name' => 'Priced Dish',
            'is_available' => 1,
            'is_preorder_enabled' => 1,
            'preorder_quota_per_day' => null,
            'preorder_cutoff_minutes' => 0,
            'created_at' => Carbon::now('UTC'),
            'updated_at' => Carbon::now('UTC'),
        ]);
        DB::table('menu_item_prices')->insert([
            'price_id' => 1,
            'item_id' => 601,
            'price' => 125000,
            'currency' => 'VND',
            'effective_from' => '2026-04-01 00:00:00',
            'effective_to' => null,
        ]);

        $reservation = $this->makeReservationService()->createReservation([
            'user_id' => 7,
            'start_time' => '2026-04-03 12:00:00',
            'end_time' => '2026-04-03 14:00:00',
            'guest_count' => 2,
            'table_ids' => [11],
            'pre_order_items' => [
                ['item_id' => 601, 'quantity' => 2],
            ],
        ], 7, ['skip_locking' => true]);

        $item = DB::table('reservation_order_items')->first();

        self::assertNotNull($reservation);
        self::assertSame(1, DB::table('reservation_order_items')->count());
        self::assertSame('125000', (string) $item->unit_price);
        self::assertSame('250000', (string) $item->line_total);
        Carbon::setTestNow();
    }

    private function makeReservationService(): ReservationService
    {
        $tableHoldService = $this->createMock(TableHoldService::class);
        $tableHoldService->method('expireStaleHolds')->willReturn(0);

        $lockService = $this->createMock(ReservationLockService::class);
        $codeGenerator = $this->createMock(ReservationCodeGenerator::class);
        $codeGenerator->method('generate')->willReturn('RSV-TEST-0001');

        $notificationOutbox = $this->createMock(NotificationOutboxService::class);
        $notificationOutbox->expects($this->any())->method('enqueueReservationCreated');

        $loyaltyPoints = $this->createMock(LoyaltyPointsService::class);
        $tableStateService = $this->createMock(RestaurantTableStateService::class);
        $tableStateService->method('isAllocatableForBooking')->willReturn(true);

        $tableTimeConflictService = $this->createMock(TableTimeConflictService::class);
        $tableTimeConflictService->method('findHoldConflictTableIds')->willReturn([]);
        $tableTimeConflictService->method('findReservationConflictTableIds')->willReturn([]);

        $financialSync = $this->createMock(ReservationFinancialSyncService::class);

        return new ReservationService(
            $tableHoldService,
            $lockService,
            $codeGenerator,
            $notificationOutbox,
            $loyaltyPoints,
            $tableStateService,
            $tableTimeConflictService,
            $financialSync,
            app(MenuPreorderPolicyService::class),
        );
    }
}
