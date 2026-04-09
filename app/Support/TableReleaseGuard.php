<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ReservationStatus;

final class TableReleaseGuard
{
    public static function isReservationBlockingRelease(?string $status, mixed $checkedInAt = null): bool
    {
        $status = trim((string) ($status ?? ''));

        if ($status === ReservationStatus::checkedInDbValue()) {
            return true;
        }

        return $status === ReservationStatus::Confirmed->value && $checkedInAt !== null;
    }

    /**
     * @param iterable<int,object|array<string,mixed>> $rows
     * @return array<int,int>
     */
    public static function blockingReservationIds(iterable $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $reservationId = self::readValue($row, 'reservation_id');
            $status = self::readValue($row, 'status');
            $checkedInAt = self::readValue($row, 'checked_in_at');

            if (! self::isReservationBlockingRelease(is_scalar($status) ? (string) $status : null, $checkedInAt)) {
                continue;
            }

            if ($reservationId === null) {
                continue;
            }

            $ids[] = (int) $reservationId;
        }

        return array_values(array_unique($ids));
    }

    private static function readValue(object|array $row, string $key): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        return $row->{$key} ?? null;
    }
}
