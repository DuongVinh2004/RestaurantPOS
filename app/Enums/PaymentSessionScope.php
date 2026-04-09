<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentSessionScope: string
{
    case Deposit = 'deposit';
    case Bill = 'bill';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'deposit payment',
            self::Bill => 'bill payment',
        };
    }
}
