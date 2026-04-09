<?php

declare(strict_types=1);

namespace App\Enums;

enum KitchenTicketStatus: string
{
    case Queued = 'Queued';
    case Fired = 'Fired';
    case Ready = 'Ready';
    case Completed = 'Completed';
    case Cancelled = 'Cancelled';
}
