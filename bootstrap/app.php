<?php

use App\Http\Middleware\AuditRequestMiddleware;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\RedisThrottleMiddleware;
use App\Http\Middleware\RequestCorrelationIdMiddleware;
use App\Http\Middleware\RequireRedisCacheMiddleware;
use App\Http\Middleware\RequireStaffCapability;
use App\Http\Middleware\TableHoldRateLimitMiddleware;
use App\Support\ApiErrorResponse;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        $makeError = static fn (Request $request, int $status, string $code, string $message, array $details = []) => ApiErrorResponse::json(
            $request,
            $status,
            $code,
            $message,
            $details,
        );

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi, $makeError) {
            if (! $isApi($request)) {
                return null;
            }

            return $makeError(
                $request,
                422,
                'validation_error',
                'Validation error.',
                ['errors' => $e->errors()],
            );
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($isApi, $makeError) {
            if (! $isApi($request)) {
                return null;
            }

            return $makeError($request, 404, 'not_found', 'Resource not found.');
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi, $makeError) {
            if (! $isApi($request)) {
                return null;
            }

            $status = (int) $e->getStatusCode();
            $defaultMessage = match ($status) {
                401 => 'Unauthorized.',
                403 => 'Forbidden.',
                404 => 'Endpoint not found.',
                409 => 'The record changed or conflicts with the current state.',
                429 => 'Too many requests.',
                default => 'HTTP error.',
            };
            $code = match ($status) {
                401 => 'unauthorized',
                403 => 'forbidden',
                404 => 'not_found',
                409 => 'conflict',
                429 => 'rate_limited',
                default => 'http_error',
            };

            return $makeError(
                $request,
                $status,
                $code,
                $e->getMessage() !== '' ? $e->getMessage() : $defaultMessage,
            );
        });

        $exceptions->render(function (QueryException $e, Request $request) use ($isApi, $makeError) {
            if (! $isApi($request)) {
                return null;
            }

            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                $errors = $mapped->errors();

                return $makeError(
                    $request,
                    409,
                    array_key_exists('row_version', $errors) ? 'stale_row_version' : 'conflict',
                    'The record changed or conflicts with the current state.',
                    ['errors' => $errors],
                );
            }

            return $makeError($request, 500, 'database_error', 'Database query error.');
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($isApi, $makeError) {
            if (! $isApi($request)) {
                return null;
            }

            return $makeError($request, 500, 'internal_error', 'Internal server error.');
        });
    })
    ->create();
