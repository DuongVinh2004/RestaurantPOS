<?php

declare(strict_types=1);

namespace App\Enums;

enum ConversationStatus: string
{
    case Open = 'Open';
    case Pending = 'Pending';
    case Closed = 'Closed';
    case Spam = 'Spam';
}
