<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Modules\BenefitsLoyalty\Domain\Guards\VoucherUsageGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class VoucherUsageGuardTest extends TestCase
{
    use BuildsBookingScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        DB::table('user_vouchers')->delete();
        DB::table('vouchers')->delete();
        DB::table('reservations')->delete();
        DB::table('users')->delete();
    }

    public function test_assert_can_consume_rejects_when_global_usage_limit_is_exhausted(): void
    {
        $usedByUserId = $this->createUser();
        $requestingUserId = $this->createUser();
        $voucherId = $this->createVoucher([
            'max_usage' => 1,
            'max_usage_per_user' => null,
        ]);
        $usedReservationId = $this->createReservation([
            'user_id' => $usedByUserId,
        ]);

        $this->assignVoucher([
            'user_id' => $usedByUserId,
            'voucher_id' => $voucherId,
            'is_used' => 1,
            'used_date' => $this->nowUtc(),
            'used_reservation_id' => $usedReservationId,
            'used_amount' => '10000.00',
        ]);

        $voucher = VoucherUsageGuard::lockVoucherForUpdate($voucherId);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Voucher usage limit has been reached.');

        VoucherUsageGuard::assertCanConsume($voucher, $requestingUserId, null);
    }

    public function test_assert_can_consume_rejects_when_per_user_limit_is_exhausted(): void
    {
        $userId = $this->createUser();
        $voucherId = $this->createVoucher([
            'max_usage' => null,
            'max_usage_per_user' => 1,
        ]);
        $usedReservationId = $this->createReservation([
            'user_id' => $userId,
        ]);

        $this->assignVoucher([
            'user_id' => $userId,
            'voucher_id' => $voucherId,
            'is_used' => 1,
            'used_date' => $this->nowUtc(),
            'used_reservation_id' => $usedReservationId,
            'used_amount' => '15000.00',
        ]);

        $voucher = VoucherUsageGuard::lockVoucherForUpdate($voucherId);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Voucher per-user usage limit has been reached.');

        VoucherUsageGuard::assertCanConsume($voucher, $userId, null);
    }
}
