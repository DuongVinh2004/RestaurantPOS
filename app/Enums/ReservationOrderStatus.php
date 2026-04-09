<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationOrderStatus: string
{
    case Active = 'Active';
    case Cancelled = 'Cancelled';
    case Completed = 'Completed';
}
