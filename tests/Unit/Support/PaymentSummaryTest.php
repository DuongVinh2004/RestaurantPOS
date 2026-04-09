<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PaymentSummary;
use PHPUnit\Framework\TestCase;

class PaymentSummaryTest extends TestCase
{
    public function test_it_summarizes_captured_and_refunded_amounts_from_linked_refund_relations(): void
    {
        $deposit = [
            'payment_id' => 1,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => 100,
            'currency' => 'VND',
        ];

        $final = [
            'payment_id' => 2,
            'payment_type' => 'Final',
            'status' => 'Partial',
            'amount' => 250,
            'currency' => 'VND',
        ];

        $depositRefund = (object) [
            'payment_id' => 3,
            'payment_type' => 'Refund',
            'status' => 'Refunded',
            'amount' => 40,
            'refund_of_payment_id' => 1,
            'currency' => 'VND',
            'refundOfPayment' => (object) $deposit,
        ];

        $finalRefund = (object) [
            'payment_id' => 4,
            'payment_type' => 'Refund',
            'status' => 'Refunded',
            'amount' => 25,
            'refund_of_payment_id' => 2,
            'currency' => 'VND',
            'refundOfPayment' => (object) $final,
        ];

        $summary = PaymentSummary::fromPayments([$deposit, $final, $depositRefund, $finalRefund]);

        $this->assertSame(100.0, $summary['deposit_captured_amount']);
        $this->assertSame(40.0, $summary['deposit_refunded_amount']);
        $this->assertSame(60.0, $summary['deposit_net_amount']);
        $this->assertSame(250.0, $summary['final_captured_amount']);
        $this->assertSame(25.0, $summary['final_refunded_amount']);
        $this->assertSame(225.0, $summary['final_net_amount']);
        $this->assertSame(285.0, $summary['net_paid_amount']);
    }

    public function test_it_exposes_over_refund_anomalies_without_hiding_them(): void
    {
        $deposit = [
            'payment_id' => 11,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => 100,
            'currency' => 'VND',
        ];

        $depositRefund = [
            'payment_id' => 12,
            'payment_type' => 'Refund',
            'status' => 'Refunded',
            'amount' => 120,
            'currency' => 'VND',
            'refund_of_payment_id' => 11,
            'refundOfPayment' => (object) $deposit,
        ];

        $summary = PaymentSummary::fromPayments([$deposit, $depositRefund]);

        $this->assertSame(-20.0, $summary['deposit_raw_net_amount']);
        $this->assertSame(20.0, $summary['deposit_over_refunded_amount']);
        $this->assertSame(20.0, $summary['over_refunded_amount']);
        $this->assertTrue(PaymentSummary::hasOverRefund($summary));
        $this->assertSame(0.0, $summary['deposit_net_amount']);
    }


    public function test_it_can_resolve_refund_target_from_provider_payload_when_relation_is_missing(): void
    {
        $refund = [
            'payment_type' => 'Refund',
            'status' => 'Refunded',
            'amount' => 10,
            'provider_response_json' => [
                'refund_target_payment_type' => 'final',
            ],
        ];

        $this->assertSame('Final', PaymentSummary::resolveRefundTargetPaymentType($refund));
    }
}
