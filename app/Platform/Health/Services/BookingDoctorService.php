<?php

declare(strict_types=1);

namespace App\Platform\Health\Services;

use App\Modules\Notifications\Application\Services\NotificationOutboxHealthService;
use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BookingDoctorService
{
    public function __construct(
        private readonly BookingEnvironmentValidator $environmentValidator,
        private readonly OpsHeartbeatService $opsHeartbeatService,
        private readonly NotificationOutboxHealthService $notificationOutboxHealthService,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   validation: array{
     *     ok: bool,
     *     errors: list<string>,
     *     warnings: list<string>,
     *     checks: array<string, array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}>
     *   },
     *   runtime: array<string, array{
     *     ok: bool,
     *     message: string|null,
     *     status?: string,
     *     dependency?: string|null,
     *     meta?: array<string, mixed>
     *   }>,
     *   meta: array{strict: bool, timestamp_utc: string}
     * }
     */
    public function inspect(bool $strict = false): array
    {
        $validation = $this->environmentValidator->validate();
        $runtime = [
            'db' => ['ok' => false, 'message' => null, 'status' => 'fail', 'dependency' => null],
            'redis' => ['ok' => false, 'message' => null, 'status' => 'fail', 'dependency' => null],
            'scheduler' => ['ok' => false, 'message' => null, 'status' => 'fail', 'dependency' => null],
            'outbox' => ['ok' => true, 'message' => null, 'status' => 'pass', 'dependency' => null],
        ];

        try {
            DB::select('SELECT 1');
            $runtime['db'] = $this->runtimePass('Database ping ok.');
        } catch (\Throwable $exception) {
            $runtime['db'] = $this->runtimeFail($exception->getMessage(), [
                'probe' => 'db_select_1',
            ]);
        }

        try {
            /** @var Repository $redis */
            $redis = Cache::store('redis');
            $key = 'doctor:redis:'.now('UTC')->format('YmdHis').':'.random_int(1000, 9999);
            $redis->put($key, 'pong', 10);
            $valueOk = ($redis->get($key) === 'pong');
            $lock = $redis->lock('doctor:redis-lock:'.$key, 3);
            $lockOk = $lock->get();
            if ($lockOk) {
                $lock->release();
            }

            $runtime['redis'] = [
                'ok' => ($valueOk && (bool) $lockOk),
                'message' => ($valueOk && (bool) $lockOk)
                    ? 'Redis set/get and lock ok.'
                    : 'Redis responded but set/get or lock acquisition failed.',
                'status' => ($valueOk && (bool) $lockOk) ? 'pass' : 'fail',
                'dependency' => null,
                'meta' => [
                    'probe' => 'cache_store_redis',
                ],
            ];
        } catch (\Throwable $exception) {
            $runtime['redis'] = $this->runtimeFail($exception->getMessage(), [
                'probe' => 'cache_store_redis',
            ]);
        }

        if (! ($runtime['redis']['ok'] ?? false)) {
            $runtime['scheduler'] = $this->runtimeDependencyBlocked(
                dependency: 'redis',
                message: 'Blocked by runtime.redis failure; scheduler heartbeat is stored in Redis and could not be read.',
                meta: [
                    'upstream_message' => (string) ($runtime['redis']['message'] ?? ''),
                ],
            );
        } else {
            try {
                $lastRun = $this->opsHeartbeatService->getLastRun('scheduler');
                if (! $lastRun) {
                    $runtime['scheduler'] = $this->runtimeFail(
                        'Scheduler heartbeat is missing. Start the scheduler worker, confirm routes/console/schedule.php touches ops:heartbeat:scheduler, then rerun booking:doctor.',
                        [
                            'probe' => 'ops_heartbeat_scheduler',
                            'state_reason' => 'scheduler_heartbeat_missing',
                        ],
                    );
                } else {
                    $ageSeconds = max(0, Carbon::now('UTC')->getTimestamp() - $lastRun->getTimestamp());
                    $staleThresholdSeconds = (int) config('booking.scheduler_heartbeat_stale_seconds', 180);
                    $runtime['scheduler'] = $ageSeconds <= $staleThresholdSeconds
                        ? $this->runtimePass(
                            sprintf('Last heartbeat %d second(s) ago.', $ageSeconds),
                            [
                                'age_seconds' => $ageSeconds,
                                'stale_threshold_seconds' => $staleThresholdSeconds,
                            ],
                        )
                        : $this->runtimeFail(
                            sprintf(
                                'Scheduler heartbeat is stale: last heartbeat %d second(s) ago; stale threshold is %d second(s). Restart the scheduler worker and check schedule/queue logs.',
                                $ageSeconds,
                                $staleThresholdSeconds
                            ),
                            [
                                'age_seconds' => $ageSeconds,
                                'stale_threshold_seconds' => $staleThresholdSeconds,
                                'state_reason' => 'scheduler_heartbeat_stale',
                            ],
                        );
                }
            } catch (\Throwable $exception) {
                $runtime['scheduler'] = $this->runtimeFail($exception->getMessage(), [
                    'probe' => 'ops_heartbeat_scheduler',
                ]);
            }
        }

        $outboxEnabled = (bool) config('notifications.outbox.enabled', true);
        if (! $outboxEnabled) {
            $runtime['outbox'] = $this->runtimePass('Notification outbox is disabled for this runtime.', [
                'enabled' => false,
            ]);
        } elseif (! ($runtime['db']['ok'] ?? false)) {
            $runtime['outbox'] = $this->runtimeDependencyBlocked(
                dependency: 'db',
                message: 'Blocked by runtime.db failure; notification outbox health is database-backed and could not be inspected.',
                meta: [
                    'upstream_message' => (string) ($runtime['db']['message'] ?? ''),
                ],
            );
        } else {
            try {
                $snapshot = $this->notificationOutboxHealthService->snapshot();
                $runtime['outbox'] = (bool) ($snapshot['ok'] ?? false)
                    ? $this->runtimePass($this->formatOutboxMessage($snapshot), $this->outboxMeta($snapshot))
                    : $this->runtimeFail($this->formatOutboxMessage($snapshot), $this->outboxMeta($snapshot));
            } catch (\Throwable $exception) {
                $runtime['outbox'] = $this->runtimeFail($exception->getMessage(), [
                    'probe' => 'notification_outbox_health',
                ]);
            }
        }

        $hasRuntimeFailure = collect($runtime)->contains(static fn (array $item): bool => ! ($item['ok'] ?? false));
        $hasWarnings = ! empty($validation['warnings']);
        $ok = ! $hasRuntimeFailure && ($validation['ok'] ?? false) && (! $strict || ! $hasWarnings);

        return [
            'ok' => $ok,
            'validation' => $validation,
            'runtime' => $runtime,
            'meta' => [
                'strict' => $strict,
                'timestamp_utc' => now('UTC')->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{ok: bool, message: string, status: string, dependency: null, meta?: array<string, mixed>}
     */
    private function runtimePass(string $message, array $meta = []): array
    {
        $result = [
            'ok' => true,
            'message' => $message,
            'status' => 'pass',
            'dependency' => null,
        ];

        if ($meta !== []) {
            $result['meta'] = $meta;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{ok: bool, message: string, status: string, dependency: null, meta?: array<string, mixed>}
     */
    private function runtimeFail(string $message, array $meta = []): array
    {
        $result = [
            'ok' => false,
            'message' => $message,
            'status' => 'fail',
            'dependency' => null,
        ];

        if ($meta !== []) {
            $result['meta'] = $meta;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{ok: bool, message: string, status: string, dependency: string, meta?: array<string, mixed>}
     */
    private function runtimeDependencyBlocked(string $dependency, string $message, array $meta = []): array
    {
        $result = [
            'ok' => false,
            'message' => $message,
            'status' => 'blocked_dependency',
            'dependency' => $dependency,
        ];

        if ($meta !== []) {
            $result['meta'] = $meta;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function formatOutboxMessage(array $snapshot): string
    {
        if (! (bool) ($snapshot['enabled'] ?? true)) {
            return 'Notification outbox is disabled for this runtime.';
        }

        $message = sprintf(
            'Notification outbox health: pending=%d processing=%d failed=%d stuck_processing=%d due_now=%d',
            (int) ($snapshot['pending_count'] ?? 0),
            (int) ($snapshot['processing_count'] ?? 0),
            (int) ($snapshot['failed_count'] ?? 0),
            (int) ($snapshot['stale_processing_count'] ?? 0),
            (int) ($snapshot['due_now_count'] ?? 0),
        );

        $error = trim((string) ($snapshot['error'] ?? ''));

        return $error === '' ? $message : $message.'; '.$error;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function outboxMeta(array $snapshot): array
    {
        return [
            'enabled' => (bool) ($snapshot['enabled'] ?? true),
            'pending_count' => (int) ($snapshot['pending_count'] ?? 0),
            'processing_count' => (int) ($snapshot['processing_count'] ?? 0),
            'failed_count' => (int) ($snapshot['failed_count'] ?? 0),
            'stale_processing_count' => (int) ($snapshot['stale_processing_count'] ?? 0),
            'due_now_count' => (int) ($snapshot['due_now_count'] ?? 0),
            'dead_letter_count' => (int) ($snapshot['dead_letter_count'] ?? 0),
            'recent_failure_attempt_count' => (int) ($snapshot['recent_failure_attempt_count'] ?? 0),
            'error' => $snapshot['error'] ?? null,
        ];
    }
}
