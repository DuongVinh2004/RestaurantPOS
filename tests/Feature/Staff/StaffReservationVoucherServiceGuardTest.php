<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffReservationVoucherServiceGuardTest extends TestCase
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

    public function test_cannot_apply_voucher_after_final_payment_has_been_recorded(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'bill_currency' => 'VND',
        ]);

        $voucherId = $this->createVoucher();
        $userVoucherId = $this->assignVoucher([
            'user_id' => $customerId,
            'voucher_id' => $voucherId,
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '250000.00',
            'transaction_code' => 'FINAL-1',
        ]);

        $service = $this->makeVoucherService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot apply voucher after final payment has been recorded.');

        $service->applyVoucher(
            reservationId: $reservationId,
            userVoucherId: $userVoucherId,
            voucherCode: null,
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );
    }
}
