<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RequestCorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $incoming = $request->header('X-Request-Id');

        $requestId = $this->sanitizeRequestId($incoming);
        if ($requestId === null) {
            $requestId = (string) Str::uuid();
        }

        $request->attributes->set('request_id', $requestId);

        // add to log context for the whole request
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);

        if (! $response->headers->has('X-Request-Id')) {
            $response->headers->set('X-Request-Id', $requestId);
        }

        return $response;
    }

    private function sanitizeRequestId(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $v = trim($value);
        if ($v === '') {
            return null;
        }

        // limit length to prevent abuse
        if (Str::length($v) > 80) {
            $v = Str::substr($v, 0, 80);
        }

        // allow simple charset only
        if (! preg_match('/^[a-zA-Z0-9\-\_\.]+$/', $v)) {
            return null;
        }

        return $v;
    }
}
