<?php

declare(strict_types=1);

namespace App\Enums;

enum WaitingListCustomerResponseStatus: string
{
    case Accepted = 'Accepted';
    case Declined = 'Declined';
}
