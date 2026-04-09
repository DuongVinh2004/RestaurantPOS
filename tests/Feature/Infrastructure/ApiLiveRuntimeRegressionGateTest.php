<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Models\User;
use App\Services\ReservationLockService;
use App\Services\RestaurantTableStateService;
use App\Services\RuntimeSettingService;
use App\Services\Staff\StaffCheckoutService;
use App\Services\TableTimeConflictService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class ApiLiveRuntimeRegressionGateTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();

        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->flush();

        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_auth.allow_role_name_fallback', false);
        config()->set('customer_auth.enabled', true);
        config()->set('customer_auth.allow_legacy_user_auth_tokens', false);

        $this->app->instance(ReservationLockService::class, $this->mockReservationLocks());
        $this->app->instance(RestaurantTableStateService::class, new RestaurantTableStateService);
        $this->app->instance(TableTimeConflictService::class, new TableTimeConflictService);
        $this->app->instance(RuntimeSettingService::class, $this->mockRuntimeSettings());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_live_status_mutation_boundary_is_protected_and_requires_capability_for_authenticated_staff(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
        ]);

        $staffId = $this->createUser(['role_name' => 'Staff']);
        config()->set('staff_capabilities.role_capabilities', [
            'Admin' => ['*'],
            'Staff' => [],
        ]);

        $payload = [
            'status' => 'Reserved',
            'row_version' => 1,
        ];

        $this->withHeaders($this->staffAuthHeaders($staffId, 'gate-live-status-staff'))
            ->patchJson('/api/v1/reservations/'.$reservationId.'/status', $payload)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'reservation.manage');

        $unauthenticated = $this->withHeaders(['Accept' => 'application/json'])
            ->patchJson('/api/v1/reservations/'.$reservationId.'/status', $payload);

        $this->assertContains($unauthenticated->getStatusCode(), [401, 403]);
    }

    public function test_live_authenticated_hold_to_reservation_flow_smoke_survives_runtime_surface(): void
    {
        $sessionId = 'sess-live-runtime-gate';
        $customerPassword = 'RuntimeGate!123';
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'password_hash' => Hash::make($customerPassword),
        ]);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addHours(3);
        $end = $start->copy()->addHours(2);

        $holdCreate = $this->withHeaders($this->withIdempotencyKey([
            'Accept' => 'application/json',
        ], 'gate-live-runtime-hold-create'))
            ->postJson('/api/v1/table-holds', [
                'session_id' => $sessionId,
                'start_time' => $start->toIso8601String(),
                'end_time' => $end->toIso8601String(),
                'table_ids' => [$tableId],
                'hold_minutes' => 5,
            ]);

        $holdCreate->assertCreated()
            ->assertJsonPath('data.hold_status', 'Holding')
            ->assertJsonPath('data.tables.0.table_id', $tableId);

        $holdId = (string) $holdCreate->json('data.hold_id');

        $login = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Session-Id' => $sessionId,
        ])->postJson('/api/v1/auth/customer/login', [
            'identifier' => (string) User::query()->whereKey($customerId)->value('username'),
            'password' => $customerPassword,
            'session_label' => 'runtime-gate',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.auth_mode', 'customer_access_session')
            ->assertJsonPath('data.user.user_id', $customerId)
            ->assertJsonPath('data.session_id', $sessionId);

        $customerHeaders = [
            'Accept' => 'application/json',
            'X-Customer-Token' => (string) $login->json('data.access_token'),
            'X-Session-Id' => (string) $login->json('data.session_id'),
        ];

        $reservationCreate = $this->withHeaders($this->withIdempotencyKey($customerHeaders, 'gate-live-runtime-reservation-create'))
            ->postJson('/api/v1/reservations', [
                'hold_id' => $holdId,
                'session_id' => (string) $login->json('data.session_id'),
                'start_time' => $start->toIso8601String(),
                'end_time' => $end->toIso8601String(),
                'guest_count' => 2,
                'notes' => 'runtime smoke hold to reservation',
            ]);

        $reservationCreate->assertCreated()
            ->assertJsonPath('data.access_scope', 'owner')
            ->assertJsonPath('data.user_id', $customerId)
            ->assertJsonPath('data.table_ids.0', $tableId);

        $reservationId = (int) $reservationCreate->json('data.reservation_id');

        $this->withHeaders($customerHeaders)
            ->getJson('/api/v1/reservations/'.$reservationId)
            ->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.table_ids.0', $tableId);
    }

    public function test_live_checkout_alias_and_finalize_route_share_replay_semantics(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($adminId, 'gate-live-checkout-staff'), 'gate-live-checkout-alias-1');

        $service = Mockery::mock(StaffCheckoutService::class);
        $service->shouldReceive('checkout')
            ->once()
            ->andReturn([
                'order_id' => 41,
                'status' => 'Completed',
                'payments' => [],
            ]);
        $this->app->instance(StaffCheckoutService::class, $service);

        $payload = [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 100000,
            'currency' => 'VND',
            'transaction_code' => 'LIVE-GATE-CHECKOUT-1',
            'row_version' => 1,
        ];

        $first = $this->withHeaders($headers)
            ->postJson('/api/v1/staff/orders/41/checkout', $payload);

        $first->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertHeader('Deprecation', 'true')
            ->assertHeader('X-Deprecated-Route-Alias', '/api/v1/staff/orders/{order_id}/checkout')
            ->assertHeader('X-Canonical-Route', '/api/v1/staff/orders/41/settlement/finalize')
            ->assertJsonPath('data.order_id', 41);

        $second = $this->withHeaders($headers)
            ->postJson('/api/v1/staff/orders/41/settlement/finalize', $payload);

        $second->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.order_id', 41)
            ->assertJsonPath('data.status', 'Completed');
    }
}
