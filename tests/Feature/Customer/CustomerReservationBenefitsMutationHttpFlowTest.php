<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerReservationBenefitsMutationHttpFlowTest extends TestCase
{
    use AssertsAuditTrail;
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

    public function test_customer_can_apply_voucher_to_owned_reservation(): void
    {
        [$user, $reservationId, $userVoucherId] = $this->seedReservationWithVoucherAndPoints();

        $response = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'cust-voucher-apply-success-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/voucher/apply", [
                'user_voucher_id' => $userVoucherId,
                'row_version' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.available_vouchers.0.user_voucher_id', $userVoucherId)
            ->assertJsonPath('data.available_vouchers.0.is_currently_applied', true)
            ->assertJsonPath('data.voucher.user_voucher_id', $userVoucherId)
            ->assertJsonPath('data.voucher.is_currently_applied', true);

        $this->assertSame('reservation:'.$reservationId, (string) DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->value('lock_token'));
        $this->assertSame($userVoucherId, (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('applied_user_voucher_id'));

        $log = $this->assertAuditLogRecorded('reservation.voucher.applied', 'reservation', $reservationId);
        self::assertSame((int) $user->user_id, $log->actor_user_id);
        self::assertSame('customer_account', $log->actor_type);
        $this->assertAuditSubjectRecorded($log, 'user_voucher', $userVoucherId, 'user_voucher');
    }

    public function test_customer_can_remove_voucher_from_owned_reservation(): void
    {
        [$user, $reservationId, $userVoucherId] = $this->seedReservationWithVoucherAndPoints();
        DB::table('reservations')->where('reservation_id', $reservationId)->update([
            'applied_user_voucher_id' => $userVoucherId,
            'discount_amount' => '50000.00',
        ]);
        DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->update([
            'lock_token' => 'reservation:'.$reservationId,
            'locked_until' => $this->nowUtc()->copy()->addMinutes(5),
        ]);

        $currentVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');

        $response = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'cust-voucher-remove-success-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/voucher/remove", [
                'row_version' => $currentVersion,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.available_vouchers.0.user_voucher_id', $userVoucherId)
            ->assertJsonPath('data.available_vouchers.0.is_currently_applied', false)
            ->assertJsonPath('data.removed_voucher.user_voucher_id', $userVoucherId)
            ->assertJsonPath('data.removed_voucher.is_currently_applied', false);

        $this->assertNull(DB::table('reservations')->where('reservation_id', $reservationId)->value('applied_user_voucher_id'));
        $this->assertNull(DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->value('lock_token'));

        $log = $this->assertAuditLogRecorded('reservation.voucher.removed', 'reservation', $reservationId);
        self::assertSame((int) $user->user_id, $log->actor_user_id);
        self::assertSame('customer_account', $log->actor_type);
        self::assertTrue((bool) data_get($log->after_json, 'voucher_removed'));
    }

    public function test_customer_can_redeem_loyalty_points_for_owned_reservation(): void
    {
        [$user, $reservationId] = $this->seedReservationWithVoucherAndPoints();

        $response = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'cust-loyalty-redeem-success-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/loyalty/redeem", [
                'points' => 50,
                'reason' => 'customer checkout preview',
                'row_version' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.reservation.loyalty.redeemed_points', 50);

        $redeemTxn = collect($response->json('data.transactions', []))->firstWhere('txn_type', 'Redeem');
        $this->assertNotNull($redeemTxn);
        $this->assertSame(50, (int) ($redeemTxn['points'] ?? 0));

        $log = $this->assertAuditLogRecorded('reservation.loyalty.redeemed', 'reservation', $reservationId);
        self::assertSame((int) $user->user_id, $log->actor_user_id);
        self::assertSame('customer_account', $log->actor_type);
        self::assertSame(50, (int) data_get($log->summary_json, 'points'));
    }

    public function test_customer_can_release_loyalty_redemption_for_owned_reservation(): void
    {
        [$user, $reservationId] = $this->seedReservationWithVoucherAndPoints();

        $redeemResponse = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'cust-loyalty-release-seed-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/loyalty/redeem", [
                'points' => 40,
                'row_version' => 1,
            ]);
        $redeemResponse->assertOk();

        $currentVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');

        $releaseResponse = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'cust-loyalty-release-success-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/loyalty/redeem/release", [
                'reason' => 'customer changed mind',
                'row_version' => $currentVersion,
            ]);

        $releaseResponse->assertOk()
            ->assertJsonPath('data.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.reservation.loyalty.redeemed_points', 0);

        $releaseTxn = collect($releaseResponse->json('data.transactions', []))
            ->first(fn (array $row): bool => (string) ($row['txn_type'] ?? '') === 'Adjust' && str_contains((string) ($row['reason'] ?? ''), 'redeem.release'));
        $this->assertNotNull($releaseTxn);

        $log = $this->assertAuditLogRecorded('reservation.loyalty.released', 'reservation', $reservationId);
        self::assertSame((int) $user->user_id, $log->actor_user_id);
        self::assertSame('customer_account', $log->actor_type);
        self::assertSame('customer changed mind', (string) data_get($log->summary_json, 'reason'));
    }

    public function test_customer_cannot_mutate_other_users_reservation_benefits(): void
    {
        [, $reservationId, $userVoucherId] = $this->seedReservationWithVoucherAndPoints();
        $otherUserId = $this->createUser(['role_name' => 'Customer']);
        $other = User::query()->findOrFail($otherUserId);

        $response = $this->actingAs($other)
            ->withHeaders(['Idempotency-Key' => 'cust-voucher-apply-other-user-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/voucher/apply", [
                'user_voucher_id' => $userVoucherId,
                'row_version' => 1,
            ]);

        $response->assertNotFound();
    }

    public function test_unauthenticated_customer_benefits_mutation_is_rejected(): void
    {
        [, $reservationId, $userVoucherId] = $this->seedReservationWithVoucherAndPoints();

        $response = $this->withHeaders(['Idempotency-Key' => 'cust-voucher-apply-unauth-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/voucher/apply", [
                'user_voucher_id' => $userVoucherId,
                'row_version' => 1,
            ]);

        $response->assertStatus(401);
    }

    public function test_staff_cannot_use_customer_benefits_mutation_endpoints(): void
    {
        [, $reservationId, $userVoucherId] = $this->seedReservationWithVoucherAndPoints();
        $staffId = $this->createUser(['role_name' => 'Staff']);
        config()->set('staff_auth.api_keys', ['customer-benefits-staff-key' => $staffId]);

        $response = $this->withHeaders([
            'X-Staff-Key' => 'customer-benefits-staff-key',
            'Idempotency-Key' => 'cust-voucher-apply-staff-misuse-1',
        ])->postJson("/api/v1/reservations/{$reservationId}/voucher/apply", [
            'user_voucher_id' => $userVoucherId,
            'row_version' => 1,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }

    public function test_customer_benefits_mutation_rejects_stale_row_version(): void
    {
        [$user, $reservationId, $userVoucherId] = $this->seedReservationWithVoucherAndPoints();
        DB::table('reservations')->where('reservation_id', $reservationId)->update(['row_version' => 3]);

        $response = $this->actingAs($user)
            ->withHeaders([
                'Idempotency-Key' => 'cust-voucher-apply-stale-1',
                'X-Request-Id' => 'req-customer-benefits-stale-row-version',
            ])
            ->postJson("/api/v1/reservations/{$reservationId}/voucher/apply", [
                'user_voucher_id' => $userVoucherId,
                'row_version' => 1,
            ]);

        $response->assertStatus(409)
            ->assertHeader('X-Request-Id', 'req-customer-benefits-stale-row-version')
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonPath('request_id', 'req-customer-benefits-stale-row-version')
            ->assertJsonPath('details.errors.row_version.0', 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.');
    }

    public function test_customer_cannot_apply_voucher_after_final_payment_has_been_recorded(): void
    {
        [$user, $reservationId, $userVoucherId] = $this->seedReservationWithVoucherAndPoints();
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '250000.00',
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'cust-voucher-apply-final-paid-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/voucher/apply", [
                'user_voucher_id' => $userVoucherId,
                'row_version' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    public function test_customer_cannot_release_loyalty_after_final_payment_has_been_recorded(): void
    {
        [$user, $reservationId] = $this->seedReservationWithVoucherAndPoints();

        $redeemResponse = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'cust-loyalty-release-final-paid-seed'])
            ->postJson("/api/v1/reservations/{$reservationId}/loyalty/redeem", [
                'points' => 20,
                'row_version' => 1,
            ]);
        $redeemResponse->assertOk();

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '180000.00',
        ]);

        $currentVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');

        $response = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'cust-loyalty-release-final-paid-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/loyalty/redeem/release", [
                'row_version' => $currentVersion,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    public function test_customer_benefits_mutation_replays_idempotent_success_response(): void
    {
        [$user, $reservationId, $userVoucherId] = $this->seedReservationWithVoucherAndPoints();
        $headers = ['Idempotency-Key' => 'cust-voucher-apply-replay-1'];
        $payload = [
            'user_voucher_id' => $userVoucherId,
            'row_version' => 1,
        ];

        $first = $this->actingAs($user)
            ->withHeaders($headers)
            ->postJson("/api/v1/reservations/{$reservationId}/voucher/apply", $payload);

        $first->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false');

        $second = $this->actingAs($user)
            ->withHeaders($headers)
            ->postJson("/api/v1/reservations/{$reservationId}/voucher/apply", $payload);

        $second->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.voucher.user_voucher_id', $userVoucherId)
            ->assertJsonPath('data.reservation.reservation_id', $reservationId);
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
