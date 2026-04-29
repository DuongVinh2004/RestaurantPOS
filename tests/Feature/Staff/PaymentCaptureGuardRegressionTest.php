<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class PaymentCaptureGuardRegressionTest extends TestCase
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
        Cache::store('redis')->getStore()->flush();
    }

    public function test_pay_persists_bill_snapshot_before_completion(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();
        $this->openCashierShiftForReservationBranch($staffId, $reservationId);

        $this->postJson('/api/v1/staff/orders/'.$orderId.'/pay', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 50000,
            'currency' => 'VND',
            'transaction_code' => 'PAY-SNAPSHOT-COMPLETE-1',
            'row_version' => 1,
        ], $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'payment-capture-snapshot'), 'idem-payment-snapshot-complete'))
            ->assertOk()
            ->assertJsonPath('data.status', 'Completed');

        $reservation = DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->first(['status', 'final_bill_amount', 'bill_currency', 'billed_at', 'checked_out_at']);

        self::assertSame('Completed', (string) $reservation->status);
        self::assertSame('50000.00', number_format((float) $reservation->final_bill_amount, 2, '.', ''));
        self::assertSame('VND', (string) $reservation->bill_currency);
        self::assertNotNull($reservation->billed_at);
        self::assertNotNull($reservation->checked_out_at);
    }

    public function test_pay_replay_same_idempotency_does_not_duplicate_payment_or_mutate_snapshot(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();
        $this->openCashierShiftForReservationBranch($staffId, $reservationId);
        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'payment-capture-idem'), 'idem-payment-capture-replay');
        $payload = [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 50000,
            'currency' => 'VND',
            'transaction_code' => 'PAY-CAPTURE-IDEM-1',
            'row_version' => 1,
        ];

        $first = $this->postJson('/api/v1/staff/orders/'.$orderId.'/pay', $payload, $headers);
        $snapshotAfterFirst = DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->first(['final_bill_amount', 'bill_currency', 'billed_at', 'checked_out_at']);
        $second = $this->postJson('/api/v1/staff/orders/'.$orderId.'/pay', $payload, $headers);
        $snapshotAfterReplay = DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->first(['final_bill_amount', 'bill_currency', 'billed_at', 'checked_out_at']);

        $first->assertOk()->assertHeader('Idempotency-Replayed', 'false');
        $second->assertOk()->assertHeader('Idempotency-Replayed', 'true');

        self::assertSame(
            1,
            (int) DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Final')
                ->where('idempotency_key', 'idem-payment-capture-replay')
                ->count()
        );
        self::assertSame('50000.00', number_format((float) DB::table('payments')->where('reservation_id', $reservationId)->value('amount'), 2, '.', ''));
        self::assertEquals($snapshotAfterFirst, $snapshotAfterReplay);
    }

    public function test_pay_idempotency_mismatch_is_rejected(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();
        $this->openCashierShiftForReservationBranch($staffId, $reservationId);
        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'payment-capture-idem-mismatch'), 'idem-payment-capture-mismatch');
        $payload = [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 50000,
            'currency' => 'VND',
            'transaction_code' => 'PAY-CAPTURE-MISMATCH-1',
            'row_version' => 1,
        ];

        $this->postJson('/api/v1/staff/orders/'.$orderId.'/pay', $payload, $headers)->assertOk();

        $this->postJson('/api/v1/staff/orders/'.$orderId.'/pay', array_merge($payload, [
            'paid_amount' => 40000,
            'transaction_code' => 'PAY-CAPTURE-MISMATCH-2',
        ]), $headers)
            ->assertStatus(409)
            ->assertJsonPath('error', 'idempotency_conflict');

        self::assertSame(
            1,
            (int) DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Final')
                ->where('idempotency_key', 'idem-payment-capture-mismatch')
                ->count()
        );
    }

    public function test_payment_requires_open_cashier_shift(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();

        $this->postJson('/api/v1/staff/orders/'.$orderId.'/pay', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 50000,
            'currency' => 'VND',
            'transaction_code' => 'PAY-NO-SHIFT-1',
            'row_version' => 1,
        ], $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'payment-capture-no-shift'), 'idem-payment-no-shift'))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['cashier_shift']);

        self::assertSame(0, (int) DB::table('payments')->where('reservation_id', $reservationId)->count());
    }

    public function test_payment_capture_uses_updated_order_item_line_total_after_quantity_edit(): void
    {
        [$staffId, $orderId, $reservationId, $orderItemId] = $this->seedActiveOrderScenario('Manager');

        $this->patchJson('/api/v1/staff/orders/'.$orderId.'/items/'.$orderItemId, [
            'order_row_version' => 1,
            'row_version' => 1,
            'qty' => 3,
        ], $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'payment-capture-updated-line-total'), 'idem-payment-line-total-update'))
            ->assertOk()
            ->assertJsonPath('data.items.0.line_total', '150000.00');

        $this->openCashierShiftForReservationBranch($staffId, $reservationId);
        $orderRowVersion = (int) DB::table('reservation_orders')->where('order_id', $orderId)->value('row_version');

        $this->postJson('/api/v1/staff/orders/'.$orderId.'/pay', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 50000,
            'currency' => 'VND',
            'transaction_code' => 'PAY-LINE-TOTAL-UPDATED-1',
            'row_version' => $orderRowVersion,
        ], $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'payment-capture-updated-line-total'), 'idem-payment-line-total-capture'))
            ->assertOk()
            ->assertJsonPath('data.total_amount', 150000)
            ->assertJsonPath('data.outstanding_amount', 100000)
            ->assertJsonPath('data.payment_status', 'Partial');

        self::assertSame('150000.00', number_format((float) DB::table('reservation_order_items')->where('order_item_id', $orderItemId)->value('line_total'), 2, '.', ''));
        self::assertSame('50000.00', number_format((float) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Final')->value('amount'), 2, '.', ''));
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function seedActiveOrderScenario(string $staffRoleName = 'Cashier'): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => $staffRoleName]);
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
        $orderItemId = $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $this->createMenuItem(),
            'quantity' => 1,
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '50000.00',
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        return [$staffId, $orderId, $reservationId, $orderItemId];
    }

    private function openCashierShiftForReservationBranch(int $staffId, int $reservationId): void
    {
        $branchId = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId > 0 ? $branchId : 1,
            'status' => 'Open',
            'currency' => 'VND',
        ]);
    }
}
