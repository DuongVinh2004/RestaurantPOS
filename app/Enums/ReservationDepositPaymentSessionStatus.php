<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationDepositPaymentSessionStatus: string
{
    case Created = 'Created';
    case Pending = 'Pending';
    case Succeeded = 'Succeeded';
    case Failed = 'Failed';
    case Cancelled = 'Cancelled';
    case Expired = 'Expired';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Succeeded,
            self::Failed,
            self::Cancelled,
            self::Expired,
        ], true);
    }
}
