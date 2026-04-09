<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationDepositPaymentSettlementStatus: string
{
    case NotApplied = 'NotApplied';
    case Applied = 'Applied';
    case Skipped = 'Skipped';
}
