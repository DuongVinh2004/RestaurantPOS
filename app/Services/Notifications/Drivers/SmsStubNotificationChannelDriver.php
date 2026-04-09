<?php

declare(strict_types=1);

namespace App\Services\Notifications\Drivers;

use App\Models\NotificationOutbox;
use App\Services\Notifications\Contracts\NotificationChannelDriver;
use App\Services\Notifications\NotificationDeliveryResult;

class SmsStubNotificationChannelDriver implements NotificationChannelDriver
{
    public function providerKey(): string
    {
        return (string) config('notifications.channels.sms.provider_key', 'sms.stub');
    }

    public function send(NotificationOutbox $message, array $dispatchPayload): NotificationDeliveryResult
    {
        return new NotificationDeliveryResult(
            providerKey: $this->providerKey(),
            providerStatus: 'stubbed',
            providerMessageId: null,
            responsePayload: [
                'channel' => 'SMS',
                'mode' => 'stub',
                'recipient' => (string) ($dispatchPayload['recipient'] ?? $message->recipient),
                'text_preview' => (string) ($dispatchPayload['text_body'] ?? ''),
            ],
        );
    }
}
