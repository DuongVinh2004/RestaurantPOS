<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffOrderCriticalFlowHttpTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->getStore()->flush();
    }

    public function test_create_order_requires_occupied_table_and_service_session_context(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-order-critical-create-key');
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $availableTableId = $this->createRestaurantTable(['status' => 'Available']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $availableTableId);

        $availableTable = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-order-available-table'))
            ->postJson('/api/v1/staff/tables/'.$availableTableId.'/orders', [
                'reservation_id' => $reservationId,
                'row_version' => 1,
            ]);

        $availableTable->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['table_id']);

        $occupiedTableWithoutSessionId = $this->createRestaurantTable(['status' => 'Occupied']);

        $missingSession = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-order-missing-session'))
            ->postJson('/api/v1/staff/tables/'.$occupiedTableWithoutSessionId.'/orders', [
                'row_version' => 1,
            ]);

        $missingSession->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['reservation_id']);

        $this->assertSame(0, (int) DB::table('reservation_orders')->where('reservation_id', $reservationId)->count());
    }

    public function test_add_order_item_requires_order_row_version(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-order-critical-add-item-key');
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $itemId = $this->createMenuItem([
            'code' => 'ORDER-CRITICAL-ADD-01',
            'name' => 'Order Critical Add',
        ]);
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '50000.00',
            'currency' => 'VND',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-staff-order-add-missing-row-version'))
            ->postJson('/api/v1/staff/orders/'.$orderId.'/items', [
                'items' => [
                    [
                        'menu_item_id' => $itemId,
                        'qty' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['row_version']);

        $this->assertSame(0, (int) DB::table('reservation_order_items')
            ->where('order_id', $orderId)
            ->where('item_id', $itemId)
            ->count());
    }
}
