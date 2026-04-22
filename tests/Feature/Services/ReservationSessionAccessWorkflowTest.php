<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ReservationSessionAccessWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('booking.customer_session_exact_link_access_hours', 24);
        config()->set('booking.customer_session_legacy_access_hours', 24);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropIfExists('table_hold_details');
        Schema::dropIfExists('table_holds');
        Schema::dropIfExists('reservation_tables');
        Schema::dropIfExists('reservations');

        Schema::create('reservations', function (Blueprint $table): void {
            $table->increments('reservation_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('reservation_code')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('status')->default('Confirmed');
            $table->dateTime('checked_in_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reservation_tables', function (Blueprint $table): void {
            $table->increments('reservation_table_id');
            $table->unsignedInteger('reservation_id');
            $table->unsignedInteger('table_id');
        });

        Schema::create('table_holds', function (Blueprint $table): void {
            $table->string('hold_id')->primary();
            $table->string('session_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('confirmed_reservation_id')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('hold_status');
            $table->dateTime('expire_at');
            $table->timestamps();
        });

        Schema::create('table_hold_details', function (Blueprint $table): void {
            $table->increments('hold_detail_id');
            $table->string('hold_id');
            $table->unsignedInteger('table_id');
        });
    }

    public function test_exact_linkage_grants_access_within_time_window(): void
    {
        $now = Carbon::parse('2026-03-15 12:00:00', 'UTC');
        Carbon::setTestNow($now);

        $reservation = $this->createReservation(
            reservationId: 10,
            userId: 77,
            start: $now->copy()->addHours(2),
            end: $now->copy()->addHours(4),
        );
        DB::table('reservation_tables')->insert([
            'reservation_id' => 10,
            'table_id' => 5,
        ]);

        DB::table('table_holds')->insert([
            'hold_id' => 'hold-exact-windowed',
            'session_id' => 'session-abc',
            'user_id' => 77,
            'confirmed_reservation_id' => 10,
            'start_time' => $reservation->start_time,
            'end_time' => $reservation->end_time,
            'hold_status' => 'Confirmed',
            'expire_at' => $now->copy()->subMinute(),
            'created_at' => $now->copy()->subDays(10),
            'updated_at' => $now->copy()->subDays(10),
        ]);

        $service = new ReservationSessionAccessWorkflow;

        self::assertTrue($service->canAccessReservationBySession($reservation, 'session-abc'));

        Carbon::setTestNow();
    }

    public function test_exact_linkage_rejects_access_outside_time_window(): void
    {
        config()->set('booking.customer_session_exact_link_access_hours', 6);
        config()->set('booking.customer_session_legacy_access_hours', 6);

        $now = Carbon::parse('2026-03-15 12:00:00', 'UTC');
        Carbon::setTestNow($now);

        $reservation = $this->createReservation(
            reservationId: 14,
            userId: 77,
            start: $now->copy()->subDay(),
            end: $now->copy()->subDay()->addHours(2),
        );
        DB::table('reservation_tables')->insert([
            'reservation_id' => 14,
            'table_id' => 5,
        ]);

        DB::table('table_holds')->insert([
            'hold_id' => 'hold-exact-expired-window',
            'session_id' => 'session-expired',
            'user_id' => 77,
            'confirmed_reservation_id' => 14,
            'start_time' => $reservation->start_time,
            'end_time' => $reservation->end_time,
            'hold_status' => 'Confirmed',
            'expire_at' => $now->copy()->subDay()->subMinute(),
            'created_at' => $now->copy()->subDays(10),
            'updated_at' => $now->copy()->subDays(10),
        ]);

        $service = new ReservationSessionAccessWorkflow;

        self::assertFalse($service->canAccessReservationBySession($reservation, 'session-expired'));

        Carbon::setTestNow();
    }

    public function test_exact_linkage_rejects_session_from_another_user_even_with_matching_reservation_link(): void
    {
        $now = Carbon::parse('2026-03-15 12:00:00', 'UTC');
        Carbon::setTestNow($now);

        $reservation = $this->createReservation(
            reservationId: 16,
            userId: 77,
            start: $now->copy()->addHours(2),
            end: $now->copy()->addHours(4),
        );
        DB::table('reservation_tables')->insert([
            'reservation_id' => 16,
            'table_id' => 5,
        ]);

        DB::table('table_holds')->insert([
            'hold_id' => 'hold-exact-wrong-user',
            'session_id' => 'session-wrong-user',
            'user_id' => 91,
            'confirmed_reservation_id' => 16,
            'start_time' => $reservation->start_time,
            'end_time' => $reservation->end_time,
            'hold_status' => 'Confirmed',
            'expire_at' => $now->copy()->subMinute(),
            'created_at' => $now->copy()->subHours(2),
            'updated_at' => $now->copy()->subHours(2),
        ]);

        $service = new ReservationSessionAccessWorkflow;

        self::assertFalse($service->canAccessReservationBySession($reservation, 'session-wrong-user'));

        Carbon::setTestNow();
    }

    public function test_extract_session_id_prioritizes_validated_payload_over_request_query_and_headers(): void
    {
        $request = request()->create('/api/v1/reservations/10', 'POST', [
            'session_id' => 'sess-from-body',
        ]);
        $request->headers->set('X-Session-Id', 'sess-from-header');
        $request->query->set('session_id', 'sess-from-query');

        $service = new ReservationSessionAccessWorkflow;

        self::assertSame(
            'sess-from-validated-payload',
            $service->extractSessionIdFromRequest($request, ['session_id' => 'sess-from-validated-payload'])
        );
        self::assertSame('sess-from-body', $service->extractSessionIdFromRequest($request));
    }

    public function test_legacy_fallback_allows_recent_matching_hold_with_null_user_id_when_table_set_matches(): void
    {
        $reservation = $this->createReservation(
            reservationId: 17,
            userId: 88,
            start: Carbon::parse('2026-03-16 18:00:00', 'UTC'),
            end: Carbon::parse('2026-03-16 20:00:00', 'UTC'),
        );
        DB::table('reservation_tables')->insert([
            ['reservation_id' => 17, 'table_id' => 3],
            ['reservation_id' => 17, 'table_id' => 4],
        ]);

        DB::table('table_holds')->insert([
            'hold_id' => 'hold-legacy-null-user',
            'session_id' => 'session-legacy-null-user',
            'user_id' => null,
            'confirmed_reservation_id' => null,
            'start_time' => $reservation->start_time,
            'end_time' => $reservation->end_time,
            'hold_status' => 'Holding',
            'expire_at' => Carbon::now('UTC')->addMinutes(15),
            'created_at' => Carbon::now('UTC')->subHours(1),
            'updated_at' => Carbon::now('UTC')->subHours(1),
        ]);
        DB::table('table_hold_details')->insert([
            ['hold_id' => 'hold-legacy-null-user', 'table_id' => 3],
            ['hold_id' => 'hold-legacy-null-user', 'table_id' => 4],
        ]);

        $service = new ReservationSessionAccessWorkflow;

        self::assertTrue($service->canAccessReservationBySession($reservation, 'session-legacy-null-user'));
    }

    public function test_legacy_fallback_allows_recent_matching_hold_and_table_set(): void
    {
        $reservation = $this->createReservation(
            reservationId: 11,
            userId: 88,
            start: Carbon::parse('2026-03-16 18:00:00', 'UTC'),
            end: Carbon::parse('2026-03-16 20:00:00', 'UTC'),
        );
        DB::table('reservation_tables')->insert([
            ['reservation_id' => 11, 'table_id' => 3],
            ['reservation_id' => 11, 'table_id' => 4],
        ]);

        DB::table('table_holds')->insert([
            'hold_id' => 'hold-legacy-recent',
            'session_id' => 'session-legacy',
            'user_id' => 88,
            'confirmed_reservation_id' => null,
            'start_time' => $reservation->start_time,
            'end_time' => $reservation->end_time,
            'hold_status' => 'Holding',
            'expire_at' => Carbon::now('UTC')->addMinutes(15),
            'created_at' => Carbon::now('UTC')->subHours(2),
            'updated_at' => Carbon::now('UTC')->subHours(2),
        ]);
        DB::table('table_hold_details')->insert([
            ['hold_id' => 'hold-legacy-recent', 'table_id' => 3],
            ['hold_id' => 'hold-legacy-recent', 'table_id' => 4],
        ]);

        $service = new ReservationSessionAccessWorkflow;

        self::assertTrue($service->canAccessReservationBySession($reservation, 'session-legacy'));
    }

    public function test_legacy_fallback_can_be_disabled_entirely(): void
    {
        config()->set('booking.customer_session_legacy_access_hours', 0);

        $reservation = $this->createReservation(
            reservationId: 15,
            userId: 88,
            start: Carbon::parse('2026-03-16 18:00:00', 'UTC'),
            end: Carbon::parse('2026-03-16 20:00:00', 'UTC'),
        );
        DB::table('reservation_tables')->insert([
            ['reservation_id' => 15, 'table_id' => 3],
            ['reservation_id' => 15, 'table_id' => 4],
        ]);

        DB::table('table_holds')->insert([
            'hold_id' => 'hold-legacy-disabled',
            'session_id' => 'session-legacy-disabled',
            'user_id' => 88,
            'confirmed_reservation_id' => null,
            'start_time' => $reservation->start_time,
            'end_time' => $reservation->end_time,
            'hold_status' => 'Holding',
            'expire_at' => Carbon::now('UTC')->addMinutes(15),
            'created_at' => Carbon::now('UTC')->subHours(2),
            'updated_at' => Carbon::now('UTC')->subHours(2),
        ]);
        DB::table('table_hold_details')->insert([
            ['hold_id' => 'hold-legacy-disabled', 'table_id' => 3],
            ['hold_id' => 'hold-legacy-disabled', 'table_id' => 4],
        ]);

        $service = new ReservationSessionAccessWorkflow;

        self::assertFalse($service->canAccessReservationBySession($reservation, 'session-legacy-disabled'));
    }

    public function test_legacy_fallback_rejects_old_unlinked_hold_rows(): void
    {
        config()->set('booking.customer_session_legacy_access_hours', 6);

        $reservation = $this->createReservation(
            reservationId: 12,
            userId: 99,
            start: Carbon::parse('2026-03-16 18:00:00', 'UTC'),
            end: Carbon::parse('2026-03-16 20:00:00', 'UTC'),
        );
        DB::table('reservation_tables')->insert([
            'reservation_id' => 12,
            'table_id' => 8,
        ]);

        DB::table('table_holds')->insert([
            'hold_id' => 'hold-legacy-old',
            'session_id' => 'session-old',
            'user_id' => 99,
            'confirmed_reservation_id' => null,
            'start_time' => $reservation->start_time,
            'end_time' => $reservation->end_time,
            'hold_status' => 'Confirmed',
            'expire_at' => Carbon::now('UTC')->addMinutes(15),
            'created_at' => Carbon::now('UTC')->subHours(7),
            'updated_at' => Carbon::now('UTC')->subHours(7),
        ]);
        DB::table('table_hold_details')->insert([
            'hold_id' => 'hold-legacy-old',
            'table_id' => 8,
        ]);

        $service = new ReservationSessionAccessWorkflow;

        self::assertFalse($service->canAccessReservationBySession($reservation, 'session-old'));
    }

    public function test_legacy_fallback_rejects_mismatched_table_sets(): void
    {
        $reservation = $this->createReservation(
            reservationId: 13,
            userId: 101,
            start: Carbon::parse('2026-03-16 18:00:00', 'UTC'),
            end: Carbon::parse('2026-03-16 20:00:00', 'UTC'),
        );
        DB::table('reservation_tables')->insert([
            ['reservation_id' => 13, 'table_id' => 6],
            ['reservation_id' => 13, 'table_id' => 7],
        ]);

        DB::table('table_holds')->insert([
            'hold_id' => 'hold-legacy-different-tables',
            'session_id' => 'session-different',
            'user_id' => 101,
            'confirmed_reservation_id' => null,
            'start_time' => $reservation->start_time,
            'end_time' => $reservation->end_time,
            'hold_status' => 'Pending',
            'expire_at' => Carbon::now('UTC')->addMinutes(15),
            'created_at' => Carbon::now('UTC')->subMinutes(30),
            'updated_at' => Carbon::now('UTC')->subMinutes(30),
        ]);
        DB::table('table_hold_details')->insert([
            ['hold_id' => 'hold-legacy-different-tables', 'table_id' => 6],
            ['hold_id' => 'hold-legacy-different-tables', 'table_id' => 9],
        ]);

        $service = new ReservationSessionAccessWorkflow;

        self::assertFalse($service->canAccessReservationBySession($reservation, 'session-different'));
    }

    private function createReservation(int $reservationId, int $userId, Carbon $start, Carbon $end): Reservation
    {
        DB::table('reservations')->insert([
            'reservation_id' => $reservationId,
            'user_id' => $userId,
            'reservation_code' => 'RSV-'.$reservationId,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'Confirmed',
            'checked_in_at' => null,
            'created_at' => Carbon::now('UTC'),
            'updated_at' => Carbon::now('UTC'),
        ]);

        /** @var Reservation $reservation */
        $reservation = Reservation::query()->findOrFail($reservationId);

        return $reservation;
    }
}
