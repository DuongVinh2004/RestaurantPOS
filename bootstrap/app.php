<?php

use App\Http\Concerns\MapsFrameworkValidationFailure;
use App\Http\Middleware\AuditRequestMiddleware;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\NormalizeApiJsonResponseEncodingMiddleware;
use App\Http\Middleware\RedisThrottleMiddleware;
use App\Http\Middleware\RequestCorrelationIdMiddleware;
use App\Http\Middleware\RequireRedisCacheMiddleware;
use App\Http\Middleware\RequireStaffCapability;
use App\Http\Middleware\TableHoldRateLimitMiddleware;
use App\Support\ApiErrorCategory;
use App\Support\ApiErrorResponse;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Make the split-frontend CORS contract explicit in the bootstrap path.
        $middleware->append(HandleCors::class);
        $middleware->append(NormalizeApiJsonResponseEncodingMiddleware::class);

        $middleware->alias([
            'reqid' => RequestCorrelationIdMiddleware::class,
            'idempotency' => IdempotencyMiddleware::class,
            'hold.ratelimit' => TableHoldRateLimitMiddleware::class,
            'audit.request' => AuditRequestMiddleware::class,
            'require.redis' => RequireRedisCacheMiddleware::class,
            'redis.throttle' => RedisThrottleMiddleware::class,
            'staff.capability' => RequireStaffCapability::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = function (Request $request): bool {
            return str_starts_with($request->path(), 'api/')
                || $request->expectsJson()
                || $request->is('api/*');
        };

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            $classified = MapsFrameworkValidationFailure::classify($e);
            $errors = $e->errors();

            if (($classified['category_code'] ?? null) === ApiErrorCategory::STALE_WRITE) {
                return ApiErrorResponse::staleWrite(
                    $request,
                    $errors,
                    (string) ($classified['message'] ?? 'The resource was modified by another writer. Reload data and try again.'),
                    (array) ($classified['extra'] ?? []),
                );
            }

            if (($classified['category_code'] ?? null) === ApiErrorCategory::IDEMPOTENCY_CONFLICT) {
                return ApiErrorResponse::idempotencyConflict(
                    $request,
                    (string) ($classified['message'] ?? 'This idempotency key conflicts with an earlier request.'),
                    ['errors' => $errors] + (array) ($classified['extra'] ?? []),
                );
            }

            if (($classified['category_code'] ?? null) === ApiErrorCategory::DOMAIN_INVARIANT_VIOLATION) {
                return ApiErrorResponse::domainInvariantViolation(
                    $request,
                    $errors,
                    (string) ($classified['message'] ?? 'The requested action violates a business rule.'),
                    (array) ($classified['extra'] ?? []),
                );
            }

            return ApiErrorResponse::validation(
                $request,
                $errors,
                (string) ($classified['message'] ?? 'Validation error.'),
                (array) ($classified['extra'] ?? []),
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiErrorResponse::authenticationRequired($request, 'Authentication is required.');
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiErrorResponse::policyDenied(
                $request,
                $e->getMessage() !== '' ? $e->getMessage() : 'Access to this API operation is denied by policy.',
            );
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiErrorResponse::notFound($request, 'Resource not found.');
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            $status = (int) $e->getStatusCode();
            $message = $e->getMessage();

            return match ($status) {
                401 => ApiErrorResponse::authenticationRequired(
                    $request,
                    $message !== '' ? $message : 'Authentication is required.',
                ),
                403 => ApiErrorResponse::policyDenied(
                    $request,
                    $message !== '' ? $message : 'Access to this API operation is denied by policy.',
                ),
                404 => ApiErrorResponse::notFound(
                    $request,
                    $message !== '' ? $message : 'Endpoint not found.',
                ),
                409 => ApiErrorResponse::resourceConflict(
                    $request,
                    $message !== '' ? $message : 'The record changed or conflicts with the current state.',
                ),
                429 => ApiErrorResponse::json(
                    $request,
                    429,
                    'rate_limited',
                    $message !== '' ? $message : 'Too many requests.',
                    extra: ['category_code' => ApiErrorCategory::RATE_LIMITED],
                ),
                default => ApiErrorResponse::json(
                    $request,
                    $status,
                    'http_error',
                    $message !== '' ? $message : 'HTTP error.',
                    extra: ['category_code' => ApiErrorCategory::HTTP_ERROR],
                ),
            };
        });

        $exceptions->render(function (QueryException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                $errors = $mapped->errors();

                if (array_key_exists('row_version', $errors)) {
                    return ApiErrorResponse::staleWrite($request, $errors);
                }

                if (array_key_exists('idempotency_key', $errors)) {
                    return ApiErrorResponse::idempotencyConflict(
                        $request,
                        'This idempotency key conflicts with an earlier request.',
                        [
                            'errors' => $errors,
                            'conflict_type' => 'idempotency_replay',
                            'replay_state' => 'already_used',
                            'state_reason' => 'idempotency_key_already_used',
                            'next_actions' => [
                                'retry_with_new_idempotency_key',
                            ],
                        ],
                    );
                }

                return ApiErrorResponse::resourceConflict(
                    $request,
                    'The record changed or conflicts with the current state.',
                    ['errors' => $errors],
                    [
                        'conflict_type' => 'state_conflict',
                        'state_reason' => 'constraint_violation',
                        'next_actions' => [
                            'reload_resource',
                            'retry_with_current_state',
                        ],
                    ],
                );
            }

            return ApiErrorResponse::json(
                $request,
                500,
                'database_error',
                'Database query error.',
                extra: ['category_code' => ApiErrorCategory::DATABASE_ERROR],
            );
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiErrorResponse::internalError($request);
        });

        $exceptions->reportable(function (Throwable $e) {
            if (app()->bound('sentry')) {
                Integration::captureUnhandledException($e);
            }
        });
    })
    ->create();
