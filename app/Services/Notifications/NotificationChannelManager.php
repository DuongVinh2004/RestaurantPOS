<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Services\Notifications\Contracts\NotificationChannelDriver;
use App\Services\Notifications\Drivers\EmailNotificationChannelDriver;
use App\Services\Notifications\Drivers\SmsStubNotificationChannelDriver;
use App\Services\Notifications\Drivers\ZaloStubNotificationChannelDriver;

class NotificationChannelManager
{
    public function __construct(
        private readonly EmailNotificationChannelDriver $emailDriver,
        private readonly SmsStubNotificationChannelDriver $smsStubDriver,
        private readonly ZaloStubNotificationChannelDriver $zaloStubDriver,
    ) {
    }

    public function resolve(string $channel): NotificationChannelDriver
    {
        $normalized = $this->normalizeChannel($channel);
        $config = $this->channelConfig($normalized);

        if (! ($config['enabled'] ?? false)) {
            throw new NotificationDeliveryException(
                sprintf('Notification channel [%s] is not enabled.', $normalized),
                'channel_disabled',
                ['channel' => $normalized]
            );
        }

        $driver = strtolower(trim((string) ($config['driver'] ?? '')));

        return match ($normalized) {
            'Email' => match ($driver) {
                '', 'mail' => $this->emailDriver,
                default => throw new NotificationDeliveryException(
                    sprintf('Unsupported email notification driver [%s].', $driver),
                    'unsupported_driver',
                    ['channel' => $normalized, 'driver' => $driver]
                ),
            },
            'SMS' => match ($driver) {
                'stub' => $this->smsStubDriver,
                default => throw new NotificationDeliveryException(
                    sprintf('Unsupported SMS notification driver [%s].', $driver),
                    'unsupported_driver',
                    ['channel' => $normalized, 'driver' => $driver]
                ),
            },
            'Zalo' => match ($driver) {
                'stub' => $this->zaloStubDriver,
                default => throw new NotificationDeliveryException(
                    sprintf('Unsupported Zalo notification driver [%s].', $driver),
                    'unsupported_driver',
                    ['channel' => $normalized, 'driver' => $driver]
                ),
            },
            default => throw new NotificationDeliveryException(
                sprintf('Unsupported notification channel [%s].', $normalized),
                'unsupported_channel',
                ['channel' => $normalized]
            ),
        };
    }

    /**
     * @return array<string,mixed>
     */
    public function describe(string $channel): array
    {
        $normalized = $this->normalizeChannel($channel);
        $config = $this->channelConfig($normalized);

        return [
            'channel' => $normalized,
            'enabled' => (bool) ($config['enabled'] ?? false),
            'driver' => strtolower(trim((string) ($config['driver'] ?? ''))),
            'provider_key' => (string) ($config['provider_key'] ?? ''),
            'delivery_mode' => (string) ($config['delivery_mode'] ?? 'unknown'),
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function configuredChannels(): array
    {
        $channels = [];
        foreach (['Email', 'SMS', 'Zalo'] as $channel) {
            $channels[$channel] = $this->describe($channel);
        }

        return $channels;
    }

    private function normalizeChannel(string $channel): string
    {
        $trimmed = trim($channel);

        return match (strtolower($trimmed)) {
            'email' => 'Email',
            'sms' => 'SMS',
            'zalo' => 'Zalo',
            'push' => 'Push',
            'webhook' => 'Webhook',
            default => $trimmed,
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function channelConfig(string $channel): array
    {
        return match ($channel) {
            'Email' => (array) config('notifications.channels.email', []),
            'SMS' => (array) config('notifications.channels.sms', []),
            'Zalo' => (array) config('notifications.channels.zalo', []),
            default => [],
        };
    }
}
