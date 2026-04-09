<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffReservationVoucherLifecycleTest extends TestCase
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

    public function test_cannot_remove_voucher_after_final_payment_has_been_recorded(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $voucherId = $this->createVoucher();
        $userVoucherId = $this->assignVoucher([
            'user_id' => $customerId,
            'voucher_id' => $voucherId,
        ]);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'bill_currency' => 'VND',
            'applied_user_voucher_id' => $userVoucherId,
            'discount_amount' => '50000.00',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '250000.00',
            'transaction_code' => 'FINAL-VOUCHER-REMOVE-1',
        ]);

        $service = $this->makeVoucherService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot remove voucher after final payment has been recorded.');

        $service->removeVoucher(
            reservationId: $reservationId,
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );
    }

    public function test_apply_voucher_bumps_row_version_and_rejects_stale_remove_request(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
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

        $voucherId = $this->createVoucher([
            'discount_type' => 'Fixed',
            'discount_value' => '25000.00',
            'min_spend' => '0.00',
        ]);
        $userVoucherId = $this->assignVoucher([
            'user_id' => $customerId,
            'voucher_id' => $voucherId,
        ]);

        $service = $this->makeVoucherService();

        $service->applyVoucher(
            reservationId: $reservationId,
            userVoucherId: $userVoucherId,
            voucherCode: null,
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );

        $this->assertSame(2, (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('row_version mismatch');

        $service->removeVoucher(
            reservationId: $reservationId,
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );
    }

    public function test_remove_voucher_preserves_existing_loyalty_discount_snapshot(): void
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

        $loyalty = $this->makeLoyaltyService();
        $voucherService = $this->makeVoucherService();

        $loyalty->redeemReservationPoints(
            reservationId: $reservationId,
            points: 50,
            reason: 'seed loyalty discount',
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );

        $currentVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');

        $voucherService->applyVoucher(
            reservationId: $reservationId,
            userVoucherId: $userVoucherId,
            voucherCode: null,
            expectedRowVersion: $currentVersion,
            staffUserId: $staffId,
        );

        $afterApplyVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');
        $this->assertSame(
            '100000.00',
            number_format((float) DB::table('reservations')->where('reservation_id', $reservationId)->value('discount_amount'), 2, '.', '')
        );

        $voucherService->removeVoucher(
            reservationId: $reservationId,
            expectedRowVersion: $afterApplyVersion,
            staffUserId: $staffId,
        );

        $this->assertSame(
            '50000.00',
            number_format((float) DB::table('reservations')->where('reservation_id', $reservationId)->value('discount_amount'), 2, '.', '')
        );
    }

    public function test_remove_voucher_releases_lock_and_resets_discount_snapshot(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
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
            'unit_price' => 75000,
            'line_total' => 150000,
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

        $service = $this->makeVoucherService();

        $applied = $service->applyVoucher(
            reservationId: $reservationId,
            userVoucherId: $userVoucherId,
            voucherCode: null,
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );

        $this->assertSame($userVoucherId, (int) ($applied['reservation']->applied_user_voucher_id ?? 0));
        $this->assertSame(50000.0, (float) ($applied['reservation']->discount_amount ?? 0.0));
        $this->assertNotNull(DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->value('lock_token'));

        $removed = $service->removeVoucher(
            reservationId: $reservationId,
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        $reservation = $removed['reservation']->fresh();

        $this->assertNull($reservation->applied_user_voucher_id);
        $this->assertSame(0.0, (float) ($reservation->discount_amount ?? 0.0));
        $this->assertNull(DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->value('lock_token'));
        $this->assertNull(DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->value('locked_until'));
    }
}
