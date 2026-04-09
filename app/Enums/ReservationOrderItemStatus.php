<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationOrderItemStatus: string
{
    case Ordered = 'Ordered';
    case InProgress = 'InProgress';
    case Served = 'Served';
    case Cancelled = 'Cancelled';
}
