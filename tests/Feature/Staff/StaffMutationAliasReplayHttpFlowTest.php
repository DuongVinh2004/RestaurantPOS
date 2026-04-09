<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Models\Reservation;
use App\Services\LoyaltyPointsService;
use App\Services\Staff\StaffCheckoutService;
use App\Services\Staff\StaffReservationVoucherService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class StaffMutationAliasReplayHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
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
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_checkout_alias_shares_replay_semantics_with_finalize_route(): void
    {
        $staffId = $this->createUser(['role_name' => 'Admin']);
        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-alias-checkout'), 'idem-staff-checkout-alias-1');

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
            'transaction_code' => 'ALIAS-CHECKOUT-1',
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

    public function test_voucher_release_alias_shares_replay_semantics_with_remove_route(): void
    {
        $staffId = $this->createUser(['role_name' => 'Admin']);
        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-alias-voucher-release'), 'idem-staff-voucher-release-1');
        $reservationId = $this->createReservation(['status' => 'Confirmed']);
        $reservation = Reservation::query()->findOrFail($reservationId);

        $service = Mockery::mock(StaffReservationVoucherService::class);
        $service->shouldReceive('removeVoucher')
            ->once()
            ->andReturn([
                'reservation' => $reservation,
                'removed_voucher' => [
                    'user_voucher_id' => 77,
                    'voucher_code' => 'ALIAS-77',
                ],
            ]);
        $this->app->instance(StaffReservationVoucherService::class, $service);

        $payload = [
            'row_version' => 1,
        ];

        $first = $this->withHeaders($headers)
            ->postJson('/api/v1/staff/reservations/' . $reservationId . '/voucher/release', $payload);

        $first->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertHeader('Deprecation', 'true')
            ->assertHeader('X-Deprecated-Route-Alias', '/api/v1/staff/reservations/{reservation_id}/voucher/release')
            ->assertHeader('X-Canonical-Route', '/api/v1/staff/reservations/' . $reservationId . '/voucher/remove')
            ->assertJsonPath('data.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.removed_voucher.user_voucher_id', 77);

        $second = $this->withHeaders($headers)
            ->postJson('/api/v1/staff/reservations/' . $reservationId . '/voucher/remove', $payload);

        $second->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.removed_voucher.user_voucher_id', 77);
    }

    public function test_loyalty_release_alias_shares_replay_semantics_with_canonical_release_route(): void
    {
        $staffId = $this->createUser(['role_name' => 'Admin']);
        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-alias-loyalty-release'), 'idem-staff-loyalty-release-1');
        $reservationId = $this->createReservation(['status' => 'Confirmed']);

        $service = Mockery::mock(LoyaltyPointsService::class);
        $service->shouldReceive('releaseReservationRedemption')
            ->once()
            ->andReturn([
                'reservation' => [
                    'reservation_id' => $reservationId,
                    'loyalty' => [
                        'redeemed_points' => 0,
                    ],
                ],
                'transactions' => collect([]),
            ]);
        $this->app->instance(LoyaltyPointsService::class, $service);

        $payload = [
            'row_version' => 1,
        ];

        $first = $this->withHeaders($headers)
            ->postJson('/api/v1/staff/reservations/' . $reservationId . '/loyalty/release', $payload);

        $first->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertHeader('Deprecation', 'true')
            ->assertHeader('X-Deprecated-Route-Alias', '/api/v1/staff/reservations/{reservation_id}/loyalty/release')
            ->assertHeader('X-Canonical-Route', '/api/v1/staff/reservations/' . $reservationId . '/loyalty/redeem/release')
            ->assertJsonPath('data.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.reservation.loyalty.redeemed_points', 0);

        $second = $this->withHeaders($headers)
            ->postJson('/api/v1/staff/reservations/' . $reservationId . '/loyalty/redeem/release', $payload);

        $second->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.reservation.loyalty.redeemed_points', 0);
    }
}
