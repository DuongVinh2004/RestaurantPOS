<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditRequestMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $requestId = (string) ($request->attributes->get('request_id') ?? '');

        Log::channel('audit')->info('http_request', [
            'request_id'   => $requestId !== '' ? $requestId : null,
            'method'       => $request->getMethod(),
            'path'         => $request->path(),
            'query'        => $this->redactArray($request->query()),
            'status'       => $response->getStatusCode(),
            'duration_ms'  => $durationMs,
            'ip'           => $request->ip(),
            'user_id'      => $request->user()?->user_id,
            'staff_actor_user_id' => $request->attributes->get('staff_actor_user_id'),
            'staff_actor_role_id' => $request->attributes->get('staff_actor_role_id'),
            'staff_actor_role_name' => $request->attributes->get('staff_actor_role_name'),
            'staff_auth_mode' => $request->attributes->get('staff_auth_mode'),
            'staff_required_capability' => $request->attributes->get('staff_required_capability'),
            'staff_capability_resolution_source' => $request->attributes->get('staff_capability_resolution_source'),
            'user_agent'   => Str::limit((string) $request->userAgent(), 180),
        ]);

        return $response;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function redactArray(array $data): array
    {
        $sensitive = ['session_id', 'token', 'access_token', 'idempotency_key', 'password', 'phone', 'email'];
        $out = [];

        foreach ($data as $key => $value) {
            $normalized = strtolower((string) $key);
            $isSensitive = in_array($normalized, $sensitive, true)
                || str_contains($normalized, 'token')
                || str_contains($normalized, 'session');

            if ($isSensitive) {
                $out[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $out[$key] = $this->redactArray($value);
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }
}
