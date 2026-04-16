<?php

namespace App\Platform\Health\Http;

use App\Platform\Health\Services\OpsHeartbeatService;
use App\Platform\Metrics\Services\OperationalInsightsService;
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
        private readonly OperationalInsightsService $operationalInsights,
    ) {}

    public function health(): JsonResponse
    {
        $snapshot = $this->runtimeSnapshot();

        return response()->json(
            [
                'status' => $snapshot['status'],
                'service' => (string) config('app.name', 'RestaurantPOS'),
                'timestamp_utc' => (string) $snapshot['timestamp_utc'],
            ],
            $this->statusCode((string) $snapshot['status']),
        );
    }

    public function detailed(): JsonResponse
    {
        $snapshot = $this->runtimeSnapshot();
        $checks = (array) $snapshot['checks'];
        $status = (string) $snapshot['status'];

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
            $checks['kitchen_kds'] = $ops['kitchen_kds'] ?? [];
            $checks['staff_operational_realtime'] = $ops['staff_operational_realtime'] ?? [];
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
                (string) ($checks['kitchen_kds']['status'] ?? 'ok'),
                (string) ($checks['staff_operational_realtime']['status'] ?? 'ok'),
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

        return response()->json([
            'status' => $status,
            'checks' => $checks,
            'meta' => [
                'request_id' => (string) $snapshot['request_id'],
                'timestamp_utc' => (string) $snapshot['timestamp_utc'],
                'app_env' => (string) config('app.env'),
            ],
        ], $this->statusCode($status));
    }

    public function redis(): JsonResponse
    {
        $snapshot = $this->redisSnapshot();

        return response()->json([
            'status' => $snapshot['status'],
            'checks' => [
                'redis_store' => $snapshot['check'],
            ],
            'meta' => [
                'request_id' => (string) $snapshot['request_id'],
                'timestamp_utc' => (string) $snapshot['timestamp_utc'],
            ],
        ], $this->statusCode((string) $snapshot['status']));
    }

    /**
     * @return array{
     *   request_id:string,
     *   status:string,
     *   checks:array<string,mixed>,
     *   timestamp_utc:string
     * }
     */
    private function runtimeSnapshot(): array
    {
        $requestId = (string) (request()->attributes->get('request_id') ?? Str::uuid());
        $timestampUtc = now('UTC')->toIso8601String();

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

        return [
            'request_id' => $requestId,
            'status' => $status,
            'checks' => $checks,
            'timestamp_utc' => $timestampUtc,
        ];
    }

    /**
     * @return array{
     *   request_id:string,
     *   status:string,
     *   check:array<string,mixed>,
     *   timestamp_utc:string
     * }
     */
    private function redisSnapshot(): array
    {
        $requestId = (string) (request()->attributes->get('request_id') ?? Str::uuid());
        $timestampUtc = now('UTC')->toIso8601String();

        $check = [
            'ok' => false,
            'reason' => null,
            'set_get_ok' => false,
            'lock_ok' => false,
            'error' => null,
        ];
        $status = 'ok';

        try {
            /** @var Repository $redis */
            $redis = Cache::store('redis');
            $check['ok'] = true;

            $key = 'health:redis:'.$requestId;
            $redis->put($key, 'pong', 10);
            $setGetOk = ($redis->get($key) === 'pong');
            $check['set_get_ok'] = $setGetOk;

            $lock = $redis->lock('health:redis-lock:'.$requestId, 3);
            $acquired = $lock->get();
            if ($acquired) {
                $lock->release();
            }
            $lockOk = (bool) $acquired;
            $check['lock_ok'] = $lockOk;

            if (! $setGetOk || ! $lockOk) {
                $status = 'degraded';
            }

            $check = array_merge($check, [
                'ok' => $status === 'ok',
                'reason' => $setGetOk ? ($lockOk ? null : 'redis_lock_failed') : 'redis_set_get_failed',
            ]);
        } catch (Throwable $e) {
            $status = 'fail';
            $check = [
                'ok' => false,
                'reason' => 'redis_unavailable',
                'set_get_ok' => false,
                'lock_ok' => false,
                'error' => $e->getMessage(),
            ];
        }

        return [
            'request_id' => $requestId,
            'status' => $status,
            'check' => $check,
            'timestamp_utc' => $timestampUtc,
        ];
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

    private function statusCode(string $status): int
    {
        return $status === 'fail' ? 503 : 200;
    }
}
