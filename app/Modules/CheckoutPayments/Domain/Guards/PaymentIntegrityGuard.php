<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Domain\Guards;

use App\Support\Money;
use App\Support\ValidationExceptionFactory;

final class PaymentIntegrityGuard
{
    /**
     * @param  array<string,mixed>  $summary
     */
    public static function assertNoOverRefund(array $summary, string $field = 'payments', string $message = 'Refunded amount exceeds captured amount.'): void
    {
        $hasOverRefund = (bool) ($summary['has_over_refund'] ?? false);
        $overRefundMinor = Money::minorUnits(($summary['over_refunded_amount'] ?? $summary['over_refunded_total']) ?? 0, true);
        $rawNetPaidMinor = Money::minorUnits(($summary['raw_net_paid_amount'] ?? $summary['raw_net_paid_total']) ?? 0);

        if (! $hasOverRefund && $overRefundMinor <= 0 && $rawNetPaidMinor >= 0) {
            return;
        }

        throw ValidationExceptionFactory::make([
            $field => [$message],
        ]);
    }
}
