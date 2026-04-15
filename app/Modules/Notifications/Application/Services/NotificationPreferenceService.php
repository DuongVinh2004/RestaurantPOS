<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Domain\Models\NotificationPreference;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationPreferenceService
{
    /**
     * @return array{
     *   enabled: bool,
     *   reason: string|null,
     *   quiet_until: Carbon|null,
     *   preference: NotificationPreference|null
     * }
     */
    public function evaluate(?int $userId, string $channel, ?Carbon $now = null, ?string $timezone = null): array
    {
        $now ??= Carbon::now('UTC');

        if ($userId === null || $userId <= 0 || ! $this->preferencesEnabled() || ! Schema::hasTable('notification_preferences')) {
            return [
                'enabled' => true,
                'reason' => null,
                'quiet_until' => null,
                'preference' => null,
            ];
        }

        $normalizedChannel = $this->normalizeChannel($channel);
        $preference = NotificationPreference::query()
            ->where('user_id', $userId)
            ->where('channel', $normalizedChannel)
            ->first();

        if ($preference === null) {
            if (! in_array($normalizedChannel, $this->defaultOptInChannels(), true)) {
                return [
                    'enabled' => false,
                    'reason' => 'channel_not_opted_in',
                    'quiet_until' => null,
                    'preference' => null,
                ];
            }

            return [
                'enabled' => true,
                'reason' => null,
                'quiet_until' => null,
                'preference' => null,
            ];
        }

        if (! (bool) $preference->is_enabled) {
            return [
                'enabled' => false,
                'reason' => 'channel_disabled_by_user',
                'quiet_until' => null,
                'preference' => $preference,
            ];
        }

        $quietUntil = $this->quietUntil($preference, $now, $timezone);

        return [
            'enabled' => true,
            'reason' => $quietUntil !== null ? 'quiet_hours_active' : null,
            'quiet_until' => $quietUntil,
            'preference' => $preference,
        ];
    }

    private function preferencesEnabled(): bool
    {
        return (bool) config('notifications.preferences.enabled', true);
    }

    /**
     * @return list<string>
     */
    private function defaultOptInChannels(): array
    {
        return array_values(array_map(
            fn ($channel) => $this->normalizeChannel((string) $channel),
            (array) config('notifications.preferences.default_opt_in_channels', ['Email'])
        ));
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

    private function quietUntil(NotificationPreference $preference, Carbon $now, ?string $timezone = null): ?Carbon
    {
        $start = $preference->quiet_hours_start_minute;
        $end = $preference->quiet_hours_end_minute;

        if ($start === null || $end === null) {
            return null;
        }

        $timezone = $this->resolveEvaluationTimezone($timezone);
        $localNow = $now->copy()->setTimezone($timezone);
        $minuteOfDay = ((int) $localNow->hour * 60) + (int) $localNow->minute;

        $inQuietWindow = false;
        if ($start === $end) {
            $inQuietWindow = true;
        } elseif ($start < $end) {
            $inQuietWindow = ($minuteOfDay >= $start && $minuteOfDay < $end);
        } else {
            $inQuietWindow = ($minuteOfDay >= $start || $minuteOfDay < $end);
        }

        if (! $inQuietWindow) {
            return null;
        }

        $quietEnd = $localNow->copy()->startOfDay()->addMinutes($end);
        if ($start >= $end && $minuteOfDay >= $start) {
            $quietEnd->addDay();
        }

        return $quietEnd->setTimezone('UTC');
    }

    private function resolveEvaluationTimezone(?string $timezone = null): string
    {
        $normalized = $this->normalizeTimezone($timezone);
        if ($normalized !== null) {
            return $normalized;
        }

        return $this->normalizeTimezone((string) config('notifications.preferences.timezone', config('app.timezone', 'UTC')))
            ?? 'UTC';
    }

    private function normalizeTimezone(?string $timezone): ?string
    {
        $normalized = trim((string) ($timezone ?? ''));
        if ($normalized === '') {
            return null;
        }

        try {
            new DateTimeZone($normalized);

            return $normalized;
        } catch (Throwable) {
            return null;
        }
    }
}
