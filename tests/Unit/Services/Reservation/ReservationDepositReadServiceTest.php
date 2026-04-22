<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Reservation;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\ReservationDepositPaymentSession;
use App\Modules\Reservations\Application\Services\ReservationDepositReadService;
use App\Modules\Reservations\Domain\Models\Reservation;
use Tests\TestCase;

class ReservationDepositReadServiceTest extends TestCase
{
    public function test_it_builds_consistent_deposit_snapshot_with_remaining_amount_and_latest_session_summary(): void
    {
        $reservation = new Reservation([
            'reservation_id' => 10,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
            'deposit_intent_status' => 'Submitted',
            'deposit_intent_submitted_at' => now('UTC'),
            'deposit_requirement_acknowledged_at' => now('UTC')->subMinute(),
        ]);

        $depositPayment = new Payment([
            'payment_id' => 1,
            'amount' => '40000.00',
            'currency' => 'VND',
            'payment_type' => 'Deposit',
            'status' => 'Success',
        ]);

        $session = new ReservationDepositPaymentSession([
            'deposit_payment_session_id' => 55,
            'reservation_id' => 10,
            'customer_user_id' => 9,
            'provider_code' => 'simulated',
            'payment_method' => 'Online',
            'amount' => '60000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'provider_expires_at' => now('UTC')->addMinutes(30),
            'row_version' => 2,
        ]);

        $snapshot = app(ReservationDepositReadService::class)->buildSnapshot(
            $reservation,
            [$depositPayment],
            [$session],
            null,
            'VND',
            true,
        );

        self::assertSame('Pending', $snapshot['status']);
        self::assertSame('100000.00', $snapshot['required_amount']);
        self::assertSame('40000.00', $snapshot['paid_amount']);
        self::assertSame('60000.00', $snapshot['remaining_amount']);
        self::assertSame('60000.00', $snapshot['outstanding_amount']);
        self::assertTrue($snapshot['status_flags']['deposit_required']);
        self::assertTrue($snapshot['status_flags']['requires_collection']);
        self::assertTrue($snapshot['status_flags']['has_open_payment_session']);
        self::assertSame('40000.00', $snapshot['payment_summary']['deposit_net']);
        self::assertSame('Submitted', data_get($snapshot, 'self_service.intent_status'));
        self::assertSame(1, data_get($snapshot, 'payment_session_summary.total_sessions'));
        self::assertSame('Pending', data_get($snapshot, 'payment_session_summary.latest_session.session_status'));
        self::assertSame('60000.00', data_get($snapshot, 'payment_session_summary.latest_session.amount'));
    }
}
