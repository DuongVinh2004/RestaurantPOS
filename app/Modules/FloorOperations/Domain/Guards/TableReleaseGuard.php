<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Domain\Guards;

use App\Enums\ReservationStatus;
use Illuminate\Support\Carbon;

class TableReleaseGuard
{
    public static function isReservationBlockingRelease(
        ?string $status,
        mixed $checkedInAt = null,
        mixed $startTime = null,
        mixed $endTime = null,
        ?Carbon $now = null,
    ): bool {
        $status = trim((string) ($status ?? ''));

        if ($status === ReservationStatus::checkedInDbValue()) {
            return true;
        }

        if ($status === ReservationStatus::Confirmed->value && $checkedInAt !== null) {
            return true;
        }

        if ($status !== ReservationStatus::Confirmed->value) {
            return false;
        }

        $startUtc = self::normalizeUtc($startTime);
        $endUtc = self::normalizeUtc($endTime);
        if ($startUtc === null || $endUtc === null) {
            return false;
        }

        $now ??= Carbon::now('UTC');

        return $startUtc->lessThanOrEqualTo($now) && $endUtc->greaterThan($now);
    }

    /**
     * @param  iterable<int,object|array<string,mixed>>  $rows
     * @return array<int,int>
     */
    public static function blockingReservationIds(iterable $rows, ?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');
        $ids = [];

        foreach ($rows as $row) {
            $reservationId = self::readValue($row, 'reservation_id');
            $status = self::readValue($row, 'status');
            $checkedInAt = self::readValue($row, 'checked_in_at');
            $startTime = self::readValue($row, 'start_time');
            $endTime = self::readValue($row, 'end_time');

            if (! self::isReservationBlockingRelease(
                is_scalar($status) ? (string) $status : null,
                $checkedInAt,
                $startTime,
                $endTime,
                $now,
            )) {
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

    private static function normalizeUtc(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }

        return Carbon::parse((string) $value)->utc();
    }
}
