<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class CustomerReservationBenefitsIdempotencyEnforcementTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_customer_voucher_apply_requires_idempotency_key(): void
    {
        [$user, $reservationId, $userVoucherId] = $this->seedReservationWithVoucherAndPoints();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/reservations/{$reservationId}/voucher/apply", [
                'user_voucher_id' => $userVoucherId,
                'row_version' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'idempotency_key_required');
    }

    public function test_customer_loyalty_redeem_requires_idempotency_key(): void
    {
        [$user, $reservationId] = $this->seedReservationWithVoucherAndPoints();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/reservations/{$reservationId}/loyalty/redeem", [
                'points' => 50,
                'row_version' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'idempotency_key_required');
    }

    /**
     * @return array{0:User,1:int,2:int}
     */
    private function seedReservationWithVoucherAndPoints(): array
    {
        $tierId = $this->createLoyaltyTier(0, 'BRONZE', 'Bronze');
        $userId = $this->createUser([
            'role_name' => 'Customer',
            'current_tier_id' => $tierId,
        ]);
        $this->ensureUserPoints($userId, 300, $userId);

        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'status' => 'Confirmed',
            'bill_currency' => 'VND',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Completed',
        ]);
        $menuItemId = $this->createMenuItem(['name' => 'Benefits Dish']);
        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $menuItemId,
            'quantity' => 2,
            'unit_price' => 100000,
            'line_total' => 200000,
            'currency' => 'VND',
            'status' => 'Served',
        ]);

        $voucherId = $this->createVoucher([
            'code' => 'BENEFITS-50',
            'discount_type' => 'Fixed',
            'discount_value' => '50000.00',
            'min_spend' => '100000.00',
        ]);
        $userVoucherId = $this->assignVoucher([
            'user_id' => $userId,
            'voucher_id' => $voucherId,
            'is_used' => 0,
        ]);

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        return [$user, $reservationId, $userVoucherId];
    }
}
