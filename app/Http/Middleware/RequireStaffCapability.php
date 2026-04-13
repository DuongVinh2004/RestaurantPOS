<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiErrorResponse;
use App\Support\StaffCapabilityResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStaffCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $resolved = app(StaffCapabilityResolver::class)->resolveForRequest($request);
        $roleName = trim((string) ($resolved['role_name'] ?? $request->attributes->get('staff_actor_role_name', '')));
        $knownCapabilities = array_values(array_filter(array_map('strval', (array) ($resolved['known_capabilities'] ?? config('staff_capabilities.known_capabilities', [])))));

        if ((bool) config('staff_capabilities.enforce_known_capabilities', false)
            && $knownCapabilities !== []
            && ! in_array($capability, $knownCapabilities, true)) {
            return ApiErrorResponse::json(
                $request,
                500,
                'staff_capability_not_registered',
                (string) config('staff_capabilities.messages.unknown_capability', 'Staff capability contract is not registered.'),
                extra: [
                    'required_capability' => $capability,
                    'state_reason' => 'capability_contract_missing',
                    'next_actions' => [
                        'register_required_capability',
                    ],
                ],
            );
        }

        $capabilities = array_values(array_filter(array_map('strval', (array) ($resolved['capabilities'] ?? []))));

        if (! in_array('*', $capabilities, true) && ! in_array($capability, $capabilities, true)) {
            return ApiErrorResponse::json(
                $request,
                403,
                'forbidden',
                (string) config('staff_capabilities.messages.default', 'Forbidden.'),
                extra: [
                    'required_capability' => $capability,
                    'staff_role_name' => $roleName !== '' ? $roleName : null,
                    'state_reason' => 'missing_required_capability',
                    'next_actions' => [
                        'request_capability_access',
                        'use_authorized_actor',
                    ],
                ],
            );
        }

        $request->attributes->set('staff_required_capability', $capability);

        return $next($request);
    }
}
