<?php

namespace App\Http\Middleware;

use App\Support\ApiErrorCategory;
use App\Support\ApiErrorResponse;
use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RequireRedisCacheMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $required = (bool) config('booking.require_redis_for_booking_api', true);
        if (! $required) {
            return $next($request);
        }

        try {
            /** @var Repository $repo */
            $repo = Cache::store('redis');

            $lock = $repo->lock('redis-healthcheck-lock', 2);

            $acquired = $lock->get();
            if ($acquired) {
                $lock->release();
            }

            return $next($request);
        } catch (Throwable $e) {
            Log::channel('audit')->error('redis_required_but_unavailable', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'exception_class' => $e::class,
            ]);

            return ApiErrorResponse::json(
                $request,
                503,
                'redis_required',
                'Redis cache is required for this API in HA mode, but it is not available.',
                ['dependency' => 'redis'],
                extra: [
                    'category_code' => ApiErrorCategory::DEPENDENCY_UNAVAILABLE,
                    'state_reason' => 'redis_unavailable',
                    'next_actions' => [
                        'retry_after_dependency_recovery',
                    ],
                ],
            );
        }
    }
}
