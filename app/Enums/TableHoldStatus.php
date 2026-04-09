<?php

declare(strict_types=1);

namespace App\Enums;

enum TableHoldStatus: string
{
    case Holding = 'Holding';
    case Pending = 'Pending';
    case Confirmed = 'Confirmed';
    case Expired = 'Expired';
    case Cancelled = 'Cancelled';
}
