<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Staff;

use App\Modules\Billing\Application\UseCases\Previews\SettlementAmountCalculator;
use App\Modules\Payments\Application\UseCases\Refunds\RefundPlannerService;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefundPlannerServiceTest extends TestCase
{
    public function test_allocate_refund_payments_prefers_latest_captured_payment_first(): void
    {
        $planner = new RefundPlannerService(new SettlementAmountCalculator);

        $older = new Payment;
        $older->setAttribute('payment_id', 10);
        $older->payment_type = 'Final';
        $older->status = 'Success';
        $older->amount = 40000;

        $newer = new Payment;
        $newer->setAttribute('payment_id', 20);
        $newer->payment_type = 'Final';
        $newer->status = 'Success';
        $newer->amount = 60000;

        $allocations = $planner->allocateRefundPaymentsBySource(collect([$older, $newer]), 'Final', 50000);

        self::assertCount(1, $allocations);
        self::assertSame(20, (int) $allocations[0]['payment']->getAttribute('payment_id'));
        self::assertSame(50000.0, $allocations[0]['amount']);
    }

    public function test_build_refund_plan_rejects_amount_exceeding_scope_balance(): void
    {
        $planner = new RefundPlannerService(new SettlementAmountCalculator);

        $this->expectException(ValidationException::class);

        $planner->buildRefundPlan('deposit', 150000, [
            'deposit' => 100000,
            'final' => 50000,
        ], false);
    }

    public function test_allocate_refund_payments_uses_exact_minor_units_across_sources(): void
    {
        $planner = new RefundPlannerService(new SettlementAmountCalculator);

        $older = new Payment;
        $older->setAttribute('payment_id', 10);
        $older->payment_type = 'Final';
        $older->status = 'Success';
        $older->amount = 10000;

        $newer = new Payment;
        $newer->setAttribute('payment_id', 20);
        $newer->payment_type = 'Final';
        $newer->status = 'Success';
        $newer->amount = 20000;

        $allocations = $planner->allocateRefundPaymentsBySource(collect([$older, $newer]), 'Final', 30000);

        self::assertCount(2, $allocations);
        self::assertSame(20, (int) $allocations[0]['payment']->getAttribute('payment_id'));
        self::assertSame(20000.0, $allocations[0]['amount']);
        self::assertSame(10, (int) $allocations[1]['payment']->getAttribute('payment_id'));
        self::assertSame(10000.0, $allocations[1]['amount']);
    }
}
