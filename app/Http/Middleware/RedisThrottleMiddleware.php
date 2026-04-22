<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RedisThrottleMiddleware
{
    public function handle(Request $request, Closure $next, string $scope = 'default', int $limit = 60, int $windowSeconds = 60, string $identity = 'ip')
    {
        $limit = max(1, (int) $limit);
        $windowSeconds = max(10, (int) $windowSeconds);

        $id = $this->resolveIdentity($request, $identity);

        $bucket = (int) floor(time() / $windowSeconds);
        $key = 'rt:'.$scope.':'.sha1($id).':'.$bucket;

        // Use Redis store explicitly (HA)
        $cache = Cache::store('redis');

        $count = $cache->increment($key);
        if ($count === 1) {
            $cache->put($key, 1, $windowSeconds + 5);
        }

        if ($count > $limit) {
            $retryAfter = $windowSeconds - (time() % $windowSeconds);

            Log::channel('audit')->warning('redis_throttle_limited', [
                'scope' => $scope,
                'identity' => $identity,
                'limit' => $limit,
                'window_sec' => $windowSeconds,
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->user_id,
                'staff_actor_user_id' => $request->attributes->get('staff_actor_user_id'),
            ]);

            return response()->json([
                'message' => 'Too many requests. Please retry later.',
                'error' => 'rate_limited',
                'scope' => $scope,
                'limit' => $limit,
                'window_sec' => $windowSeconds,
                'retry_after' => $retryAfter,
            ], 429)->withHeaders([
                'Retry-After' => (string) $retryAfter,
            ]);
        }

        return $next($request);
    }

    private function resolveIdentity(Request $request, string $mode): string
    {
        $mode = strtolower(trim($mode));

        if ($mode === 'either') {
            $uid = $request->user()?->user_id;
            if ($uid) {
                return 'u:'.(int) $uid;
            }

            $staffActorUserId = $request->attributes->get('staff_actor_user_id');
            if ($staffActorUserId) {
                return 's:'.(int) $staffActorUserId;
            }

            $sessionId = trim((string) ($request->input('session_id') ?? $request->query('session_id', '')));
            if ($sessionId !== '') {
                return 'sess:'.hash_hmac('sha256', $sessionId, (string) config('app.key', 'app'));
            }

            return 'ip:'.(string) $request->ip();
        }

        // default ip
        return 'ip:'.(string) $request->ip();
    }
}
