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
        $description = $this->describe($normalized);

        if (! ($description['enabled'] ?? false)) {
            throw new NotificationDeliveryException(
                sprintf('Notification channel [%s] is not enabled.', $normalized),
                'channel_disabled',
                $description,
                false,
            );
        }

        $driver = strtolower(trim((string) ($description['driver'] ?? '')));

        return match ($normalized) {
            'Email' => match ($driver) {
                '', 'mail' => $this->emailDriver,
                default => throw new NotificationDeliveryException(
                    sprintf('Unsupported email notification driver [%s].', $driver),
                    'unsupported_driver',
                    $description,
                    false,
                ),
            },
            'SMS' => match ($driver) {
                'stub' => $this->smsStubDriver,
                default => throw new NotificationDeliveryException(
                    sprintf('Unsupported SMS notification driver [%s].', $driver),
                    'unsupported_driver',
                    $description,
                    false,
                ),
            },
            'Zalo' => match ($driver) {
                'stub' => $this->zaloStubDriver,
                default => throw new NotificationDeliveryException(
                    sprintf('Unsupported Zalo notification driver [%s].', $driver),
                    'unsupported_driver',
                    $description,
                    false,
                ),
            },
            default => throw new NotificationDeliveryException(
                sprintf('Unsupported notification channel [%s].', $normalized),
                'unsupported_channel',
                $this->unsupportedDescription($normalized),
                false,
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
        $driver = strtolower(trim((string) ($config['driver'] ?? '')));
        $deliveryMode = strtolower(trim((string) ($config['delivery_mode'] ?? 'unknown')));
        $readiness = $this->normalizeReadiness((string) ($config['readiness'] ?? $this->defaultReadiness($normalized)));
        $enabled = (bool) ($config['enabled'] ?? false);

        return [
            'channel' => $normalized,
            'enabled' => $enabled,
            'driver' => $driver,
            'provider_key' => (string) ($config['provider_key'] ?? ''),
            'delivery_mode' => $deliveryMode,
            'readiness' => $readiness,
            'supports_live_delivery' => $enabled && $deliveryMode === 'real' && $readiness === 'production_lean',
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

    /**
     * @return array<string,mixed>
     */
    private function unsupportedDescription(string $channel): array
    {
        return [
            'channel' => $channel,
            'enabled' => false,
            'driver' => '',
            'provider_key' => '',
            'delivery_mode' => 'unknown',
            'readiness' => 'unknown',
            'supports_live_delivery' => false,
        ];
    }

    private function defaultReadiness(string $channel): string
    {
        return match ($channel) {
            'Email' => 'production_lean',
            'SMS', 'Zalo' => 'provider_ready',
            default => 'unknown',
        };
    }

    private function normalizeReadiness(string $readiness): string
    {
        $normalized = strtolower(str_replace('-', '_', trim($readiness)));

        return match ($normalized) {
            'production_lean' => 'production_lean',
            'provider_ready' => 'provider_ready',
            'stub_sandbox_only' => 'stub_sandbox_only',
            default => $normalized !== '' ? $normalized : 'unknown',
        };
    }
}
