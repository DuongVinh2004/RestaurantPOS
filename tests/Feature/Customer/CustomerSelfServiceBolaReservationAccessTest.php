<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class CustomerSelfServiceBolaReservationAccessTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('booking.customer_session_exact_link_access_hours', 24);
        config()->set('booking.customer_session_legacy_access_hours', 0);
        config()->set('customer_auth.enabled', true);
        config()->set('customer_auth.allow_legacy_user_auth_tokens', true);
        config()->set('customer_auth.legacy_user_auth_tokens_allowed_environments', ['testing']);
        config()->set('customer_auth.allowed_role_ids', [$this->ensureRole('Customer')]);
    }

    public function test_customer_reservation_access_denies_bola_idor_and_expired_token_paths(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $otherId = $this->createUser(['role_name' => 'Customer']);
        $owner = User::query()->findOrFail($ownerId);
        $other = User::query()->findOrFail($otherId);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'status' => 'Confirmed',
            'start_time' => $this->nowUtc()->copy()->addHours(4),
            'end_time' => $this->nowUtc()->copy()->addHours(6),
        ]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4));
        $expiredToken = 'expired-customer-bola-token';

        DB::table('user_auth_tokens')->insert([
            'user_id' => $ownerId,
            'channel' => 'Email',
            'recipient' => (string) $owner->email,
            'token_hash' => hash('sha256', $expiredToken),
            'purpose' => 'VerifyEmail',
            'attempt_count' => 0,
            'max_attempts' => 5,
            'expires_at' => $this->nowUtc()->copy()->subMinute(),
            'used_at' => null,
            'created_at' => $this->nowUtc()->copy()->subHour(),
        ]);

        $this->actingAs($owner)
            ->getJson('/api/v1/reservations/'.$reservationId)
            ->assertOk()
            ->assertJsonPath('data.access_scope', 'owner')
            ->assertJsonPath('data.reservation_id', $reservationId);

        $this->actingAs($other)
            ->getJson('/api/v1/reservations/'.$reservationId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        $this->getJson('/api/v1/reservations/'.$reservationId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        $this->withHeaders(['X-Customer-Token' => $expiredToken])
            ->getJson('/api/v1/reservations/'.$reservationId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');
    }

    public function test_session_scoped_reservation_access_requires_exact_linked_session(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $start = $this->nowUtc()->copy()->addHours(5);
        $end = $start->copy()->addHours(2);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'status' => 'Confirmed',
            'start_time' => $start,
            'end_time' => $end,
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $sessionId = 'sess-bola-linked-reservation';
        $this->createTableHold([
            'session_id' => $sessionId,
            'user_id' => $ownerId,
            'confirmed_reservation_id' => $reservationId,
            'hold_status' => 'Confirmed',
            'start_time' => $start,
            'end_time' => $end,
            'expire_at' => $this->nowUtc()->copy()->addMinute(),
        ], [$tableId]);

        $this->withHeaders(['X-Session-Id' => $sessionId])
            ->getJson('/api/v1/reservations/'.$reservationId)
            ->assertOk()
            ->assertJsonPath('data.access_scope', 'session')
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.user.email', null);

        $this->withHeaders(['X-Session-Id' => 'sess-bola-wrong-reservation'])
            ->getJson('/api/v1/reservations/'.$reservationId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');
    }
}
