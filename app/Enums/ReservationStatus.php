<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationStatus: string
{
    case Confirmed = 'Confirmed';
    /**
     * Historical DB/API value. Despite the name, this status means the guest
     * has already checked in and is actively occupying table(s).
     *
     * Prefer ReservationStatus::checkedIn(), ::checkedInDbValue(),
     * ::isCheckedInDbValue(), and ::activeDbValues() in domain code so the
     * semantic intent stays explicit even while the persisted value remains
     * backward-compatible.
     */
    case Reserved = 'Reserved';
    case Cancelled = 'Cancelled';
    case Expired = 'Expired';
    case Completed = 'Completed';
    case NoShow = 'NoShow';

    public static function checkedIn(): self
    {
        return self::Reserved;
    }

    public static function checkedInDbValue(): string
    {
        return self::checkedIn()->value;
    }

    /**
     * @return list<string>
     */
    public static function activeDbValues(): array
    {
        return [
            self::Confirmed->value,
            self::checkedInDbValue(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function cancellableDbValues(): array
    {
        return [
            self::Confirmed->value,
            self::checkedInDbValue(),
        ];
    }

    public static function isCheckedInDbValue(string $value): bool
    {
        return $value === self::checkedInDbValue();
    }

    public static function isActiveDbValue(string $value): bool
    {
        return in_array($value, self::activeDbValues(), true);
    }
}
