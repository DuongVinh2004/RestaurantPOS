<?php

declare(strict_types=1);

namespace App\Support;


final class PaymentIntegrityGuard
{
    /**
     * @param array<string,mixed> $summary
     */
    public static function assertNoOverRefund(array $summary, string $field = 'payments', string $message = 'Refunded amount exceeds captured amount.'): void
    {
        $hasOverRefund = (bool) ($summary['has_over_refund'] ?? false);
        $overRefundTotal = round(max(0.0, (float) (($summary['over_refunded_amount'] ?? $summary['over_refunded_total']) ?? 0.0)), 2);
        $rawNetPaidTotal = round((float) (($summary['raw_net_paid_amount'] ?? $summary['raw_net_paid_total']) ?? 0.0), 2);

        if (! $hasOverRefund && $overRefundTotal <= 0.0001 && $rawNetPaidTotal >= -0.0001) {
            return;
        }

        throw ValidationExceptionFactory::make([
            $field => [$message],
        ]);
    }
}
