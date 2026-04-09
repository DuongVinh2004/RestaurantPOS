<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseReceiptStatus: string
{
    case Posted = 'Posted';
    case Voided = 'Voided';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
