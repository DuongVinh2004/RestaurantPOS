<?php

declare(strict_types=1);

namespace App\Services\PaymentIntegration;

final class PaymentSessionStatusTransitionPolicy
{
    public static function shouldApply(string $currentStatus, string $incomingStatus): bool
    {
        return self::ignoreReason($currentStatus, $incomingStatus) === null;
    }

    public static function ignoreReason(string $currentStatus, string $incomingStatus): ?string
    {
        $currentStatus = trim($currentStatus);
        $incomingStatus = trim($incomingStatus);

        if ($currentStatus === '' || $incomingStatus === '' || $currentStatus === $incomingStatus) {
            return null;
        }

        if (in_array($currentStatus, self::terminalStatuses(), true)) {
            return 'terminal_state_regression_ignored';
        }

        $currentRank = self::statusRank($currentStatus);
        $incomingRank = self::statusRank($incomingStatus);
        if ($currentRank !== null && $incomingRank !== null && $incomingRank < $currentRank) {
            return 'state_regression_ignored';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function terminalStatuses(): array
    {
        return ['Succeeded', 'Failed', 'Cancelled', 'Expired'];
    }

    private static function statusRank(string $status): ?int
    {
        return match (trim($status)) {
            'Created' => 10,
            'Pending' => 20,
            'Succeeded', 'Failed', 'Cancelled', 'Expired' => 30,
            default => null,
        };
    }
}
