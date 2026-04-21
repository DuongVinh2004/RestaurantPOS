<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerReservationSelfServiceHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        $this->ensureSessionAccessTables();

        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');

        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_authenticated_owner_can_list_and_show_only_owned_reservations(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $otherId = $this->createUser(['role_name' => 'Customer']);
        $ownedReservationId = $this->createReservation([
            'user_id' => $ownerId,
            'status' => 'Confirmed',
        ]);
        $foreignReservationId = $this->createReservation([
            'user_id' => $otherId,
            'status' => 'Confirmed',
        ]);

        $owner = User::query()->findOrFail($ownerId);

        $this->actingAs($owner)
            ->getJson('/api/v1/reservations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reservation_id', $ownedReservationId)
            ->assertJsonMissing(['reservation_id' => $foreignReservationId]);

        $this->actingAs($owner)
            ->getJson('/api/v1/reservations/'.$ownedReservationId)
            ->assertOk()
            ->assertJsonPath('data.reservation_id', $ownedReservationId)
            ->assertJsonPath('data.access_scope', 'owner')
            ->assertJsonPath('data.user_id', $ownerId);
    }

    public function test_session_linked_guest_can_list_and_show_exact_linked_reservations_without_owner_identity(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'start_time' => Carbon::now('UTC')->addHour(),
            'end_time' => Carbon::now('UTC')->addHours(3),
        ]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTable());
        $this->linkReservationToSession($reservationId, 'sess-self-service-1', $customerId);

        $this->getJson('/api/v1/reservations?session_id=sess-self-service-1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reservation_id', $reservationId)
            ->assertJsonPath('data.0.access_scope', 'session')
            ->assertJsonPath('data.0.user_id', null);

        $this->getJson('/api/v1/reservations/'.$reservationId.'?session_id=sess-self-service-1')
            ->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.access_scope', 'session')
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.user.email', null)
            ->assertJsonPath('data.user.phone', null);
    }

    public function test_unrelated_customer_gets_not_found_while_staff_override_can_read_shared_show_surface(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $otherId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'status' => 'Confirmed',
        ]);

        $other = User::query()->findOrFail($otherId);

        $this->actingAs($other)
            ->getJson('/api/v1/reservations/'.$reservationId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
        auth()->logout();
        app('auth')->forgetGuards();

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $staffRoleId = (int) DB::table('users')->where('user_id', $staffId)->value('role_id');
        config()->set('staff_auth.allowed_role_ids', [$staffRoleId]);
        config()->set('staff_auth.api_keys', ['staff-self-service-key' => $staffId]);

        $this->withHeaders(['X-Staff-Key' => 'staff-self-service-key'])
            ->getJson('/api/v1/reservations/'.$reservationId)
            ->assertOk()
            ->assertJsonPath('data.access_scope', 'staff')
            ->assertJsonPath('data.reservation_id', $reservationId);
    }

    public function test_owner_cancel_is_idempotent_and_updates_reservation_through_existing_service(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'status' => 'Confirmed',
            'row_version' => 1,
        ]);
        $owner = User::query()->findOrFail($ownerId);

        $headers = ['Idempotency-Key' => 'customer-reservation-cancel-owner-1'];
        $payload = [
            'row_version' => 1,
            'cancel_reason' => 'Customer changed plans',
        ];

        $first = $this->actingAs($owner)
            ->withHeaders($headers)
            ->postJson('/api/v1/reservations/'.$reservationId.'/cancel', $payload);

        $second = $this->actingAs($owner)
            ->withHeaders($headers)
            ->postJson('/api/v1/reservations/'.$reservationId.'/cancel', $payload);

        $first->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.status', 'Cancelled')
            ->assertJsonPath('meta.action', 'reservation.cancelled')
            ->assertJsonPath('data.cancel_reason', 'Customer changed plans');

        $second->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.status', 'Cancelled');

        $this->assertSame('Cancelled', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        $this->assertSame($ownerId, (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('cancelled_by'));
    }

    public function test_session_guest_can_cancel_exact_linked_reservation_without_impersonating_owner(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTable());
        $this->linkReservationToSession($reservationId, 'sess-self-service-cancel', $customerId);

        $this->withHeaders([
            'Idempotency-Key' => 'customer-reservation-cancel-session-1',
            'X-Session-Id' => 'sess-self-service-cancel',
        ])
            ->postJson('/api/v1/reservations/'.$reservationId.'/cancel', [
                'row_version' => 1,
                'cancel_reason' => 'Guest requested cancellation',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Cancelled')
            ->assertJsonPath('data.access_scope', 'session');

        $this->assertNull(DB::table('reservations')->where('reservation_id', $reservationId)->value('cancelled_by'));
    }

    public function test_invalid_staff_key_does_not_fallback_to_session_customer_cancel_flow(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTable());
        $this->linkReservationToSession($reservationId, 'sess-invalid-key', $customerId);

        $this->withHeaders([
            'Idempotency-Key' => 'customer-reservation-cancel-invalid-staff-key',
            'X-Staff-Key' => 'invalid-key',
            'X-Session-Id' => 'sess-invalid-key',
        ])
            ->postJson('/api/v1/reservations/'.$reservationId.'/cancel', [
                'row_version' => 1,
            ])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    private function ensureSessionAccessTables(): void
    {
        if (! Schema::hasTable('table_holds')) {
            Schema::create('table_holds', function (Blueprint $table): void {
                $table->string('hold_id')->primary();
                $table->unsignedInteger('user_id')->nullable();
                $table->string('session_id')->nullable();
                $table->unsignedInteger('confirmed_reservation_id')->nullable();
                $table->string('hold_status')->default('Confirmed');
                $table->dateTime('start_time')->nullable();
                $table->dateTime('end_time')->nullable();
                $table->dateTime('expire_at')->nullable();
                $table->dateTime('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('table_hold_details')) {
            Schema::create('table_hold_details', function (Blueprint $table): void {
                $table->increments('table_hold_detail_id');
                $table->string('hold_id');
                $table->unsignedInteger('table_id');
            });
        }
    }

    private function linkReservationToSession(int $reservationId, string $sessionId, ?int $userId): void
    {
        /** @var Reservation $reservation */
        $reservation = Reservation::query()->findOrFail($reservationId);
        $holdId = 'hold-'.$reservationId.'-'.substr(md5($sessionId), 0, 8);
        $tableIds = DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        DB::table('table_holds')->insert([
            'hold_id' => $holdId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'confirmed_reservation_id' => $reservationId,
            'hold_status' => 'Confirmed',
            'start_time' => $reservation->start_time,
            'end_time' => $reservation->end_time,
            'expire_at' => Carbon::now('UTC')->addHour(),
            'created_at' => Carbon::now('UTC'),
        ]);

        foreach ($tableIds as $tableId) {
            DB::table('table_hold_details')->insert([
                'hold_id' => $holdId,
                'table_id' => $tableId,
            ]);
        }
    }
}
