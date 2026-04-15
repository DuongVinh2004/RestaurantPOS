<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Contracts;

use App\Modules\Notifications\Domain\Models\NotificationOutbox;
use App\Modules\Notifications\Infrastructure\NotificationDeliveryResult;

interface NotificationChannelDriver
{
    public function providerKey(): string;

    /**
     * @param  array<string,mixed>  $dispatchPayload
     */
    public function send(NotificationOutbox $message, array $dispatchPayload): NotificationDeliveryResult;
}
