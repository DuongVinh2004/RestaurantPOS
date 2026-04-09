<?php

declare(strict_types=1);

namespace App\Services\Notifications;

class NotificationDeliveryResult
{
    /**
     * @param  array<string,mixed>  $responsePayload
     */
    public function __construct(
        public readonly string $providerKey,
        public readonly string $providerStatus,
        public readonly ?string $providerMessageId = null,
        public readonly array $responsePayload = [],
    ) {
    }
}
