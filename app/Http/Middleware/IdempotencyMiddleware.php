<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiErrorResponse;
use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next, string $scope = 'default')
    {
        $required = (bool) config('booking.idempotency_required_for_reservations', true);

        $requiredScopes = config('booking.idempotency_required_scopes', null);
        if (is_array($requiredScopes)) {
            $required = in_array($scope, $requiredScopes, true);
        }

        $idemKey = $this->getIdempotencyKey($request);
        if ($required && ($idemKey === null || $idemKey === '')) {
            Log::channel('audit')->warning('idempotency_missing_key', [
                'scope' => $scope,
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->user_id,
            ]);

            return ApiErrorResponse::json(
                $request,
                422,
                'idempotency_key_required',
                'Missing Idempotency-Key header.',
                legacyErrorAlias: true,
                extra: [
                    'category_code' => 'validation_error',
                    'state_reason' => 'missing_idempotency_key',
                    'next_actions' => [
                        'provide_idempotency_key',
                    ],
                ],
            );
        }

        if ($idemKey === null || $idemKey === '') {
            return $next($request);
        }

        $clientKey = $this->normalizeKey($idemKey);
        if ($clientKey === '') {
            return ApiErrorResponse::json(
                $request,
                422,
                'invalid_idempotency_key',
                'Invalid Idempotency-Key.',
                legacyErrorAlias: true,
                extra: [
                    'category_code' => 'validation_error',
                    'state_reason' => 'invalid_idempotency_key',
                    'next_actions' => [
                        'retry_with_new_idempotency_key',
                    ],
                ],
            );
        }

        $identity = $this->buildIdentity($request);
        $routeFingerprint = $this->buildRouteFingerprint($request);
        $cacheKey = "idem:{$scope}:{$identity}:{$routeFingerprint}:{$clientKey}";
        $lockKey = "idem-lock:{$scope}:{$identity}:{$routeFingerprint}:{$clientKey}";
        $pendingKey = "idem-pending:{$scope}:{$identity}:{$routeFingerprint}:{$clientKey}";
        $payloadHash = $this->hashRequestPayload($request);

        /** @var Repository $cache */
        $cache = Cache::store('redis');

        $cached = $cache->get($cacheKey);
        if ($terminalResponse = $this->buildTerminalResponseFromCache($request, $scope, $identity, $clientKey, $payloadHash, $cached)) {
            return $terminalResponse;
        }

        $pending = $cache->get($pendingKey);
        if ($pendingResponse = $this->buildPendingResponseFromCache($request, $scope, $identity, $clientKey, $payloadHash, $pending)) {
            return $pendingResponse;
        }

        $ttlSeconds = max(1, (int) config('booking.idempotency_ttl_hours', 24)) * 3600;
        $lockSeconds = max(5, min($ttlSeconds, (int) config('booking.idempotency_lock_seconds', 120)));
        $pendingSeconds = max(
            $lockSeconds + 30,
            min($ttlSeconds, (int) config('booking.idempotency_pending_seconds', max(300, $lockSeconds + 30)))
        );

        $lock = $cache->lock($lockKey, $lockSeconds);
        $pendingWritten = false;

        try {
            if (! $lock->get()) {
                $cached = $cache->get($cacheKey);
                if ($terminalResponse = $this->buildTerminalResponseFromCache($request, $scope, $identity, $clientKey, $payloadHash, $cached)) {
                    return $terminalResponse;
                }

                $pending = $cache->get($pendingKey);
                if ($pendingResponse = $this->buildPendingResponseFromCache($request, $scope, $identity, $clientKey, $payloadHash, $pending)) {
                    return $pendingResponse;
                }

                Log::channel('audit')->warning('idempotency_in_progress', [
                    'scope' => $scope,
                    'identity' => $identity,
                    'key_hash' => sha1($clientKey),
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                    'user_id' => $request->user()?->user_id,
                ]);

                return ApiErrorResponse::idempotencyConflict(
                    $request,
                    'A request with this Idempotency-Key is already in progress.',
                    [
                        'error_code' => 'idempotency_in_progress',
                        'conflict_type' => 'idempotency_replay',
                        'replay_state' => 'in_progress',
                        'state_reason' => 'original_request_in_progress',
                        'next_actions' => [
                            'wait_for_original_request',
                            'retry_with_same_key',
                        ],
                    ],
                );
            }

            $cached = $cache->get($cacheKey);
            if ($terminalResponse = $this->buildTerminalResponseFromCache($request, $scope, $identity, $clientKey, $payloadHash, $cached)) {
                return $terminalResponse;
            }

            $pending = $cache->get($pendingKey);
            if ($pendingResponse = $this->buildPendingResponseFromCache($request, $scope, $identity, $clientKey, $payloadHash, $pending)) {
                return $pendingResponse;
            }

            $cache->put($pendingKey, [
                'state' => 'pending',
                'payload_hash' => $payloadHash,
                'started_at' => now()->toIso8601String(),
            ], $pendingSeconds);
            $pendingWritten = true;

            /** @var Response $response */
            $response = $next($request);

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                $body = $this->extractJsonBody($response);

                $cache->put($cacheKey, [
                    'status' => $status,
                    'headers' => $this->filterHeaders($response->headers->all()),
                    'body' => $body,
                    'payload_hash' => $payloadHash,
                    'stored_at' => now()->toIso8601String(),
                ], $ttlSeconds);

                $response->headers->set('Idempotency-Replayed', 'false');
            }

            if ($pendingWritten) {
                $cache->forget($pendingKey);
                $pendingWritten = false;
            }

            return $response;
        } catch (Throwable $e) {
            if ($pendingWritten) {
                $cache->forget($pendingKey);
            }

            throw $e;
        } finally {
            optional($lock)->release();
        }
    }

    private function buildTerminalResponseFromCache(Request $request, string $scope, string $identity, string $clientKey, string $payloadHash, mixed $cached): ?Response
    {
        if (
            ! is_array($cached)
            || ! array_key_exists('status', $cached)
            || ! array_key_exists('body', $cached)
            || ! array_key_exists('payload_hash', $cached)
        ) {
            return null;
        }

        if (! hash_equals((string) $cached['payload_hash'], (string) $payloadHash)) {
            Log::channel('audit')->warning('idempotency_conflict', [
                'scope' => $scope,
                'identity' => $identity,
                'key_hash' => sha1($clientKey),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->user_id,
            ]);

            return ApiErrorResponse::idempotencyConflict(
                $request,
                'This Idempotency-Key was already used for a different payload.',
                [
                    'conflict_type' => 'idempotency_payload_mismatch',
                    'replay_state' => 'payload_mismatch',
                    'state_reason' => 'key_reused_for_different_payload',
                    'next_actions' => [
                        'retry_with_new_idempotency_key',
                    ],
                ],
            );
        }

        Log::channel('audit')->info('idempotency_replay', [
            'scope' => $scope,
            'identity' => $identity,
            'key_hash' => sha1($clientKey),
            'path' => $request->path(),
            'status' => (int) $cached['status'],
            'ip' => $request->ip(),
            'user_id' => $request->user()?->user_id,
        ]);

        return $this->buildResponseFromCache($cached);
    }

    private function buildPendingResponseFromCache(Request $request, string $scope, string $identity, string $clientKey, string $payloadHash, mixed $pending): ?Response
    {
        if (! $this->isPendingMarker($pending)) {
            return null;
        }

        $payloadMatches = isset($pending['payload_hash'])
            ? hash_equals((string) $pending['payload_hash'], (string) $payloadHash)
            : null;

        Log::channel('audit')->warning($payloadMatches === false ? 'idempotency_in_progress_pending_hash_mismatch' : 'idempotency_in_progress_pending', [
            'scope' => $scope,
            'identity' => $identity,
            'key_hash' => sha1($clientKey),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->user_id,
            'started_at' => $pending['started_at'] ?? null,
        ]);

        return ApiErrorResponse::idempotencyConflict(
            $request,
            'A request with this Idempotency-Key is already in progress.',
            [
                'error_code' => 'idempotency_in_progress',
                'conflict_type' => 'idempotency_replay',
                'replay_state' => 'in_progress',
                'state_reason' => $payloadMatches === false
                    ? 'key_reused_while_original_request_in_progress'
                    : 'original_request_in_progress',
                'next_actions' => [
                    $payloadMatches === false ? 'retry_with_new_idempotency_key' : 'retry_with_same_key',
                    'wait_for_original_request',
                ],
            ],
        );
    }

    private function getIdempotencyKey(Request $request): ?string
    {
        return $request->header('Idempotency-Key')
            ?? $request->header('X-Idempotency-Key')
            ?? $request->input('idempotency_key');
    }

    private function normalizeKey(string $key): string
    {
        $k = trim($key);
        if ($k === '') {
            return '';
        }
        if (Str::length($k) > 120) {
            $k = Str::substr($k, 0, 120);
        }

        return $k;
    }

    private function buildIdentity(Request $request): string
    {
        $userId = $request->user()?->user_id;
        if ($userId) {
            return 'u'.(int) $userId;
        }

        $staffActorUserId = $request->attributes->get('staff_actor_user_id');
        if ($staffActorUserId !== null && (int) $staffActorUserId > 0) {
            return 'st'.(int) $staffActorUserId;
        }

        $sessionId = $this->resolveSessionIdentity($request);
        if ($sessionId !== '') {
            return 's'.sha1($sessionId);
        }

        return 'ip'.sha1((string) $request->ip());
    }

    private function isPendingMarker(mixed $pending): bool
    {
        if (! is_array($pending)) {
            return false;
        }

        if (($pending['state'] ?? null) === 'pending') {
            return true;
        }

        return isset($pending['payload_hash'], $pending['started_at'])
            && ! isset($pending['status'], $pending['body']);
    }

    private function resolveSessionIdentity(Request $request): string
    {
        $candidates = [
            $request->input('session_id'),
            $request->query('session_id'),
            $request->header('X-Session-Id'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function hashRequestPayload(Request $request): string
    {
        $data = $request->all();
        unset($data['idempotency_key']);

        $payload = [
            'method' => strtoupper((string) $request->method()),
            'path' => $this->resolveCanonicalRoutePath($request),
            'route' => $this->normalizeRouteParameters($request->route()?->parameters() ?? []),
            'body' => $data,
        ];

        $json = json_encode($this->ksortRecursive($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', (string) $json);
    }

    private function buildRouteFingerprint(Request $request): string
    {
        $params = $this->normalizeRouteParameters($request->route()?->parameters() ?? []);

        $json = json_encode([
            'method' => strtoupper((string) $request->method()),
            'path' => $this->resolveCanonicalRoutePath($request),
            'route' => $params,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', (string) $json);
    }

    private function resolveCanonicalRoutePath(Request $request): string
    {
        $rawUri = trim((string) ($request->route()?->uri() ?? $request->path()), '/');
        $uri = $this->normalizeRoutePath($rawUri);

        $aliases = config('booking.idempotency_route_aliases', []);
        if (is_array($aliases)) {
            foreach (array_values(array_unique([$uri, $rawUri])) as $candidate) {
                if (! isset($aliases[$candidate]) || ! is_string($aliases[$candidate]) || $aliases[$candidate] === '') {
                    continue;
                }

                return $this->normalizeRoutePath((string) $aliases[$candidate]);
            }
        }

        return $uri;
    }

    private function normalizeRoutePath(string $path): string
    {
        $path = trim($path, '/');

        if (str_starts_with($path, 'api/')) {
            return substr($path, 4);
        }

        return $path;
    }

    private function normalizeRouteParameters(array $parameters): array
    {
        $normalized = [];

        foreach ($parameters as $key => $value) {
            if (is_object($value)) {
                if (method_exists($value, 'getRouteKey')) {
                    $value = $value->getRouteKey();
                } elseif (isset($value->id)) {
                    $value = $value->id;
                } else {
                    $value = (string) $value;
                }
            }

            if (is_array($value)) {
                $value = $this->ksortRecursive($value);
            }

            $normalized[(string) $key] = $value;
        }

        ksort($normalized);

        return $normalized;
    }

    private function ksortRecursive($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if ($isAssoc) {
            ksort($value);
        }

        foreach ($value as $k => $v) {
            $value[$k] = $this->ksortRecursive($v);
        }

        return $value;
    }

    private function extractJsonBody(Response $response): mixed
    {
        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
    }

    private function filterHeaders(array $headers): array
    {
        $deny = ['set-cookie', 'cookie', 'authorization'];

        $filtered = [];
        foreach ($headers as $k => $v) {
            $lk = strtolower($k);
            if (in_array($lk, $deny, true)) {
                continue;
            }
            $filtered[$k] = $v;
        }

        return $filtered;
    }

    private function buildResponseFromCache(array $cached)
    {
        $status = (int) ($cached['status'] ?? 200);
        $body = $cached['body'] ?? null;

        if (in_array($status, [204, 205, 304], true)) {
            $res = response('', $status);
        } elseif (is_array($body) || is_object($body)) {
            $res = response()->json($body, $status);
        } elseif ($body === null) {
            $res = response('', $status);
        } else {
            $res = response((string) $body, $status);
        }

        if (! empty($cached['headers']) && is_array($cached['headers'])) {
            foreach ($cached['headers'] as $k => $values) {
                if (is_array($values)) {
                    foreach ($values as $val) {
                        $res->headers->set($k, $val, false);
                    }
                } else {
                    $res->headers->set($k, $values);
                }
            }
        }

        $res->headers->set('Idempotency-Replayed', 'true');

        return $res;
    }
}
