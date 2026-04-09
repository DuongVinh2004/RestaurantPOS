<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationDepositIntentStatus: string
{
    case None = 'None';
    case Submitted = 'Submitted';
    case Revoked = 'Revoked';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
