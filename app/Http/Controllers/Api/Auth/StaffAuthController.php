<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginStaffAuthRequest;
use App\Services\Auth\OpaqueProductAuthService;
use App\Support\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffAuthController extends Controller
{
    public function __construct(
        private readonly OpaqueProductAuthService $service,
    ) {}

    public function login(LoginStaffAuthRequest $request): JsonResponse
    {
        $payload = $this->service->loginStaff(
            (string) $request->input('identifier'),
            (string) $request->input('password'),
            array_filter([
                'label' => $request->input('label'),
                'device_name' => $request->input('device_name'),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        );

        return response()->json([
            'data' => $payload,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $staffApiKeyId = (int) $request->attributes->get('staff_api_key_id', 0);
        if ($staffApiKeyId <= 0) {
            return $this->unauthorized($request);
        }

        return response()->json([
            'data' => $this->service->currentStaff($staffApiKeyId),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $staffApiKeyId = (int) $request->attributes->get('staff_api_key_id', 0);
        if ($staffApiKeyId <= 0) {
            return $this->unauthorized($request);
        }

        return response()->json([
            'data' => $this->service->refreshStaff($staffApiKeyId),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $staffApiKeyId = (int) $request->attributes->get('staff_api_key_id', 0);
        if ($staffApiKeyId <= 0) {
            return $this->unauthorized($request);
        }

        return response()->json([
            'data' => $this->service->logoutStaff($staffApiKeyId),
        ]);
    }

    private function unauthorized(Request $request): JsonResponse
    {
        return ApiErrorResponse::json($request, 401, 'unauthorized', 'Unauthorized.');
    }
}
