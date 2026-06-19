<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerReservationOrderBillControllerTest extends TestCase
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
        Cache::store('redis')->flush();
        config()->set('booking.customer_session_exact_link_access_hours', 24);
        config()->set('booking.customer_session_legacy_access_hours', 24);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_owner_can_view_reservation_bill_preview(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'discount_amount' => '10000',
            'final_bill_amount' => '90000',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $itemId = $this->createMenuItem(['name' => 'Fried Rice', 'code' => 'FRIED-RICE']);
        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 2,
            'unit_price' => '50000',
            'currency' => 'VND',
            'line_total' => '100000',
            'item_name_snapshot' => 'Fried Rice',
        ]);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '30000',
            'currency' => 'VND',
            'transaction_code' => 'DEP-BILL-1',
        ]);

        $customer = User::query()->findOrFail($customerId);
        $response = $this->actingAs($customer)->getJson("/api/v1/reservations/{$reservationId}/bill");

        $response
            ->assertOk()
            ->assertJsonPath('data.access_scope', 'owner')
            ->assertJsonPath('data.bill.scope', 'reservation')
            ->assertJsonPath('data.bill.total_due', '90000')
            ->assertJsonPath('data.bill.is_locked', true)
            ->assertJsonPath('data.settlement.deposit_applied', '30000')
            ->assertJsonPath('data.settlement.remaining_due', '60000')
            ->assertJsonPath('data.workflow.payment_session_support.create', false)
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonPath('data.orders.0.items.0.item.name', 'Fried Rice');
    }

    public function test_session_linked_guest_can_view_bill(): void
    {
        $now = Carbon::parse('2026-03-25 10:00:00', 'UTC');
        Carbon::setTestNow($now);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'start_time' => $now->copy()->addHours(1),
            'end_time' => $now->copy()->addHours(3),
        ]);
        $tableId = $this->attachReservationTable($reservationId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 1,
            'unit_price' => '75000',
            'currency' => 'VND',
            'line_total' => '75000',
            'item_name_snapshot' => 'Tea',
        ]);

        DB::table('table_holds')->insert([
            'hold_id' => 'hold-bill-session-1',
            'session_id' => 'session-bill-1',
            'user_id' => $customerId,
            'confirmed_reservation_id' => $reservationId,
            'start_time' => $now->copy()->addHours(1),
            'end_time' => $now->copy()->addHours(3),
            'hold_status' => 'Confirmed',
            'expire_at' => $now->copy()->subMinute(),
            'created_at' => $now->copy()->subDays(2),
            'updated_at' => $now->copy()->subDays(2),
        ]);
        DB::table('table_hold_details')->insert([
            'hold_id' => 'hold-bill-session-1',
            'table_id' => $tableId,
        ]);

        $response = $this->getJson("/api/v1/reservations/{$reservationId}/bill", [
            'X-Session-Id' => 'session-bill-1',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.access_scope', 'session')
            ->assertJsonPath('data.bill.total_due', '75000')
            ->assertJsonPath('data.settlement.remaining_due', '75000');

        Carbon::setTestNow();
    }

    public function test_cross_user_access_returns_not_found(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $otherUserId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'status' => 'Reserved',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 1,
            'unit_price' => '100000',
            'currency' => 'VND',
            'line_total' => '100000',
        ]);

        $otherUser = User::query()->findOrFail($otherUserId);
        $response = $this->actingAs($otherUser)->getJson("/api/v1/reservations/{$reservationId}/bill");

        $response->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_ineligible_reservation_state_is_rejected(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 1,
            'unit_price' => '100000',
            'currency' => 'VND',
            'line_total' => '100000',
        ]);

        $customer = User::query()->findOrFail($customerId);
        $response = $this->actingAs($customer)->getJson("/api/v1/reservations/{$reservationId}/bill");

        $response
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');
    }

    public function test_staff_actor_is_rejected_from_customer_bill_route_even_with_valid_session_link(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $tableId = $this->attachReservationTable($reservationId);
        $sessionId = 'session-bill-staff-forbidden';

        DB::table('table_holds')->insert([
            'hold_id' => 'hold-bill-staff-forbidden',
            'session_id' => $sessionId,
            'user_id' => $customerId,
            'confirmed_reservation_id' => $reservationId,
            'start_time' => $this->nowUtc()->copy()->addHour(),
            'end_time' => $this->nowUtc()->copy()->addHours(3),
            'hold_status' => 'Confirmed',
            'expire_at' => $this->nowUtc()->copy()->addMinutes(30),
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);
        DB::table('table_hold_details')->insert([
            'hold_id' => 'hold-bill-staff-forbidden',
            'table_id' => $tableId,
        ]);

        $response = $this->withHeaders(array_merge(
            $this->staffAuthHeaders($staffId, 'staff-bill-forbidden'),
            ['X-Session-Id' => $sessionId]
        ))->getJson("/api/v1/reservations/{$reservationId}/bill");

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }
}
