<?php

declare(strict_types=1);

namespace App\Platform\Metrics\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OperationalAlertService
{
    /**
     * @return array<string,mixed>
     */
    public function snapshot(?Carbon $now = null, int $paymentSampleLimit = 10): array
    {
        $now ??= Carbon::now('UTC');

        return app(OperationalInsightsService::class)->snapshot($now, $paymentSampleLimit);
    }

    /**
     * @param  array<string,mixed>  $snapshot
     * @return array<int,array<string,mixed>>
     */
    public function buildAlerts(array $snapshot, ?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');
        $alerts = [];
        $maxAlerts = max(1, (int) config('ops_alerts.max_alerts_per_run', 25));

        foreach ($snapshot as $section => $payload) {
            if (! is_array($payload)) {
                continue;
            }

            $status = strtolower((string) ($payload['status'] ?? 'ok'));
            if (! in_array($status, ['fail', 'degraded'], true)) {
                continue;
            }

            $severity = $status === 'fail' ? 'critical' : 'warning';
            $priority = $status === 'fail' ? 200 : 100;
            $reasons = array_values((array) ($payload['reasons'] ?? []));

            $fingerprintSource = json_encode([
                'section' => (string) $section,
                'status' => $status,
                'severity' => $severity,
                'reasons' => $reasons,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';

            $fingerprint = hash('sha1', $fingerprintSource);
            $message = sprintf(
                '[RestaurantPOS] %s is %s (%s)',
                (string) $section,
                $status,
                $reasons !== [] ? implode(', ', $reasons) : 'no_reason_provided'
            );

            $alerts[] = [
                'fingerprint' => $fingerprint,
                'dedupe_key' => 'ops-alert:'.$fingerprint,
                'section' => (string) $section,
                'status' => $status,
                'severity' => $severity,
                'priority' => $priority,
                'title' => sprintf('[%s] %s', strtoupper($status), (string) $section),
                'message' => $message,
                'reasons' => $reasons,
                'context' => $payload,
                'generated_at_utc' => $now->copy()->utc()->toIso8601String(),
            ];
        }

        usort($alerts, static function (array $left, array $right): int {
            $priorityCompare = ((int) ($right['priority'] ?? 0)) <=> ((int) ($left['priority'] ?? 0));
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            return strcmp((string) ($left['section'] ?? ''), (string) ($right['section'] ?? ''));
        });

        return array_slice($alerts, 0, $maxAlerts);
    }

    /**
     * @param  array<int,array<string,mixed>>  $alerts
     * @return array<string,mixed>
     */
    public function dispatchAlerts(array $alerts, bool $force = false, ?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');
        $cooldownSeconds = max(1, (int) config('ops_alerts.cooldown_seconds', 900));
        $webhookConfig = $this->webhookConfig();
        $webhookUrl = $webhookConfig['url'];
        $timeoutSeconds = $webhookConfig['timeout_seconds'];

        $results = [];
        $sentCount = 0;
        $suppressedCount = 0;

        foreach ($alerts as $alert) {
            $cooldownKey = $this->cooldownKey($alert);
            $fingerprint = $this->normalizeString($alert['fingerprint'] ?? null);

            if (! $force && $cooldownKey !== '' && Cache::has($cooldownKey)) {
                $suppressedCount++;
                $results[] = [
                    'status' => 'suppressed',
                    'suppression_reason' => 'cooldown_active',
                    'cooldown_key' => $cooldownKey,
                    'fingerprint' => $fingerprint,
                    'alert' => $alert,
                ];

                continue;
            }

            if ($webhookUrl === '') {
                $results[] = [
                    'status' => 'skipped',
                    'suppression_reason' => 'missing_webhook_url',
                    'cooldown_key' => $cooldownKey,
                    'fingerprint' => $fingerprint,
                    'alert' => $alert,
                ];

                continue;
            }

            $response = Http::asJson()
                ->timeout($timeoutSeconds)
                ->post($webhookUrl, [
                    'alert' => $alert,
                    'dispatched_at_utc' => $now->copy()->utc()->toIso8601String(),
                ])
                ->throw();

            if ($cooldownKey !== '') {
                Cache::put($cooldownKey, $now->copy()->utc()->toIso8601String(), $cooldownSeconds);
            }

            $sentCount++;
            $results[] = [
                'status' => 'sent',
                'suppression_reason' => null,
                'cooldown_key' => $cooldownKey,
                'fingerprint' => $fingerprint,
                'response_status' => $response->status(),
                'alert' => $alert,
            ];
        }

        return [
            'sent_count' => $sentCount,
            'suppressed_count' => $suppressedCount,
            'results' => $results,
        ];
    }

    /**
     * @param  array<string,mixed>  $alert
     */
    private function cooldownKey(array $alert): string
    {
        $dedupeKey = $this->normalizeString($alert['dedupe_key'] ?? null);
        if ($dedupeKey !== '') {
            return $dedupeKey;
        }

        $fingerprint = $this->normalizeString($alert['fingerprint'] ?? null);
        if ($fingerprint !== '') {
            return 'ops-alert:'.$fingerprint;
        }

        $fallback = hash('sha1', json_encode([
            'section' => $this->normalizeString($alert['section'] ?? null),
            'status' => $this->normalizeString($alert['status'] ?? null),
            'severity' => $this->normalizeString($alert['severity'] ?? null),
            'message' => $this->normalizeString($alert['message'] ?? null),
            'reasons' => array_values((array) ($alert['reasons'] ?? [])),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');

        return 'ops-alert:'.$fallback;
    }

    /**
     * @return array{url: string, timeout_seconds: int}
     */
    private function webhookConfig(): array
    {
        $url = $this->firstNonEmptyString([
            config('ops_alerts.channels.webhook.url'),
            config('ops_alerts.channels.slack.webhook_url'),
            config('booking.ops.alerts.webhook_url'),
            config('services.operational_alerts.webhook_url'),
        ]);

        return [
            'url' => $url,
            'timeout_seconds' => max(1, (int) ($this->firstNonEmptyScalar([
                config('ops_alerts.channels.webhook.timeout_seconds'),
                config('ops_alerts.channels.slack.timeout_seconds'),
                config('booking.ops.alerts.timeout_seconds'),
                config('services.operational_alerts.timeout_seconds'),
                5,
            ]) ?? 5)),
        ];
    }

    /**
     * @param  array<int,mixed>  $values
     */
    private function firstNonEmptyString(array $values): string
    {
        foreach ($values as $value) {
            $normalized = $this->normalizeString($value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    /**
     * @param  array<int,mixed>  $values
     */
    private function firstNonEmptyScalar(array $values): int|string|float|bool|null
    {
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function normalizeString(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
