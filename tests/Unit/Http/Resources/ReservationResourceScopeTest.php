<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Loyalty\Domain\Models\UserPoint;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Http\Resources\ReservationResource;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReservationResourceScopeTest extends TestCase
{
    #[Test]
    public function it_redacts_sensitive_fields_for_session_scope(): void
    {
        $reservation = $this->makeReservationGraph();
        $request = Request::create('/api/v1/reservations/1', 'GET');
        $request->attributes->set('reservation_access_scope', 'session');
        $request->attributes->set('is_staff', false);

        $payload = (new ReservationResource($reservation))->toArray($request);

        $this->assertSame('session', $payload['access_scope']);
        $this->assertNull($payload['user_id']);
        $this->assertNull($payload['deposit_required_amount']);
        $this->assertNull($payload['payment_summary']);
        $this->assertIsArray($payload['user']);
        $this->assertSame('Session Customer', $payload['user']['full_name']);
        $this->assertNull($payload['user']['email']);
        $this->assertNull($payload['user']['phone']);
        $this->assertNull($payload['applied_voucher']);
    }

    #[Test]
    public function it_keeps_owner_fields_visible(): void
    {
        $reservation = $this->makeReservationGraph();
        $request = Request::create('/api/v1/reservations/1', 'GET');
        $request->attributes->set('reservation_access_scope', 'owner');
        $request->attributes->set('is_staff', false);

        $payload = (new ReservationResource($reservation))->toArray($request);

        $this->assertSame('owner', $payload['access_scope']);
        $this->assertSame(55, $payload['user_id']);
        $this->assertSame('100.00', $payload['deposit_required_amount']);
        $this->assertIsArray($payload['user']);
        $this->assertSame(120, $payload['user']['current_points']);
        $this->assertIsIterable($payload['payments']);
    }

    private function makeReservationGraph(): Reservation
    {
        $reservation = new Reservation([
            'reservation_id' => 1,
            'user_id' => 55,
            'reservation_code' => 'R-0001',
            'guest_count' => 4,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100.00',
            'deposit_paid_amount' => '50.00',
            'discount_amount' => '0.00',
            'final_bill_amount' => '500.00',
            'bill_currency' => 'VND',
            'row_version' => 3,
        ]);

        $user = new User([
            'user_id' => 55,
            'full_name' => 'Session Customer',
            'email' => 'customer@example.com',
            'phone' => '0900000000',
        ]);
        $user->setRelation('points', new UserPoint([
            'user_id' => 55,
            'total_points' => 120,
        ]));
        $reservation->setRelation('user', $user);

        $payment = new Payment([
            'payment_id' => 10,
            'amount' => '50.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'POS',
            'payment_type' => 'Deposit',
            'status' => 'Success',
        ]);
        $reservation->setRelation('payments', collect([$payment]));
        $reservation->setRelation('tables', collect());
        $reservation->setRelation('orders', collect());

        return $reservation;
    }
}
