<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Domain\Policies;

use App\Support\Money;
use App\Support\ValidationExceptionFactory;

final class RefundAllocationPolicy
{
    /**
     * @param  array<int,array<string,mixed>>  $sources
     * @return array<int,array<string,mixed>>
     */
    public static function allocate(
        array $sources,
        mixed $requestedAmount,
        ?string $errorMessage = null,
        string $field = 'refund_scope',
    ): array {
        $requestedMinor = Money::minorUnits($requestedAmount, true);
        if ($requestedMinor <= 0) {
            return [];
        }

        $remainingMinor = $requestedMinor;
        $allocations = [];

        foreach ($sources as $source) {
            $capturedMinor = Money::minorUnits($source['captured_amount'] ?? 0, true);
            $alreadyRefundedMinor = Money::minorUnits($source['already_refunded_amount'] ?? 0, true);
            $refundableMinor = max(0, $capturedMinor - $alreadyRefundedMinor);

            if ($refundableMinor <= 0) {
                continue;
            }

            $allocationMinor = min($remainingMinor, $refundableMinor);
            if ($allocationMinor <= 0) {
                continue;
            }

            $allocations[] = $source + [
                'allocation_amount' => Money::minorToFloat($allocationMinor),
                'refundable_amount' => Money::minorToFloat($refundableMinor),
            ];

            $remainingMinor -= $allocationMinor;
            if ($remainingMinor <= 0) {
                break;
            }
        }

        if ($remainingMinor > 0) {
            throw ValidationExceptionFactory::make([
                $field => [$errorMessage ?? 'Requested refund exceeds refundable payment lineage.'],
            ]);
        }

        return $allocations;
    }
}
