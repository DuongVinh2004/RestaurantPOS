<?php

declare(strict_types=1);

namespace App\Enums;

enum WaitingListCustomerResponseState: string
{
    case None = 'none';
    case Accepted = 'accepted';
    case ArrivalConfirmed = 'arrival_confirmed';
    case Declined = 'declined';
}
