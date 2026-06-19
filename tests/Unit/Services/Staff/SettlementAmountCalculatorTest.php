<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Staff;

use App\Modules\Billing\Application\UseCases\Previews\SettlementAmountCalculator;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SettlementAmountCalculatorTest extends TestCase
{
    public function test_build_settlement_amounts_applies_deposit_before_final_payments(): void
    {
        $calculator = new SettlementAmountCalculator;

        $deposit = new Payment;
        $deposit->payment_type = 'Deposit';
        $deposit->status = 'Success';
        $deposit->amount = 100000;
        $deposit->currency = 'VND';

        $final = new Payment;
        $final->payment_type = 'Final';
        $final->status = 'Success';
        $final->amount = 50000;
        $final->currency = 'VND';

        $summary = $calculator->buildSettlementAmounts(collect([$deposit, $final]), 120000);

        self::assertSame(100000.0, $summary['deposit_net_amount']);
        self::assertSame(100000.0, $summary['deposit_applied_amount']);
        self::assertSame(50000.0, $summary['final_paid_amount']);
        self::assertSame(150000.0, $summary['settled_amount']);
        self::assertSame(0.0, $summary['remaining_due']);
    }

    public function test_assert_single_currency_rejects_mixed_currencies(): void
    {
        $calculator = new SettlementAmountCalculator;

        $first = new Payment;
        $first->currency = 'VND';

        $second = new Payment;
        $second->currency = 'USD';

        $this->expectException(ValidationException::class);

        $calculator->assertPaymentsSingleCurrency([$first, $second], null, 'currency');
    }

    public function test_build_settlement_amounts_is_exact_for_small_values(): void
    {
        $calculator = new SettlementAmountCalculator;

        $deposit = new Payment;
        $deposit->payment_type = 'Deposit';
        $deposit->status = 'Success';
        $deposit->amount = 100;
        $deposit->currency = 'VND';

        $final = new Payment;
        $final->payment_type = 'Final';
        $final->status = 'Success';
        $final->amount = 200;
        $final->currency = 'VND';

        $summary = $calculator->buildSettlementAmounts(collect([$deposit, $final]), 300);

        self::assertSame(100.0, $summary['deposit_net_amount']);
        self::assertSame(100.0, $summary['deposit_applied_amount']);
        self::assertSame(200.0, $summary['final_paid_amount']);
        self::assertSame(300.0, $summary['settled_amount']);
        self::assertSame(0.0, $summary['remaining_due']);
    }
}
