<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Support\PaymentSummary;
use Illuminate\Support\Collection;

class OrderSettlementService
{
    /**
     * @param Collection<int,mixed> $payments
     * @return array{deposit_net_amount:float,deposit_applied_amount:float,final_paid_amount:float,settled_amount:float,remaining_due:float}
     */
    public function buildSettlementAmounts(Collection $payments, float $totalDue): array
    {
        $summary = PaymentSummary::fromPayments($payments);
        $depositNet = round(max(0.0, (float) ($summary['deposit_net_amount'] ?? 0.0)), 2);
        $finalPaid = round(max(0.0, (float) ($summary['final_net_amount'] ?? 0.0)), 2);
        $depositApplied = round(min(max(0.0, $totalDue), $depositNet), 2);
        $settled = round(min(max(0.0, $totalDue), $depositApplied + $finalPaid), 2);

        return [
            'deposit_net_amount' => $depositNet,
            'deposit_applied_amount' => $depositApplied,
            'final_paid_amount' => $finalPaid,
            'settled_amount' => $settled,
            'remaining_due' => round(max(0.0, $totalDue - $settled), 2),
        ];
    }
}
