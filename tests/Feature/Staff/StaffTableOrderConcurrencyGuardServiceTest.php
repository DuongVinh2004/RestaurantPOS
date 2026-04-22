<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffTableOrderConcurrencyGuardServiceTest extends TestCase
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

    public function test_create_on_spot_order_reuses_single_active_order_across_distinct_idempotency_keys(): void
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
            idempotencyKey: 'idem-overlap-order-1',
            notes: ''
        );

        $second = $service->createOnSpotOrder(
            tableId: $tableId,
            reservationId: $reservationId,
            items: [],
            staffUserId: $staffId,
            idempotencyKey: 'idem-overlap-order-2',
            notes: ''
        );

        self::assertSame((int) $first->order_id, (int) $second->order_id);
        self::assertSame(
            1,
            (int) DB::table('reservation_orders')
                ->where('reservation_id', $reservationId)
                ->where('order_type', 'OnSpot')
                ->where('status', 'Active')
                ->count()
        );
    }

    public function test_create_on_spot_order_rejects_stale_row_version_when_active_order_has_newer_version(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 2,
        ]);

        $service = $this->makeTableOrderService();

        try {
            $service->createOnSpotOrder(
                tableId: $tableId,
                reservationId: $reservationId,
                items: [],
                staffUserId: $staffId,
                idempotencyKey: 'idem-overlap-order-stale',
                notes: '',
                expectedRowVersion: 1,
            );

            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('row_version', $e->errors());
            self::assertSame('Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.', $e->errors()['row_version'][0]);
        }
    }

    public function test_create_on_spot_order_rejects_stale_reservation_row_version_before_initial_order_create(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'row_version' => 3,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $service = $this->makeTableOrderService();

        try {
            $service->createOnSpotOrder(
                tableId: $tableId,
                reservationId: $reservationId,
                items: [],
                staffUserId: $staffId,
                idempotencyKey: 'idem-overlap-order-stale-reservation',
                notes: '',
                expectedRowVersion: 1,
            );

            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('row_version', $e->errors());
            self::assertSame('Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.', $e->errors()['row_version'][0]);
        }
    }

    public function test_add_items_rejects_after_interleaving_checkout_completes_order(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 1,
            'unit_price' => '100000.00',
            'currency' => 'VND',
            'line_total' => '100000.00',
        ]);
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => 1,
            'status' => 'Open',
        ]);

        $checkoutService = $this->makeCheckoutService();
        $checkoutService->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 100000.00,
            currency: 'VND',
            transactionCode: 'ORDER-OVERLAP-PAY-1',
            paymentProvider: 'Cash',
            notes: 'complete before add-items',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-order-overlap-pay-1'
        );

        $itemId = $this->createMenuItem();
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '25000.00',
            'currency' => 'VND',
        ]);

        $service = $this->makeTableOrderService();

        try {
            $service->addItems(
                orderId: $orderId,
                items: [[
                    'item_id' => $itemId,
                    'qty' => 1,
                ]],
                staffUserId: $staffId,
                idempotencyKey: 'idem-order-overlap-add-1',
                expectedRowVersion: 1,
            );

            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('row_version', $e->errors());
            self::assertStringContainsString('row_version mismatch', $e->errors()['row_version'][0]);
        }
    }

    public function test_add_items_rejects_bill_locked_reservation_even_with_current_order_version(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'bill_currency' => 'VND',
            'final_bill_amount' => '100000.00',
            'billed_at' => $this->nowUtc(),
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $itemId = $this->createMenuItem();
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '25000.00',
            'currency' => 'VND',
        ]);

        $service = $this->makeTableOrderService();

        try {
            $service->addItems(
                orderId: $orderId,
                items: [[
                    'item_id' => $itemId,
                    'qty' => 1,
                ]],
                staffUserId: $staffId,
                idempotencyKey: 'idem-order-bill-locked-add-1',
                expectedRowVersion: 1,
            );

            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('reservation_id', $e->errors());
            self::assertStringContainsString('bill has already been closed', $e->errors()['reservation_id'][0]);
        }
    }
}
