<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditRequestMiddleware
{
    /**
     * Sensitive field names that should be redacted from logs.
     * @var list<string>
     */
    private const SENSITIVE_FIELDS = [
        'session_id', 'token', 'access_token', 'refresh_token', 'api_key',
        'idempotency_key', 'password', 'pin', 'phone', 'email', 'card_number',
        'card_cvv', 'ssn', 'tax_id', 'bank_account', 'secret', 'x_customer_token',
        'x_staff_key', 'x_session_id', 'authorization',
    ];

    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $requestId = (string) ($request->attributes->get('request_id') ?? '');

        // Redact sensitive fields from request body/JSON
        $requestPayload = $this->extractAndRedactPayload($request);

        Log::channel('audit')->info('http_request', [
            'request_id'   => $requestId !== '' ? $requestId : null,
            'method'       => $request->getMethod(),
            'path'         => $request->path(),
            'query'        => $this->redactArray($request->query()),
            'request_payload_summary' => $requestPayload,
            'response_status' => $response->getStatusCode(),
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
     * Extract and redact the request payload (JSON or form data).
     * @return array<string,mixed>|null
     */
    private function extractAndRedactPayload(Request $request): ?array
    {
        // Only log for mutation operations to reduce noise and avoid logging large payloads
        if (! in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return null;
        }

        $payload = [];

        // Extract from JSON or form data
        if ($request->isJson()) {
            $decoded = $request->json()->all();
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        } else {
            $payload = $request->post();
        }

        if (empty($payload)) {
            return null;
        }

        // Redact sensitive fields. Keep structure for audit trail visibility.
        return $this->redactArray($payload);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function redactArray(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $normalized = strtolower((string) $key);
            $isSensitive = in_array($normalized, self::SENSITIVE_FIELDS, true)
                || str_contains($normalized, 'token')
                || str_contains($normalized, 'session')
                || str_contains($normalized, 'password')
                || str_contains($normalized, 'secret')
                || str_contains($normalized, 'key');

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
