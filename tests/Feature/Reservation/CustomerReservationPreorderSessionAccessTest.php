<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerReservationPreorderSessionAccessTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('booking.customer_preorder_management_cutoff_minutes', 60);
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->getStore()->flush();
    }

    public function test_session_linked_customer_can_view_current_preorder_via_canonical_route(): void
    {
        [$reservationId, $orderId, $itemId, $sessionId] = $this->seedSessionLinkedPreorderReservation();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Session-Id' => $sessionId,
        ])->getJson('/api/v1/reservations/'.$reservationId.'/preorder');

        $response->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.pre_order.present', true)
            ->assertJsonPath('data.pre_order.order_id', $orderId)
            ->assertJsonPath('data.pre_order.lines.0.item_id', $itemId)
            ->assertJsonPath('meta.access_scope', 'session');
    }

    public function test_wrong_session_cannot_replace_preorder_through_session_bound_flow(): void
    {
        [$reservationId, $orderId] = $this->seedSessionLinkedPreorderReservation();
        $replacementItemId = $this->seedPreorderMenuItem('Session Replace Dish', 20, '65000.00');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Session-Id' => 'sess-preorder-wrong-session',
            'Idempotency-Key' => 'session-preorder-wrong-session-replace',
        ])->putJson('/api/v1/reservations/'.$reservationId.'/preorder', [
            'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
            'pre_order_row_version' => (int) DB::table('preorders')->where('preorder_id', $orderId)->value('row_version'),
            'pre_order_items' => [
                ['item_id' => $replacementItemId, 'quantity' => 1],
            ],
        ]);

        $response->assertNotFound();
    }

    /**
     * @return array{0:int,1:int,2:int,3:string}
     */
    private function seedSessionLinkedPreorderReservation(): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable();
        $start = $this->nowUtc()->copy()->addHours(8);
        $end = $start->copy()->addHours(2);
        $sessionId = 'sess-preorder-linked-001';

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'start_time' => $start,
            'end_time' => $end,
            'row_version' => 2,
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createTableHold([
            'session_id' => $sessionId,
            'user_id' => $customerId,
            'confirmed_reservation_id' => $reservationId,
            'hold_status' => 'Confirmed',
            'start_time' => $start,
            'end_time' => $end,
            'expire_at' => $this->nowUtc()->copy()->addMinutes(30),
        ], [$tableId]);

        $itemId = $this->seedPreorderMenuItem('Session Seeded Pho', 30, '80000.00');
        $orderId = DB::table('preorders')->insertGetId([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'status' => 'draft',
            'row_version' => 3,
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);
        DB::table('preorder_items')->insert([
            'preorder_id' => $orderId,
            'menu_item_id' => $itemId,
            'item_name_snapshot' => 'Session Seeded Pho',
            'unit_price_snapshot' => '80000.00',
            'quantity' => 2,
            'line_total_snapshot' => '160000.00',
            'currency' => 'VND',
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);

        return [$reservationId, $orderId, $itemId, $sessionId];
    }

    private function seedPreorderMenuItem(string $name, int $cutoffMinutes, string $price = '100000.00'): int
    {
        $itemId = $this->createMenuItem([
            'name' => $name,
            'code' => strtoupper(substr(str_replace(' ', '-', $name), 0, 12)).'-'.random_int(10, 99),
            'is_available' => 1,
            'is_preorder_enabled' => 1,
            'preorder_cutoff_minutes' => $cutoffMinutes,
            'preorder_quota_per_day' => 50,
        ]);

        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => $price,
            'currency' => 'VND',
            'effective_from' => $this->nowUtc()->copy()->subDay(),
        ]);

        return $itemId;
    }
}
