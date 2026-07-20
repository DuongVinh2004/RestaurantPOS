<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AuditTrail\AuditPayloadSanitizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class AuditRequestMiddleware
{
    public function __construct(
        private readonly AuditPayloadSanitizer $sanitizer,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $requestId = (string) ($request->attributes->get('request_id') ?? '');

        $context = $this->sanitizer->sanitize([
            'request_id' => $requestId !== '' ? $requestId : null,
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'query' => $request->query(),
            'request_payload_summary' => $this->extractMutationPayload($request),
            'response_status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->user_id,
            'staff_actor_user_id' => $request->attributes->get('staff_actor_user_id'),
            'staff_actor_role_id' => $request->attributes->get('staff_actor_role_id'),
            'staff_actor_role_name' => $request->attributes->get('staff_actor_role_name'),
            'staff_auth_mode' => $request->attributes->get('staff_auth_mode'),
            'staff_required_capability' => $request->attributes->get('staff_required_capability'),
            'staff_capability_resolution_source' => $request->attributes->get('staff_capability_resolution_source'),
            'user_agent' => Str::limit((string) $request->userAgent(), 180),
        ]);

        Log::channel('audit')->info('http_request', $context);

        return $response;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function extractMutationPayload(Request $request): ?array
    {
        if (! in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return null;
        }

        $payload = $request->isJson() ? $request->json()->all() : $request->post();

        return $payload !== [] ? $payload : null;
    }
}
