<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationOrderType: string
{
    case PreOrder = 'PreOrder';
    case OnSpot = 'OnSpot';
}
