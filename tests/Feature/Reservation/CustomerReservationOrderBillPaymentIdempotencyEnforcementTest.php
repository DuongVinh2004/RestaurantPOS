<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use App\Models\User;
use App\Services\Staff\StaffCheckoutService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class CustomerReservationOrderBillPaymentIdempotencyEnforcementTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->getStore()->flush();
    }

    public function test_create_requires_idempotency_key_on_customer_bill_payment_session_route(): void
    {
        [$customer, $reservationId] = $this->seedCustomerBillPaymentScenario();

        $response = $this->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/reservations/' . $reservationId . '/bill/payment-sessions', [
                'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
                'provider_code' => 'simulated',
                'currency' => 'VND',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'idempotency_key_required');
    }

    public function test_refresh_requires_idempotency_key_on_customer_bill_payment_session_route(): void
    {
        [$customer, $reservationId, $sessionId] = $this->seedCustomerBillPaymentSession();

        $response = $this->flushHeaders()
            ->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->withoutHeader('Idempotency-Key')
            ->withoutHeader('X-Idempotency-Key')
            ->postJson('/api/v1/reservations/' . $reservationId . '/bill/payment-sessions/' . $sessionId . '/refresh', [
                'row_version' => (int) DB::table('reservation_bill_payment_sessions')->where('bill_payment_session_id', $sessionId)->value('row_version'),
                'simulation_outcome' => 'pending',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'idempotency_key_required');
    }

    public function test_confirm_requires_idempotency_key_on_customer_bill_payment_session_route(): void
    {
        [$customer, $reservationId, $sessionId] = $this->seedCustomerBillPaymentSession();

        $response = $this->flushHeaders()
            ->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->withoutHeader('Idempotency-Key')
            ->withoutHeader('X-Idempotency-Key')
            ->postJson('/api/v1/reservations/' . $reservationId . '/bill/payment-sessions/' . $sessionId . '/confirm', [
                'row_version' => (int) DB::table('reservation_bill_payment_sessions')->where('bill_payment_session_id', $sessionId)->value('row_version'),
                'simulation_outcome' => 'succeeded',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'idempotency_key_required');
    }

    /**
     * @return array{0:User,1:int}
     */
    private function seedCustomerBillPaymentScenario(): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
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
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 2,
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '100000.00',
        ]);

        app(StaffCheckoutService::class)->lockBill(
            orderId: $orderId,
            discountAmount: null,
            notes: 'lock bill for customer bill payment idempotency enforcement',
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );

        return [User::query()->findOrFail($customerId), $reservationId];
    }

    /**
     * @return array{0:User,1:int,2:int}
     */
    private function seedCustomerBillPaymentSession(): array
    {
        [$customer, $reservationId] = $this->seedCustomerBillPaymentScenario();

        $create = $this->actingAs($customer)
            ->withHeaders([
                'Accept' => 'application/json',
                'Idempotency-Key' => 'cust-bill-idem-enforcement-seed-' . $reservationId,
            ])
            ->postJson('/api/v1/reservations/' . $reservationId . '/bill/payment-sessions', [
                'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
                'provider_code' => 'simulated',
                'currency' => 'VND',
            ]);

        $create->assertCreated();

        // withHeaders() persists default headers across subsequent requests on the same
        // test case instance. Clear the seed Idempotency-Key so callers can assert the
        // real missing-header behavior on refresh/confirm routes.
        $this->flushHeaders();

        return [$customer, $reservationId, (int) $create->json('data.payment_session.bill_payment_session_id')];
    }
}
