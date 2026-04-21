<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\Support\InteractsWithCheckoutOutbox;
use Tests\TestCase;

class StaffCheckoutFinancialIntegrationMatrixTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;
    use InteractsWithCheckoutOutbox;

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

    public function test_refund_cancel_after_completed_settlement_restores_loyalty_voucher_and_payment_state(): void
    {
        $tierId = $this->createLoyaltyTier();
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'current_tier_id' => $tierId,
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
        $this->ensureUserPoints($customerId, 500, $staffId);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(20),
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'currency' => 'VND',
            'quantity' => 1,
            'unit_price' => 250000,
            'line_total' => 250000,
            'status' => 'Ordered',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-R5-MATRIX-1',
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
            reason: 'round5',
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        $service = $this->makeCheckoutServiceWithOutbox();

        $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 50000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-MATRIX-1',
            paymentProvider: 'Cash',
            notes: 'finalize matrix',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-final-r5-matrix-1',
        );

        $result = $service->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-R5-MATRIX-1',
            paymentProvider: 'Cash',
            notes: 'refund cancel matrix',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-r5-matrix-1',
        );

        $reservation = $result['reservation']->fresh();
        $paymentSummary = PaymentSummary::fromPayments(Payment::query()->where('reservation_id', $reservationId)->get());
        $userVoucher = DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->first();

        $this->assertSame('Cancelled', (string) ($reservation->status->value ?? $reservation->status));
        $this->assertNull($reservation->applied_user_voucher_id);
        $this->assertSame(0.0, (float) ($reservation->discount_amount ?? 0.0));
        $this->assertSame(250000.0, (float) ($reservation->final_bill_amount ?? 0.0));
        $this->assertSame(0.0, (float) ($paymentSummary['deposit_net_amount'] ?? 0.0));
        $this->assertSame(0.0, (float) ($paymentSummary['final_net_amount'] ?? 0.0));
        $this->assertSame(500, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        $this->assertSame(0, (int) ($userVoucher->is_used ?? 1));
        $this->assertNull($userVoucher->used_reservation_id);
        $this->assertNull($userVoucher->used_amount);
        $this->assertNull($userVoucher->lock_token);
        $this->assertNull($userVoucher->locked_until);

        $reasons = DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->orderBy('txn_id')
            ->pluck('reason')
            ->map(static fn ($reason) => (string) $reason)
            ->all();

        $this->assertContains('redeem.apply:round5', $reasons);
        $this->assertContains('earn.completed', $reasons);
        $this->assertContains('redeem.release:cancelled_after_payment', $reasons);
        $this->assertContains('earn.sync.refund', $reasons);
        $this->assertOutboxTemplateCounts($reservationId, [
            'checkout.completed' => 1,
            'reservation.cancelled' => 1,
            'payment.refunded' => 0,
        ]);
    }

    public function test_partial_refund_after_completed_settlement_keeps_voucher_and_loyalty_redemption_but_reconciles_earn_points(): void
    {
        $tierId = $this->createLoyaltyTier();
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'current_tier_id' => $tierId,
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
        $this->ensureUserPoints($customerId, 500, $staffId);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(20),
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'currency' => 'VND',
            'quantity' => 1,
            'unit_price' => 250000,
            'line_total' => 250000,
            'status' => 'Ordered',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-R5-MATRIX-2',
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
            reason: 'round5',
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        $service = $this->makeCheckoutServiceWithOutbox();

        $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 50000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-MATRIX-2',
            paymentProvider: 'Cash',
            notes: 'finalize matrix',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-final-r5-matrix-2',
        );

        $result = $service->refundReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'final',
            refundAmount: 20000.00,
            currency: 'VND',
            transactionCode: 'RF-R5-MATRIX-2',
            paymentProvider: 'Cash',
            notes: 'partial refund matrix',
            reason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-r5-matrix-2',
        );

        $reservation = $result['reservation']->fresh();
        $paymentSummary = PaymentSummary::fromPayments(Payment::query()->where('reservation_id', $reservationId)->get());
        $userVoucher = DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->first();

        $this->assertSame('Completed', (string) ($reservation->status->value ?? $reservation->status));
        $this->assertSame($userVoucherId, (int) ($reservation->applied_user_voucher_id ?? 0));
        $this->assertSame(150000.0, (float) ($reservation->discount_amount ?? 0.0));
        $this->assertSame(100000.0, (float) ($reservation->final_bill_amount ?? 0.0));
        $this->assertSame(50000.0, (float) ($paymentSummary['deposit_net_amount'] ?? 0.0));
        $this->assertSame(30000.0, (float) ($paymentSummary['final_net_amount'] ?? 0.0));
        $this->assertSame(403, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        $this->assertSame(1, (int) ($userVoucher->is_used ?? 0));
        $this->assertSame($reservationId, (int) ($userVoucher->used_reservation_id ?? 0));
        $this->assertNull($userVoucher->lock_token);
        $this->assertNull($userVoucher->locked_until);

        $this->assertSame(100, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('txn_type', 'Redeem')
            ->sum(DB::raw('ABS(points)')));

        $this->assertSame(-2, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'earn.sync.refund')
            ->sum('points'));
        $this->assertOutboxTemplateCounts($reservationId, [
            'checkout.completed' => 1,
            'reservation.cancelled' => 0,
            'payment.refunded' => 1,
        ]);
    }

    public function test_refund_cancel_allows_confirmed_reservation_with_existing_deposit_payment(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '100000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '100000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-R5-MATRIX-3',
        ]);

        $result = $this->makeCheckoutService()->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-R5-MATRIX-3',
            paymentProvider: 'Cash',
            notes: 'confirmed refund cancel',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-r5-matrix-3',
        );

        $reservation = $result['reservation']->fresh();
        $paymentSummary = PaymentSummary::fromPayments(Payment::query()->where('reservation_id', $reservationId)->get());

        $this->assertSame('Cancelled', (string) ($reservation->status->value ?? $reservation->status));
        $this->assertSame('Refunded', (string) ($reservation->deposit_status->value ?? $reservation->deposit_status));
        $this->assertSame(0.0, (float) ($reservation->deposit_paid_amount ?? 0.0));
        $this->assertSame(0.0, (float) ($paymentSummary['deposit_net_amount'] ?? 0.0));
    }

    public function test_voucher_only_completed_reservation_refund_cancel_restores_voucher_without_loyalty_side_effects(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(20),
            'bill_currency' => 'VND',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'currency' => 'VND',
            'quantity' => 1,
            'unit_price' => 120000,
            'line_total' => 120000,
            'status' => 'Ordered',
        ]);

        $voucherId = $this->createVoucher([
            'discount_type' => 'Fixed',
            'discount_value' => '20000.00',
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

        $service = $this->makeCheckoutServiceWithOutbox();

        $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 100000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-MATRIX-4',
            paymentProvider: 'Cash',
            notes: 'voucher-only finalize',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-final-r5-matrix-4',
        );

        $result = $service->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-R5-MATRIX-4',
            paymentProvider: 'Cash',
            notes: 'voucher-only refund cancel',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-r5-matrix-4',
        );

        $reservation = $result['reservation']->fresh();
        $paymentSummary = PaymentSummary::fromPayments(Payment::query()->where('reservation_id', $reservationId)->get());
        $userVoucher = DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->first();

        $this->assertSame('Cancelled', (string) ($reservation->status->value ?? $reservation->status));
        $this->assertNull($reservation->applied_user_voucher_id);
        $this->assertSame(0.0, (float) ($reservation->discount_amount ?? 0.0));
        $this->assertSame(120000.0, (float) ($reservation->final_bill_amount ?? 0.0));
        $this->assertSame(0.0, (float) ($paymentSummary['final_net_amount'] ?? 0.0));
        $this->assertSame(0, (int) ($userVoucher->is_used ?? 1));
        $this->assertNull($userVoucher->used_reservation_id);
        $reasons = DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->orderBy('txn_id')
            ->pluck('reason')
            ->map(static fn ($reason) => (string) $reason)
            ->all();
        $this->assertNotContains('redeem.release:cancelled_after_payment', $reasons);
        $this->assertNotContains('redeem.apply:round5', $reasons);
        $this->assertContains('earn.completed', $reasons);
        $this->assertContains('earn.sync.refund', $reasons);
        $this->assertOutboxTemplateCounts($reservationId, [
            'checkout.completed' => 1,
            'reservation.cancelled' => 1,
            'payment.refunded' => 0,
        ]);
    }


    public function test_refund_cancel_replays_same_idempotency_key_without_double_loyalty_or_voucher_release(): void
    {
        $tierId = $this->createLoyaltyTier();
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'current_tier_id' => $tierId,
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
        $this->ensureUserPoints($customerId, 300, $staffId);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(20),
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'bill_currency' => 'VND',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
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
            reason: 'round5-replay',
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        $service = $this->makeCheckoutServiceWithOutbox();

        $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 50000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-MATRIX-REPLAY-1',
            paymentProvider: 'Cash',
            notes: 'finalize matrix replay',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-final-r5-matrix-replay-1',
        );

        $first = $service->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-R5-MATRIX-REPLAY-1',
            paymentProvider: 'Cash',
            notes: 'refund cancel replay matrix',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-r5-matrix-replay-1',
        );

        $second = $service->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-R5-MATRIX-REPLAY-1',
            paymentProvider: 'Cash',
            notes: 'refund cancel replay matrix',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-r5-matrix-replay-1',
        );

        $this->assertSame(
            $first['refund']['refund_payment_ids'] ?? [],
            $second['refund']['refund_payment_ids'] ?? []
        );

        $this->assertSame(300, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        $this->assertSame(1, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'redeem.release:cancelled_after_payment')
            ->count());
        $this->assertSame(1, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'earn.sync.refund')
            ->count());
        $this->assertSame(1, (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Refund')
            ->count());

        $userVoucher = DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->first();
        $this->assertSame(0, (int) ($userVoucher->is_used ?? 1));
        $this->assertNull($userVoucher->used_reservation_id);
        $this->assertOutboxTemplateCounts($reservationId, [
            'checkout.completed' => 1,
            'reservation.cancelled' => 1,
            'payment.refunded' => 0,
        ]);
    }


    public function test_finalize_replays_same_idempotency_key_without_double_loyalty_earn_or_voucher_consume(): void
    {
        $tierId = $this->createLoyaltyTier();
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'current_tier_id' => $tierId,
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
        $this->ensureUserPoints($customerId, 300, $staffId);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(20),
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'bill_currency' => 'VND',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
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
            reason: 'round5-finalize-replay',
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        $service = $this->makeCheckoutServiceWithOutbox();
        $first = $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 50000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-MATRIX-FINALIZE-REPLAY-1',
            paymentProvider: 'Cash',
            notes: 'finalize replay matrix',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-finalize-r5-matrix-replay-1',
        );

        $second = $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 50000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-MATRIX-FINALIZE-REPLAY-1',
            paymentProvider: 'Cash',
            notes: 'finalize replay matrix',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-finalize-r5-matrix-replay-1',
        );

        $this->assertSame($first['order_id'] ?? null, $second['order_id'] ?? null);
        $this->assertSame('Completed', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        $this->assertSame(1, (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Final')
            ->where('idempotency_key', 'idem-finalize-r5-matrix-replay-1')
            ->count());
        $this->assertSame(1, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->whereIn('reason', ['earn.completed', 'earn.sync.complete'])
            ->count());

        $userVoucher = DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->first();
        $this->assertSame(1, (int) ($userVoucher->is_used ?? 0));
        $this->assertSame($reservationId, (int) ($userVoucher->used_reservation_id ?? 0));
        $this->assertOutboxTemplateCounts($reservationId, [
            'checkout.completed' => 1,
            'reservation.cancelled' => 0,
            'payment.refunded' => 0,
        ]);
    }


}
