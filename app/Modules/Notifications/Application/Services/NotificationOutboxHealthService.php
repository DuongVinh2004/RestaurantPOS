<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use App\Modules\Notifications\Domain\Models\NotificationOutbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationOutboxHealthService
{
    public function __construct(
        private readonly NotificationChannelManager $channelManager,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   enabled: bool,
     *   pending_count: int,
     *   processing_count: int,
     *   failed_count: int,
     *   cancelled_count: int,
     *   due_now_count: int,
     *   stale_processing_count: int,
     *   oldest_pending_age_seconds: int|null,
     *   dead_letter_count: int,
     *   recent_failure_attempt_count: int,
     *   recent_failure_attempt_window_hours: int,
     *   channel_breakdown: array<string,array<string,mixed>>,
     *   error: string|null
     * }
     */
    public function snapshot(?Carbon $now = null): array
    {
        $enabled = (bool) config('notifications.outbox.enabled', true);
        $recentFailureWindowHours = max(1, (int) config('notifications.outbox.health.recent_failure_attempt_window_hours', 24));

        if (! $enabled) {
            return [
                'ok' => true,
                'enabled' => false,
                'pending_count' => 0,
                'processing_count' => 0,
                'failed_count' => 0,
                'cancelled_count' => 0,
                'due_now_count' => 0,
                'stale_processing_count' => 0,
                'oldest_pending_age_seconds' => null,
                'dead_letter_count' => 0,
                'recent_failure_attempt_count' => 0,
                'recent_failure_attempt_window_hours' => $recentFailureWindowHours,
                'channel_breakdown' => $this->emptyChannelBreakdown(),
                'error' => null,
            ];
        }

        try {
            if (! Schema::hasTable('notification_outbox')) {
                return [
                    'ok' => false,
                    'enabled' => true,
                    'pending_count' => 0,
                    'processing_count' => 0,
                    'failed_count' => 0,
                    'cancelled_count' => 0,
                    'due_now_count' => 0,
                    'stale_processing_count' => 0,
                    'oldest_pending_age_seconds' => null,
                    'dead_letter_count' => 0,
                    'recent_failure_attempt_count' => 0,
                    'recent_failure_attempt_window_hours' => $recentFailureWindowHours,
                    'channel_breakdown' => $this->emptyChannelBreakdown(),
                    'error' => 'notification_outbox table is missing.',
                ];
            }

            $now ??= Carbon::now('UTC');
            $lockSeconds = max(30, (int) config('notifications.outbox.lock_seconds', 90));
            $staleBefore = $now->copy()->subSeconds($lockSeconds + 30);
            $failedThreshold = max(0, (int) config('notifications.outbox.health_failed_threshold', 0));
            $pendingAgeThreshold = max(60, (int) config('notifications.outbox.health_oldest_pending_seconds', 1800));

            $pendingCount = NotificationOutbox::query()->where('status', 'Pending')->count();
            $processingCount = NotificationOutbox::query()->where('status', 'Processing')->count();
            $failedCount = NotificationOutbox::query()->where('status', 'Failed')->count();
            $cancelledCount = NotificationOutbox::query()->where('status', 'Cancelled')->count();

            $dueNowCount = NotificationOutbox::query()
                ->whereIn('status', ['Pending', 'Failed'])
                ->where(function ($query) use ($now) {
                    $query->whereNull('next_retry_at')
                        ->orWhere('next_retry_at', '<=', $now);
                })
                ->count();

            $staleProcessingCount = NotificationOutbox::query()
                ->where('status', 'Processing')
                ->where(function ($q) use ($staleBefore) {
                    $q->whereNull('locked_until')
                        ->orWhere('locked_until', '<', $staleBefore);
                })
                ->count();

            $oldestPendingCreatedAt = NotificationOutbox::query()
                ->where('status', 'Pending')
                ->min('created_at');

            $oldestPendingAgeSeconds = null;
            if ($oldestPendingCreatedAt) {
                $createdAt = Carbon::parse((string) $oldestPendingCreatedAt)->utc();
                $oldestPendingAgeSeconds = max(0, $now->utc()->getTimestamp() - $createdAt->getTimestamp());
            }

            $recentFailureAttemptCount = 0;
            if (Schema::hasTable('notification_delivery_attempts')) {
                $recentFailureAttemptCount = NotificationDeliveryAttempt::query()
                    ->where('status', 'Failed')
                    ->where('attempted_at', '>=', $now->copy()->subHours($recentFailureWindowHours))
                    ->count();
            }

            $ok = true;
            if ($failedCount > $failedThreshold || $staleProcessingCount > 0) {
                $ok = false;
            }
            if ($oldestPendingAgeSeconds !== null && $oldestPendingAgeSeconds > $pendingAgeThreshold) {
                $ok = false;
            }

            return [
                'ok' => $ok,
                'enabled' => true,
                'pending_count' => $pendingCount,
                'processing_count' => $processingCount,
                'failed_count' => $failedCount,
                'cancelled_count' => $cancelledCount,
                'due_now_count' => $dueNowCount,
                'stale_processing_count' => $staleProcessingCount,
                'oldest_pending_age_seconds' => $oldestPendingAgeSeconds,
                'dead_letter_count' => $cancelledCount,
                'recent_failure_attempt_count' => $recentFailureAttemptCount,
                'recent_failure_attempt_window_hours' => $recentFailureWindowHours,
                'channel_breakdown' => $this->channelBreakdown($now, $recentFailureWindowHours),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'enabled' => true,
                'pending_count' => 0,
                'processing_count' => 0,
                'failed_count' => 0,
                'cancelled_count' => 0,
                'due_now_count' => 0,
                'stale_processing_count' => 0,
                'oldest_pending_age_seconds' => null,
                'dead_letter_count' => 0,
                'recent_failure_attempt_count' => 0,
                'recent_failure_attempt_window_hours' => $recentFailureWindowHours,
                'channel_breakdown' => $this->emptyChannelBreakdown(),
                'error' => $this->runtimeFailureMessage($exception),
            ];
        }
    }

    /**
     * @return array{
     *   ok: bool,
     *   channel: string|null,
     *   limit: int,
     *   count: int,
     *   rows: list<array<string,mixed>>,
     *   error: string|null
     * }
     */
    public function deadLetterSnapshot(?string $channel = null, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        try {
            if (! Schema::hasTable('notification_outbox')) {
                return [
                    'ok' => false,
                    'channel' => $channel,
                    'limit' => $limit,
                    'count' => 0,
                    'rows' => [],
                    'error' => 'notification_outbox table is missing.',
                ];
            }

            $normalizedChannel = $channel !== null && trim($channel) !== ''
                ? $this->normalizeChannel($channel)
                : null;

            $rows = NotificationOutbox::query()
                ->when($normalizedChannel !== null, fn ($query) => $query->where('channel', $normalizedChannel))
                ->whereIn('status', ['Failed', 'Cancelled'])
                ->orderByDesc('last_attempted_at')
                ->orderByDesc('outbox_id')
                ->limit($limit)
                ->get();

            $items = [];
            foreach ($rows as $row) {
                $channelMeta = $this->channelManager->describe((string) $row->channel);
                $latestAttempt = null;
                if (Schema::hasTable('notification_delivery_attempts')) {
                    $latestAttempt = NotificationDeliveryAttempt::query()
                        ->where('outbox_id', (int) $row->outbox_id)
                        ->orderByDesc('attempted_at')
                        ->orderByDesc('attempt_id')
                        ->first();
                }

                $items[] = [
                    'outbox_id' => (int) $row->outbox_id,
                    'channel' => (string) $row->channel,
                    'status' => (string) $row->status,
                    'template_key' => (string) $row->template_key,
                    'attempt_count' => (int) $row->attempt_count,
                    'provider_key' => $channelMeta['provider_key'] ?? null,
                    'delivery_mode' => $channelMeta['delivery_mode'] ?? null,
                    'readiness' => $channelMeta['readiness'] ?? null,
                    'recipient_masked' => $this->maskRecipient((string) $row->recipient),
                    'last_error' => $row->last_error,
                    'latest_error_code' => $latestAttempt?->error_code,
                    'latest_provider_status' => $latestAttempt?->provider_status,
                    'last_attempted_at_utc' => $row->last_attempted_at?->utc()->toIso8601String(),
                    'next_retry_at_utc' => $row->next_retry_at?->utc()->toIso8601String(),
                    'latest_attempt' => $latestAttempt === null ? null : [
                        'provider_key' => $latestAttempt->provider_key,
                        'status' => $latestAttempt->status,
                        'provider_status' => $latestAttempt->provider_status,
                        'error_code' => $latestAttempt->error_code,
                        'error_message' => $latestAttempt->error_message,
                        'attempted_at_utc' => $latestAttempt->attempted_at?->utc()->toIso8601String(),
                    ],
                ];
            }

            return [
                'ok' => true,
                'channel' => $normalizedChannel,
                'limit' => $limit,
                'count' => count($items),
                'rows' => $items,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'channel' => $channel,
                'limit' => $limit,
                'count' => 0,
                'rows' => [],
                'error' => $this->runtimeFailureMessage($exception),
            ];
        }
    }

    private function runtimeFailureMessage(Throwable $exception): string
    {
        return 'notification outbox health inspection failed: '.trim($exception->getMessage());
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function channelBreakdown(Carbon $now, int $recentFailureWindowHours): array
    {
        $breakdown = $this->emptyChannelBreakdown();

        foreach (NotificationOutbox::query()->get(['channel', 'status']) as $row) {
            $channel = $this->normalizeChannel((string) $row->channel);
            if (! array_key_exists($channel, $breakdown)) {
                $breakdown[$channel] = $this->defaultChannelMeta($channel);
            }

            $breakdown[$channel]['total_count']++;
            $key = strtolower((string) $row->status).'_count';
            if (array_key_exists($key, $breakdown[$channel])) {
                $breakdown[$channel][$key]++;
            }
        }

        if (Schema::hasTable('notification_delivery_attempts')) {
            $windowStart = $now->copy()->subHours($recentFailureWindowHours);
            $recentFailures = NotificationDeliveryAttempt::query()
                ->where('status', 'Failed')
                ->where('attempted_at', '>=', $windowStart)
                ->get(['channel']);

            foreach ($recentFailures as $attempt) {
                $channel = $this->normalizeChannel((string) $attempt->channel);
                if (! array_key_exists($channel, $breakdown)) {
                    $breakdown[$channel] = $this->defaultChannelMeta($channel);
                }

                $breakdown[$channel]['recent_failure_attempt_count']++;
            }
        }

        return $breakdown;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function emptyChannelBreakdown(): array
    {
        $channels = [];
        foreach ($this->channelManager->configuredChannels() as $channel => $meta) {
            $channels[$channel] = array_merge($this->defaultChannelMeta($channel), $meta);
        }

        return $channels;
    }

    /**
     * @return array<string,mixed>
     */
    private function defaultChannelMeta(string $channel): array
    {
        return [
            'channel' => $channel,
            'enabled' => false,
            'driver' => null,
            'provider_key' => null,
            'delivery_mode' => null,
            'readiness' => null,
            'supports_live_delivery' => false,
            'total_count' => 0,
            'pending_count' => 0,
            'processing_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'cancelled_count' => 0,
            'recent_failure_attempt_count' => 0,
        ];
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

    private function maskRecipient(string $recipient): string
    {
        $recipient = trim($recipient);
        if ($recipient === '') {
            return '';
        }

        $at = strpos($recipient, '@');
        if ($at === false) {
            return strlen($recipient) <= 4
                ? str_repeat('*', strlen($recipient))
                : substr($recipient, 0, 2).str_repeat('*', max(1, strlen($recipient) - 4)).substr($recipient, -2);
        }

        $local = substr($recipient, 0, $at);
        $domain = substr($recipient, $at + 1);

        return match (strlen($local)) {
            0 => '@'.$domain,
            1 => '*@'.$domain,
            2 => substr($local, 0, 1).'*@'.$domain,
            default => substr($local, 0, 1).str_repeat('*', max(1, strlen($local) - 2)).substr($local, -1).'@'.$domain,
        };
    }
}
