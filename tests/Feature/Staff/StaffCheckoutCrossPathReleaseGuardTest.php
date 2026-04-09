<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Enums\ReservationStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffCheckoutCrossPathReleaseGuardTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();
        config()->set('notifications.outbox.enabled', false);
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->getStore()->flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_refund_cancel_then_dedicated_release_paths_do_not_double_restore_voucher_or_loyalty(): void
    {
        [
            'customer_id' => $customerId,
            'staff_id' => $staffId,
            'reservation_id' => $reservationId,
            'order_id' => $orderId,
            'user_voucher_id' => $userVoucherId,
        ] = $this->seedReservationWithVoucherAndLoyalty(
            status: ReservationStatus::Reserved->value,
            checkedIn: true,
        );

        $checkoutService = $this->makeCheckoutService();
        $checkoutService->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 50000.0,
            currency: 'VND',
            transactionCode: 'FINAL-CROSS-PATH-1',
            paymentProvider: 'Cash',
            notes: 'cross path finalize',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-final-cross-path-1',
        );

        $checkoutService->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'REFUND-CANCEL-CROSS-PATH-1',
            paymentProvider: 'Cash',
            notes: 'cross path refund cancel',
            reason: 'cross_path_guard',
            cancelReason: 'cross path cancel',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-refund-cancel-cross-path-1',
        );

        $reservation = DB::table('reservations')->where('reservation_id', $reservationId)->first();
        $userVoucher = DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->first();

        self::assertSame('Cancelled', (string) ($reservation->status ?? ''));
        self::assertSame(300, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        self::assertSame(1, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'redeem.release:cancelled_after_payment')
            ->count());
        self::assertSame(1, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'earn.sync.refund')
            ->count());
        self::assertSame(0, (int) ($userVoucher->is_used ?? 0));
        self::assertNull($userVoucher->used_reservation_id);
        self::assertNull($reservation->applied_user_voucher_id);
        self::assertSame(0.0, (float) ($reservation->discount_amount ?? 0.0));

        try {
            $this->makeVoucherService()->removeVoucher(
                reservationId: $reservationId,
                expectedRowVersion: null,
                staffUserId: $staffId,
            );
            self::fail('Expected voucher remove to reject cancelled reservation.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            self::assertSame(
                'Voucher can only be managed for Confirmed or checked-in (Reserved) reservations.',
                $errors['reservation'][0] ?? null,
            );
        }

        try {
            $this->makeLoyaltyService()->releaseReservationRedemption(
                reservationId: $reservationId,
                reason: 'should_not_release_twice',
                expectedRowVersion: null,
                staffUserId: $staffId,
            );
            self::fail('Expected loyalty release to reject cancelled reservation.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            self::assertSame(
                'Loyalty redemption only supports Confirmed or Reserved reservations.',
                $errors['reservation'][0] ?? null,
            );
        }

        self::assertSame(300, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        self::assertSame(1, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'redeem.release:cancelled_after_payment')
            ->count());
        self::assertSame(1, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'earn.sync.refund')
            ->count());
        self::assertSame(0, (int) DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->value('is_used'));
    }

    public function test_manual_voucher_and_loyalty_release_before_status_cancel_do_not_double_restore_on_cancel_path(): void
    {
        [
            'customer_id' => $customerId,
            'staff_id' => $staffId,
            'reservation_id' => $reservationId,
            'user_voucher_id' => $userVoucherId,
        ] = $this->seedReservationWithVoucherAndLoyalty(
            status: ReservationStatus::Confirmed->value,
            checkedIn: false,
        );

        $this->makeVoucherService()->removeVoucher(
            reservationId: $reservationId,
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        $this->makeLoyaltyService()->releaseReservationRedemption(
            reservationId: $reservationId,
            reason: 'pre_cancel_manual',
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        self::assertSame(300, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        self::assertSame(1, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'like', 'redeem.release:%')
            ->count());
        self::assertSame(0, (int) DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->value('is_used'));
        self::assertNull(DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->value('lock_token'));

        $freshRowVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');
        self::assertGreaterThan(1, $freshRowVersion);

        $response = $this->withHeaders($this->withIdempotencyKey('status-cancel-cross-path-release-1', $this->staffAuthHeaders($staffId)))
            ->patchJson("/api/v1/reservations/{$reservationId}/status", [
                'status' => ReservationStatus::Cancelled->value,
                'row_version' => $freshRowVersion,
                'cancel_reason' => 'manual cancel after release',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Cancelled->value);

        $reservation = DB::table('reservations')->where('reservation_id', $reservationId)->first();
        $userVoucher = DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->first();

        self::assertSame(300, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        self::assertSame(1, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'like', 'redeem.release:%')
            ->count());
        self::assertNull($reservation->applied_user_voucher_id);
        self::assertSame(0.0, (float) ($reservation->discount_amount ?? 0.0));
        self::assertSame(0, (int) ($userVoucher->is_used ?? 0));
        self::assertNull($userVoucher->used_reservation_id);
        self::assertNull($userVoucher->lock_token);
        self::assertNull($userVoucher->locked_until);
    }

    public function test_status_cancel_rejects_stale_row_version_when_drift_came_from_bill_lock_not_release_paths(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $reservationId = $this->createReservation([
            'user_id' => $this->createUser(['role_name' => 'Customer']),
            'status' => ReservationStatus::Reserved->value,
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(10),
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'bill_currency' => 'VND',
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4, [
            'status' => 'Occupied',
        ]));

        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'currency' => 'VND',
            'quantity' => 1,
            'unit_price' => 120000,
            'line_total' => 120000,
            'status' => 'Ordered',
        ]);

        $this->makeCheckoutService()->lockBill(
            orderId: $orderId,
            discountAmount: null,
            notes: 'bill lock before stale cancel',
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );

        self::assertSame(2, (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'));
        self::assertNotNull(DB::table('reservations')->where('reservation_id', $reservationId)->value('billed_at'));

        $response = $this->withHeaders($this->withIdempotencyKey('status-cancel-stale-bill-lock-1', $this->staffAuthHeaders($staffId)))
            ->patchJson("/api/v1/reservations/{$reservationId}/status", [
                'status' => ReservationStatus::Cancelled->value,
                'row_version' => 1,
                'force' => true,
                'cancel_reason' => 'stale cancel should fail after bill lock',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('details.errors.row_version.0', 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.');

        self::assertSame(ReservationStatus::Reserved->value, (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
    }

    /**
     * @return array{customer_id:int,staff_id:int,reservation_id:int,order_id:int,user_voucher_id:int}
     */
    private function seedReservationWithVoucherAndLoyalty(string $status, bool $checkedIn): array
    {
        $tierId = $this->createLoyaltyTier();
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'current_tier_id' => $tierId,
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->ensureUserPoints($customerId, 300, $staffId);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => $status,
            'checked_in_at' => $checkedIn ? $this->nowUtc()->copy()->subMinutes(15) : null,
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'bill_currency' => 'VND',
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4, [
            'status' => $checkedIn ? 'Occupied' : 'Available',
        ]));

        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'currency' => 'VND',
            'quantity' => 1,
            'unit_price' => 200000,
            'line_total' => 200000,
            'status' => 'Ordered',
        ]);

        $voucherId = $this->createVoucher([
            'discount_type' => 'Fixed',
            'discount_value' => '50000.00',
            'min_spend' => '0.00',
        ]);
        $userVoucherId = $this->assignVoucher([
            'user_id' => $customerId,
            'voucher_id' => $voucherId,
        ]);

        $this->makeVoucherService()->applyVoucher(
            reservationId: $reservationId,
            userVoucherId: $userVoucherId,
            voucherCode: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        $this->makeLoyaltyService()->redeemReservationPoints(
            reservationId: $reservationId,
            points: 100,
            reason: 'cross_path_seed',
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        return [
            'customer_id' => $customerId,
            'staff_id' => $staffId,
            'reservation_id' => $reservationId,
            'order_id' => $orderId,
            'user_voucher_id' => $userVoucherId,
        ];
    }
}
