<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Internal\StaffBrowserSessionCookieFactory;
use App\Modules\IdentityAccess\Infrastructure\Tokenization\StaffApiKeyActorResolver;
use App\Support\ApiErrorResponse;
use App\Support\Auth\RequestActorContext;
use Closure;
use Illuminate\Http\Request;

class StaffRefreshSessionMiddleware
{
    public function __construct(
        private readonly StaffApiKeyActorResolver $resolver,
        private readonly StaffBrowserSessionCookieFactory $cookieFactory,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $refreshToken = $this->cookieFactory->refreshTokenFromRequest($request);
        if ($this->cookieFactory->enabled() && $refreshToken !== '') {
            if (! $this->cookieFactory->csrfMatches($request)) {
                return ApiErrorResponse::json(
                    $request,
                    419,
                    'csrf_token_mismatch',
                    'CSRF token is required for staff browser session refresh.',
                    extra: ['category_code' => 'csrf_token_mismatch'],
                );
            }

            $resolved = $this->resolver->resolveRefreshCookieToken($refreshToken);
            if (! ($resolved['ok'] ?? false)) {
                return $this->failed($request, $resolved);
            }

            /** @var User $user */
            $user = $resolved['user'];
            RequestActorContext::staff(
                user: $user,
                authMode: 'browser_refresh_cookie',
                staffApiKeyId: isset($resolved['staff_api_key_id']) ? (int) $resolved['staff_api_key_id'] : null,
            )->applyToRequest($request);

            $request->attributes->set('staff_auth_credential_source', 'refresh_cookie');
            $request->attributes->set('staff_refresh_api_key_id', (int) ($resolved['staff_api_key_id'] ?? 0));
            $request->attributes->set('staff_access_api_key_id', $this->resolveOptionalAccessKeyId($request));

            return $next($request);
        }

        $resolved = $this->resolver->resolveFromRequest($request);
        if (! ($resolved['ok'] ?? false)) {
            return $this->failed($request, $resolved);
        }

        /** @var User $user */
        $user = $resolved['user'];
        RequestActorContext::staff(
            user: $user,
            authMode: (string) ($resolved['mode'] ?? 'mapped_key'),
            staffApiKeyId: isset($resolved['staff_api_key_id']) ? (int) $resolved['staff_api_key_id'] : null,
        )->applyToRequest($request);

        $request->attributes->set('staff_auth_credential_source', 'staff_api_key');

        return $next($request);
    }

    /**
     * @param  array<string,mixed>  $resolved
     */
    private function failed(Request $request, array $resolved)
    {
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

    private function resolveOptionalAccessKeyId(Request $request): ?int
    {
        $provided = $this->resolver->extractProvidedKey($request);
        if ($provided === '') {
            return null;
        }

        $resolved = $this->resolver->resolveFromProvidedKey($provided);

        return ($resolved['ok'] ?? false) && isset($resolved['staff_api_key_id'])
            ? (int) $resolved['staff_api_key_id']
            : null;
    }
}
