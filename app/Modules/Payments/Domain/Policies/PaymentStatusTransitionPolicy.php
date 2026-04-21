<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Policies;

use App\Modules\Payments\Domain\Models\Payment;
use App\Enums\PaymentStatus;
use App\SharedKernel\Money\Money;
use Illuminate\Validation\ValidationException;

final class PaymentStatusTransitionPolicy
{
    /**
     * @return list<PaymentStatus>
     */
    public static function allowedTargets(PaymentStatus|string $currentStatus): array
    {
        $current = self::normalize($currentStatus);

        return match ($current) {
            PaymentStatus::Pending => [
                PaymentStatus::Partial,
                PaymentStatus::Success,
                PaymentStatus::Failed,
            ],
            PaymentStatus::Partial => [
                PaymentStatus::Success,
                PaymentStatus::Refunded,
            ],
            PaymentStatus::Success => [
                PaymentStatus::Refunded,
            ],
            PaymentStatus::Failed,
            PaymentStatus::Refunded => [],
        };
    }

    public static function canTransition(
        PaymentStatus|string $currentStatus,
        PaymentStatus|string $targetStatus,
    ): bool {
        $current = self::normalize($currentStatus);
        $target = self::normalize($targetStatus);

        if ($current === $target) {
            return true;
        }

        return in_array($target, self::allowedTargets($current), true);
    }

    public static function assertTransitionAllowed(
        PaymentStatus|string $currentStatus,
        PaymentStatus|string $targetStatus,
        string $field = 'payment_status',
    ): void {
        $current = self::normalize($currentStatus);
        $target = self::normalize($targetStatus);

        if (self::canTransition($current, $target)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => [sprintf('Cannot transition payment status from %s to %s.', $current->value, $target->value)],
        ]);
    }

    public static function captureStatusForAppliedAmount(mixed $appliedAmount, mixed $remainingDueBefore): PaymentStatus
    {
        return Money::minorUnits($appliedAmount, true) >= Money::minorUnits($remainingDueBefore, true)
            ? PaymentStatus::Success
            : PaymentStatus::Partial;
    }

    public static function derivedStatusForSettlementProgress(mixed $settledAmount, mixed $totalDue): PaymentStatus
    {
        $settledMinor = Money::minorUnits($settledAmount, true);
        $totalDueMinor = Money::minorUnits($totalDue, true);

        if ($settledMinor >= $totalDueMinor) {
            return PaymentStatus::Success;
        }

        return $settledMinor > 0
            ? PaymentStatus::Partial
            : PaymentStatus::Failed;
    }

    public static function isRefundableSourceStatus(PaymentStatus|string|null $status): bool
    {
        if ($status === null || trim((string) ($status instanceof PaymentStatus ? $status->value : $status)) === '') {
            return false;
        }

        $normalized = self::normalize($status);

        return in_array($normalized, [PaymentStatus::Success, PaymentStatus::Partial], true);
    }

    public static function isRefundedStatus(PaymentStatus|string|null $status): bool
    {
        if ($status === null || trim((string) ($status instanceof PaymentStatus ? $status->value : $status)) === '') {
            return false;
        }

        return self::normalize($status) === PaymentStatus::Refunded;
    }

    private static function normalize(PaymentStatus|string $status): PaymentStatus
    {
        return $status instanceof PaymentStatus
            ? $status
            : PaymentStatus::from((string) $status);
    }
}
