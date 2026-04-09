<?php

declare(strict_types=1);

namespace App\Enums;

enum DepositStatus: string
{
    case NotRequired = 'NotRequired';
    case Pending = 'Pending';
    case Paid = 'Paid';
    case Refunded = 'Refunded';
    case PartiallyRefunded = 'PartiallyRefunded';
    case Forfeited = 'Forfeited';

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
