<?php

declare(strict_types=1);

namespace App\Enums;

enum KitchenStationOutputMode: string
{
    case KDS = 'KDS';
    case Printer = 'Printer';
    case Both = 'Both';
}
