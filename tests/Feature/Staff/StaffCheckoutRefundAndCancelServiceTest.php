<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\Cashiering\Application\Workflows\OrderSettlementWorkflow;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffCheckoutRefundAndCancelServiceTest extends TestCase
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

    public function test_refund_cancel_requires_at_least_one_existing_payment(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '200000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
        ]);

        $service = $this->makeCheckoutService();

        try {
            $service->refundAndCancelReservation(
                reservationId: $reservationId,
                paymentMethod: 'Cash',
                refundScope: 'all',
                refundAmount: null,
                currency: 'VND',
                transactionCode: 'RF-NO-PAY',
                paymentProvider: 'Cash',
                notes: 'test',
                reason: 'customer_request',
                cancelReason: 'customer_request',
                expectedRowVersion: 1,
                staffUserId: $staffId,
                idempotencyKey: 'idem-refund-no-payment'
            );

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('refund_amount', $errors);
            $this->assertStringContainsString('cancel-after-payment', $errors['refund_amount'][0]);
        }
    }

    public function test_cancel_after_payment_clears_stale_lifecycle_timestamps(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(30),
            'checked_out_at' => $this->nowUtc()->copy()->subMinutes(5),
            'no_show_at' => $this->nowUtc()->copy()->subHour(),
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '100000.00',
            'deposit_status' => 'Paid',
        ]);
        $this->attachReservationTable($reservationId);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '100000.00',
            'transaction_code' => 'DEP-PAID-1',
        ]);

        $service = $this->makeCheckoutService();
        $result = $service->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-CANCEL-1',
            paymentProvider: 'Cash',
            notes: 'cancel with refund',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-refund-cancel-1'
        );

        $reservation = $result['reservation']->fresh();

        $this->assertSame('Cancelled', (string) ($reservation->status->value ?? $reservation->status));
        $this->assertNull($reservation->checked_in_at);
        $this->assertNull($reservation->checked_out_at);
        $this->assertNull($reservation->no_show_at);
        $this->assertNotNull($reservation->cancelled_at);
    }

    public function test_cancel_after_payment_release_audit_includes_actor_and_context(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '100000.00',
            'deposit_status' => 'Paid',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '100000.00',
            'transaction_code' => 'DEP-AUDIT-1',
        ]);

        $service = $this->makeCheckoutServiceWithRealTableState();
        $service->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-CANCEL-AUDIT-1',
            paymentProvider: 'Cash',
            notes: 'cancel with refund',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-refund-cancel-audit-1'
        );

        $auditRow = DB::table('audit_logs')
            ->where('entity_type', 'restaurant_table')
            ->where('entity_id', (string) $tableId)
            ->where('action', 'table_state_released')
            ->orderByDesc('audit_id')
            ->first();

        $this->assertNotNull($auditRow);
        $this->assertSame($staffId, (int) ($auditRow->actor_user_id ?? 0));

        $afterPayload = json_decode((string) ($auditRow->after_json ?? ''), true, 512, JSON_THROW_ON_ERROR);
        $metaPayload = json_decode((string) ($auditRow->meta_json ?? ''), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($reservationId, $afterPayload['context']['reservation_id'] ?? null);
        $this->assertSame('staff_checkout_refund', $afterPayload['context']['source'] ?? null);
        $this->assertSame('refund_cancel_after_payment', $afterPayload['context']['reason'] ?? null);
        $this->assertSame($reservationId, $metaPayload['context']['reservation_id'] ?? null);
        $this->assertSame('staff_checkout_refund', $metaPayload['context']['source'] ?? null);
        $this->assertSame('Available', $afterPayload['status'] ?? null);
    }

    private function makeCheckoutServiceWithRealTableState(): OrderSettlementWorkflow
    {
        $financialSync = new ReservationFinancialSyncService;
        $loyalty = new LoyaltyPointsService($financialSync, $this->mockRuntimeSettings());

        return new OrderSettlementWorkflow(
            $this->mockReservationLocks(),
            $this->mockNotificationOutbox(),
            $loyalty,
            new RestaurantTableStateService,
            $financialSync,
        );
    }
}
