<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Drivers;

use App\Modules\Notifications\Domain\Models\NotificationOutbox;
use App\Modules\Notifications\Infrastructure\Contracts\NotificationChannelDriver;
use App\Modules\Notifications\Infrastructure\NotificationDeliveryResult;

class ZaloStubNotificationChannelDriver implements NotificationChannelDriver
{
    public function providerKey(): string
    {
        return (string) config('notifications.channels.zalo.provider_key', 'zalo.stub');
    }

    public function send(NotificationOutbox $message, array $dispatchPayload): NotificationDeliveryResult
    {
        return new NotificationDeliveryResult(
            providerKey: $this->providerKey(),
            providerStatus: 'stubbed',
            providerMessageId: null,
            responsePayload: [
                'channel' => 'Zalo',
                'mode' => 'stub',
                'recipient' => (string) ($dispatchPayload['recipient'] ?? $message->recipient),
                'text_preview' => (string) ($dispatchPayload['text_body'] ?? ''),
            ],
        );
    }
}
