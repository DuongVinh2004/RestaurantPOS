<?php

namespace App\Modules\Reservations\Application\Services;

use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class ReservationLockService
{
    private int $lockTtlSeconds;

    private int $lockWaitSeconds;

    private string $tablePrefix;

    private string $reservationPrefix;

    public function __construct()
    {
        $this->lockTtlSeconds = max(1, (int) config('booking.reservation_lock_ttl_seconds', 20));
        $this->lockWaitSeconds = max(0, (int) config('booking.reservation_lock_wait_seconds', 5));

        $base = (string) config('booking.reservation_lock_prefix', 'booking:lock:table');
        $this->tablePrefix = rtrim($base, ':');

        $resBase = (string) config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation');
        $this->reservationPrefix = rtrim($resBase, ':');
    }

    public function withReservationLock(int $reservationId, Closure $callback): mixed
    {
        return $this->withLockKeys([
            $this->reservationPrefix.':'.$reservationId,
        ], $callback);
    }

    public function withTableLocks(array $tableIds, Closure $callback): mixed
    {
        $keys = array_map(fn (int $id) => $this->tablePrefix.':'.$id, $tableIds);

        return $this->withLockKeys($keys, $callback);
    }

    public function withLockKeys(array $keys, Closure $callback): mixed
    {
        $keys = array_values(array_unique(array_filter($keys, fn ($k) => is_string($k) && $k !== '')));
        sort($keys); // Keep lock ordering stable to reduce deadlock risk.

        /** @var Repository $redis */
        $redis = Cache::store('redis');

        $locks = [];
        try {
            foreach ($keys as $key) {
                $lock = $redis->lock($key, $this->lockTtlSeconds);
                if (! $lock->block($this->lockWaitSeconds)) {
                    throw new RuntimeException("Could not acquire lock: {$key}");
                }
                $locks[] = $lock;
            }

            return $callback();
        } finally {
            // Release in reverse order.
            for ($i = count($locks) - 1; $i >= 0; $i--) {
                try {
                    $locks[$i]->release();
                } catch (\Throwable) {
                }
            }
        }
    }
}
