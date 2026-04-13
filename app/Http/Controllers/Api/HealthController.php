<?php

namespace App\Http\Controllers\Api;

use App\Services\OperationalInsightsService;
use App\Services\OpsHeartbeatService;
use App\Support\StaffActorResolver;
use Illuminate\Cache\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class HealthController
{
    public function __construct(
        private readonly OpsHeartbeatService $heartbeat,
        private readonly StaffActorResolver $staffActorResolver,
        private readonly OperationalInsightsService $operationalInsights,
    ) {}

    public function health(): JsonResponse
    {
        $requestId = (string) (request()->attributes->get('request_id') ?? Str::uuid());

        $checks = [
            'db' => [
                'ok' => false,
                'latency_ms' => null,
                'error' => null,
            ],
            'redis' => [
                'ok' => false,
                'set_get_ok' => false,
                'lock_ok' => false,
                'error' => null,
            ],
            'scheduler' => [
                'ok' => false,
                'last_run_at_utc' => null,
                'age_seconds' => null,
                'stale_threshold_seconds' => (int) config('booking.scheduler_heartbeat_stale_seconds', 180),
            ],
            'disk' => [
                'ok' => true,
                'free_bytes' => null,
                'total_bytes' => null,
                'error' => null,
            ],
        ];

        $status = 'ok';

        try {
            $t0 = microtime(true);
            DB::select('SELECT 1');
            $checks['db']['ok'] = true;
            $checks['db']['latency_ms'] = (int) round((microtime(true) - $t0) * 1000);
        } catch (Throwable $e) {
            $checks['db']['ok'] = false;
            $checks['db']['error'] = $e->getMessage();
            $status = 'fail';
        }

        try {
            /** @var Repository $redis */
            $redis = Cache::store('redis');
            $checks['redis']['ok'] = true;

            $key = 'health:redis:'.$requestId;
            $redis->put($key, 'pong', 10);
            $checks['redis']['set_get_ok'] = ($redis->get($key) === 'pong');

            $lock = $redis->lock('health:redis-lock:'.$requestId, 3);
            $acquired = $lock->get();
            if ($acquired) {
                $lock->release();
            }
            $checks['redis']['lock_ok'] = (bool) $acquired;

            if (! $checks['redis']['set_get_ok'] || ! $checks['redis']['lock_ok']) {
                $status = $status === 'fail' ? 'fail' : 'degraded';
            }
        } catch (Throwable $e) {
            $checks['redis']['ok'] = false;
            $checks['redis']['error'] = $e->getMessage();
            $status = 'fail';
        }

        try {
            $last = $this->heartbeat->getLastRun('scheduler');
            if ($last) {
                $now = Carbon::now('UTC');
                $age = $now->diffInSeconds($last);

                $checks['scheduler']['last_run_at_utc'] = $last->toIso8601String();
                $checks['scheduler']['age_seconds'] = $age;

                $stale = (int) $checks['scheduler']['stale_threshold_seconds'];
                $checks['scheduler']['ok'] = ($age <= $stale);

                if (! $checks['scheduler']['ok']) {
                    $status = $status === 'fail' ? 'fail' : 'degraded';
                }
            } else {
                $checks['scheduler']['ok'] = false;
                $status = $status === 'fail' ? 'fail' : 'degraded';
            }
        } catch (Throwable) {
            $checks['scheduler']['ok'] = false;
            $status = $status === 'fail' ? 'fail' : 'degraded';
        }

        try {
            $path = storage_path();
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);

            if ($free !== false) {
                $checks['disk']['free_bytes'] = (int) $free;
            }
            if ($total !== false) {
                $checks['disk']['total_bytes'] = (int) $total;
            }
        } catch (Throwable $e) {
            $checks['disk']['ok'] = false;
            $checks['disk']['error'] = $e->getMessage();
        }

        $response = [
            'status' => $status,
            'checks' => $this->publicHealthChecks($checks),
            'meta' => [
                'request_id' => $requestId,
                'timestamp_utc' => now('UTC')->toIso8601String(),
            ],
        ];

        if ($this->isDetailedOpsRequest()) {
            try {
                $ops = $this->operationalInsights->snapshot();
                $checks['notification_outbox'] = $ops['notification_outbox'] ?? [];
                $checks['payment_integrity'] = $ops['payment_integrity'] ?? [];
                $checks['voucher_locks'] = $ops['voucher_locks'] ?? [];
                $checks['session_linkage'] = $ops['session_linkage'] ?? [];
                $checks['staff_api_keys'] = $ops['staff_api_keys'] ?? [];
                $checks['table_state_audit'] = $ops['table_state_audit'] ?? [];
                $checks['row_version_contract'] = $ops['row_version_contract'] ?? [];
                $checks['reporting_snapshots'] = $ops['reporting_snapshots'] ?? [];
                $checks['branch_defaults'] = $ops['branch_defaults'] ?? [];
                $checks['database_contract'] = $ops['database_contract'] ?? [];
                $status = $this->mergeOperationalStatus(
                    $status,
                    (string) ($checks['notification_outbox']['status'] ?? 'ok'),
                    (string) ($checks['payment_integrity']['status'] ?? 'ok'),
                    (string) ($checks['voucher_locks']['status'] ?? 'ok'),
                    (string) ($checks['session_linkage']['status'] ?? 'ok'),
                    (string) ($checks['staff_api_keys']['status'] ?? 'ok'),
                    (string) ($checks['table_state_audit']['status'] ?? 'ok'),
                    (string) ($checks['row_version_contract']['status'] ?? 'ok'),
                    (string) ($checks['reporting_snapshots']['status'] ?? 'ok'),
                    (string) ($checks['branch_defaults']['status'] ?? 'ok'),
                    (string) ($checks['database_contract']['status'] ?? 'ok'),
                );
            } catch (Throwable $e) {
                $checks['ops'] = [
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
                $status = $status === 'fail' ? 'fail' : 'degraded';
            }
            $response['checks'] = $checks;
            $response['status'] = $status;
            $response['meta']['app_env'] = (string) config('app.env');
        }

        $httpCode = ($status === 'fail') ? 503 : 200;

        return response()->json($response, $httpCode);
    }

    public function redis(): JsonResponse
    {
        $requestId = (string) (request()->attributes->get('request_id') ?? Str::uuid());

        $result = [
            'status' => 'ok',
            'checks' => [
                'redis_store' => [
                    'ok' => false,
                    'set_get_ok' => false,
                    'lock_ok' => false,
                    'error' => null,
                ],
            ],
            'meta' => [
                'request_id' => $requestId,
                'app_env' => (string) config('app.env'),
                'timestamp_utc' => now('UTC')->toIso8601String(),
            ],
        ];

        try {
            /** @var Repository $redis */
            $redis = Cache::store('redis');
            $result['checks']['redis_store']['ok'] = true;

            $key = 'health:redis:'.$requestId;
            $redis->put($key, 'pong', 10);
            $result['checks']['redis_store']['set_get_ok'] = ($redis->get($key) === 'pong');

            $lock = $redis->lock('health:redis-lock:'.$requestId, 3);
            $acquired = $lock->get();
            if ($acquired) {
                $lock->release();
            }
            $result['checks']['redis_store']['lock_ok'] = (bool) $acquired;

            if (! $result['checks']['redis_store']['set_get_ok'] || ! $result['checks']['redis_store']['lock_ok']) {
                $result['status'] = 'degraded';
            }

            return response()->json($result, 200);
        } catch (Throwable $e) {
            $result['status'] = 'fail';
            $result['checks']['redis_store']['error'] = $e->getMessage();

            return response()->json($result, 503);
        }
    }

    private function isDetailedOpsRequest(): bool
    {
        $resolved = $this->staffActorResolver->resolveFromRequest(request());

        return (bool) ($resolved['ok'] ?? false);
    }

    private function mergeOperationalStatus(string $baseStatus, string ...$statuses): string
    {
        $status = $baseStatus;

        foreach ($statuses as $candidate) {
            if ($candidate === 'fail') {
                return 'fail';
            }

            if ($candidate === 'degraded' && $status === 'ok') {
                $status = 'degraded';
            }
        }

        return $status;
    }

    /**
     * @param  array<string,mixed>  $checks
     * @return array<string,mixed>
     */
    private function publicHealthChecks(array $checks): array
    {
        return [
            'db' => [
                'ok' => (bool) ($checks['db']['ok'] ?? false),
                'latency_ms' => $checks['db']['latency_ms'] ?? null,
                'reason' => $this->dbReason($checks),
            ],
            'redis' => [
                'ok' => (bool) ($checks['redis']['ok'] ?? false),
                'set_get_ok' => (bool) ($checks['redis']['set_get_ok'] ?? false),
                'lock_ok' => (bool) ($checks['redis']['lock_ok'] ?? false),
                'reason' => $this->redisReason($checks),
            ],
            'scheduler' => [
                'ok' => (bool) ($checks['scheduler']['ok'] ?? false),
                'last_run_at_utc' => $checks['scheduler']['last_run_at_utc'] ?? null,
                'age_seconds' => $checks['scheduler']['age_seconds'] ?? null,
                'stale_threshold_seconds' => $checks['scheduler']['stale_threshold_seconds'] ?? null,
                'reason' => $this->schedulerReason($checks),
            ],
            'disk' => [
                'ok' => (bool) ($checks['disk']['ok'] ?? false),
                'free_bytes' => $checks['disk']['free_bytes'] ?? null,
                'total_bytes' => $checks['disk']['total_bytes'] ?? null,
                'reason' => $this->diskReason($checks),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $checks
     */
    private function dbReason(array $checks): ?string
    {
        return (bool) ($checks['db']['ok'] ?? false) ? null : 'db_unavailable';
    }

    /**
     * @param  array<string,mixed>  $checks
     */
    private function redisReason(array $checks): ?string
    {
        if ((bool) ($checks['redis']['ok'] ?? false)) {
            if (! (bool) ($checks['redis']['set_get_ok'] ?? false)) {
                return 'redis_set_get_failed';
            }

            if (! (bool) ($checks['redis']['lock_ok'] ?? false)) {
                return 'redis_lock_failed';
            }

            return null;
        }

        return 'redis_unavailable';
    }

    /**
     * @param  array<string,mixed>  $checks
     */
    private function schedulerReason(array $checks): ?string
    {
        if ((bool) ($checks['scheduler']['ok'] ?? false)) {
            return null;
        }

        if (($checks['scheduler']['last_run_at_utc'] ?? null) === null) {
            return 'scheduler_heartbeat_missing';
        }

        return 'scheduler_heartbeat_stale';
    }

    /**
     * @param  array<string,mixed>  $checks
     */
    private function diskReason(array $checks): ?string
    {
        return (bool) ($checks['disk']['ok'] ?? true) ? null : 'disk_probe_failed';
    }
}
