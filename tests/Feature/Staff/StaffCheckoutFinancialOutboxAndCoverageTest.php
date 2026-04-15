<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\CheckoutPayments\Domain\Models\Payment;
use App\Modules\CheckoutPayments\Domain\ValueObjects\PaymentSummary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\Support\InteractsWithCheckoutOutbox;
use Tests\TestCase;

class StaffCheckoutFinancialOutboxAndCoverageTest extends TestCase
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

    public function test_loyalty_only_completed_reservation_refund_cancel_restores_points_and_outbox_state(): void
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

        $this->makeLoyaltyService()->redeemReservationPoints(
            reservationId: $reservationId,
            points: 20,
            reason: 'round5-loyalty-only',
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        $service = $this->makeCheckoutServiceWithOutbox();
        $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 100000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-COVERAGE-LOYALTY-ONLY',
            paymentProvider: 'Cash',
            notes: 'loyalty-only finalize',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-final-r5-coverage-loyalty-only',
        );

        $result = $service->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-R5-COVERAGE-LOYALTY-ONLY',
            paymentProvider: 'Cash',
            notes: 'loyalty-only refund cancel',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-r5-coverage-loyalty-only',
        );

        $reservation = $result['reservation']->fresh();
        $paymentSummary = PaymentSummary::fromPayments(Payment::query()->where('reservation_id', $reservationId)->get());

        $this->assertSame('Cancelled', (string) ($reservation->status->value ?? $reservation->status));
        $this->assertSame(0.0, (float) ($reservation->discount_amount ?? 0.0));
        $this->assertSame(120000.0, (float) ($reservation->final_bill_amount ?? 0.0));
        $this->assertSame(0.0, (float) ($paymentSummary['final_net_amount'] ?? 0.0));
        $this->assertSame(500, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));

        $reasons = DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->orderBy('txn_id')
            ->pluck('reason')
            ->map(static fn ($reason) => (string) $reason)
            ->all();

        $this->assertContains('redeem.apply:round5-loyalty-only', $reasons);
        $this->assertContains('redeem.release:cancelled_after_payment', $reasons);
        $this->assertContains('earn.completed', $reasons);
        $this->assertContains('earn.sync.refund', $reasons);
        $this->assertOutboxTemplateCounts($reservationId, [
            'checkout.completed' => 1,
            'reservation.cancelled' => 1,
            'payment.refunded' => 0,
        ]);
    }

    public function test_deposit_only_completed_reservation_refund_cancel_restores_payment_state_and_outbox(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
        $this->ensureUserPoints($customerId, 0, $staffId);

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
            'unit_price' => 120000,
            'line_total' => 120000,
            'status' => 'Ordered',
        ]);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-R5-COVERAGE-DEPOSIT-ONLY',
        ]);

        $service = $this->makeCheckoutServiceWithOutbox();
        $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 70000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-COVERAGE-DEPOSIT-ONLY',
            paymentProvider: 'Cash',
            notes: 'deposit-only finalize',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-final-r5-coverage-deposit-only',
        );

        $result = $service->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-R5-COVERAGE-DEPOSIT-ONLY',
            paymentProvider: 'Cash',
            notes: 'deposit-only refund cancel',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-r5-coverage-deposit-only',
        );

        $reservation = $result['reservation']->fresh();
        $paymentSummary = PaymentSummary::fromPayments(Payment::query()->where('reservation_id', $reservationId)->get());
        $reasons = DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->orderBy('txn_id')
            ->pluck('reason')
            ->map(static fn ($reason) => (string) $reason)
            ->all();

        $this->assertSame('Cancelled', (string) ($reservation->status->value ?? $reservation->status));
        $this->assertSame('Refunded', (string) ($reservation->deposit_status->value ?? $reservation->deposit_status));
        $this->assertSame(120000.0, (float) ($reservation->final_bill_amount ?? 0.0));
        $this->assertSame(0.0, (float) ($paymentSummary['deposit_net_amount'] ?? 0.0));
        $this->assertSame(0.0, (float) ($paymentSummary['final_net_amount'] ?? 0.0));
        $this->assertSame(0, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        $this->assertNotContains('redeem.release:cancelled_after_payment', $reasons);
        $this->assertContains('earn.completed', $reasons);
        $this->assertContains('earn.sync.refund', $reasons);
        $this->assertOutboxTemplateCounts($reservationId, [
            'checkout.completed' => 1,
            'reservation.cancelled' => 1,
            'payment.refunded' => 0,
        ]);
    }

    public function test_voucher_and_deposit_completed_reservation_refund_cancel_restores_voucher_and_deposit_state(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
        $this->ensureUserPoints($customerId, 0, $staffId);

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
            'unit_price' => 200000,
            'line_total' => 200000,
            'status' => 'Ordered',
        ]);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-R5-COVERAGE-VOUCHER-DEPOSIT',
        ]);

        $voucherId = $this->createVoucher([
            'discount_type' => 'Fixed',
            'discount_value' => '30000.00',
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
            paidAmount: 120000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-COVERAGE-VOUCHER-DEPOSIT',
            paymentProvider: 'Cash',
            notes: 'voucher+deposit finalize',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-final-r5-coverage-voucher-deposit',
        );

        $result = $service->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-R5-COVERAGE-VOUCHER-DEPOSIT',
            paymentProvider: 'Cash',
            notes: 'voucher+deposit refund cancel',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-r5-coverage-voucher-deposit',
        );

        $reservation = $result['reservation']->fresh();
        $paymentSummary = PaymentSummary::fromPayments(Payment::query()->where('reservation_id', $reservationId)->get());
        $userVoucher = DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->first();
        $reasons = DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->orderBy('txn_id')
            ->pluck('reason')
            ->map(static fn ($reason) => (string) $reason)
            ->all();

        $this->assertSame('Cancelled', (string) ($reservation->status->value ?? $reservation->status));
        $this->assertNull($reservation->applied_user_voucher_id);
        $this->assertSame(0.0, (float) ($reservation->discount_amount ?? 0.0));
        $this->assertSame(200000.0, (float) ($reservation->final_bill_amount ?? 0.0));
        $this->assertSame(0.0, (float) ($paymentSummary['deposit_net_amount'] ?? 0.0));
        $this->assertSame(0.0, (float) ($paymentSummary['final_net_amount'] ?? 0.0));
        $this->assertSame(0, (int) ($userVoucher->is_used ?? 1));
        $this->assertNull($userVoucher->used_reservation_id);
        $this->assertNotContains('redeem.release:cancelled_after_payment', $reasons);
        $this->assertContains('earn.completed', $reasons);
        $this->assertContains('earn.sync.refund', $reasons);
        $this->assertOutboxTemplateCounts($reservationId, [
            'checkout.completed' => 1,
            'reservation.cancelled' => 1,
            'payment.refunded' => 0,
        ]);
    }

    public function test_loyalty_and_deposit_partial_refund_after_completed_reconciles_earn_without_releasing_redemption(): void
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
            'transaction_code' => 'DEP-R5-COVERAGE-LOYALTY-DEPOSIT',
        ]);

        $this->makeLoyaltyService()->redeemReservationPoints(
            reservationId: $reservationId,
            points: 100,
            reason: 'round5-loyalty-deposit',
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        $service = $this->makeCheckoutServiceWithOutbox();
        $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 100000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-COVERAGE-LOYALTY-DEPOSIT',
            paymentProvider: 'Cash',
            notes: 'loyalty+deposit finalize',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-final-r5-coverage-loyalty-deposit',
        );

        $result = $service->refundReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'final',
            refundAmount: 20000.00,
            currency: 'VND',
            transactionCode: 'RF-R5-COVERAGE-LOYALTY-DEPOSIT',
            paymentProvider: 'Cash',
            notes: 'loyalty+deposit partial refund',
            reason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-r5-coverage-loyalty-deposit',
        );

        $reservation = $result['reservation']->fresh();
        $paymentSummary = PaymentSummary::fromPayments(Payment::query()->where('reservation_id', $reservationId)->get());

        $this->assertSame('Completed', (string) ($reservation->status->value ?? $reservation->status));
        $this->assertSame(100000.0, (float) ($reservation->discount_amount ?? 0.0));
        $this->assertSame(150000.0, (float) ($reservation->final_bill_amount ?? 0.0));
        $this->assertSame(50000.0, (float) ($paymentSummary['deposit_net_amount'] ?? 0.0));
        $this->assertSame(80000.0, (float) ($paymentSummary['final_net_amount'] ?? 0.0));
        $this->assertSame(408, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
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

    public function test_voucher_and_loyalty_completed_reservation_refund_cancel_without_deposit_restores_points_and_voucher(): void
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
            reason: 'round5-voucher-loyalty',
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        $service = $this->makeCheckoutServiceWithOutbox();
        $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 50000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-COVERAGE-VOUCHER-LOYALTY',
            paymentProvider: 'Cash',
            notes: 'voucher+loyalty finalize',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-final-r5-coverage-voucher-loyalty',
        );

        $result = $service->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-R5-COVERAGE-VOUCHER-LOYALTY',
            paymentProvider: 'Cash',
            notes: 'voucher+loyalty refund cancel',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-r5-coverage-voucher-loyalty',
        );

        $reservation = $result['reservation']->fresh();
        $paymentSummary = PaymentSummary::fromPayments(Payment::query()->where('reservation_id', $reservationId)->get());
        $userVoucher = DB::table('user_vouchers')->where('user_voucher_id', $userVoucherId)->first();

        $this->assertSame('Cancelled', (string) ($reservation->status->value ?? $reservation->status));
        $this->assertNull($reservation->applied_user_voucher_id);
        $this->assertSame(0.0, (float) ($reservation->discount_amount ?? 0.0));
        $this->assertSame(200000.0, (float) ($reservation->final_bill_amount ?? 0.0));
        $this->assertSame(0.0, (float) ($paymentSummary['final_net_amount'] ?? 0.0));
        $this->assertSame(500, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        $this->assertSame(0, (int) ($userVoucher->is_used ?? 1));
        $this->assertNull($userVoucher->used_reservation_id);
        $this->assertOutboxTemplateCounts($reservationId, [
            'checkout.completed' => 1,
            'reservation.cancelled' => 1,
            'payment.refunded' => 0,
        ]);
    }


    public function test_refund_cancel_emits_reservation_cancelled_but_not_payment_refunded_outbox(): void
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
            'unit_price' => 90000,
            'line_total' => 90000,
            'status' => 'Ordered',
        ]);

        $service = $this->makeCheckoutServiceWithOutbox();
        $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 90000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-OUTBOX-CONTRACT-CANCEL',
            paymentProvider: 'Cash',
            notes: 'outbox cancel contract finalize',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-final-r5-outbox-contract-cancel',
        );

        $service->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-R5-OUTBOX-CONTRACT-CANCEL',
            paymentProvider: 'Cash',
            notes: 'outbox cancel contract refund',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-r5-outbox-contract-cancel',
        );

        $this->assertOutboxTemplateCounts($reservationId, [
            'checkout.completed' => 1,
            'reservation.cancelled' => 1,
            'payment.refunded' => 0,
        ]);
    }

    public function test_partial_payment_then_finalize_emits_single_checkout_outbox_and_settles_remaining_due(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(20),
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 2,
            'unit_price' => '100000.00',
            'currency' => 'VND',
            'line_total' => '200000.00',
        ]);

        $service = $this->makeCheckoutServiceWithOutbox();
        $service->payOrder(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 50000.00,
            currency: 'VND',
            transactionCode: 'PAY-R5-COVERAGE-PARTIAL',
            paymentProvider: 'Cash',
            notes: 'partial capture',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-pay-r5-coverage-partial',
        );

        $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 150000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-COVERAGE-PARTIAL',
            paymentProvider: 'Cash',
            notes: 'final settle after partial',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-final-r5-coverage-partial',
        );

        $paymentSummary = PaymentSummary::fromPayments(Payment::query()->where('reservation_id', $reservationId)->get());
        $this->assertSame('Completed', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        $this->assertSame(200000.0, (float) ($paymentSummary['final_net_amount'] ?? 0.0));
        $this->assertSame(2, (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Final')
            ->count());
        $this->assertOutboxTemplateCounts($reservationId, [
            'checkout.completed' => 1,
            'reservation.cancelled' => 0,
            'payment.refunded' => 0,
        ]);
    }

    public function test_refund_after_finalize_without_cancel_keeps_completed_status_and_emits_single_refund_outbox(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
        $this->ensureUserPoints($customerId, 0, $staffId);

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

        $service = $this->makeCheckoutServiceWithOutbox();
        $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 120000.00,
            currency: 'VND',
            transactionCode: 'FINAL-R5-COVERAGE-REFUND-NO-CANCEL',
            paymentProvider: 'Cash',
            notes: 'plain finalize',
            discountAmount: null,
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-final-r5-coverage-refund-no-cancel',
        );

        $result = $service->refundReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-R5-COVERAGE-REFUND-NO-CANCEL',
            paymentProvider: 'Cash',
            notes: 'plain refund without cancel',
            reason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-r5-coverage-refund-no-cancel',
        );

        $reservation = $result['reservation']->fresh();
        $paymentSummary = PaymentSummary::fromPayments(Payment::query()->where('reservation_id', $reservationId)->get());
        $reasons = DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->orderBy('txn_id')
            ->pluck('reason')
            ->map(static fn ($reason) => (string) $reason)
            ->all();

        $this->assertSame('Completed', (string) ($reservation->status->value ?? $reservation->status));
        $this->assertSame(0.0, (float) ($paymentSummary['final_net_amount'] ?? 0.0));
        $this->assertSame(0, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        $this->assertContains('earn.completed', $reasons);
        $this->assertContains('earn.sync.refund', $reasons);
        $this->assertOutboxTemplateCounts($reservationId, [
            'checkout.completed' => 1,
            'reservation.cancelled' => 0,
            'payment.refunded' => 1,
        ]);
    }
}
