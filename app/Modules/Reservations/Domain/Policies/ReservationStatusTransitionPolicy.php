<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain\Policies;

use App\Enums\ReservationStatus;
use Illuminate\Validation\ValidationException;

final class ReservationStatusTransitionPolicy
{
    /**
     * @return list<string>
     */
    public static function allowedTargets(ReservationStatus|string $currentStatus, bool $forceCancel = false): array
    {
        $current = self::normalize($currentStatus);

        return match ($current) {
            ReservationStatus::Confirmed->value => [
                ReservationStatus::checkedInDbValue(),
                ReservationStatus::Cancelled->value,
                ReservationStatus::Expired->value,
                ReservationStatus::NoShow->value,
            ],
            ReservationStatus::checkedInDbValue() => array_values(array_filter([
                ReservationStatus::Completed->value,
                $forceCancel ? ReservationStatus::Cancelled->value : null,
            ])),
            ReservationStatus::Cancelled->value,
            ReservationStatus::Expired->value,
            ReservationStatus::Completed->value,
            ReservationStatus::NoShow->value => [],
            default => [],
        };
    }

    public static function canTransition(
        ReservationStatus|string $currentStatus,
        ReservationStatus|string $targetStatus,
        bool $forceCancel = false,
    ): bool {
        $current = self::normalize($currentStatus);
        $target = self::normalize($targetStatus);

        if ($current === $target) {
            return true;
        }

        return in_array($target, self::allowedTargets($current, $forceCancel), true);
    }

    public static function assertTransitionAllowed(
        ReservationStatus|string $currentStatus,
        ReservationStatus|string $targetStatus,
        bool $forceCancel = false,
        string $field = 'status',
    ): void {
        $current = self::normalize($currentStatus);
        $target = self::normalize($targetStatus);

        if (self::canTransition($current, $target, $forceCancel)) {
            return;
        }

        if (! in_array($current, array_map(
            static fn (ReservationStatus $status): string => $status->value,
            ReservationStatus::cases()
        ), true)) {
            throw ValidationException::withMessages([
                $field => ["Khong cho phep chuyen trang thai tu '{$current}'."],
            ]);
        }

        throw ValidationException::withMessages([
            $field => ["Transition khong hop le: {$current} -> {$target}."],
        ]);
    }

    private static function normalize(ReservationStatus|string $status): string
    {
        return $status instanceof ReservationStatus
            ? $status->value
            : trim((string) $status);
    }
}
