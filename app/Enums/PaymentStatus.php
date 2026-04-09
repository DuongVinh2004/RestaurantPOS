<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'Pending';
    case Partial = 'Partial';
    case Success = 'Success';
    case Failed = 'Failed';
    case Refunded = 'Refunded';
}
