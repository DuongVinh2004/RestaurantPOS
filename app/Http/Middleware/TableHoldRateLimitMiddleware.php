<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TableHoldRateLimitMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $sessionId = (string) ($request->input('session_id') ?? '');
        if ($sessionId === '') {
            return response()->json([
                'message' => 'Missing session_id.',
                'error'   => 'session_id_required',
            ], 422);
        }

        $ip = (string) $request->ip();

        $limit = max(1, (int) config('booking.hold_rate_limit_per_minute', 20));
        $window = max(10, (int) config('booking.hold_rate_limit_window_seconds', 60));

        $bucket = (int) floor(time() / $window);
        $key = 'rl:hold:' . hash_hmac('sha256', $sessionId . '|' . $ip, (string) config('app.key', 'app')) . ':' . $bucket;

        $cache = Cache::store('redis');

        $count = $cache->increment($key);
        if ($count === 1) {
            $cache->put($key, 1, $window + 5);
        }

        if ($count > $limit) {
            $retryAfter = $window - (time() % $window);

            Log::channel('audit')->warning('hold_rate_limited', [
                'ip' => $ip,
                'session_hash' => hash_hmac('sha256', $sessionId, (string) config('app.key', 'app')),
                'limit' => $limit,
                'window_sec' => $window,
                'path' => $request->path(),
            ]);

            return response()->json([
                'message'      => 'Too many requests. Please retry later.',
                'error'        => 'rate_limited',
                'limit'        => $limit,
                'window_sec'   => $window,
                'retry_after'  => $retryAfter,
            ], 429)->withHeaders([
                'Retry-After' => (string) $retryAfter,
            ]);
        }

        return $next($request);
    }
}
