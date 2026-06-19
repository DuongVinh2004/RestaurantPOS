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

    public function test_other_customer_cannot_show_cancel_reschedule_pay_deposit_or_preorder_reservation(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $otherId = $this->createUser(['role_name' => 'Customer']);
        $other = User::query()->findOrFail($otherId);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'status' => 'Confirmed',
            'start_time' => $this->nowUtc()->copy()->addHours(4),
            'end_time' => $this->nowUtc()->copy()->addHours(6),
            'guest_count' => 2,
            'deposit_required_amount' => '100000',
            'deposit_paid_amount' => '0',
            'deposit_status' => 'Pending',
            'final_bill_amount' => '150000',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $preorderItemId = $this->createMenuItem([
            'is_preorder_enabled' => 1,
            'preorder_cutoff_minutes' => 0,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $preorderItemId,
            'price' => '50000',
            'currency' => 'VND',
            'effective_from' => $this->nowUtc()->copy()->subHour(),
            'effective_to' => null,
        ]);

        $this->actingAs($other)
            ->getJson('/api/v1/reservations/'.$reservationId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        $this->actingAs($other)
            ->withHeaders(['Idempotency-Key' => 'bola-other-cancel-reservation'])
            ->postJson('/api/v1/reservations/'.$reservationId.'/cancel', [
                'row_version' => 1,
            ])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        $this->actingAs($other)
            ->withHeaders(['Idempotency-Key' => 'bola-other-reschedule-reservation'])
            ->postJson('/api/v1/reservations/'.$reservationId.'/reschedule', [
                'row_version' => 1,
                'guest_count' => 3,
            ])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        $this->actingAs($other)
            ->getJson('/api/v1/reservations/'.$reservationId.'/deposit-preview')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        $this->actingAs($other)
            ->withHeaders(['Idempotency-Key' => 'bola-other-deposit-ack'])
            ->postJson('/api/v1/reservations/'.$reservationId.'/deposit/acknowledge', [
                'row_version' => 1,
            ])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        $this->actingAs($other)
            ->withHeaders(['Idempotency-Key' => 'bola-other-deposit-payment-session'])
            ->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions', [
                'row_version' => 1,
                'provider_code' => 'simulated',
                'currency' => 'VND',
            ])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        $this->actingAs($other)
            ->withHeaders(['Idempotency-Key' => 'bola-other-bill-payment-session'])
            ->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions', [
                'row_version' => 1,
                'provider_code' => 'simulated',
                'currency' => 'VND',
            ])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        $this->actingAs($other)
            ->getJson('/api/v1/reservations/'.$reservationId.'/preorder')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        $this->actingAs($other)
            ->withHeaders(['Idempotency-Key' => 'bola-other-preorder-replace'])
            ->putJson('/api/v1/reservations/'.$reservationId.'/preorder', [
                'row_version' => 1,
                'pre_order_items' => [
                    ['item_id' => $preorderItemId, 'quantity' => 1],
                ],
            ])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        $reservation = DB::table('reservations')->where('reservation_id', $reservationId)->first();
        self::assertSame('Confirmed', (string) $reservation->status);
        self::assertSame(2, (int) $reservation->guest_count);
        self::assertNull($reservation->cancelled_at);
        self::assertSame(0, (int) DB::table('reservation_deposit_payment_sessions')->where('reservation_id', $reservationId)->count());
        self::assertSame(0, (int) DB::table('reservation_bill_payment_sessions')->where('reservation_id', $reservationId)->count());
        self::assertSame(0, (int) DB::table('reservation_orders')->where('reservation_id', $reservationId)->count());
    }
}
