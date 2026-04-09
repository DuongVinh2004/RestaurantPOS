<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class AvailabilityCacheVersion
{
    private const GENERATION_KEY = 'avtbl:generation';

    public static function current(): int
    {
        $store = self::store();
        $value = $store->get(self::GENERATION_KEY, 1);
        $generation = is_numeric($value) ? (int) $value : 1;

        return $generation > 0 ? $generation : 1;
    }

    public static function bump(): int
    {
        $store = self::store();

        try {
            $next = $store->increment(self::GENERATION_KEY);
        } catch (Throwable) {
            $current = $store->get(self::GENERATION_KEY, 1);
            $next = is_numeric($current) ? ((int) $current + 1) : 2;
            $store->forever(self::GENERATION_KEY, $next);
        }

        if (! is_numeric($next) || (int) $next <= 0) {
            $store->forever(self::GENERATION_KEY, 2);
            return 2;
        }

        return (int) $next;
    }

    private static function store(): Repository
    {
        try {
            return Cache::store('redis');
        } catch (Throwable) {
            return Cache::store(config('cache.default'));
        }
    }
}
