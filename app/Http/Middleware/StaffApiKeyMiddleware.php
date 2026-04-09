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
            return ApiErrorResponse::json(
                $request,
                (int) ($resolved['status'] ?? 401),
                (string) ($resolved['error_code'] ?? 'unauthorized'),
                (string) ($resolved['message'] ?? 'Unauthorized.'),
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
