<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\UseCases\Previews;

use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Collection;

class OrderSettlementService
{
    /**
     * @param  Collection<int,mixed>  $payments
     * @return array{deposit_net_amount:float,deposit_applied_amount:float,final_paid_amount:float,settled_amount:float,remaining_due:float}
     */
    public function buildSettlementAmounts(Collection $payments, mixed $totalDue): array
    {
        $summary = PaymentSummary::fromPayments($payments);
        $totalDueMinor = Money::minorUnits($totalDue, true);
        $depositNetMinor = Money::minorUnits($summary['deposit_net_amount'] ?? 0, true);
        $finalPaidMinor = Money::minorUnits($summary['final_net_amount'] ?? 0, true);
        $depositAppliedMinor = min($totalDueMinor, $depositNetMinor);
        $settledMinor = min($totalDueMinor, $depositAppliedMinor + $finalPaidMinor);

        return [
            'deposit_net_amount' => Money::minorToFloat($depositNetMinor),
            'deposit_applied_amount' => Money::minorToFloat($depositAppliedMinor),
            'final_paid_amount' => Money::minorToFloat($finalPaidMinor),
            'settled_amount' => Money::minorToFloat($settledMinor),
            'remaining_due' => Money::minorToFloat(max(0, $totalDueMinor - $settledMinor)),
        ];
    }
}
