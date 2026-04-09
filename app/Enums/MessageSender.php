<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageSender: string
{
    case User = 'user';
    case Agent = 'agent';
    case System = 'system';
}
