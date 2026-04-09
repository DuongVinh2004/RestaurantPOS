<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiErrorResponse;
use App\Support\CustomerSessionRouteContract;
use App\Support\RequestActorContext;
use App\Support\StaffActorResolver;
use Closure;
use Illuminate\Http\Request;

class CustomerOrStaffMiddleware
{
    public function __construct(
        private readonly StaffActorResolver $resolver,
        private readonly CustomerSessionRouteContract $customerSessionRouteContract,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $resolved = $this->resolveStaffActor($request);
        if (($resolved['ok'] ?? false) === true) {
            /** @var User $user */
            $user = $resolved['user'];

            RequestActorContext::staff(
                user: $user,
                authMode: (string) ($resolved['mode'] ?? 'mapped_key'),
                staffApiKeyId: isset($resolved['staff_api_key_id']) ? (int) $resolved['staff_api_key_id'] : null,
            )->applyToRequest($request);

            return $next($request);
        }

        if (($resolved['provided_key'] ?? false) === true) {
            return $this->unauthorizedResponse($request, $resolved);
        }

        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isCustomerOwner()) {
            $actor->applyToRequest($request);

            return $next($request);
        }

        if ($actor->isCustomerSession() && $this->customerSessionRouteContract->allows($request, $actor)) {
            $actor->applyToRequest($request);

            return $next($request);
        }

        return $this->unauthorizedResponse($request, $resolved);
    }

    /**
     * @return array{ok:bool,status:int,error_code?:string,message?:string,user?:User,mode?:string,staff_api_key_id?:int,provided_key?:bool}
     */
    private function resolveStaffActor(Request $request): array
    {
        try {
            $provided = trim((string) $this->resolver->extractProvidedKey($request));

            if ($provided !== '') {
                $resolved = $this->resolver->resolveFromProvidedKey($provided);
                $resolved['provided_key'] = true;

                return $resolved;
            }

            return [
                'ok' => false,
                'status' => 401,
                'error_code' => 'unauthorized',
                'message' => 'Unauthorized.',
                'provided_key' => false,
            ];
        } catch (\Throwable) {
            $resolved = $this->resolver->resolveFromRequest($request);
            $resolved['provided_key'] = trim((string) ($request->header('X-Staff-Key') ?? '')) !== ''
                || (stripos(trim((string) ($request->header('Authorization') ?? '')), 'Bearer ') === 0);

            return $resolved;
        }
    }

    /**
     * @param  array{status?:int,error_code?:string,message?:string}  $resolved
     */
    private function unauthorizedResponse(Request $request, array $resolved)
    {
        return ApiErrorResponse::json(
            $request,
            (int) ($resolved['status'] ?? 401),
            (string) ($resolved['error_code'] ?? 'unauthorized'),
            (string) ($resolved['message'] ?? 'Unauthorized.'),
        );
    }
}
