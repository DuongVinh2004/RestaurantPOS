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
     *   runtime: array<string, array{ok: bool, message: string|null}>,
     *   meta: array{strict: bool, timestamp_utc: string}
     * }
     */
    public function inspect(bool $strict = false): array
    {
        $validation = $this->environmentValidator->validate();
        $runtime = [
            'db' => ['ok' => false, 'message' => null],
            'redis' => ['ok' => false, 'message' => null],
            'scheduler' => ['ok' => false, 'message' => null],
            'outbox' => ['ok' => true, 'message' => null],
        ];

        try {
            DB::select('SELECT 1');
            $runtime['db'] = ['ok' => true, 'message' => 'Database ping ok.'];
        } catch (\Throwable $exception) {
            $runtime['db'] = ['ok' => false, 'message' => $exception->getMessage()];
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
            ];
        } catch (\Throwable $exception) {
            $runtime['redis'] = ['ok' => false, 'message' => $exception->getMessage()];
        }

        try {
            $lastRun = $this->opsHeartbeatService->getLastRun('scheduler');
            if (! $lastRun) {
                $runtime['scheduler'] = ['ok' => false, 'message' => 'No scheduler heartbeat found.'];
            } else {
                $ageSeconds = Carbon::now('UTC')->diffInSeconds($lastRun);
                $staleThresholdSeconds = (int) config('booking.scheduler_heartbeat_stale_seconds', 180);
                $runtime['scheduler'] = [
                    'ok' => ($ageSeconds <= $staleThresholdSeconds),
                    'message' => sprintf('Last heartbeat %d second(s) ago.', $ageSeconds),
                ];
            }
        } catch (\Throwable $exception) {
            $runtime['scheduler'] = ['ok' => false, 'message' => $exception->getMessage()];
        }

        try {
            $snapshot = $this->notificationOutboxHealthService->snapshot();
            $runtime['outbox'] = [
                'ok' => (bool) ($snapshot['ok'] ?? false),
                'message' => sprintf(
                    'Outbox pending=%d processing=%d failed=%d stale=%d due_now=%d',
                    (int) ($snapshot['pending_count'] ?? 0),
                    (int) ($snapshot['processing_count'] ?? 0),
                    (int) ($snapshot['failed_count'] ?? 0),
                    (int) ($snapshot['stale_processing_count'] ?? 0),
                    (int) ($snapshot['due_now_count'] ?? 0),
                ),
            ];
        } catch (\Throwable $exception) {
            $runtime['outbox'] = ['ok' => false, 'message' => $exception->getMessage()];
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
}
