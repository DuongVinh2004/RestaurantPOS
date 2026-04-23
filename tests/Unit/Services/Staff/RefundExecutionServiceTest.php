<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Staff;

use App\Enums\PaymentStatus;
use App\Modules\Billing\Application\UseCases\Previews\SettlementAmountCalculator;
use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Payments\Application\UseCases\Refunds\RefundExecutionService;
use App\Modules\Payments\Application\UseCases\Refunds\RefundPlannerService;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

final class RefundExecutionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_build_refund_plan_rejects_amount_exceeding_available_scope(): void
    {
        $planner = new RefundPlannerService(new SettlementAmountCalculator);
        $runtime = Mockery::mock(RuntimeSettingService::class);
        $runtime->shouldIgnoreMissing();
        $loyalty = new LoyaltyPointsService(new ReservationFinancialSyncService, $runtime);
        $service = new RefundExecutionService($planner, new SettlementAmountCalculator, $loyalty);

        $this->expectException(ValidationException::class);
        $service->buildRefundPlan('deposit', 120.0, ['deposit' => 100.0, 'final' => 0.0], false);
    }

    public function test_allocate_refund_payments_prefers_latest_captured_payment_first(): void
    {
        $planner = new RefundPlannerService(new SettlementAmountCalculator);
        $runtime = Mockery::mock(RuntimeSettingService::class);
        $runtime->shouldIgnoreMissing();
        $loyalty = new LoyaltyPointsService(new ReservationFinancialSyncService, $runtime);
        $service = new RefundExecutionService($planner, new SettlementAmountCalculator, $loyalty);

        $older = new Payment([
            'payment_type' => 'Final',
            'status' => PaymentStatus::Success,
            'amount' => 70.0,
            'paid_at' => Carbon::parse('2026-03-18 10:00:00', 'UTC'),
        ]);
        $older->setAttribute('payment_id', 10);

        $newer = new Payment([
            'payment_type' => 'Final',
            'status' => PaymentStatus::Success,
            'amount' => 50.0,
            'paid_at' => Carbon::parse('2026-03-18 11:00:00', 'UTC'),
        ]);
        $newer->setAttribute('payment_id', 11);

        $allocations = $service->allocateRefundPaymentsBySource(new Collection([$older, $newer]), 'Final', 80.0);

        self::assertCount(2, $allocations);
        self::assertSame(11, (int) $allocations[0]['payment']->getAttribute('payment_id'));
        self::assertSame(50.0, (float) $allocations[0]['amount']);
        self::assertSame(10, (int) $allocations[1]['payment']->getAttribute('payment_id'));
        self::assertSame(30.0, (float) $allocations[1]['amount']);
    }

    public function test_execute_locked_rejects_refund_source_payments_from_another_reservation(): void
    {
        $planner = new RefundPlannerService(new SettlementAmountCalculator);
        $runtime = Mockery::mock(RuntimeSettingService::class);
        $runtime->shouldIgnoreMissing();
        $loyalty = new LoyaltyPointsService(new ReservationFinancialSyncService, $runtime);

        $branchContext = Mockery::mock(BranchContextService::class);
        $branchContext->shouldNotReceive('assertSingleBranch');
        $branchContext->shouldNotReceive('resolveBranchId');
        $branchContext->shouldNotReceive('assertSameBranch');

        $service = new RefundExecutionService($planner, new SettlementAmountCalculator, $loyalty, $branchContext);

        $reservation = new Reservation([
            'branch_id' => 1,
            'status' => 'Completed',
            'bill_currency' => 'VND',
        ]);
        $reservation->setAttribute('reservation_id', 101);

        $foreignSourcePayment = new Payment([
            'branch_id' => 1,
            'reservation_id' => 202,
            'payment_type' => 'Deposit',
            'status' => PaymentStatus::Success,
            'amount' => '100000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-FOREIGN-1',
        ]);
        $foreignSourcePayment->setAttribute('payment_id', 301);

        try {
            $service->executeLocked(
                reservation: $reservation,
                orders: new Collection,
                payments: new Collection([$foreignSourcePayment]),
                tableIds: [],
                paymentMethod: 'Cash',
                refundScope: 'deposit',
                refundAmount: 10000.00,
                baseCurrency: 'VND',
                transactionCode: 'RF-FOREIGN-1',
                paymentProvider: 'Cash',
                notes: 'should fail',
                reason: 'customer_request',
                cancelAfterPayment: false,
                cancelReason: null,
                staffUserId: 7,
                idempotencyKey: 'idem-rf-foreign-1',
                syncDepositSnapshot: fn (): never => $this->fail('Refund guard must run before snapshot sync.'),
                releaseAppliedVoucherLocked: fn (): never => $this->fail('Refund guard must run before voucher release.'),
                cancelReservationLocked: fn (): never => $this->fail('Refund guard must run before reservation cancellation.'),
                isDuplicatePaymentIdempotencyConstraint: fn (): bool => false,
                throwIfDuplicatePaymentConstraint: fn (): null => null,
            );

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $errors = $e->errors();

            self::assertArrayHasKey('refund_of_payment_id', $errors);
            self::assertStringContainsString('must belong to the reservation', $errors['refund_of_payment_id'][0]);
        }
    }

    public function test_resolve_reservation_branch_id_rejects_payments_from_other_branch(): void
    {
        $planner = new RefundPlannerService(new SettlementAmountCalculator);
        $runtime = Mockery::mock(RuntimeSettingService::class);
        $runtime->shouldIgnoreMissing();
        $loyalty = new LoyaltyPointsService(new ReservationFinancialSyncService, $runtime);

        $branchContext = Mockery::mock(BranchContextService::class);
        $branchContext->shouldReceive('assertSingleBranch')
            ->once()
            ->with([2], 'Payments for the reservation must belong to a single branch.', 'payment_id', false)
            ->andReturn(2);
        $branchContext->shouldReceive('resolveBranchId')
            ->once()
            ->with(1, false)
            ->andReturn(1);
        $branchContext->shouldReceive('assertSameBranch')
            ->once()
            ->with(1, 2, 'Payments do not belong to the reservation branch.', 'payment_id', false)
            ->andThrow(ValidationException::withMessages([
                'payment_id' => ['Payments do not belong to the reservation branch.'],
            ]));

        $service = new RefundExecutionService($planner, new SettlementAmountCalculator, $loyalty, $branchContext);
        $reservation = new Reservation(['branch_id' => 1]);
        $payments = new Collection([new Payment(['branch_id' => 2])]);

        $method = new ReflectionMethod($service, 'resolveReservationBranchId');
        $method->setAccessible(true);

        $this->expectException(ValidationException::class);
        $method->invoke($service, $reservation, $payments);
    }
}
