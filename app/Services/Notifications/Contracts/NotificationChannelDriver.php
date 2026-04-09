<?php

declare(strict_types=1);

namespace App\Services\Notifications\Contracts;

use App\Models\NotificationOutbox;
use App\Services\Notifications\NotificationDeliveryResult;

interface NotificationChannelDriver
{
    public function providerKey(): string;

    /**
     * @param  array<string,mixed>  $dispatchPayload
     */
    public function send(NotificationOutbox $message, array $dispatchPayload): NotificationDeliveryResult;
}
