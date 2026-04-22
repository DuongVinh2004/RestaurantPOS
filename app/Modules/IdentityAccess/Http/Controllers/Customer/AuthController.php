<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Application\UseCases\Authentication\LoginCustomerHandler;
use App\Modules\IdentityAccess\Application\UseCases\Authentication\LogoutCustomerHandler;
use App\Modules\IdentityAccess\Application\UseCases\Authentication\ProductAuthConfigurationException;
use App\Modules\IdentityAccess\Application\UseCases\Authentication\RefreshCustomerSessionHandler;
use App\Modules\IdentityAccess\Application\UseCases\Authentication\ShowCurrentCustomerSessionHandler;
use App\Modules\IdentityAccess\Http\Requests\Customer\LoginRequest;
use App\Modules\IdentityAccess\Http\Requests\Customer\RefreshSessionRequest;
use App\Modules\IdentityAccess\Http\Resources\Customer\AccessSessionResource;
use App\Support\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginCustomerHandler $loginCustomer,
        private readonly ShowCurrentCustomerSessionHandler $showCurrentCustomerSession,
        private readonly RefreshCustomerSessionHandler $refreshCustomerSession,
        private readonly LogoutCustomerHandler $logoutCustomer,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $payload = $this->loginCustomer->handle(
                (string) $request->input('identifier'),
                (string) $request->input('password'),
                array_filter([
                    'session_id' => $request->input('session_id'),
                    'guest_name' => $request->input('guest_name'),
                    'phone' => $request->input('phone'),
                    'device_id' => $request->input('device_id'),
                    'session_label' => $request->input('session_label'),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            );
        } catch (ProductAuthConfigurationException $exception) {
            return ApiErrorResponse::json(
                $request,
                $exception->status(),
                $exception->errorCode(),
                $exception->getMessage(),
                extra: [
                    'state_reason' => $exception->errorCode(),
                ],
            );
        }

        return $this->respond($request, $payload);
    }

    public function me(Request $request): JsonResponse
    {
        $accessSessionId = (int) $request->attributes->get('customer_access_session_id', 0);
        if ($accessSessionId <= 0 || ! $request->user()) {
            return $this->unauthorized($request);
        }

        return $this->respond($request, $this->showCurrentCustomerSession->handle($accessSessionId));
    }

    public function refresh(RefreshSessionRequest $request): JsonResponse
    {
        $accessSessionId = (int) $request->attributes->get('customer_access_session_id', 0);
        if ($accessSessionId <= 0 || ! $request->user()) {
            return $this->unauthorized($request);
        }

        return $this->respond($request, $this->refreshCustomerSession->handle($accessSessionId));
    }

    public function logout(Request $request): JsonResponse
    {
        $accessSessionId = (int) $request->attributes->get('customer_access_session_id', 0);
        if ($accessSessionId <= 0 || ! $request->user()) {
            return $this->unauthorized($request);
        }

        return $this->respond($request, $this->logoutCustomer->handle($accessSessionId));
    }

    private function unauthorized(Request $request): JsonResponse
    {
        return ApiErrorResponse::authenticationRequired($request, 'Authentication is required.');
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function respond(Request $request, array $payload): JsonResponse
    {
        return response()->json([
            'data' => (new AccessSessionResource($payload))->toArray($request),
        ]);
    }
}
