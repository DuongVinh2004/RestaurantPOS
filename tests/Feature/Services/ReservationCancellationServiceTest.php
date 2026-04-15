<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Reservations\Application\Services\ReservationCancellationService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ReservationCancellationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropIfExists('reservation_order_items');
        Schema::dropIfExists('reservation_orders');
        Schema::dropIfExists('reservations');

        Schema::create('reservations', function (Blueprint $table): void {
            $table->increments('reservation_id');
            $table->unsignedInteger('user_id')->default(1);
            $table->string('reservation_code')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->unsignedInteger('guest_count')->default(2);
            $table->string('status')->default('Reserved');
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->unsignedInteger('cancelled_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
            $table->timestamps();
        });

        Schema::create('reservation_orders', function (Blueprint $table): void {
            $table->increments('order_id');
            $table->unsignedInteger('reservation_id');
            $table->string('order_type')->default('OnSpot');
            $table->string('status')->default('Active');
            $table->string('notes')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
            $table->timestamps();
        });

        Schema::create('reservation_order_items', function (Blueprint $table): void {
            $table->increments('order_item_id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 14, 2)->default(10);
            $table->string('currency')->default('VND');
            $table->decimal('line_total', 14, 2)->default(10);
            $table->string('item_name_snapshot')->nullable();
            $table->string('status')->default('Pending');
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
            $table->timestamps();
        });
    }

    public function test_it_cancels_active_orders_and_releases_tables_for_reserved_reservation(): void
    {
        $reservation = Reservation::query()->create([
            'reservation_code' => 'RSV-1',
            'status' => 'Reserved',
        ]);

        $order = ReservationOrder::query()->create([
            'reservation_id' => (int) $reservation->reservation_id,
            'status' => 'Active',
        ]);

        ReservationOrderItem::query()->create([
            'order_id' => (int) $order->order_id,
            'status' => 'InProgress',
        ]);
        ReservationOrderItem::query()->create([
            'order_id' => (int) $order->order_id,
            'status' => 'Served',
        ]);

        $mock = $this->mock(RestaurantTableStateService::class, function ($mock): void {
            $mock->shouldReceive('releaseTablesSafely')
                ->once()
                ->with(
                    [11, 12],
                    \Mockery::type(Carbon::class),
                    99,
                    [
                        'reservation_id' => 1,
                        'source' => 'reservation_cancel_after_payment',
                        'reason' => 'cancel_after_payment',
                    ]
                );
        });

        $service = new ReservationCancellationService($mock);
        $service->cancelAfterPaymentLocked($reservation, new Collection([$order]), [11, 12], 99, 'Customer requested cancellation');
        $reservation->save();

        $reservation->refresh();
        $order->refresh();
        $items = ReservationOrderItem::query()->where('order_id', $order->order_id)->orderBy('order_item_id')->get()->all();

        self::assertSame('Cancelled', (string) ($reservation->status->value ?? $reservation->status));
        self::assertSame('Customer requested cancellation', $reservation->cancel_reason);
        self::assertSame(99, (int) $reservation->cancelled_by);
        self::assertSame('Cancelled', (string) ($order->status->value ?? $order->status));
        self::assertSame('Cancelled', (string) ($items[0]->status->value ?? $items[0]->status));
        self::assertSame('Served', (string) ($items[1]->status->value ?? $items[1]->status));
    }
}
