<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiErrorResponse;
use App\Support\RequestActorContext;
use App\Support\StaffActorResolver;
use Closure;
use Illuminate\Http\Request;

class StaffApiKeyMiddleware
{
    public function __construct(
        private readonly StaffActorResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $resolved = $this->resolver->resolveFromRequest($request);
        if (! ($resolved['ok'] ?? false)) {
            $status = (int) ($resolved['status'] ?? 401);
            $message = (string) ($resolved['message'] ?? 'Unauthorized.');

            if ($status === 401) {
                return ApiErrorResponse::authenticationRequired($request, $message);
            }

            if ($status === 403) {
                return ApiErrorResponse::policyDenied($request, $message);
            }

            return ApiErrorResponse::json(
                $request,
                $status,
                (string) ($resolved['error_code'] ?? 'unauthorized'),
                $message,
            );
        }

        /** @var User $user */
        $user = $resolved['user'];
        RequestActorContext::staff(
            user: $user,
            authMode: (string) ($resolved['mode'] ?? 'mapped_key'),
            staffApiKeyId: isset($resolved['staff_api_key_id']) ? (int) $resolved['staff_api_key_id'] : null,
        )->applyToRequest($request);

        return $next($request);
    }
}
