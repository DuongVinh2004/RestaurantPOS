<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffCheckoutHttpGuardFlowTest extends TestCase
{
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

    public function test_pay_endpoint_allows_partial_payment_without_completing_order(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-http-pay-1'), 'idem-http-pay-1');
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
        ]);
        $this->openCashierShiftForReservationBranch($staffId, $reservationId);
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

        $response = $this->postJson('/api/v1/staff/orders/' . $orderId . '/pay', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 40000,
            'currency' => 'VND',
            'transaction_code' => 'HTTP-PARTIAL-1',
            'row_version' => 1,
        ], $headers);

        $response->assertOk()->assertJsonPath('data.order_id', $orderId);
        $this->assertSame('Active', (string) DB::table('reservation_orders')->where('order_id', $orderId)->value('status'));
        $this->assertSame('Reserved', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        $this->assertSame(1, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Final')->count());
        $this->assertSame('Partial', (string) DB::table('payments')->where('reservation_id', $reservationId)->value('status'));
    }

    public function test_pay_endpoint_rejects_overpay(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-http-pay-2'), 'idem-http-pay-2');
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation(['status' => 'Reserved']);
        $this->openCashierShiftForReservationBranch($staffId, $reservationId);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 1,
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '50000.00',
        ]);

        $response = $this->postJson('/api/v1/staff/orders/' . $orderId . '/pay', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 60000,
            'currency' => 'VND',
            'transaction_code' => 'HTTP-OVERPAY-1',
            'row_version' => 1,
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');
        $this->assertSame(0, (int) DB::table('payments')->where('reservation_id', $reservationId)->count());
    }

    public function test_bill_snapshot_rejects_stale_row_version(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-http-bill-1'), 'idem-http-bill-1');
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation(['status' => 'Reserved']);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 2,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 1,
            'unit_price' => '80000.00',
            'currency' => 'VND',
            'line_total' => '80000.00',
        ]);

        $response = $this->postJson('/api/v1/staff/orders/' . $orderId . '/bill-snapshot', [
            'row_version' => 1,
            'notes' => 'lock current bill',
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');
        $this->assertNull(DB::table('reservations')->where('reservation_id', $reservationId)->value('billed_at'));
    }

    public function test_finalize_endpoint_replays_same_idempotency_key_and_rejects_payload_conflict(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-http-finalize-1'), 'idem-http-finalize-1');
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation(['status' => 'Reserved']);
        $this->openCashierShiftForReservationBranch($staffId, $reservationId);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 2,
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '100000.00',
        ]);

        $payload = [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 100000,
            'currency' => 'VND',
            'transaction_code' => 'HTTP-FINALIZE-1',
            'row_version' => 1,
        ];

        $first = $this->postJson('/api/v1/staff/orders/' . $orderId . '/settlement/finalize', $payload, $headers);
        $first->assertOk()->assertHeader('Idempotency-Replayed', 'false');

        $second = $this->postJson('/api/v1/staff/orders/' . $orderId . '/settlement/finalize', $payload, $headers);
        $second->assertOk()->assertHeader('Idempotency-Replayed', 'true');

        $conflict = $this->postJson('/api/v1/staff/orders/' . $orderId . '/settlement/finalize', array_merge($payload, [
            'paid_amount' => 90000,
        ]), $headers);
        $conflict->assertStatus(409)
            ->assertJsonPath('error', 'idempotency_conflict');

        $this->assertSame(1, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('idempotency_key', 'idem-http-finalize-1')->count());
    }

    public function test_finalize_endpoint_rejects_idempotency_key_longer_than_payment_storage_limit(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId, 'staff-http-finalize-too-long'),
            str_repeat('f', Payment::IDEMPOTENCY_KEY_MAX_LENGTH + 1),
        );
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation(['status' => 'Reserved']);
        $this->openCashierShiftForReservationBranch($staffId, $reservationId);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 2,
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '100000.00',
        ]);

        $response = $this->postJson('/api/v1/staff/orders/' . $orderId . '/settlement/finalize', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 100000,
            'currency' => 'VND',
            'transaction_code' => 'HTTP-FINALIZE-LONG-IDEM-1',
            'row_version' => 1,
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
        $this->assertSame(0, (int) DB::table('payments')->where('reservation_id', $reservationId)->count());
    }

    private function openCashierShiftForReservationBranch(int $staffId, int $reservationId): void
    {
        $branchId = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId > 0 ? $branchId : 1,
            'status' => 'Open',
        ]);
    }
}
