<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class LoyaltyPointsServiceGuardTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cannot_redeem_points_after_final_payment_has_been_recorded(): void
    {
        $tierId = $this->createLoyaltyTier();
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'current_tier_id' => $tierId,
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->ensureUserPoints($customerId, 500, $staffId);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'bill_currency' => 'VND',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '150000.00',
            'transaction_code' => 'FINAL-LOYALTY-1',
        ]);

        $service = $this->makeLoyaltyService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot change loyalty redemption after final payment has been recorded.');

        $service->redeemReservationPoints(
            reservationId: $reservationId,
            points: 100,
            reason: 'test',
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );
    }
}
