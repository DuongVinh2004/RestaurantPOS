<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerBenefitsSelfServiceHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_authenticated_customer_can_view_loyalty_summary(): void
    {
        // Deactivate all pre-existing tiers inside this transaction to avoid next tier collision on pre-seeded databases
        DB::table('loyalty_tiers')->update(['is_active' => 0]);

        $bronzeId = $this->createLoyaltyTier(0, 'BRONZE', 'Bronze');
        $this->createLoyaltyTier(1000, 'SILVER', 'Silver');
        $userId = $this->createUser([
            'role_name' => 'Customer',
            'current_tier_id' => $bronzeId,
        ]);
        $this->ensureUserPoints($userId, 650, $userId);

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        $response = $this->actingAs($user)->getJson('/api/v1/me/loyalty?limit=5');

        $response->assertOk()
            ->assertJsonPath('data.user.user_id', $userId)
            ->assertJsonPath('data.user.total_points', 650)
            ->assertJsonPath('data.user.current_tier.tier_code', 'BRONZE')
            ->assertJsonPath('data.user.next_tier.tier_code', 'SILVER');
    }

    public function test_authenticated_customer_can_list_only_active_vouchers(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        $activeVoucherId = $this->createVoucher(['code' => 'ACTIVE-10']);
        $usedVoucherId = $this->createVoucher(['code' => 'USED-10']);

        $this->assignVoucher([
            'user_id' => $userId,
            'voucher_id' => $activeVoucherId,
            'is_used' => 0,
        ]);
        $this->assignVoucher([
            'user_id' => $userId,
            'voucher_id' => $usedVoucherId,
            'is_used' => 1,
            'used_date' => $this->nowUtc(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/me/vouchers?bucket=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.voucher_code', 'ACTIVE-10')
            ->assertJsonPath('data.0.current_status', 'Active');
    }

    public function test_customer_can_preview_loyalty_and_voucher_applicability_for_owned_reservation(): void
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
        $menuItemId = $this->createMenuItem(['name' => 'Preview Dish']);
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
            'code' => 'FIX50',
            'discount_type' => 'Fixed',
            'discount_value' => '50000',
            'min_spend' => '100000',
        ]);
        $this->assignVoucher([
            'user_id' => $userId,
            'voucher_id' => $voucherId,
            'is_used' => 0,
        ]);

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        $response = $this->actingAs($user)->getJson('/api/v1/reservations/'.$reservationId.'/benefits-preview');

        $response->assertOk()
            ->assertJsonPath('data.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.reservation.loyalty.available_points', 300)
            ->assertJsonPath('data.available_vouchers.0.voucher_code', 'FIX50')
            ->assertJsonPath('data.available_vouchers.0.can_apply', true)
            ->assertJsonPath('data.available_vouchers.0.preview_discount_amount', '50000');
    }

    public function test_customer_cannot_preview_other_users_reservation_benefits(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $otherId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'status' => 'Confirmed',
        ]);

        /** @var User $other */
        $other = User::query()->findOrFail($otherId);

        $response = $this->actingAs($other)->getJson('/api/v1/reservations/'.$reservationId.'/benefits-preview');

        $response->assertNotFound();
    }

    public function test_customer_active_voucher_bucket_only_returns_vouchers_usable_now(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        $activeVoucherId = $this->createVoucher(['code' => 'ACTIVE-NOW']);
        $expiredVoucherId = $this->createVoucher([
            'code' => 'EXPIRED-NOW',
            'expiry_date' => $this->nowUtc()->copy()->subDay(),
        ]);

        $this->assignVoucher([
            'user_id' => $userId,
            'voucher_id' => $activeVoucherId,
            'is_used' => 0,
        ]);
        $this->assignVoucher([
            'user_id' => $userId,
            'voucher_id' => $expiredVoucherId,
            'is_used' => 0,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/me/vouchers?bucket=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.voucher_code', 'ACTIVE-NOW');
    }

    public function test_customer_benefits_preview_marks_terminal_reservation_vouchers_as_not_applicable(): void
    {
        $tierId = $this->createLoyaltyTier(0, 'BRONZE', 'Bronze');
        $userId = $this->createUser([
            'role_name' => 'Customer',
            'current_tier_id' => $tierId,
        ]);
        $this->ensureUserPoints($userId, 300, $userId);

        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'status' => 'Completed',
            'bill_currency' => 'VND',
            'checked_out_at' => $this->nowUtc(),
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Completed',
        ]);
        $menuItemId = $this->createMenuItem(['name' => 'Terminal Dish']);
        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $menuItemId,
            'quantity' => 1,
            'unit_price' => 120000,
            'line_total' => 120000,
            'currency' => 'VND',
            'status' => 'Served',
        ]);

        $voucherId = $this->createVoucher([
            'code' => 'TERM-50',
            'discount_type' => 'Fixed',
            'discount_value' => '50000',
            'min_spend' => '100000',
        ]);
        $this->assignVoucher([
            'user_id' => $userId,
            'voucher_id' => $voucherId,
            'is_used' => 0,
        ]);

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        $response = $this->actingAs($user)->getJson('/api/v1/reservations/'.$reservationId.'/benefits-preview');

        $response->assertOk()
            ->assertJsonPath('data.available_vouchers.0.voucher_code', 'TERM-50')
            ->assertJsonPath('data.available_vouchers.0.can_apply', false);

        $reasonCodes = collect($response->json('data.available_vouchers.0.applicability_reason_codes', []));
        self::assertTrue($reasonCodes->contains('reservation_inactive'));
    }
}
