<?php

declare(strict_types=1);

namespace App\Support;

final class LoyaltyEarnReconciliation
{
    /**
     * @return array{delta:int, adjustment_points:int, clawback_points:int, shortfall_points:int}
     */
    public static function plan(int $desiredEarnPoints, int $currentEarnNetPoints, int $availableBalance): array
    {
        $desiredEarnPoints = max(0, $desiredEarnPoints);
        $currentEarnNetPoints = $currentEarnNetPoints;
        $availableBalance = max(0, $availableBalance);

        $delta = $desiredEarnPoints - $currentEarnNetPoints;
        if ($delta >= 0) {
            return [
                'delta' => $delta,
                'adjustment_points' => $delta,
                'clawback_points' => 0,
                'shortfall_points' => 0,
            ];
        }

        $requiredClawback = abs($delta);
        $clawbackPoints = min($availableBalance, $requiredClawback);
        $shortfallPoints = max(0, $requiredClawback - $clawbackPoints);

        return [
            'delta' => $delta,
            'adjustment_points' => -$clawbackPoints,
            'clawback_points' => $clawbackPoints,
            'shortfall_points' => $shortfallPoints,
        ];
    }
}
