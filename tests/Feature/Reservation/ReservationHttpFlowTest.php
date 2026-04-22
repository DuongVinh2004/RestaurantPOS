<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class ReservationHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();
        config()->set('booking.require_redis_for_booking_api', false);
    }

    public function test_authenticated_customer_can_create_and_view_reservation_with_owner_scope(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4);

        $user = User::query()->findOrFail($userId);
        $start = $this->nowUtc()->copy()->addHours(2);
        $end = $start->copy()->addHours(2);

        $create = $this->actingAs($user)->postJson('/api/v1/reservations', [
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
            'table_ids' => [$tableId],
            'notes' => 'Owner flow test',
        ], $this->withIdempotencyKey('reservation-owner-create'));

        $create->assertCreated()
            ->assertJsonPath('data.access_scope', 'owner')
            ->assertJsonPath('data.user_id', $userId)
            ->assertJsonPath('data.user.user_id', $userId)
            ->assertJsonPath('data.user.email', (string) $user->email)
            ->assertJsonPath('data.table_ids.0', $tableId)
            ->assertJsonPath('data.notes', 'Owner flow test');

        $reservationId = (int) $create->json('data.reservation_id');

        $show = $this->actingAs($user)->getJson('/api/v1/reservations/'.$reservationId);

        $show->assertOk()
            ->assertJsonPath('data.access_scope', 'owner')
            ->assertJsonPath('data.user_id', $userId)
            ->assertJsonPath('data.user.user_id', $userId)
            ->assertJsonPath('data.user.full_name', (string) $user->full_name)
            ->assertJsonPath('data.user.email', (string) $user->email)
            ->assertJsonPath('data.table_ids.0', $tableId);
    }

    public function test_authenticated_customer_can_claim_guest_hold_and_backfill_hold_owner_on_reservation_create(): void
    {
        $customerUserId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $customer = User::query()->findOrFail($customerUserId);
        $start = $this->nowUtc()->copy()->addHours(3);
        $end = $start->copy()->addHours(2);
        $sessionId = 'sess-guest-claim-001';

        $holdId = $this->createTableHold([
            'session_id' => $sessionId,
            'user_id' => null,
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => (int) $start->diffInMinutes($end),
            'expire_at' => $this->nowUtc()->copy()->addMinutes(45),
        ], [$tableId]);

        $create = $this->actingAs($customer)->postJson('/api/v1/reservations', [
            'hold_id' => $holdId,
            'session_id' => $sessionId,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
            'notes' => 'Authenticated claim from guest hold',
        ], $this->withIdempotencyKey('reservation-authenticated-guest-hold-claim'));

        $create->assertCreated()
            ->assertJsonPath('data.access_scope', 'owner')
            ->assertJsonPath('data.user_id', $customerUserId)
            ->assertJsonPath('data.user.user_id', $customerUserId)
            ->assertJsonPath('data.user.email', (string) $customer->email)
            ->assertJsonPath('data.table_ids.0', $tableId);

        $reservationId = (int) $create->json('data.reservation_id');

        $this->assertSame('Confirmed', DB::table('table_holds')->where('hold_id', $holdId)->value('hold_status'));
        $this->assertSame($reservationId, (int) DB::table('table_holds')->where('hold_id', $holdId)->value('confirmed_reservation_id'));
        $this->assertSame($customerUserId, (int) DB::table('table_holds')->where('hold_id', $holdId)->value('user_id'));
        $this->assertSame(2, (int) DB::table('table_holds')->where('hold_id', $holdId)->value('row_version'));
    }

    public function test_authenticated_customer_cannot_create_reservation_from_hold_owned_by_another_customer(): void
    {
        $ownerUserId = $this->createUser(['role_name' => 'Customer']);
        $viewerUserId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $start = $this->nowUtc()->copy()->addHours(3);
        $end = $start->copy()->addHours(2);
        $sessionId = 'sess-owned-by-other';

        $holdId = $this->createTableHold([
            'session_id' => $sessionId,
            'user_id' => $ownerUserId,
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => (int) $start->diffInMinutes($end),
            'expire_at' => $this->nowUtc()->copy()->addMinutes(45),
        ], [$tableId]);

        $viewer = User::query()->findOrFail($viewerUserId);

        $response = $this->actingAs($viewer)->postJson('/api/v1/reservations', [
            'hold_id' => $holdId,
            'session_id' => $sessionId,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
        ], $this->withIdempotencyKey('reservation-authenticated-foreign-hold'));

        $response->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized');

        $this->assertNull(DB::table('table_holds')->where('hold_id', $holdId)->value('confirmed_reservation_id'));
        $this->assertSame('Holding', DB::table('table_holds')->where('hold_id', $holdId)->value('hold_status'));
        $this->assertSame(0, DB::table('reservations')->where('user_id', $viewerUserId)->count());
    }

    public function test_authenticated_customer_create_from_owned_expired_hold_returns_domain_validation_error(): void
    {
        $customerUserId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $customer = User::query()->findOrFail($customerUserId);
        $start = $this->nowUtc()->copy()->addHours(3);
        $end = $start->copy()->addHours(2);
        $sessionId = 'sess-expired-owned-hold';

        $holdId = $this->createTableHold([
            'session_id' => $sessionId,
            'user_id' => $customerUserId,
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => (int) $start->diffInMinutes($end),
            'expire_at' => $this->nowUtc()->copy()->subMinutes(5),
            'hold_status' => 'Holding',
        ], [$tableId]);

        $response = $this->actingAs($customer)->postJson('/api/v1/reservations', [
            'hold_id' => $holdId,
            'session_id' => $sessionId,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
        ], $this->withIdempotencyKey('reservation-expired-owned-hold'));

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['hold_id']);

        $this->assertSame(0, DB::table('reservations')->where('user_id', $customerUserId)->count());
        $this->assertNull(DB::table('table_holds')->where('hold_id', $holdId)->value('confirmed_reservation_id'));
    }

    public function test_session_only_guest_hold_without_user_binding_still_cannot_create_reservation(): void
    {
        $tableId = $this->createRestaurantTableWithSeats(4);
        $start = $this->nowUtc()->copy()->addHours(3);
        $end = $start->copy()->addHours(2);
        $sessionId = 'sess-guest-unbound';

        $holdId = $this->createTableHold([
            'session_id' => $sessionId,
            'user_id' => null,
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => (int) $start->diffInMinutes($end),
            'expire_at' => $this->nowUtc()->copy()->addMinutes(45),
        ], [$tableId]);

        $response = $this->postJson('/api/v1/reservations', [
            'hold_id' => $holdId,
            'session_id' => $sessionId,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
        ], $this->withIdempotencyKey('reservation-session-guest-hold-unbound'));

        $response->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized');

        $this->assertNull(DB::table('table_holds')->where('hold_id', $holdId)->value('confirmed_reservation_id'));
        $this->assertSame('Holding', DB::table('table_holds')->where('hold_id', $holdId)->value('hold_status'));
    }

    public function test_session_owned_hold_can_create_and_view_reservation_with_session_scope_and_redacted_identity(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $start = $this->nowUtc()->copy()->addHours(3);
        $end = $start->copy()->addHours(2);
        $sessionId = 'sess-linked-001';

        $holdId = $this->createTableHold([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => (int) $start->diffInMinutes($end),
            'expire_at' => $this->nowUtc()->copy()->addMinutes(45),
        ], [$tableId]);

        $create = $this->postJson('/api/v1/reservations', [
            'hold_id' => $holdId,
            'session_id' => $sessionId,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
            'notes' => 'Session flow test',
        ], $this->withIdempotencyKey('reservation-session-create'));

        $sessionUser = User::query()->findOrFail($userId);

        $create->assertCreated()
            ->assertJsonPath('data.access_scope', 'session')
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.user.user_id', null)
            ->assertJsonPath('data.user.full_name', (string) $sessionUser->full_name)
            ->assertJsonPath('data.user.email', null)
            ->assertJsonPath('data.table_ids.0', $tableId)
            ->assertJsonPath('data.notes', 'Session flow test');

        $reservationId = (int) $create->json('data.reservation_id');

        $this->assertSame('Confirmed', DB::table('table_holds')->where('hold_id', $holdId)->value('hold_status'));
        $this->assertSame($reservationId, (int) DB::table('table_holds')->where('hold_id', $holdId)->value('confirmed_reservation_id'));
        $this->assertSame(2, (int) DB::table('table_holds')->where('hold_id', $holdId)->value('row_version'));

        $show = $this->getJson('/api/v1/reservations/'.$reservationId.'?session_id='.$sessionId);

        $show->assertOk()
            ->assertJsonPath('data.access_scope', 'session')
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.user.user_id', null)
            ->assertJsonPath('data.user.full_name', (string) $sessionUser->full_name)
            ->assertJsonPath('data.user.email', null)
            ->assertJsonPath('data.table_ids.0', $tableId);
    }

    public function test_staff_can_create_reservation_for_customer_and_view_with_staff_scope(): void
    {
        $customerUserId = $this->createUser(['role_name' => 'Customer']);
        $staffUserId = $this->createUser(['role_name' => 'Admin']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $start = $this->nowUtc()->copy()->addHours(4);
        $end = $start->copy()->addHours(2);

        $headers = $this->withIdempotencyKey('reservation-staff-create', $this->staffAuthHeaders($staffUserId));
        $create = $this->withHeaders($headers)->postJson('/api/v1/reservations', [
            'user_id' => $customerUserId,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 3,
            'table_ids' => [$tableId],
            'notes' => 'Staff booked reservation',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.access_scope', 'staff')
            ->assertJsonPath('data.user_id', $customerUserId)
            ->assertJsonPath('data.table_ids.0', $tableId)
            ->assertJsonPath('data.notes', 'Staff booked reservation');

        $reservationId = (int) $create->json('data.reservation_id');

        $show = $this->getJson('/api/v1/reservations/'.$reservationId, $this->staffAuthHeaders($staffUserId, 'staff-view-key'));
        $show->assertOk()
            ->assertJsonPath('data.access_scope', 'staff')
            ->assertJsonPath('data.user_id', $customerUserId)
            ->assertJsonPath('data.table_ids.0', $tableId);
    }

    public function test_staff_can_create_guest_snapshot_reservation_without_user_id(): void
    {
        $staffUserId = $this->createUser(['role_name' => 'Admin']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $start = $this->nowUtc()->copy()->addHours(4);
        $end = $start->copy()->addHours(2);

        $headers = $this->withIdempotencyKey('reservation-staff-create-guest', $this->staffAuthHeaders($staffUserId));
        $create = $this->withHeaders($headers)->postJson('/api/v1/reservations', [
            'guest_name' => 'Caller Guest',
            'guest_phone' => '0905566778',
            'guest_email' => 'caller.guest@example.test',
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
            'table_ids' => [$tableId],
            'notes' => 'Phone-in reservation',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.access_scope', 'staff')
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.user.user_id', null)
            ->assertJsonPath('data.user.full_name', 'Caller Guest')
            ->assertJsonPath('data.user.phone', '0905566778')
            ->assertJsonPath('data.user.email', 'caller.guest@example.test')
            ->assertJsonPath('data.guest.full_name', 'Caller Guest')
            ->assertJsonPath('data.source', 'Offline')
            ->assertJsonPath('data.table_ids.0', $tableId);

        $reservationId = (int) $create->json('data.reservation_id');

        $reservationRow = DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->first(['user_id', 'guest_name', 'guest_phone', 'guest_email', 'source']);

        self::assertNotNull($reservationRow);
        self::assertNull($reservationRow->user_id);
        self::assertSame('Caller Guest', $reservationRow->guest_name);
        self::assertSame('0905566778', $reservationRow->guest_phone);
        self::assertSame('caller.guest@example.test', $reservationRow->guest_email);
        self::assertSame('Offline', $reservationRow->source);

        $show = $this->getJson('/api/v1/reservations/'.$reservationId, $this->staffAuthHeaders($staffUserId, 'staff-view-guest-key'));
        $show->assertOk()
            ->assertJsonPath('data.user.full_name', 'Caller Guest')
            ->assertJsonPath('data.user.phone', '0905566778')
            ->assertJsonPath('data.guest.email', 'caller.guest@example.test');

        $outbox = DB::table('notification_outbox')
            ->where('related_reservation_id', $reservationId)
            ->where('template_key', 'reservation.created')
            ->orderByDesc('outbox_id')
            ->first(['recipient', 'recipient_user_id']);

        self::assertNotNull($outbox);
        self::assertSame('caller.guest@example.test', $outbox->recipient);
        self::assertNull($outbox->recipient_user_id);
    }

    public function test_staff_create_requires_guest_snapshot_when_user_id_is_omitted(): void
    {
        $staffUserId = $this->createUser(['role_name' => 'Admin']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $start = $this->nowUtc()->copy()->addHours(4);
        $end = $start->copy()->addHours(2);

        $headers = $this->withIdempotencyKey('reservation-staff-create-missing-user', $this->staffAuthHeaders($staffUserId));
        $response = $this->withHeaders($headers)->postJson('/api/v1/reservations', [
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
            'table_ids' => [$tableId],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['guest_name', 'guest_phone'])
            ->assertJsonMissingValidationErrors(['user_id']);
    }

    public function test_staff_without_reservation_manage_cannot_create_reservation_via_shared_route(): void
    {
        $customerUserId = $this->createUser(['role_name' => 'Customer']);
        $staffUserId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $start = $this->nowUtc()->copy()->addHours(4);
        $end = $start->copy()->addHours(2);

        config()->set('staff_capabilities.role_capabilities', [
            'Admin' => ['*'],
            'Staff' => ['table.board.view'],
        ]);

        $response = $this->withHeaders(
            $this->withIdempotencyKey('reservation-staff-create-forbidden', $this->staffAuthHeaders($staffUserId))
        )->postJson('/api/v1/reservations', [
            'user_id' => $customerUserId,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
            'table_ids' => [$tableId],
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'reservation.manage')
            ->assertJsonPath('staff_role_name', 'Staff');

        $this->assertSame(0, DB::table('reservations')->where('user_id', $customerUserId)->count());
    }

    public function test_authenticated_customer_create_rejects_table_with_insufficient_capacity(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(2);
        $user = User::query()->findOrFail($userId);
        $start = $this->nowUtc()->copy()->addHours(5);
        $end = $start->copy()->addHours(2);

        $response = $this->actingAs($user)->postJson('/api/v1/reservations', [
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 4,
            'table_ids' => [$tableId],
        ], $this->withIdempotencyKey('reservation-capacity-reject'));

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        $message = (string) data_get($response->json(), 'details.errors.guest_count.0', '');
        self::assertStringContainsString('2 seats', $message);
    }

    public function test_authenticated_customer_create_rejects_overlapping_reservation(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $user = User::query()->findOrFail($userId);
        $start = $this->nowUtc()->copy()->addHours(6);
        $end = $start->copy()->addHours(2);

        $existingReservationId = $this->createReservation([
            'start_time' => $start->copy()->addMinutes(30),
            'end_time' => $end->copy()->addMinutes(30),
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($existingReservationId, $tableId);

        $response = $this->actingAs($user)->postJson('/api/v1/reservations', [
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
            'table_ids' => [$tableId],
        ], $this->withIdempotencyKey('reservation-overlap-reject'));

        $this->assertSame(1, (int) DB::table('reservations')->count());
        $this->assertSame(1, (int) DB::table('reservation_tables')->where('table_id', $tableId)->count());

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        $message = (string) data_get($response->json(), 'details.errors.table_ids.0', '');
        self::assertStringContainsString((string) $tableId, $message);
        self::assertStringContainsString('overlap reservation', $message);
    }

    public function test_authenticated_customer_create_rejects_branch_local_window_outside_business_hours(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'HCM02',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'business_hours' => collect(range(0, 6))
                ->map(static fn (int $day): array => [
                    'day_of_week' => $day,
                    'periods' => [[
                        'start_time' => '10:00',
                        'end_time' => '22:00',
                    ]],
                ])
                ->all(),
        ]);
        $userId = $this->createUser(['role_name' => 'Customer']);
        $user = User::query()->findOrFail($userId);
        $tableId = $this->createRestaurantTableWithSeats(4, ['branch_id' => $branchId]);
        $start = now('Asia/Ho_Chi_Minh')->addDay()->setTime(9, 30)->utc();
        $end = $start->copy()->addHour();

        $response = $this->actingAs($user)->postJson('/api/v1/reservations', [
            'branch_id' => $branchId,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
            'table_ids' => [$tableId],
        ], $this->withIdempotencyKey('reservation-business-hours-reject'));

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('details.errors.start_time.0', 'Requested reservation window falls outside the configured branch business hours.');
    }

    public function test_authenticated_customer_create_rejects_branch_closure_window(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'HCMCL',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'business_hours' => collect(range(0, 6))
                ->map(static fn (int $day): array => [
                    'day_of_week' => $day,
                    'periods' => [[
                        'start_time' => '09:00',
                        'end_time' => '22:00',
                    ]],
                ])
                ->all(),
            'closure_windows' => [[
                'start_local' => '2026-09-10 18:00:00',
                'end_local' => '2026-09-10 20:00:00',
                'type' => 'blackout',
                'reason' => 'Private event',
            ]],
        ]);
        $userId = $this->createUser(['role_name' => 'Customer']);
        $user = User::query()->findOrFail($userId);
        $tableId = $this->createRestaurantTableWithSeats(4, ['branch_id' => $branchId]);
        $start = Carbon::parse('2026-09-10 18:30:00', 'Asia/Ho_Chi_Minh')->utc();
        $end = $start->copy()->addHour();

        $response = $this->actingAs($user)->postJson('/api/v1/reservations', [
            'branch_id' => $branchId,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
            'table_ids' => [$tableId],
        ], $this->withIdempotencyKey('reservation-closure-window-reject'));

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('details.errors.start_time.0', 'Requested reservation window overlaps a branch closure window: Private event.');
    }

    public function test_authenticated_customer_create_rejects_same_day_cutoff_in_branch_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', 'UTC'));

        try {
            $branchId = $this->createBranch([
                'branch_code' => 'HCMCUT',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'business_hours' => collect(range(0, 6))
                    ->map(static fn (int $day): array => [
                        'day_of_week' => $day,
                        'periods' => [[
                            'start_time' => '00:00',
                            'end_time' => '24:00',
                        ]],
                    ])
                    ->all(),
                'booking_policy' => [
                    'reservation' => [
                        'same_day_cutoff_time' => '18:00',
                    ],
                    'waiting_list' => [],
                    'availability' => [],
                ],
            ]);
            $userId = $this->createUser(['role_name' => 'Customer']);
            $user = User::query()->findOrFail($userId);
            $tableId = $this->createRestaurantTableWithSeats(4, ['branch_id' => $branchId]);
            $start = Carbon::parse('2026-09-10 13:30:00', 'UTC');
            $end = $start->copy()->addHour();

            $response = $this->actingAs($user)->postJson('/api/v1/reservations', [
                'branch_id' => $branchId,
                'start_time' => $start->toIso8601String(),
                'end_time' => $end->toIso8601String(),
                'guest_count' => 2,
                'table_ids' => [$tableId],
            ], $this->withIdempotencyKey('reservation-same-day-cutoff-reject'));

            $response->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_error')
                ->assertJsonPath('details.errors.start_time.0', 'Same-day reservation requests close at 18:00 in the branch timezone.');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_authenticated_customer_create_rejects_requested_branch_that_does_not_match_selected_tables(): void
    {
        $customerUserId = $this->createUser(['role_name' => 'Customer']);
        $customer = User::query()->findOrFail($customerUserId);
        $annexBranchId = $this->createBranch([
            'branch_code' => 'RSVANNEX',
            'branch_name' => 'Reservation Annex',
        ]);
        $tableId = $this->createRestaurantTableWithSeats(4, ['branch_id' => 1]);
        $start = $this->nowUtc()->copy()->addHours(5);
        $end = $start->copy()->addHours(2);

        $response = $this->actingAs($customer)->postJson('/api/v1/reservations', [
            'branch_id' => $annexBranchId,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
            'table_ids' => [$tableId],
        ], $this->withIdempotencyKey('reservation-branch-mismatch'));

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['branch_id']);

        $this->assertSame(0, DB::table('reservations')->where('user_id', $customerUserId)->count());
    }

    public function test_session_create_rejects_hold_owned_by_different_session(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $start = $this->nowUtc()->copy()->addHours(3);
        $end = $start->copy()->addHours(2);
        $holdId = $this->createTableHold([
            'session_id' => 'session-owner',
            'user_id' => $userId,
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => (int) $start->diffInMinutes($end),
            'expire_at' => $this->nowUtc()->copy()->addMinutes(45),
        ], [$tableId]);

        $response = $this->postJson('/api/v1/reservations', [
            'hold_id' => $holdId,
            'session_id' => 'other-session',
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'guest_count' => 2,
        ], $this->withIdempotencyKey('reservation-session-hold-mismatch'));

        $response->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_staff_can_view_reservation_with_staff_scope_and_identity_fields(): void
    {
        $customerUserId = $this->createUser(['role_name' => 'Customer']);
        $staffUserId = $this->createUser(['role_name' => 'Admin']);
        $staffRoleId = (int) DB::table('users')->where('user_id', $staffUserId)->value('role_id');
        config()->set('staff_auth.allowed_role_ids', [$staffRoleId]);

        $reservationId = $this->createReservation([
            'user_id' => $customerUserId,
        ]);
        $tableId = $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4));

        $show = $this->getJson(
            '/api/v1/reservations/'.$reservationId,
            $this->staffAuthHeaders($staffUserId, 'staff-view-key')
        );

        $customer = User::query()->findOrFail($customerUserId);

        $show->assertOk()
            ->assertJsonPath('data.access_scope', 'staff')
            ->assertJsonPath('data.user_id', $customerUserId)
            ->assertJsonPath('data.user.user_id', $customerUserId)
            ->assertJsonPath('data.user.full_name', (string) $customer->full_name)
            ->assertJsonPath('data.user.email', (string) $customer->email)
            ->assertJsonPath('data.table_ids.0', $tableId);
    }

    public function test_staff_without_reservation_manage_cannot_view_reservation_via_shared_route(): void
    {
        $customerUserId = $this->createUser(['role_name' => 'Customer']);
        $staffUserId = $this->createUser(['role_name' => 'Staff']);
        $reservationId = $this->createReservation([
            'user_id' => $customerUserId,
        ]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4));

        config()->set('staff_capabilities.role_capabilities', [
            'Admin' => ['*'],
            'Staff' => ['table.board.view'],
        ]);

        $response = $this->withHeaders($this->staffAuthHeaders($staffUserId, 'staff-view-forbidden-key'))
            ->getJson('/api/v1/reservations/'.$reservationId);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'reservation.manage')
            ->assertJsonPath('staff_role_name', 'Staff');
    }

    public function test_authenticated_unrelated_customer_cannot_view_other_users_reservation(): void
    {
        $ownerUserId = $this->createUser(['role_name' => 'Customer']);
        $viewerUserId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerUserId,
        ]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4));

        $viewer = User::query()->findOrFail($viewerUserId);
        $response = $this->actingAs($viewer)->getJson('/api/v1/reservations/'.$reservationId);

        $response->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_unlinked_session_cannot_view_reservation(): void
    {
        $reservationId = $this->createReservation();
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4));

        $response = $this->getJson('/api/v1/reservations/'.$reservationId.'?session_id=session-other');

        $response->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }
}
