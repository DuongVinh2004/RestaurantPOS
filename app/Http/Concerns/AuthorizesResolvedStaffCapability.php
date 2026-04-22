<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Modules\IdentityAccess\Application\Queries\StaffCapabilityResolver;
use App\Support\ApiErrorResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

trait AuthorizesResolvedStaffCapability
{
    protected function authorizeResolvedStaffCapability(Request $request, string $capability): void
    {
        $resolved = app(StaffCapabilityResolver::class)->resolveForRequest($request);
        $roleName = trim((string) ($resolved['role_name'] ?? $request->attributes->get('staff_actor_role_name', '')));
        $knownCapabilities = array_values(array_filter(array_map('strval', (array) ($resolved['known_capabilities'] ?? config('staff_capabilities.known_capabilities', [])))));

        if ((bool) config('staff_capabilities.enforce_known_capabilities', false)
            && $knownCapabilities !== []
            && ! in_array($capability, $knownCapabilities, true)) {
            $this->throwStaffCapabilityResponse($request, 500, [
                'error_code' => 'staff_capability_not_registered',
                'message' => (string) config('staff_capabilities.messages.unknown_capability', 'Staff capability contract is not registered.'),
                'required_capability' => $capability,
                'state_reason' => 'capability_contract_missing',
                'next_actions' => [
                    'register_required_capability',
                ],
            ]);
        }

        $capabilities = array_values(array_filter(array_map('strval', (array) ($resolved['capabilities'] ?? []))));
        if (! in_array('*', $capabilities, true) && ! in_array($capability, $capabilities, true)) {
            $this->throwStaffCapabilityResponse($request, 403, [
                'error_code' => 'forbidden',
                'category_code' => 'forbidden_capability',
                'message' => (string) config('staff_capabilities.messages.default', 'Forbidden.'),
                'required_capability' => $capability,
                'staff_role_name' => $roleName !== '' ? $roleName : null,
                'state_reason' => 'missing_required_capability',
                'next_actions' => [
                    'request_capability_access',
                    'use_authorized_actor',
                ],
            ]);
        }

        $request->attributes->set('staff_required_capability', $capability);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function throwStaffCapabilityResponse(Request $request, int $status, array $payload): never
    {
        $code = (string) ($payload['error_code'] ?? 'unauthorized');
        $message = (string) ($payload['message'] ?? 'Unauthorized.');
        $categoryCode = isset($payload['category_code']) ? (string) $payload['category_code'] : null;

        unset($payload['error_code'], $payload['message'], $payload['category_code']);

        throw new HttpResponseException(ApiErrorResponse::json(
            $request,
            $status,
            $code,
            $message,
            extra: ($categoryCode !== null ? ['category_code' => $categoryCode] : []) + $payload,
        ));
    }
}
