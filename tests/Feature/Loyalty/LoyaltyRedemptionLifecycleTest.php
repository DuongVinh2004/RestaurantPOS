<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class LoyaltyRedemptionLifecycleTest extends TestCase
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

    public function test_cannot_release_loyalty_redemption_after_final_payment_has_been_recorded(): void
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
            'transaction_code' => 'FINAL-LOYALTY-RELEASE-1',
        ]);

        $service = $this->makeLoyaltyService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot release loyalty redemption after final payment has been recorded.');

        $service->releaseReservationRedemption(
            reservationId: $reservationId,
            reason: 'test',
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );
    }

    public function test_redeem_points_bumps_row_version_and_rejects_stale_release_request(): void
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
            'discount_amount' => '0.00',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Completed',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'currency' => 'VND',
            'quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
        ]);

        $service = $this->makeLoyaltyService();

        $service->redeemReservationPoints(
            reservationId: $reservationId,
            points: 50,
            reason: 'seed stale release check',
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );

        $this->assertSame(2, (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('row_version mismatch');

        $service->releaseReservationRedemption(
            reservationId: $reservationId,
            reason: 'stale request',
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );
    }

    public function test_release_loyalty_redemption_preserves_existing_voucher_discount_snapshot(): void
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
            'discount_amount' => '0.00',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Completed',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'currency' => 'VND',
            'quantity' => 2,
            'unit_price' => 100000,
            'line_total' => 200000,
        ]);

        $voucherId = $this->createVoucher([
            'discount_type' => 'Fixed',
            'discount_value' => '50000.00',
            'min_spend' => '0.00',
        ]);
        $userVoucherId = $this->assignVoucher([
            'user_id' => $customerId,
            'voucher_id' => $voucherId,
        ]);

        $voucherService = $this->makeVoucherService();
        $service = $this->makeLoyaltyService();

        $voucherService->applyVoucher(
            reservationId: $reservationId,
            userVoucherId: $userVoucherId,
            voucherCode: null,
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );

        $currentVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');

        $service->redeemReservationPoints(
            reservationId: $reservationId,
            points: 50,
            reason: 'stack loyalty on voucher',
            expectedRowVersion: $currentVersion,
            staffUserId: $staffId,
        );

        $afterRedeemVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');
        $this->assertSame(
            '100000.00',
            number_format((float) DB::table('reservations')->where('reservation_id', $reservationId)->value('discount_amount'), 2, '.', '')
        );

        $service->releaseReservationRedemption(
            reservationId: $reservationId,
            reason: 'keep voucher only',
            expectedRowVersion: $afterRedeemVersion,
            staffUserId: $staffId,
        );

        $this->assertSame(
            '50000.00',
            number_format((float) DB::table('reservations')->where('reservation_id', $reservationId)->value('discount_amount'), 2, '.', '')
        );
    }

    public function test_release_loyalty_redemption_restores_points_and_clears_discount_snapshot(): void
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
            'discount_amount' => '0.00',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Completed',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'currency' => 'VND',
            'quantity' => 2,
            'unit_price' => 100000,
            'line_total' => 200000,
        ]);

        $service = $this->makeLoyaltyService();

        $redeemed = $service->redeemReservationPoints(
            reservationId: $reservationId,
            points: 100,
            reason: 'test',
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );

        $this->assertSame(100.0, (float) ($redeemed['reservation']['loyalty']['redeemed_points'] ?? 0.0));
        $this->assertSame(100000.0, (float) ($redeemed['reservation']['loyalty']['discount_amount'] ?? 0.0));
        $this->assertSame(400, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));

        $released = $service->releaseReservationRedemption(
            reservationId: $reservationId,
            reason: 'cleanup',
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        $reservation = DB::table('reservations')->where('reservation_id', $reservationId)->first();

        $this->assertSame(0.0, (float) ($reservation->discount_amount ?? 0.0));
        $this->assertSame(500, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        $this->assertSame(0.0, (float) ($released['reservation']['loyalty']['redeemed_points'] ?? 0.0));
        $this->assertSame(0.0, (float) ($released['reservation']['loyalty']['discount_amount'] ?? 0.0));
    }
}
