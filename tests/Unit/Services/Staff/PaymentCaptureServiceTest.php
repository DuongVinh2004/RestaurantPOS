<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Staff;

use App\Enums\PaymentStatus;
use App\Modules\Billing\Application\UseCases\Previews\CheckoutResponseFactory;
use App\Modules\Billing\Application\UseCases\Previews\SettlementAmountCalculator;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Payments\Application\UseCases\Capture\PaymentCaptureService;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Validation\ValidationException;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

final class PaymentCaptureServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_determine_payment_status_marks_partial_until_remaining_due_is_covered(): void
    {
        $service = new PaymentCaptureService(
            new SettlementAmountCalculator,
            new CheckoutResponseFactory(new SettlementAmountCalculator),
            Mockery::mock(NotificationOutboxService::class),
        );

        self::assertSame(PaymentStatus::Partial, $service->determinePaymentStatus(50.0, 100.0));
        self::assertSame(PaymentStatus::Success, $service->determinePaymentStatus(100.0, 100.0));
        self::assertSame(PaymentStatus::Success, $service->determinePaymentStatus(99.99995, 100.0));
    }

    public function test_is_settled_uses_same_tolerance_as_checkout_flow(): void
    {
        $service = new PaymentCaptureService(
            new SettlementAmountCalculator,
            new CheckoutResponseFactory(new SettlementAmountCalculator),
            Mockery::mock(NotificationOutboxService::class),
        );

        self::assertFalse($service->isSettled(99.0, 100.0));
        self::assertTrue($service->isSettled(100.0, 100.0));
        self::assertTrue($service->isSettled(99.99995, 100.0));
    }

    public function test_resolve_reservation_branch_id_rejects_existing_payment_from_other_branch(): void
    {
        $branchContext = Mockery::mock(BranchContextService::class);
        $branchContext->shouldReceive('assertSingleBranch')
            ->once()
            ->with([2], 'Existing payments for the reservation must belong to a single branch.', 'payment_id', false)
            ->andReturn(2);
        $branchContext->shouldReceive('resolveBranchId')
            ->once()
            ->with(1, false)
            ->andReturn(1);
        $branchContext->shouldReceive('assertSameBranch')
            ->once()
            ->with(1, 2, 'Existing payments do not belong to the reservation branch.', 'payment_id', false)
            ->andThrow(ValidationException::withMessages([
                'payment_id' => ['Existing payments do not belong to the reservation branch.'],
            ]));

        $service = new PaymentCaptureService(
            new SettlementAmountCalculator,
            new CheckoutResponseFactory(new SettlementAmountCalculator),
            Mockery::mock(NotificationOutboxService::class),
            $branchContext,
        );

        $reservation = new Reservation(['branch_id' => 1]);
        $payments = collect([new Payment(['branch_id' => 2])]);

        $method = new ReflectionMethod($service, 'resolveReservationBranchId');
        $method->setAccessible(true);

        $this->expectException(ValidationException::class);
        $method->invoke($service, $reservation, $payments);
    }
}
