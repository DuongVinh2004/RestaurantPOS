<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerReservationDepositPaymentVisibilityFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_staff_cannot_use_customer_deposit_payment_session_endpoint(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Admin']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);

        $response = $this->withHeaders(
            $this->withIdempotencyKey(
                $this->staffAuthHeaders($staffId),
                'customer-deposit-session-staff-block'
            )
        )->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions', [
            'row_version' => 1,
            'provider_code' => 'simulated',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }
}
