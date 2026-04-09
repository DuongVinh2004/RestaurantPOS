<?php

declare(strict_types=1);

namespace App\Enums;

enum WaitingListStatus: string
{
    case Waiting = 'Waiting';
    case Notified = 'Notified';
    case Seated = 'Seated';
    case Cancelled = 'Cancelled';
}
