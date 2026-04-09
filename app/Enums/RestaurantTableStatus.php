<?php

declare(strict_types=1);

namespace App\Enums;

enum RestaurantTableStatus: string
{
    case Available = 'Available';
    case Reserved = 'Reserved';
    case Occupied = 'Occupied';
    case Blocked = 'Blocked';
    case Maintenance = 'Maintenance';
}
