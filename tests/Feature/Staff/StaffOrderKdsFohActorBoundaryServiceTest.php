<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\FloorOperations\Application\UseCases\ServiceSessions\StaffServiceSessionService;
use App\Modules\KitchenDispatch\Application\Workflows\KitchenRoutingService;
use App\Modules\Ordering\Application\Queries\StaffOrderReadService;
use App\Modules\Ordering\Application\UseCases\OrderItems\StaffOrderItemLifecycleService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Support\Auth\StaffActorGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class StaffOrderKdsFohActorBoundaryServiceTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('booking.realtime.enabled', true);
        config()->set('booking.realtime.cache_store', 'array');
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
        Cache::store('array')->flush();
        Cache::store('redis')->getStore()->flush();

        $this->app->instance(ReservationLockService::class, $this->mockReservationLocks());
        $this->app->instance(RestaurantTableStateService::class, new RestaurantTableStateService);
        $this->app->instance(TableTimeConflictService::class, new TableTimeConflictService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_staff_order_read_without_actor_fails_closed(): void
    {
        $this->assertMissingStaffActorFails(fn () => app(StaffOrderReadService::class)->findOrder(999999, null));
    }

    public function test_staff_order_add_item_without_actor_fails_closed(): void
    {
        $scenario = $this->seedActiveOrderScenario();
        $beforeCount = (int) DB::table('reservation_order_items')->where('order_id', $scenario['order_id'])->count();

        $this->assertMissingStaffActorFails(fn () => $this->makeTableOrderService()->addItems(
            orderId: $scenario['order_id'],
            items: [[
                'item_id' => $scenario['item_id'],
                'qty' => 1,
            ]],
            staffUserId: null,
            idempotencyKey: 'idem-order-add-item-no-actor',
            expectedRowVersion: 1,
        ));

        self::assertSame($beforeCount, (int) DB::table('reservation_order_items')->where('order_id', $scenario['order_id'])->count());
    }

    public function test_staff_order_item_lifecycle_without_actor_fails_closed(): void
    {
        $scenario = $this->seedActiveOrderScenario();

        $this->assertMissingStaffActorFails(fn () => app(StaffOrderItemLifecycleService::class)->updateItem(
            orderId: $scenario['order_id'],
            orderItemId: $scenario['order_item_id'],
            attributes: ['qty' => 2],
            staffUserId: null,
            expectedOrderRowVersion: 1,
            expectedItemRowVersion: 1,
        ));

        self::assertSame(1, (int) DB::table('reservation_order_items')->where('order_item_id', $scenario['order_item_id'])->value('quantity'));
    }

    public function test_kitchen_dispatch_without_actor_fails_closed(): void
    {
        $scenario = $this->seedRoutedKitchenOrderScenario();

        $this->assertMissingStaffActorFails(fn () => app(KitchenRoutingService::class)->dispatchOrder(
            orderId: $scenario['order_id'],
            expectedOrderRowVersion: 1,
            actorUserId: null,
        ));

        self::assertSame(0, (int) DB::table('kitchen_order_item_tickets')->where('order_id', $scenario['order_id'])->count());
    }

    public function test_kitchen_action_without_actor_fails_closed(): void
    {
        $scenario = $this->seedRoutedKitchenOrderScenario();
        $result = app(KitchenRoutingService::class)->dispatchOrder($scenario['order_id'], 1, $scenario['staff_id']);
        $ticketId = (int) $result['tickets']->first()->ticket_id;

        $this->assertMissingStaffActorFails(fn () => app(KitchenRoutingService::class)->fireTicket(
            ticketId: $ticketId,
            expectedTicketRowVersion: 1,
            actorUserId: null,
        ));

        self::assertSame('Queued', (string) DB::table('kitchen_order_item_tickets')->where('ticket_id', $ticketId)->value('ticket_status'));
    }

    public function test_service_session_without_actor_fails_closed(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => 1,
            'status' => 'Available',
        ]);

        $this->assertMissingStaffActorFails(fn () => app(StaffServiceSessionService::class)->createWalkInSession([
            'branch_id' => 1,
            'user_id' => $customerId,
            'table_ids' => [$tableId],
            'guest_count' => 2,
            'started_at' => $this->nowUtc()->copy()->addMinutes(30)->toIso8601String(),
            'service_minutes' => 60,
        ], null));

        self::assertSame(0, (int) DB::table('reservations')->where('source', 'WalkIn')->count());
        self::assertSame('Available', (string) DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_authorized_same_branch_staff_order_flow_still_succeeds(): void
    {
        $scenario = $this->seedActiveReservationScenario();
        $service = $this->makeTableOrderService();

        $order = $service->createOnSpotOrder(
            tableId: $scenario['table_id'],
            reservationId: $scenario['reservation_id'],
            items: [],
            staffUserId: $scenario['staff_id'],
            idempotencyKey: 'idem-authorized-order-create',
            expectedRowVersion: 1,
        );

        $createdOrderId = (int) $order->order_id;
        $service->addItems(
            orderId: $createdOrderId,
            items: [[
                'item_id' => $scenario['item_id'],
                'qty' => 2,
                'note' => 'same branch',
            ]],
            staffUserId: $scenario['staff_id'],
            idempotencyKey: 'idem-authorized-order-add-item',
            expectedRowVersion: (int) ($order->row_version ?? 1),
        );

        self::assertSame(1, (int) DB::table('reservation_order_items')
            ->where('order_id', $createdOrderId)
            ->where('item_id', $scenario['item_id'])
            ->where('quantity', 2)
            ->count());
    }

    public function test_authorized_same_branch_kds_flow_still_succeeds(): void
    {
        $scenario = $this->seedRoutedKitchenOrderScenario();

        $dispatch = app(KitchenRoutingService::class)->dispatchOrder($scenario['order_id'], 1, $scenario['staff_id']);
        $ticketId = (int) $dispatch['tickets']->first()->ticket_id;

        $ticket = app(KitchenRoutingService::class)->fireTicket($ticketId, 1, $scenario['staff_id']);

        self::assertSame('Fired', (string) ($ticket->ticket_status?->value ?? $ticket->ticket_status));
        self::assertSame('InProgress', (string) DB::table('reservation_order_items')->where('order_item_id', $scenario['order_item_id'])->value('status'));
    }

    private function assertMissingStaffActorFails(callable $callback): void
    {
        try {
            $callback();

            $this->fail('Expected missing staff actor validation failure was not thrown.');
        } catch (ValidationException $e) {
            self::assertSame(
                [StaffActorGuard::REQUIRED_MESSAGE],
                $e->errors()['staff_user_id'] ?? [],
            );
        }
    }

    /**
     * @return array{staff_id:int,table_id:int,reservation_id:int,item_id:int}
     */
    private function seedActiveReservationScenario(): array
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => 1,
            'status' => 'Occupied',
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => 1,
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'bill_currency' => 'VND',
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $itemId = $this->createMenuItem();
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '50000.00',
            'currency' => 'VND',
        ]);

        return [
            'staff_id' => $staffId,
            'table_id' => $tableId,
            'reservation_id' => $reservationId,
            'item_id' => $itemId,
        ];
    }

    /**
     * @return array{staff_id:int,table_id:int,reservation_id:int,order_id:int,order_item_id:int,item_id:int}
     */
    private function seedActiveOrderScenario(): array
    {
        $scenario = $this->seedActiveReservationScenario();
        $orderId = $this->createOrder([
            'reservation_id' => $scenario['reservation_id'],
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $orderItemId = $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $scenario['item_id'],
            'quantity' => 1,
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '50000.00',
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        return $scenario + [
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
        ];
    }

    /**
     * @return array{staff_id:int,order_id:int,order_item_id:int}
     */
    private function seedRoutedKitchenOrderScenario(): array
    {
        $categoryId = $this->ensureMenuCategory('Actor Boundary Kitchen');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'Actor Boundary Bowl',
        ]);
        $stationId = $this->createKitchenStation([
            'branch_id' => 1,
            'name' => 'Actor Boundary Pass',
        ]);
        $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'branch_id' => 1,
            'category_id' => $categoryId,
        ]);

        $staffId = $this->createUser(['role_name' => 'Manager']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'branch_id' => 1,
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $orderItemId = $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        return [
            'staff_id' => $staffId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
        ];
    }
}
