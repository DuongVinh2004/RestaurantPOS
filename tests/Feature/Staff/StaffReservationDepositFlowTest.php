<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffReservationDepositFlowTest extends TestCase
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

        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', true);
        config()->set('staff_auth.env_fallback_allowed_environments', ['testing']);
    }

    public function test_staff_can_preview_reservation_deposit_snapshot(): void
    {
        [$staffId, $reservationId] = $this->seedDepositReservation();

        $response = $this->withHeaders($this->staffHeaders($staffId, 'deposit-preview-staff-key'))
            ->getJson("/api/v1/staff/reservations/{$reservationId}/deposit-preview");

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'deposit_preview')
            ->assertJsonPath('data.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.deposit.status', 'Pending')
            ->assertJsonPath('data.deposit.required_amount', '100000.00')
            ->assertJsonPath('data.deposit.paid_amount', '0.00')
            ->assertJsonPath('data.deposit.outstanding_amount', '100000.00')
            ->assertJsonPath('data.deposit.can_accept_payment', true);
    }

    public function test_staff_can_capture_partial_deposit_payment(): void
    {
        [$staffId, $reservationId] = $this->seedDepositReservation();

        $response = $this->withHeaders($this->idempotentStaffHeaders($staffId, 'deposit-partial-staff-key', 'idem-deposit-pay-partial-1'))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'amount' => 40000,
                'currency' => 'VND',
                'transaction_code' => 'DEP-PARTIAL-1',
                'row_version' => 1,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'deposit_pay')
            ->assertJsonPath('data.payment.payment_type', 'Deposit')
            ->assertJsonPath('data.payment.status', 'Partial')
            ->assertJsonPath('data.deposit.status', 'Pending')
            ->assertJsonPath('data.deposit.paid_amount', '40000.00')
            ->assertJsonPath('data.deposit.outstanding_amount', '60000.00')
            ->assertJsonPath('data.deposit.payment_summary.deposit_captured', '40000.00');

        $this->assertSame(1, (int) $this->table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Deposit')->count());
        $this->assertSame(
            '40000.00',
            number_format((float) $this->table('reservations')->where('reservation_id', $reservationId)->value('deposit_paid_amount'), 2, '.', '')
        );
        $this->assertSame('Pending', (string) $this->table('reservations')->where('reservation_id', $reservationId)->value('deposit_status'));
    }

    public function test_staff_can_fully_pay_required_deposit_and_mark_reservation_paid(): void
    {
        [$staffId, $reservationId] = $this->seedDepositReservation();

        $response = $this->withHeaders($this->idempotentStaffHeaders($staffId, 'deposit-full-staff-key', 'idem-deposit-pay-full-1'))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'amount' => 100000,
                'currency' => 'VND',
                'transaction_code' => 'DEP-FULL-1',
                'row_version' => 1,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'Success')
            ->assertJsonPath('data.deposit.status', 'Paid')
            ->assertJsonPath('data.deposit.paid_amount', '100000.00')
            ->assertJsonPath('data.deposit.outstanding_amount', '0.00')
            ->assertJsonPath('data.deposit.can_accept_payment', false);

        $this->assertSame('Paid', (string) $this->table('reservations')->where('reservation_id', $reservationId)->value('deposit_status'));
    }

    public function test_staff_deposit_pay_rejects_over_collection(): void
    {
        [$staffId, $reservationId] = $this->seedDepositReservation();

        $response = $this->withHeaders($this->idempotentStaffHeaders($staffId, 'deposit-overpay-staff-key', 'idem-deposit-pay-over-1'))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'amount' => 120000,
                'currency' => 'VND',
                'transaction_code' => 'DEP-OVER-1',
                'row_version' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_staff_deposit_pay_rejects_stale_row_version(): void
    {
        [$staffId, $reservationId] = $this->seedDepositReservation();
        $this->table('reservations')->where('reservation_id', $reservationId)->update(['row_version' => 3]);

        $response = $this->withHeaders($this->idempotentStaffHeaders($staffId, 'deposit-stale-staff-key', 'idem-deposit-pay-stale-1'))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'amount' => 20000,
                'currency' => 'VND',
                'transaction_code' => 'DEP-STALE-1',
                'row_version' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['row_version']);
    }

    public function test_staff_deposit_pay_rejects_invalid_reservation_status(): void
    {
        [$staffId, $reservationId] = $this->seedDepositReservation([
            'status' => 'Cancelled',
        ]);

        $response = $this->withHeaders($this->idempotentStaffHeaders($staffId, 'deposit-invalid-status-staff-key', 'idem-deposit-pay-invalid-status-1'))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'amount' => 20000,
                'currency' => 'VND',
                'transaction_code' => 'DEP-INVALID-STATUS-1',
                'row_version' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    public function test_staff_deposit_pay_replays_same_idempotency_key_without_duplicate_payment(): void
    {
        [$staffId, $reservationId] = $this->seedDepositReservation();
        $headers = $this->idempotentStaffHeaders($staffId, 'deposit-idem-staff-key', 'idem-deposit-pay-replay-1');

        $first = $this->withHeaders($headers)
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'amount' => 50000,
                'currency' => 'VND',
                'transaction_code' => 'DEP-IDEM-1',
                'row_version' => 1,
            ]);

        $second = $this->withHeaders($headers)
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'amount' => 50000,
                'currency' => 'VND',
                'transaction_code' => 'DEP-IDEM-1',
                'row_version' => 1,
            ]);

        $first->assertOk()->assertJsonPath('data.payment.payment_type', 'Deposit');
        $second->assertOk()->assertJsonPath('data.payment.payment_type', 'Deposit');
        $this->assertSame(1, (int) $this->table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Deposit')->count());
        $this->assertSame(
            '50000.00',
            number_format((float) $this->table('reservations')->where('reservation_id', $reservationId)->value('deposit_paid_amount'), 2, '.', '')
        );
    }

    public function test_staff_deposit_pay_rejects_idempotency_key_longer_than_payment_storage_limit(): void
    {
        [$staffId, $reservationId] = $this->seedDepositReservation();

        $response = $this->withHeaders(
            $this->idempotentStaffHeaders(
                $staffId,
                'deposit-too-long-staff-key',
                str_repeat('d', Payment::IDEMPOTENCY_KEY_MAX_LENGTH + 1),
            ),
        )->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'amount' => 50000,
            'currency' => 'VND',
            'transaction_code' => 'DEP-IDEM-LONG-1',
            'row_version' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
        $this->assertSame(0, (int) $this->table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Deposit')->count());
    }

    public function test_refund_preview_sees_newly_captured_deposit_payment(): void
    {
        [$staffId, $reservationId] = $this->seedDepositReservation();

        $payResponse = $this->withHeaders($this->idempotentStaffHeaders($staffId, 'deposit-refund-preview-staff-key', 'idem-deposit-pay-preview-1'))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'amount' => 60000,
                'currency' => 'VND',
                'transaction_code' => 'DEP-REFUND-PREVIEW-1',
                'row_version' => 1,
            ]);
        $payResponse->assertOk();

        $preview = $this->withHeaders($this->staffHeaders($staffId, 'deposit-refund-preview-staff-key'))
            ->getJson("/api/v1/staff/reservations/{$reservationId}/refund-preview");

        $preview
            ->assertOk()
            ->assertJsonPath('meta.action', 'refund_preview')
            ->assertJsonPath('data.refund.cancelled', true)
            ->assertJsonPath('data.refund.refund_scope', 'all')
            ->assertJsonPath('data.refund.payment_summary.deposit_captured', '60000.00')
            ->assertJsonPath('data.refund.payment_summary.deposit_net', '60000.00')
            ->assertJsonPath('data.refund.refund_amount', '60000.00');
    }

    public function test_refund_preview_accepts_scope_and_partial_amount_using_execute_semantics(): void
    {
        [$staffId, $reservationId] = $this->seedDepositReservation();

        $this->withHeaders($this->idempotentStaffHeaders($staffId, 'deposit-refund-preview-partial-staff-key', 'idem-deposit-pay-preview-partial-1'))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'amount' => 60000,
                'currency' => 'VND',
                'transaction_code' => 'DEP-REFUND-PREVIEW-PARTIAL-1',
                'row_version' => 1,
            ])->assertOk();

        $preview = $this->withHeaders($this->staffHeaders($staffId, 'deposit-refund-preview-partial-staff-key'))
            ->getJson("/api/v1/staff/reservations/{$reservationId}/refund-preview?refund_scope=deposit&refund_amount=20000&cancel_after_payment=1");

        $preview
            ->assertOk()
            ->assertJsonPath('meta.action', 'refund_preview')
            ->assertJsonPath('data.refund.refund_scope', 'deposit')
            ->assertJsonPath('data.refund.cancelled', true)
            ->assertJsonPath('data.refund.refund_amount', '20000.00')
            ->assertJsonPath('data.refund.payment_summary.deposit_net', '60000.00');
    }

    public function test_refund_preview_rejects_refund_only_preview_for_confirmed_reservation(): void
    {
        [$staffId, $reservationId] = $this->seedDepositReservation();

        $this->withHeaders($this->idempotentStaffHeaders($staffId, 'deposit-refund-preview-invalid-staff-key', 'idem-deposit-pay-preview-invalid-1'))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'amount' => 30000,
                'currency' => 'VND',
                'transaction_code' => 'DEP-REFUND-PREVIEW-INVALID-1',
                'row_version' => 1,
            ])->assertOk();

        $preview = $this->withHeaders($this->staffHeaders($staffId, 'deposit-refund-preview-invalid-staff-key'))
            ->getJson("/api/v1/staff/reservations/{$reservationId}/refund-preview?refund_scope=deposit&refund_amount=10000&cancel_after_payment=0");

        $preview
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.reservation.0', 'Only Completed reservations can be refunded without cancellation.');
    }

    public function test_refund_preview_rejects_branch_drifted_reservation_assignment(): void
    {
        [$staffId, $reservationId] = $this->seedDepositReservation();
        $annexBranchId = $this->createBranch([
            'branch_code' => 'ANNEXREFPRE',
            'branch_name' => 'Annex Refund Preview',
        ]);

        $this->withHeaders($this->idempotentStaffHeaders($staffId, 'deposit-refund-preview-branch-staff-key', 'idem-deposit-pay-preview-branch-1'))
            ->postJson("/api/v1/staff/reservations/{$reservationId}/deposit/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'amount' => 30000,
                'currency' => 'VND',
                'transaction_code' => 'DEP-REFUND-PREVIEW-BRANCH-1',
                'row_version' => 1,
            ])->assertOk();

        $this->table('reservations')
            ->where('reservation_id', $reservationId)
            ->update(['branch_id' => $annexBranchId]);

        $preview = $this->withHeaders($this->staffHeaders($staffId, 'deposit-refund-preview-branch-staff-key'))
            ->getJson("/api/v1/staff/reservations/{$reservationId}/refund-preview");

        $preview
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.reservation_id.0', 'Reservation branch does not match its assigned tables.');
    }

    /**
     * @param  array<string,mixed>  $reservationOverrides
     * @return array{0:int,1:int}
     */
    private function seedDepositReservation(array $reservationOverrides = []): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Reserved']);
        $reservationId = $this->createReservation(array_merge([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ], $reservationOverrides));
        $this->attachReservationTable($reservationId, $tableId);

        return [$staffId, $reservationId];
    }

    /**
     * @return array<string,string>
     */
    private function staffHeaders(int $staffId, string $apiKey): array
    {
        return $this->staffAuthHeaders($staffId, $apiKey);
    }

    /**
     * @return array<string,string>
     */
    private function idempotentStaffHeaders(int $staffId, string $apiKey, string $idempotencyKey): array
    {
        return $this->staffHeaders($staffId, $apiKey) + [
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    private function table(string $table)
    {
        return DB::table($table);
    }
}
