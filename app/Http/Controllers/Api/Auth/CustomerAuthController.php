<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginCustomerAuthRequest;
use App\Services\Auth\OpaqueProductAuthService;
use App\Support\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAuthController extends Controller
{
    public function __construct(
        private readonly OpaqueProductAuthService $service,
    ) {}

    public function login(LoginCustomerAuthRequest $request): JsonResponse
    {
        $payload = $this->service->loginCustomer(
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

        return response()->json([
            'data' => $payload,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $accessSessionId = (int) $request->attributes->get('customer_access_session_id', 0);
        if ($accessSessionId <= 0 || ! $request->user()) {
            return $this->unauthorized($request);
        }

        return response()->json([
            'data' => $this->service->currentCustomer($accessSessionId),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $accessSessionId = (int) $request->attributes->get('customer_access_session_id', 0);
        if ($accessSessionId <= 0 || ! $request->user()) {
            return $this->unauthorized($request);
        }

        return response()->json([
            'data' => $this->service->refreshCustomer($accessSessionId),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $accessSessionId = (int) $request->attributes->get('customer_access_session_id', 0);
        if ($accessSessionId <= 0 || ! $request->user()) {
            return $this->unauthorized($request);
        }

        return response()->json([
            'data' => $this->service->logoutCustomer($accessSessionId),
        ]);
    }

    private function unauthorized(Request $request): JsonResponse
    {
        return ApiErrorResponse::json($request, 401, 'unauthorized', 'Unauthorized.');
    }
}
