<?php

declare(strict_types=1);

namespace App\Enums;

enum VoucherDiscountType: string
{
    case Fixed = 'Fixed';
    case Percent = 'Percent';
    case FreeItem = 'FreeItem';
}
