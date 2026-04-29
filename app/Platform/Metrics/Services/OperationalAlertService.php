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
            $profile = $this->alertProfile((string) $section, $status, $reasons);

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
                'title' => (string) ($profile['title'] ?? sprintf('[%s] %s', strtoupper($status), (string) $section)),
                'message' => $message,
                'reasons' => $reasons,
                'meaning' => (string) ($profile['meaning'] ?? 'Operational snapshot reported an unhealthy section.'),
                'first_commands' => (array) ($profile['first_commands'] ?? []),
                'owner' => (string) ($profile['owner'] ?? 'Ops on-call'),
                'escalation_path' => (string) ($profile['escalation_path'] ?? 'Escalate to engineering lead if unresolved after initial triage.'),
                'runbook' => (string) ($profile['runbook'] ?? 'docs/runbooks/booking-alerting-runbook.md'),
                'context' => $this->redactContext($payload),
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

    /**
     * @param  array<int,mixed>  $reasons
     * @return array<string,mixed>
     */
    private function alertProfile(string $section, string $status, array $reasons): array
    {
        $profiles = [
            'mysql_runtime' => [
                'title' => '[CRITICAL] MySQL runtime unavailable',
                'meaning' => 'The application cannot prove the primary MySQL runtime is reachable, so deploy/data guards and runtime APIs are unsafe.',
                'owner' => 'Ops/DBA on-call',
                'escalation_path' => 'Escalate to DBA or infrastructure lead immediately if connection restoration is not obvious.',
                'first_commands' => [
                    'php artisan booking:doctor --strict --json',
                    'php artisan booking:deploy-check --mode=preflight --strict --json',
                ],
            ],
            'redis_runtime' => [
                'title' => '[CRITICAL] Redis runtime unavailable',
                'meaning' => 'Redis set/get or locking failed; distributed locks, idempotency, throttles, and scheduler heartbeat storage are degraded.',
                'owner' => 'Ops/infrastructure on-call',
                'escalation_path' => 'Escalate to infrastructure lead if Redis health or network reachability is not restored promptly.',
                'first_commands' => [
                    'php artisan booking:doctor --strict --json',
                    'php artisan booking:ops-snapshot --json',
                ],
            ],
            'scheduler_heartbeat' => [
                'title' => '[CRITICAL] Scheduler heartbeat stale',
                'meaning' => 'The scheduler heartbeat is missing or stale, so scheduled maintenance, outbox processing, and recurring jobs are not proven live.',
                'owner' => 'Ops on-call',
                'escalation_path' => 'Escalate to application owner if restarting the scheduler does not refresh the heartbeat.',
                'first_commands' => [
                    'php artisan booking:doctor --strict --json',
                    'php artisan schedule:list',
                ],
            ],
            'notification_outbox' => [
                'title' => sprintf('[%s] Notification outbox unhealthy', strtoupper($status)),
                'meaning' => 'Notification delivery has failed, stale processing, or backlog conditions that can hide customer/staff communication loss.',
                'owner' => 'Ops/app on-call',
                'escalation_path' => 'Escalate to notification/channel owner if backlog keeps growing or provider errors continue.',
                'first_commands' => [
                    'php artisan notifications:outbox-health --json',
                    'php artisan booking:doctor --strict --json',
                ],
            ],
            'payment_integrity' => [
                'title' => '[CRITICAL] Payment/refund guard failure',
                'meaning' => 'Payment or refund integrity drift was detected and can affect settlement correctness.',
                'owner' => 'Finance engineering owner',
                'escalation_path' => 'Escalate to finance engineering and data owner before accepting new settlement/refund traffic.',
                'first_commands' => [
                    'php artisan booking:deploy-check --mode=preflight --strict --json',
                    'php artisan booking:ops-snapshot --json',
                ],
            ],
            'inventory_purchasing' => [
                'title' => sprintf('[%s] Inventory guard unhealthy', strtoupper($status)),
                'meaning' => 'Inventory or purchasing lineage/backlog drift was detected and can affect stock correctness.',
                'owner' => 'Inventory operations owner',
                'escalation_path' => 'Escalate to inventory engineering/data owner if reconciliation does not identify a safe repair.',
                'first_commands' => [
                    'php artisan booking:deploy-check --mode=preflight --strict --json',
                    'php artisan booking:ops-snapshot --json',
                ],
            ],
            'kitchen_kds' => [
                'title' => sprintf('[%s] KDS health degraded', strtoupper($status)),
                'meaning' => 'Kitchen ticket backlog, routing drift, or ticket status drift may affect KDS board correctness.',
                'owner' => 'Kitchen/KDS owner',
                'escalation_path' => 'Escalate to KDS engineering owner when drift or stale backlog persists after operational triage.',
                'first_commands' => [
                    'php artisan booking:ops-snapshot --json',
                    'php artisan booking:deploy-check --mode=preflight --strict --json',
                ],
            ],
            'staff_operational_realtime' => [
                'title' => sprintf('[%s] Realtime operations degraded', strtoupper($status)),
                'meaning' => 'Realtime operational feeds are degraded, so staff boards may miss fresh table/order/KDS events.',
                'owner' => 'Realtime/platform owner',
                'escalation_path' => 'Escalate to platform owner if cache/backend readiness cannot be restored quickly.',
                'first_commands' => [
                    'php artisan booking:ops-snapshot --json',
                    'php artisan booking:doctor --strict --json',
                ],
            ],
            'table_state_audit' => [
                'title' => sprintf('[%s] Table/order audit drift', strtoupper($status)),
                'meaning' => 'Table state transitions are missing required audit context, which weakens incident reconstruction.',
                'owner' => 'FOH/platform owner',
                'escalation_path' => 'Escalate to FOH engineering owner if recent table/order transitions continue without actor/context.',
                'first_commands' => [
                    'php artisan booking:ops-snapshot --json',
                ],
            ],
            'row_version_contract' => [
                'title' => '[CRITICAL] Stale-write guard contract missing',
                'meaning' => 'One or more staff mutation surfaces are missing row_version/stale-write guard coverage.',
                'owner' => 'Platform/API owner',
                'escalation_path' => 'Escalate to API/platform owner before releasing write-path changes.',
                'first_commands' => [
                    'php artisan booking:ops-snapshot --json',
                    'php artisan booking:deploy-check --mode=preflight --strict --json',
                ],
            ],
            'branch_defaults' => [
                'title' => '[CRITICAL] Branch default drift',
                'meaning' => 'Branch defaults are missing, ambiguous, inactive, or scheduling-incomplete and can break branch-scoped operations.',
                'owner' => 'Branch operations owner',
                'escalation_path' => 'Escalate to branch/data owner if default branch repair is not immediately clear.',
                'first_commands' => [
                    'php artisan booking:ops-snapshot --json',
                    'php artisan booking:deploy-check --mode=preflight --strict --json',
                ],
            ],
        ];

        $profile = $profiles[$section] ?? [
            'title' => sprintf('[%s] %s', strtoupper($status), $section),
            'meaning' => 'Operational snapshot reported an unhealthy section: '.implode(', ', array_map('strval', $reasons)),
            'owner' => 'Ops on-call',
            'escalation_path' => 'Escalate to the service owner if the first triage commands do not identify a safe repair.',
            'first_commands' => [
                'php artisan booking:ops-snapshot --json',
            ],
        ];

        $profile['runbook'] = 'docs/runbooks/booking-alerting-runbook.md';

        return $profile;
    }

    private function redactContext(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveContextKey($key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $childKey => $childValue) {
                $redacted[$childKey] = $this->redactContext($childValue, is_string($childKey) ? $childKey : null);
            }

            return $redacted;
        }

        if (is_string($value) && strlen($value) > 500) {
            return substr($value, 0, 500).'...';
        }

        return $value;
    }

    private function isSensitiveContextKey(string $key): bool
    {
        $normalized = strtolower($key);

        if (str_ends_with($normalized, '_count') || str_ends_with($normalized, '_counts')) {
            return false;
        }

        foreach ([
            'secret',
            'token',
            'password',
            'authorization',
            'signature',
            'webhook',
            'credential',
            'private_url',
            'dsn',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return in_array($normalized, ['key', 'api_key', 'url', 'uri'], true)
            || str_ends_with($normalized, '_url')
            || str_ends_with($normalized, '_uri');
    }
}
