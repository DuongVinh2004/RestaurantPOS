<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffCheckoutConcurrencyGuardFlowTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();
        config()->set('notifications.outbox.enabled', false);
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

    public function test_pay_endpoint_rejects_duplicate_transaction_code_for_same_provider_across_distinct_requests(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();

        $firstHeaders = $this->withIdempotencyKey('idem-pay-duplicate-a', $this->staffAuthHeaders($staffId, 'staff-pay-dup-1'));
        $secondHeaders = $this->withIdempotencyKey('idem-pay-duplicate-b', $this->staffAuthHeaders($staffId, 'staff-pay-dup-2'));

        $this->postJson('/api/v1/staff/orders/' . $orderId . '/pay', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 40000,
            'currency' => 'VND',
            'transaction_code' => 'PAY-DUP-SAME-PROVIDER',
            'row_version' => 1,
        ], $firstHeaders)->assertOk();

        $duplicate = $this->postJson('/api/v1/staff/orders/' . $orderId . '/pay', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 30000,
            'currency' => 'VND',
            'transaction_code' => 'PAY-DUP-SAME-PROVIDER',
            'row_version' => 1,
        ], $secondHeaders);

        $duplicate->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        self::assertSame(1, (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_provider', 'Cash')
            ->where('transaction_code', 'PAY-DUP-SAME-PROVIDER')
            ->count());
    }

    public function test_pay_endpoint_allows_same_transaction_code_when_provider_differs(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();

        $cashHeaders = $this->withIdempotencyKey('idem-pay-provider-cash', $this->staffAuthHeaders($staffId, 'staff-pay-provider-1'));
        $cardHeaders = $this->withIdempotencyKey('idem-pay-provider-card', $this->staffAuthHeaders($staffId, 'staff-pay-provider-2'));

        $this->postJson('/api/v1/staff/orders/' . $orderId . '/pay', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 40000,
            'currency' => 'VND',
            'transaction_code' => 'PAY-SHARED-CODE',
            'row_version' => 1,
        ], $cashHeaders)->assertOk();

        $second = $this->postJson('/api/v1/staff/orders/' . $orderId . '/pay', [
            'payment_method' => 'Card',
            'payment_provider' => 'Card',
            'paid_amount' => 30000,
            'currency' => 'VND',
            'transaction_code' => 'PAY-SHARED-CODE',
            'row_version' => 1,
        ], $cardHeaders);

        $second->assertOk()->assertJsonPath('data.order_id', $orderId);

        self::assertSame(2, (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('transaction_code', 'PAY-SHARED-CODE')
            ->count());
    }

    public function test_finalize_endpoint_rejects_second_non_replay_request_after_settlement_is_completed(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();

        $firstHeaders = $this->withIdempotencyKey('idem-finalize-first', $this->staffAuthHeaders($staffId, 'staff-finalize-1'));
        $secondHeaders = $this->withIdempotencyKey('idem-finalize-second', $this->staffAuthHeaders($staffId, 'staff-finalize-2'));

        $this->postJson('/api/v1/staff/orders/' . $orderId . '/settlement/finalize', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 100000,
            'currency' => 'VND',
            'transaction_code' => 'FINALIZE-ONCE-1',
            'row_version' => 1,
        ], $firstHeaders)->assertOk();

        $second = $this->postJson('/api/v1/staff/orders/' . $orderId . '/settlement/finalize', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 100000,
            'currency' => 'VND',
            'transaction_code' => 'FINALIZE-ONCE-2',
            'row_version' => 1,
        ], $secondHeaders);

        $second->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        self::assertSame('Completed', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame(1, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Final')->count());

        $paymentId = (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Final')
            ->value('payment_id');
        $log = $this->assertAuditLogRecorded('checkout.finalized', 'reservation', $reservationId);
        self::assertSame($staffId, $log->actor_user_id);
        self::assertSame('staff_user', $log->actor_type);
        $this->assertAuditSubjectRecorded($log, 'reservation_order', $orderId, 'order');
        $this->assertAuditSubjectRecorded($log, 'payment', $paymentId, 'payment');
    }

    public function test_finalize_mutation_bumps_versions_and_rejects_stale_refund_cancel_request(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();

        $finalizeHeaders = $this->withIdempotencyKey('idem-finalize-stale-race-a', $this->staffAuthHeaders($staffId, 'staff-finalize-stale-a'));
        $refundCancelHeaders = $this->withIdempotencyKey('idem-refund-cancel-stale-race-a', $this->staffAuthHeaders($staffId, 'staff-refund-cancel-stale-a'));

        $this->postJson('/api/v1/staff/orders/' . $orderId . '/settlement/finalize', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 100000,
            'currency' => 'VND',
            'transaction_code' => 'FINALIZE-STALE-RACE-1',
            'row_version' => 1,
        ], $finalizeHeaders)->assertOk();

        self::assertSame(2, (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'));
        self::assertSame(2, (int) DB::table('reservation_orders')->where('order_id', $orderId)->value('row_version'));

        $stale = $this->postJson('/api/v1/staff/reservations/' . $reservationId . '/refund-cancel', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'refund_scope' => 'all',
            'currency' => 'VND',
            'transaction_code' => 'REFUND-CANCEL-STALE-RACE-1',
            'row_version' => 1,
            'reason' => 'customer_request',
            'cancel_reason' => 'customer_request',
        ], $refundCancelHeaders);

        $stale->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('details.errors.row_version.0', 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.');

        self::assertSame('Completed', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame(1, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Final')->count());
        self::assertSame(0, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Refund')->count());
    }

    public function test_refund_cancel_accepts_fresh_row_version_after_finalize_mutation_and_bumps_version_again(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();

        $finalizeHeaders = $this->withIdempotencyKey('idem-finalize-stale-race-b', $this->staffAuthHeaders($staffId, 'staff-finalize-stale-b'));
        $refundCancelHeaders = $this->withIdempotencyKey('idem-refund-cancel-stale-race-b', $this->staffAuthHeaders($staffId, 'staff-refund-cancel-stale-b'));

        $this->postJson('/api/v1/staff/orders/' . $orderId . '/settlement/finalize', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 100000,
            'currency' => 'VND',
            'transaction_code' => 'FINALIZE-STALE-RACE-2',
            'row_version' => 1,
        ], $finalizeHeaders)->assertOk();

        $freshReservationVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');
        self::assertSame(2, $freshReservationVersion);

        $response = $this->postJson('/api/v1/staff/reservations/' . $reservationId . '/refund-cancel', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'refund_scope' => 'all',
            'currency' => 'VND',
            'transaction_code' => 'REFUND-CANCEL-STALE-RACE-2',
            'row_version' => $freshReservationVersion,
            'reason' => 'customer_request',
            'cancel_reason' => 'customer_request',
        ], $refundCancelHeaders);

        $response->assertOk()
            ->assertJsonPath('data.reservation.status', 'Cancelled')
            ->assertJsonPath('data.refund.cancelled', true);

        self::assertSame(3, (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'));
        self::assertSame(1, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Refund')->count());

        $log = $this->assertAuditLogRecorded('reservation.refund_cancelled', 'reservation', $reservationId);
        self::assertSame($staffId, $log->actor_user_id);
        self::assertSame('staff_user', $log->actor_type);
        self::assertSame('all', (string) data_get($log->summary_json, 'refund_scope'));
    }

    public function test_refund_endpoint_rejects_over_refund_when_distinct_idempotency_keys_target_same_source_payment(): void
    {
        [$staffId, $reservationId, $depositPaymentId] = $this->seedCompletedReservationWithDeposit();

        $firstHeaders = $this->withIdempotencyKey('idem-refund-source-contention-a', $this->staffAuthHeaders($staffId, 'staff-refund-source-contention-1'));
        $secondHeaders = $this->withIdempotencyKey('idem-refund-source-contention-b', $this->staffAuthHeaders($staffId, 'staff-refund-source-contention-2'));

        $this->postJson('/api/v1/staff/reservations/' . $reservationId . '/refund', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'refund_scope' => 'deposit',
            'refund_amount' => 70000,
            'currency' => 'VND',
            'transaction_code' => 'REFUND-SOURCE-CONTENTION-1',
            'row_version' => 1,
        ], $firstHeaders)->assertOk();

        $currentVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');
        self::assertGreaterThanOrEqual(1, $currentVersion);

        $overRefund = $this->postJson('/api/v1/staff/reservations/' . $reservationId . '/refund', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'refund_scope' => 'deposit',
            'refund_amount' => 40000,
            'currency' => 'VND',
            'transaction_code' => 'REFUND-SOURCE-CONTENTION-2',
            'row_version' => $currentVersion,
        ], $secondHeaders);

        $overRefund->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        self::assertSame(70000, (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Refund')
            ->where('refund_of_payment_id', $depositPaymentId)
            ->sum('amount'));

        self::assertSame(1, (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Refund')
            ->where('refund_of_payment_id', $depositPaymentId)
            ->count());

        $log = $this->assertAuditLogRecorded('payment.refunded', 'reservation', $reservationId);
        self::assertSame($staffId, $log->actor_user_id);
        self::assertSame('staff_user', $log->actor_type);
        self::assertSame('deposit', (string) data_get($log->summary_json, 'refund_scope'));
    }

    public function test_refund_endpoint_allows_second_distinct_request_only_for_remaining_balance_on_same_source_payment(): void
    {
        [$staffId, $reservationId, $depositPaymentId] = $this->seedCompletedReservationWithDeposit();

        $firstHeaders = $this->withIdempotencyKey('idem-refund-source-remaining-a', $this->staffAuthHeaders($staffId, 'staff-refund-source-remaining-1'));
        $secondHeaders = $this->withIdempotencyKey('idem-refund-source-remaining-b', $this->staffAuthHeaders($staffId, 'staff-refund-source-remaining-2'));
        $thirdHeaders = $this->withIdempotencyKey('idem-refund-source-remaining-c', $this->staffAuthHeaders($staffId, 'staff-refund-source-remaining-3'));

        $this->postJson('/api/v1/staff/reservations/' . $reservationId . '/refund', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'refund_scope' => 'deposit',
            'refund_amount' => 70000,
            'currency' => 'VND',
            'transaction_code' => 'REFUND-SOURCE-REMAINING-1',
            'row_version' => 1,
        ], $firstHeaders)->assertOk();

        $secondVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');
        self::assertGreaterThanOrEqual(1, $secondVersion);

        $this->postJson('/api/v1/staff/reservations/' . $reservationId . '/refund', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'refund_scope' => 'deposit',
            'refund_amount' => 30000,
            'currency' => 'VND',
            'transaction_code' => 'REFUND-SOURCE-REMAINING-2',
            'row_version' => $secondVersion,
        ], $secondHeaders)->assertOk();

        $thirdVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');
        self::assertGreaterThanOrEqual($secondVersion, $thirdVersion);

        $excess = $this->postJson('/api/v1/staff/reservations/' . $reservationId . '/refund', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'refund_scope' => 'deposit',
            'refund_amount' => 1000,
            'currency' => 'VND',
            'transaction_code' => 'REFUND-SOURCE-REMAINING-3',
            'row_version' => $thirdVersion,
        ], $thirdHeaders);

        $excess->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        self::assertSame(100000, (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Refund')
            ->where('refund_of_payment_id', $depositPaymentId)
            ->sum('amount'));

        self::assertSame(2, (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Refund')
            ->where('refund_of_payment_id', $depositPaymentId)
            ->count());
    }

    public function test_refund_endpoint_rejects_duplicate_transaction_code_for_same_provider_across_distinct_requests(): void
    {
        [$staffId, $reservationId, $depositPaymentId] = $this->seedCompletedReservationWithDeposit();

        $firstHeaders = $this->withIdempotencyKey('idem-refund-duplicate-a', $this->staffAuthHeaders($staffId, 'staff-refund-dup-1'));
        $secondHeaders = $this->withIdempotencyKey('idem-refund-duplicate-b', $this->staffAuthHeaders($staffId, 'staff-refund-dup-2'));

        $this->postJson('/api/v1/staff/reservations/' . $reservationId . '/refund', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'refund_scope' => 'deposit',
            'refund_amount' => 40000,
            'currency' => 'VND',
            'transaction_code' => 'REFUND-DUP-SAME-PROVIDER',
            'row_version' => 1,
        ], $firstHeaders)->assertOk();

        $currentVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');
        if ($currentVersion <= 0) {
            $currentVersion = 1;
        }

        $duplicate = $this->postJson('/api/v1/staff/reservations/' . $reservationId . '/refund', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'refund_scope' => 'deposit',
            'refund_amount' => 10000,
            'currency' => 'VND',
            'transaction_code' => 'REFUND-DUP-SAME-PROVIDER',
            'row_version' => $currentVersion,
        ], $secondHeaders);

        $duplicate->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        self::assertSame(1, (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Refund')
            ->where('payment_provider', 'Cash')
            ->where('transaction_code', 'like', 'REFUND-DUP-SAME-PROVIDER.%')
            ->count());
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function seedActiveOrderScenario(): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
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
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '100000.00',
        ]);

        return [$staffId, $orderId, $reservationId];
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function seedCompletedReservationWithDeposit(): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Completed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '100000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
            'row_version' => 1,
        ]);
        $depositPaymentId = $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '100000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-CONCURRENCY-1',
            'payment_provider' => 'Cash',
        ]);

        return [$staffId, $reservationId, $depositPaymentId];
    }
}
