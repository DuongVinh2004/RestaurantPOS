<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Modules\CheckoutPayments\Domain\Guards\PaymentIntegrityGuard;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class PaymentIntegrityGuardTest extends TestCase
{
    public function test_it_allows_clean_payment_summary(): void
    {
        PaymentIntegrityGuard::assertNoOverRefund([
            'has_over_refund' => false,
            'over_refunded_total' => 0,
            'raw_net_paid_total' => 100,
        ]);

        self::assertTrue(true);
    }

    public function test_it_rejects_summary_when_over_refund_flag_is_present(): void
    {
        $this->expectException(ValidationException::class);

        PaymentIntegrityGuard::assertNoOverRefund([
            'has_over_refund' => true,
            'over_refunded_total' => 5,
            'raw_net_paid_total' => -5,
        ], 'payments');
    }
}
