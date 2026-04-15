<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Domain\Policies;

use App\Support\ValidationExceptionFactory;

final class RefundAllocationPolicy
{
    /**
     * @param  array<int,array<string,mixed>>  $sources
     * @return array<int,array<string,mixed>>
     */
    public static function allocate(
        array $sources,
        float $requestedAmount,
        ?string $errorMessage = null,
        string $field = 'refund_scope',
    ): array {
        $requestedAmount = round(max(0.0, $requestedAmount), 2);
        if ($requestedAmount <= 0.0001) {
            return [];
        }

        $remaining = $requestedAmount;
        $allocations = [];

        foreach ($sources as $source) {
            $capturedAmount = round(max(0.0, (float) ($source['captured_amount'] ?? 0.0)), 2);
            $alreadyRefundedAmount = round(max(0.0, (float) ($source['already_refunded_amount'] ?? 0.0)), 2);
            $refundableAmount = round(max(0.0, $capturedAmount - $alreadyRefundedAmount), 2);

            if ($refundableAmount <= 0.0001) {
                continue;
            }

            $allocationAmount = round(min($remaining, $refundableAmount), 2);
            if ($allocationAmount <= 0.0001) {
                continue;
            }

            $allocations[] = $source + [
                'allocation_amount' => $allocationAmount,
                'refundable_amount' => $refundableAmount,
            ];

            $remaining = round($remaining - $allocationAmount, 2);
            if ($remaining <= 0.0001) {
                break;
            }
        }

        if ($remaining > 0.0001) {
            throw ValidationExceptionFactory::make([
                $field => [$errorMessage ?? 'Requested refund exceeds refundable payment lineage.'],
            ]);
        }

        return $allocations;
    }
}
