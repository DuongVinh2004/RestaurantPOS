<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\Billing\Application\UseCases\Previews\CheckoutResponseFactory;
use App\Modules\Billing\Application\UseCases\Previews\SettlementAmountCalculator;
use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\Cashiering\Application\UseCases\Shifts\StaffCashierShiftService;
use App\Modules\Cashiering\Application\Workflows\OrderSettlementWorkflow;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Payments\Application\UseCases\Capture\PaymentCaptureService;
use App\Modules\Payments\Application\UseCases\Refunds\RefundExecutionService;
use App\Modules\Payments\Application\UseCases\Refunds\RefundPlannerService;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class StaffMoneyActorBoundaryServiceTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_checkout_staff_service_without_actor_fails_closed(): void
    {
        [, $orderId] = $this->seedActiveOrderScenario();

        $this->assertMissingStaffActorFails(fn (): array => app(OrderSettlementWorkflow::class)->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 100000.0,
            currency: 'VND',
            transactionCode: 'NO-ACTOR-CHECKOUT',
            paymentProvider: 'Cash',
            notes: 'missing actor',
            expectedRowVersion: 1,
            staffUserId: null,
            idempotencyKey: 'idem-no-actor-checkout',
        ));
    }

    public function test_payment_finalize_without_actor_fails_closed(): void
    {
        $service = new PaymentCaptureService(
            new SettlementAmountCalculator,
            new CheckoutResponseFactory(new SettlementAmountCalculator),
            Mockery::mock(NotificationOutboxService::class),
        );

        $order = new ReservationOrder([
            'reservation_id' => 456,
            'status' => 'Active',
            'order_type' => 'OnSpot',
        ]);
        $order->setAttribute('order_id', 123);

        $reservation = new Reservation([
            'branch_id' => 1,
            'status' => 'Reserved',
            'bill_currency' => 'VND',
        ]);
        $reservation->setAttribute('reservation_id', 456);

        $this->assertMissingStaffActorFails(fn (): ReservationOrder => $service->executeLocked(
            order: $order,
            reservation: $reservation,
            computeReservationBillSnapshot: fn (): never => $this->fail('Missing actor guard must run before bill snapshot computation.'),
            findExistingOrderPaymentReplay: fn (): never => $this->fail('Missing actor guard must run before replay lookup.'),
            paymentReplayCachePut: fn (): never => $this->fail('Missing actor guard must run before replay writes.'),
            isDuplicatePaymentIdempotencyConstraint: fn (): bool => false,
            throwIfDuplicatePaymentConstraint: fn (): null => null,
            completeReservationSettlement: fn (): never => $this->fail('Missing actor guard must run before settlement finalization.'),
            touchFinancialMutation: fn (): never => $this->fail('Missing actor guard must run before financial mutation touch.'),
            paymentMethod: 'Cash',
            paidAmount: 100000.0,
            currency: 'VND',
            transactionCode: 'NO-ACTOR-PAYMENT',
            paymentProvider: 'Cash',
            notes: 'missing actor',
            staffUserId: null,
            idempotencyKey: 'idem-no-actor-payment',
        ));
    }

    public function test_refund_without_actor_fails_closed(): void
    {
        [$reservationId] = $this->seedCompletedPaidReservation();

        $this->assertMissingStaffActorFails(fn (): array => app(OrderSettlementWorkflow::class)->refundReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'NO-ACTOR-REFUND',
            paymentProvider: 'Cash',
            notes: 'missing actor',
            reason: 'customer_request',
            expectedRowVersion: 1,
            staffUserId: null,
            idempotencyKey: 'idem-no-actor-refund',
        ));

        $this->assertMissingStaffActorFails(fn (): array => $this->makeRefundExecutionService()->executeLocked(
            reservation: $this->detachedReservationForRefund(),
            orders: new Collection,
            payments: new Collection,
            tableIds: [],
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            baseCurrency: 'VND',
            transactionCode: 'NO-ACTOR-REFUND-EXECUTION',
            paymentProvider: 'Cash',
            notes: 'missing actor',
            reason: 'customer_request',
            cancelAfterPayment: false,
            cancelReason: null,
            staffUserId: null,
            idempotencyKey: 'idem-no-actor-refund-execution',
            syncDepositSnapshot: fn (): never => $this->fail('Missing actor guard must run before refund snapshot sync.'),
            releaseAppliedVoucherLocked: fn (): never => $this->fail('Missing actor guard must run before voucher release.'),
            cancelReservationLocked: fn (): never => $this->fail('Missing actor guard must run before reservation cancellation.'),
            isDuplicatePaymentIdempotencyConstraint: fn (): bool => false,
            throwIfDuplicatePaymentConstraint: fn (): null => null,
        ));
    }

    public function test_refund_cancel_without_actor_fails_closed(): void
    {
        [$reservationId] = $this->seedCompletedPaidReservation();

        $this->assertMissingStaffActorFails(fn (): array => app(OrderSettlementWorkflow::class)->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'NO-ACTOR-REFUND-CANCEL',
            paymentProvider: 'Cash',
            notes: 'missing actor',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: 1,
            staffUserId: null,
            idempotencyKey: 'idem-no-actor-refund-cancel',
        ));
    }

    public function test_cashier_shift_mutation_without_actor_fails_closed(): void
    {
        $staffId = $this->createUser(['role_name' => 'Cashier']);
        $shiftId = $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => 1,
            'status' => 'Open',
            'currency' => 'VND',
            'row_version' => 1,
        ]);

        /** @var StaffCashierShiftService $service */
        $service = app(StaffCashierShiftService::class);

        $this->assertMissingStaffActorFails(fn () => $service->closeShift(
            shiftId: $shiftId,
            actualCashAmount: 0.0,
            expectedRowVersion: 1,
            closingNote: 'missing actor',
            closedBy: null,
            cashierUserId: null,
        ));

        $this->assertMissingStaffActorFails(fn () => $service->openShift(
            cashierUserId: 0,
            openingFloatAmount: 0.0,
            currency: 'VND',
        ));

        self::assertSame('Open', (string) DB::table('cashier_shifts')->where('cashier_shift_id', $shiftId)->value('status'));
    }

    public function test_authorized_staff_payment_still_succeeds(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario('Cashier');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => 1,
            'status' => 'Open',
            'currency' => 'VND',
        ]);

        $order = app(OrderSettlementWorkflow::class)->payOrder(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 100000.0,
            currency: 'VND',
            transactionCode: 'AUTHORIZED-PAYMENT-1',
            paymentProvider: 'Cash',
            notes: 'authorized payment',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-authorized-payment-1',
        );

        self::assertSame('Completed', (string) ($order->status?->value ?? $order->status));
        self::assertSame(1, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Final')->count());
    }

    public function test_authorized_staff_refund_still_succeeds(): void
    {
        [$reservationId, $staffId] = $this->seedCompletedPaidReservation();
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => 1,
            'status' => 'Open',
            'currency' => 'VND',
        ]);

        $result = app(OrderSettlementWorkflow::class)->refundReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'AUTHORIZED-REFUND-1',
            paymentProvider: 'Cash',
            notes: 'authorized refund',
            reason: 'customer_request',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-authorized-refund-1',
        );

        self::assertSame('Completed', (string) ($result['reservation']->status?->value ?? $result['reservation']->status));
        self::assertSame(1, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Refund')->count());
    }

    private function assertMissingStaffActorFails(callable $callback): void
    {
        try {
            $callback();

            $this->fail('Expected missing staff actor validation failure was not thrown.');
        } catch (ValidationException $e) {
            self::assertSame(
                ['Authenticated staff actor is required.'],
                $e->errors()['staff_user_id'] ?? []
            );
        }
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function seedActiveOrderScenario(string $staffRole = 'Staff'): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => $staffRole]);
        $tableId = $this->createRestaurantTable([
            'branch_id' => 1,
            'status' => 'Occupied',
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => 1,
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '0',
            'deposit_paid_amount' => '0',
            'deposit_status' => 'NotRequired',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 2,
            'unit_price' => '50000',
            'currency' => 'VND',
            'line_total' => '100000',
        ]);

        return [$staffId, $orderId, $reservationId];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function seedCompletedPaidReservation(): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Cashier']);
        $reservationId = $this->createReservation([
            'branch_id' => 1,
            'user_id' => $customerId,
            'status' => 'Completed',
            'deposit_required_amount' => '0',
            'deposit_paid_amount' => '0',
            'deposit_status' => 'NotRequired',
            'final_bill_amount' => '100000',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
        ]);
        $this->createPayment([
            'branch_id' => 1,
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '100000',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'transaction_code' => 'AUTHORIZED-REFUND-SOURCE-1',
        ]);

        return [$reservationId, $staffId];
    }

    private function makeRefundExecutionService(): RefundExecutionService
    {
        $financialSync = new ReservationFinancialSyncService;

        return new RefundExecutionService(
            new RefundPlannerService(new SettlementAmountCalculator),
            new SettlementAmountCalculator,
            new LoyaltyPointsService($financialSync, $this->mockRuntimeSettings()),
        );
    }

    private function detachedReservationForRefund(): Reservation
    {
        $reservation = new Reservation([
            'branch_id' => 1,
            'status' => 'Completed',
            'bill_currency' => 'VND',
        ]);
        $reservation->setAttribute('reservation_id', 999);

        return $reservation;
    }
}
