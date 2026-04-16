<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Domain\Policies;

use App\Enums\ReservationOrderItemStatus;
use Illuminate\Validation\ValidationException;

final class ReservationOrderItemStatusTransitionPolicy
{
    /**
     * @return list<ReservationOrderItemStatus>
     */
    public static function allowedTargets(ReservationOrderItemStatus|string $currentStatus): array
    {
        $current = self::normalize($currentStatus);

        return match ($current) {
            ReservationOrderItemStatus::Ordered => [
                ReservationOrderItemStatus::InProgress,
                ReservationOrderItemStatus::Served,
                ReservationOrderItemStatus::Cancelled,
            ],
            ReservationOrderItemStatus::InProgress => [
                ReservationOrderItemStatus::Served,
                ReservationOrderItemStatus::Cancelled,
            ],
            ReservationOrderItemStatus::Served,
            ReservationOrderItemStatus::Cancelled => [],
        };
    }

    public static function canTransition(
        ReservationOrderItemStatus|string $currentStatus,
        ReservationOrderItemStatus|string $targetStatus,
    ): bool {
        $current = self::normalize($currentStatus);
        $target = self::normalize($targetStatus);

        if ($current === $target) {
            return true;
        }

        return in_array($target, self::allowedTargets($current), true);
    }

    public static function assertTransitionAllowed(
        ReservationOrderItemStatus|string $currentStatus,
        ReservationOrderItemStatus|string $targetStatus,
        string $field = 'status',
    ): void {
        $current = self::normalize($currentStatus);
        $target = self::normalize($targetStatus);

        if (self::canTransition($current, $target)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => [sprintf('Cannot transition order item from %s to %s.', $current->value, $target->value)],
        ]);
    }

    private static function normalize(ReservationOrderItemStatus|string $status): ReservationOrderItemStatus
    {
        return $status instanceof ReservationOrderItemStatus
            ? $status
            : ReservationOrderItemStatus::from((string) $status);
    }
}
