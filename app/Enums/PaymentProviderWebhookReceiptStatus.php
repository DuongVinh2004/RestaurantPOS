<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentProviderWebhookReceiptStatus: string
{
    case Received = 'Received';
    case Applied = 'Applied';
    case Ignored = 'Ignored';
    case Failed = 'Failed';
}
