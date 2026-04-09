<?php

namespace App\Http\Middleware;

use App\Services\MetricsService;
use Closure;
use Illuminate\Http\Request;

class MetricsRequestMiddleware
{
    public function __construct(
        private readonly MetricsService $metrics
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $t0 = microtime(true);

        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $t0) * 1000);

        // route template: v1/reservations/{id}

        $route = $request->route();
        $uri = $route?->uri() ?? $request->path();

        if ($uri === 'v1/metrics') {
            return $response;
        }

        if (! (bool) config('booking.metrics_enabled', true)) {
            return $response;
        }

        $sampleRate = (float) config('booking.metrics_sample_rate', 1.0);
        if ($sampleRate <= 0.0 || ($sampleRate < 1.0 && mt_rand() / mt_getrandmax() > $sampleRate)) {
            return $response;
        }

        try {
            $this->metrics->recordHttp(
                $request->getMethod(),
                (string) $uri,
                (int) $response->getStatusCode(),
                $durationMs
            );
        } catch (\Throwable) {
            // tuyệt đối không làm chết request vì metrics
        }

        return $response;
    }
}
