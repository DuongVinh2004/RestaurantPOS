<?php

namespace App\Platform\Health\Services;

use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class OpsHeartbeatService
{
    private function key(string $name): string
    {
        return 'ops:heartbeat:'.$name;
    }

    public function touch(string $name, int $ttlSeconds = 300): void
    {
        try {
            /** @var Repository $redis */
            $redis = Cache::store(config('booking.ops_heartbeat_store', 'redis'));

            $ttlSeconds = max(30, $ttlSeconds);

            $redis->put($this->key($name), Carbon::now('UTC')->toIso8601String(), $ttlSeconds);
        } catch (\Throwable) {
            // Ignore cache connection errors. The health check will report the heartbeat as missing.
        }
    }

    public function getLastRun(string $name): ?Carbon
    {
        try {
            /** @var Repository $redis */
            $redis = Cache::store(config('booking.ops_heartbeat_store', 'redis'));

            $val = $redis->get($this->key($name));
            if (! is_string($val) || $val === '') {
                return null;
            }

            return Carbon::parse($val)->utc();
        } catch (\Throwable) {
            return null; // Doctor will report as missing
        }
    }
}
