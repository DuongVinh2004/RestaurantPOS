<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffTableOrderIdempotencyReplayServiceTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->getStore()->flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Group('booking-smoke')]
    public function test_create_on_spot_order_replays_same_order_for_same_idempotency_key(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $service = $this->makeTableOrderService();

        $first = $service->createOnSpotOrder(
            tableId: $tableId,
            reservationId: $reservationId,
            items: [],
            staffUserId: $staffId,
            idempotencyKey: 'idem-onspot-order-1',
            notes: 'same request'
        );

        $second = $service->createOnSpotOrder(
            tableId: $tableId,
            reservationId: $reservationId,
            items: [],
            staffUserId: $staffId,
            idempotencyKey: 'idem-onspot-order-1',
            notes: 'same request'
        );

        $this->assertSame((int) $first->order_id, (int) $second->order_id);
        $this->assertSame(
            1,
            (int) DB::table('reservation_orders')
                ->where('reservation_id', $reservationId)
                ->where('order_type', 'OnSpot')
                ->where('status', 'Active')
                ->count()
        );
    }

    public function test_create_on_spot_order_replays_when_reservation_is_resolved_from_table(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $service = $this->makeTableOrderService();

        $first = $service->createOnSpotOrder(
            tableId: $tableId,
            reservationId: 0,
            items: [],
            staffUserId: $staffId,
            idempotencyKey: 'idem-onspot-order-resolved',
            notes: 'same resolved request'
        );

        $second = $service->createOnSpotOrder(
            tableId: $tableId,
            reservationId: 0,
            items: [],
            staffUserId: $staffId,
            idempotencyKey: 'idem-onspot-order-resolved',
            notes: 'same resolved request'
        );

        $this->assertSame((int) $first->order_id, (int) $second->order_id);
        $this->assertSame((int) $reservationId, (int) $second->reservation_id);
    }

    public function test_create_on_spot_order_rejects_same_idempotency_key_with_different_payload(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $service = $this->makeTableOrderService();

        $service->createOnSpotOrder(
            tableId: $tableId,
            reservationId: $reservationId,
            items: [],
            staffUserId: $staffId,
            idempotencyKey: 'idem-onspot-order-payload-mismatch',
            notes: 'same request'
        );

        try {
            $service->createOnSpotOrder(
                tableId: $tableId,
                reservationId: $reservationId,
                items: [],
                staffUserId: $staffId,
                idempotencyKey: 'idem-onspot-order-payload-mismatch',
                notes: 'different request'
            );

            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('idempotency_key', $e->errors());
            self::assertSame(
                'Idempotency key has already been used with a different request payload. Retry with a new Idempotency-Key.',
                $e->errors()['idempotency_key'][0]
            );
        }

        $this->assertSame(
            1,
            (int) DB::table('reservation_orders')
                ->where('reservation_id', $reservationId)
                ->where('order_type', 'OnSpot')
                ->where('status', 'Active')
                ->count()
        );
    }

    #[Group('booking-smoke')]
    public function test_add_items_replays_without_creating_duplicate_line_items(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $itemId = $this->createMenuItem();
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '120000.00',
            'currency' => 'VND',
        ]);

        $service = $this->makeTableOrderService();
        $items = [[
            'item_id' => $itemId,
            'qty' => 1,
            'note' => 'less ice',
        ]];

        $first = $service->addItems(
            orderId: $orderId,
            items: $items,
            staffUserId: $staffId,
            idempotencyKey: 'idem-order-items-1',
            expectedRowVersion: 1
        );

        $second = $service->addItems(
            orderId: $orderId,
            items: $items,
            staffUserId: $staffId,
            idempotencyKey: 'idem-order-items-1',
            expectedRowVersion: null
        );

        $this->assertSame((int) $first->order_id, (int) $second->order_id);
        $this->assertSame(
            1,
            (int) DB::table('reservation_order_items')
                ->where('order_id', $orderId)
                ->where('item_id', $itemId)
                ->count()
        );
    }

    public function test_add_items_rejects_same_idempotency_key_with_different_payload(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $itemId = $this->createMenuItem();
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '120000.00',
            'currency' => 'VND',
        ]);

        $service = $this->makeTableOrderService();

        $service->addItems(
            orderId: $orderId,
            items: [[
                'item_id' => $itemId,
                'qty' => 1,
                'note' => 'less ice',
            ]],
            staffUserId: $staffId,
            idempotencyKey: 'idem-order-items-payload-mismatch',
            expectedRowVersion: 1
        );

        try {
            $service->addItems(
                orderId: $orderId,
                items: [[
                    'item_id' => $itemId,
                    'qty' => 2,
                    'note' => 'less ice',
                ]],
                staffUserId: $staffId,
                idempotencyKey: 'idem-order-items-payload-mismatch',
                expectedRowVersion: null
            );

            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('idempotency_key', $e->errors());
            self::assertSame(
                'Idempotency key has already been used with a different request payload. Retry with a new Idempotency-Key.',
                $e->errors()['idempotency_key'][0]
            );
        }

        $this->assertSame(
            1,
            (int) DB::table('reservation_order_items')
                ->where('order_id', $orderId)
                ->where('item_id', $itemId)
                ->count()
        );
    }
}
