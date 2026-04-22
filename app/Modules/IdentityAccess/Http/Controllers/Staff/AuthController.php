<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Application\UseCases\Authentication\LoginStaffHandler;
use App\Modules\IdentityAccess\Application\UseCases\Authentication\LogoutStaffHandler;
use App\Modules\IdentityAccess\Application\UseCases\Authentication\ProductAuthConfigurationException;
use App\Modules\IdentityAccess\Application\UseCases\Authentication\RefreshStaffSessionHandler;
use App\Modules\IdentityAccess\Application\UseCases\Authentication\ShowCurrentStaffSessionHandler;
use App\Modules\IdentityAccess\Http\Requests\Staff\LoginRequest;
use App\Modules\IdentityAccess\Http\Resources\Staff\AuthenticatedActorResource;
use App\Support\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginStaffHandler $loginStaff,
        private readonly ShowCurrentStaffSessionHandler $showCurrentStaffSession,
        private readonly RefreshStaffSessionHandler $refreshStaffSession,
        private readonly LogoutStaffHandler $logoutStaff,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $payload = $this->loginStaff->handle(
                (string) $request->input('identifier'),
                (string) $request->input('password'),
                array_filter([
                    'label' => $request->input('label'),
                    'device_name' => $request->input('device_name'),
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
        $staffApiKeyId = (int) $request->attributes->get('staff_api_key_id', 0);
        if ($staffApiKeyId <= 0) {
            return $this->unauthorized($request);
        }

        return $this->respond($request, $this->showCurrentStaffSession->handle($staffApiKeyId));
    }

    public function refresh(Request $request): JsonResponse
    {
        $staffApiKeyId = (int) $request->attributes->get('staff_api_key_id', 0);
        if ($staffApiKeyId <= 0) {
            return $this->unauthorized($request);
        }

        return $this->respond($request, $this->refreshStaffSession->handle($staffApiKeyId));
    }

    public function logout(Request $request): JsonResponse
    {
        $staffApiKeyId = (int) $request->attributes->get('staff_api_key_id', 0);
        if ($staffApiKeyId <= 0) {
            return $this->unauthorized($request);
        }

        return $this->respond($request, $this->logoutStaff->handle($staffApiKeyId));
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
            'data' => (new AuthenticatedActorResource($payload))->toArray($request),
        ]);
    }
}
