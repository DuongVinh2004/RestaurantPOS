<?php

namespace App\Platform\Health\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class OpsHeartbeatService
{
    private function key(string $name): string
    {
        return 'ops:heartbeat:' . $name;
    }

    public function touch(string $name, int $ttlSeconds = 300): void
    {
        /** @var \Illuminate\Cache\Repository $redis */
        $redis = Cache::store('redis');

        $ttlSeconds = max(30, $ttlSeconds);

        $redis->put($this->key($name), Carbon::now('UTC')->toIso8601String(), $ttlSeconds);
    }

    public function getLastRun(string $name): ?Carbon
    {
        /** @var \Illuminate\Cache\Repository $redis */
        $redis = Cache::store('redis');

        $val = $redis->get($this->key($name));
        if (! is_string($val) || $val === '') {
            return null;
        }

        try {
            return Carbon::parse($val)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
