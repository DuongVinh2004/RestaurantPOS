<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Modules\CheckoutPayments\Domain\ValueObjects\PaymentSummary;
use PHPUnit\Framework\TestCase;

final class PaymentSummaryHardeningTest extends TestCase
{
    public function test_it_surfaces_over_refund_instead_of_silently_hiding_it(): void
    {
        $summary = PaymentSummary::fromPayments([
            [
                'payment_type' => 'Deposit',
                'status' => 'Success',
                'amount' => 100,
                'currency' => 'VND',
            ],
            [
                'payment_type' => 'Refund',
                'status' => 'Refunded',
                'amount' => 120,
                'currency' => 'VND',
                'provider_response_json' => ['refund_target_payment_type' => 'Deposit'],
            ],
        ]);

        self::assertSame(100.0, $summary['deposit_captured_amount']);
        self::assertSame(120.0, $summary['deposit_refunded_amount']);
        self::assertSame(-20.0, $summary['deposit_raw_net_amount']);
        self::assertSame(0.0, $summary['deposit_net_amount']);
        self::assertSame(20.0, $summary['deposit_over_refunded_amount']);
        self::assertSame(20.0, $summary['over_refunded_amount']);
        self::assertTrue(PaymentSummary::hasOverRefund($summary));
    }

    public function test_it_resolves_refund_target_from_source_payment_id_without_loaded_relation(): void
    {
        $summary = PaymentSummary::fromPayments([
            [
                'payment_id' => 10,
                'payment_type' => 'Final',
                'status' => 'Success',
                'amount' => 300,
                'currency' => 'VND',
            ],
            [
                'payment_id' => 11,
                'payment_type' => 'Refund',
                'status' => 'Refunded',
                'amount' => 80,
                'currency' => 'VND',
                'refund_of_payment_id' => 10,
            ],
        ]);

        self::assertSame(300.0, $summary['final_captured_amount']);
        self::assertSame(80.0, $summary['final_refunded_amount']);
        self::assertSame(220.0, $summary['final_net_amount']);
        self::assertSame(220.0, $summary['net_paid_amount']);
    }
}
