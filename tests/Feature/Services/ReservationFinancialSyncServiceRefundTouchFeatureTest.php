<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\Staff\StaffCheckoutService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class ReservationFinancialSyncServiceRefundTouchFeatureTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    public function test_partial_refund_touches_reservation_row_version_even_when_timestamp_is_frozen(): void
    {
        $frozenNow = Carbon::create(2026, 3, 25, 0, 0, 0, 'UTC');
        Carbon::setTestNow($frozenNow);

        try {
            $customerId = $this->createUser(['role_name' => 'Customer']);
            $staffId = $this->createUser(['role_name' => 'Staff']);
            $reservationId = $this->createReservation([
                'user_id' => $customerId,
                'status' => 'Completed',
                'deposit_required_amount' => '100000.00',
                'deposit_paid_amount' => '100000.00',
                'deposit_status' => 'Paid',
                'bill_currency' => 'VND',
                'updated_at' => $frozenNow,
                'row_version' => 1,
            ]);

            $this->createPayment([
                'reservation_id' => $reservationId,
                'payment_type' => 'Deposit',
                'status' => 'Success',
                'amount' => '100000.00',
                'currency' => 'VND',
                'transaction_code' => 'DEP-REFUND-TOUCH-1',
            ]);

            $beforeVersion = (int) $this->table('reservations')->where('reservation_id', $reservationId)->value('row_version');

            app(StaffCheckoutService::class)->refundReservation(
                reservationId: $reservationId,
                paymentMethod: 'Cash',
                refundScope: 'deposit',
                refundAmount: 40000.00,
                currency: 'VND',
                transactionCode: 'RF-REFUND-TOUCH-1',
                paymentProvider: 'Cash',
                notes: 'partial refund touch',
                reason: 'customer_request',
                expectedRowVersion: $beforeVersion,
                staffUserId: $staffId,
                idempotencyKey: 'idem-rf-refund-touch-1'
            );

            $afterVersion = (int) $this->table('reservations')->where('reservation_id', $reservationId)->value('row_version');
            self::assertSame($beforeVersion + 1, $afterVersion);

            $persistedUpdatedAt = (string) $this->table('reservations')->where('reservation_id', $reservationId)->value('updated_at');
            self::assertTrue(Carbon::parse($persistedUpdatedAt, 'UTC')->equalTo($frozenNow));
        } finally {
            Carbon::setTestNow();
        }
    }
}
