<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Modules\Reservations\Http\Resources\ReservationResource;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Payments\Domain\Models\ReservationDepositPaymentSession;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReservationResourceDepositSummaryScopeTest extends TestCase
{
    public function test_owner_scope_exposes_enriched_deposit_summary_with_session_context(): void
    {
        $reservation = new Reservation([
            'reservation_id' => 1,
            'user_id' => 5,
            'guest_count' => 4,
            'status' => 'Confirmed',
            'start_time' => now('UTC')->addHour(),
            'end_time' => now('UTC')->addHours(2),
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
            'row_version' => 2,
        ]);
        $reservation->setRelation('payments', collect([
            new Payment([
                'payment_id' => 1,
                'amount' => '50000.00',
                'currency' => 'VND',
                'payment_type' => 'Deposit',
                'status' => 'Success',
            ]),
        ]));
        $reservation->setRelation('depositPaymentSessions', collect([
            new ReservationDepositPaymentSession([
                'deposit_payment_session_id' => 9,
                'reservation_id' => 1,
                'customer_user_id' => 5,
                'provider_code' => 'simulated',
                'amount' => '50000.00',
                'currency' => 'VND',
                'session_status' => 'Pending',
                'settlement_status' => 'NotApplied',
            ]),
        ]));
        $reservation->setRelation('tables', collect());
        $reservation->setRelation('orders', collect());

        $request = Request::create('/api/v1/reservations/1', 'GET');
        $request->attributes->set('reservation_access_scope', 'owner');

        $payload = (new ReservationResource($reservation))->toArray($request);

        self::assertSame('Required', data_get($payload, 'deposit_summary.status'));
        self::assertSame('50000.00', data_get($payload, 'deposit_summary.remaining_amount'));
        self::assertTrue((bool) data_get($payload, 'deposit_summary.status_flags.has_open_payment_session'));
        self::assertSame('Pending', data_get($payload, 'deposit_summary.payment_session_summary.latest_session.session_status'));
    }

    public function test_staff_scope_keeps_canonical_pending_deposit_status_in_summary(): void
    {
        $reservation = new Reservation([
            'reservation_id' => 3,
            'user_id' => 8,
            'guest_count' => 4,
            'status' => 'Confirmed',
            'start_time' => now('UTC')->addHour(),
            'end_time' => now('UTC')->addHours(2),
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
            'row_version' => 2,
        ]);
        $reservation->setRelation('payments', collect([
            new Payment([
                'payment_id' => 2,
                'amount' => '50000.00',
                'currency' => 'VND',
                'payment_type' => 'Deposit',
                'status' => 'Success',
            ]),
        ]));
        $reservation->setRelation('depositPaymentSessions', collect());
        $reservation->setRelation('tables', collect());
        $reservation->setRelation('orders', collect());

        $request = Request::create('/api/v1/staff/reservations/3', 'GET');
        $request->attributes->set('reservation_access_scope', 'staff');

        $payload = (new ReservationResource($reservation))->toArray($request);

        self::assertSame('Pending', data_get($payload, 'deposit_summary.status'));
    }

    public function test_session_scope_keeps_deposit_summary_redacted(): void
    {
        $reservation = new Reservation([
            'reservation_id' => 2,
            'guest_count' => 2,
            'status' => 'Confirmed',
            'start_time' => now('UTC')->addHour(),
            'end_time' => now('UTC')->addHours(2),
            'deposit_required_amount' => '80000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'row_version' => 1,
        ]);
        $reservation->setRelation('tables', collect());
        $reservation->setRelation('orders', collect());

        $request = Request::create('/api/v1/reservations/2', 'GET');
        $request->attributes->set('reservation_access_scope', 'session');

        $payload = (new ReservationResource($reservation))->toArray($request);

        self::assertNull($payload['deposit_summary']);
    }
}
