<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use App\Modules\Reservations\Http\Resources\ReservationResource;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReservationResourceVisibilityTest extends TestCase
{
    public function test_session_scope_redacts_sensitive_user_fields(): void
    {
        $reservation = new Reservation([
            'reservation_id' => 10,
            'user_id' => 5,
            'guest_count' => 2,
            'status' => 'Confirmed',
            'row_version' => 1,
        ]);

        $user = new User([
            'user_id' => 5,
            'full_name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '0123',
        ]);

        $reservation->setRelation('user', $user);

        $request = Request::create('/api/v1/reservations/10', 'GET');
        $request->attributes->set('reservation_access_scope', 'session');

        $payload = (new ReservationResource($reservation))->toArray($request);

        self::assertSame('session', $payload['api_contract']['access_scope']);
        self::assertSame('Test User', $payload['user']['full_name']);
        self::assertNull($payload['user']['email']);
        self::assertNull($payload['user']['phone']);
        self::assertNull($payload['user']['current_points']);
        self::assertNull($payload['user']['current_tier']);
    }
}
