<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\CustomerAuthTokenResolver;
use App\Support\RequestActorContext;
use Closure;
use Illuminate\Http\Request;

class ResolveCustomerAuthMiddleware
{
    public function __construct(
        private readonly CustomerAuthTokenResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $context = $this->resolver->resolveContextFromRequest($request);
        $user = $context['user'] ?? null;

        if (! $user instanceof User) {
            $user = $this->resolver->normalizeAllowedCustomerUser($request->user());
            if ($user instanceof User) {
                $context['user'] = $user;
                $context['auth_mode'] = $context['auth_mode'] ?? 'customer_guard';
            }
        }

        if ($user instanceof User) {
            RequestActorContext::customerOwner(
                user: $user,
                authMode: isset($context['auth_mode']) ? (string) $context['auth_mode'] : 'customer_account',
                sessionId: isset($context['session_id']) ? (string) $context['session_id'] : null,
                customerAccessSessionId: isset($context['access_session_id']) ? (int) $context['access_session_id'] : null,
                guestName: isset($context['guest_name']) ? (string) $context['guest_name'] : null,
                phone: isset($context['phone']) ? (string) $context['phone'] : null,
            )->applyToRequest($request);

            return $next($request);
        }

        if (($context['session_id'] ?? null) !== null) {
            RequestActorContext::customerSession(
                sessionId: (string) $context['session_id'],
                authMode: isset($context['auth_mode']) ? (string) $context['auth_mode'] : 'customer_session',
                customerAccessSessionId: isset($context['access_session_id']) ? (int) $context['access_session_id'] : null,
                guestName: isset($context['guest_name']) ? (string) $context['guest_name'] : null,
                phone: isset($context['phone']) ? (string) $context['phone'] : null,
            )->applyToRequest($request);
        }

        return $next($request);
    }
}
