<?php

declare(strict_types=1);

namespace App\Enums;

enum PreorderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Converted = 'converted';
}
