<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Internal\CustomerSessionRouteContract;
use App\Modules\IdentityAccess\Infrastructure\Tokenization\StaffApiKeyActorResolver;
use App\Support\ApiErrorResponse;
use App\Support\Auth\RequestActorContext;
use Closure;
use Illuminate\Http\Request;

class CustomerOrStaffMiddleware
{
    public function __construct(
        private readonly StaffApiKeyActorResolver $resolver,
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
}
