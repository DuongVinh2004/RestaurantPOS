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
use App\Modules\IdentityAccess\Infrastructure\Internal\StaffBrowserSessionCookieFactory;
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
        private readonly StaffBrowserSessionCookieFactory $browserSessionCookies,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $context = array_filter([
            'label' => $request->input('label'),
            'device_name' => $request->input('device_name'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        try {
            if ($this->browserSessionCookies->requested($request)) {
                if (! $this->browserSessionCookies->enabled()) {
                    return ApiErrorResponse::json(
                        $request,
                        409,
                        'staff_browser_session_disabled',
                        'Staff browser refresh-cookie sessions are not enabled.',
                        extra: ['category_code' => 'staff_browser_session_disabled'],
                    );
                }

                $issued = $this->loginStaff->handleBrowserSession(
                    (string) $request->input('identifier'),
                    (string) $request->input('password'),
                    $context,
                );

                return $this->respondWithBrowserSession(
                    $request,
                    $issued['payload'],
                    $issued['refresh_token'],
                );
            }

            $payload = $this->loginStaff->handle(
                (string) $request->input('identifier'),
                (string) $request->input('password'),
                $context,
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
        if ($request->attributes->get('staff_auth_credential_source') === 'refresh_cookie') {
            $refreshStaffApiKeyId = (int) $request->attributes->get('staff_refresh_api_key_id', 0);
            if ($refreshStaffApiKeyId <= 0) {
                return $this->unauthorized($request);
            }

            $refreshed = $this->refreshStaffSession->handleBrowserSession(
                $refreshStaffApiKeyId,
                $request->attributes->get('staff_access_api_key_id') !== null
                    ? (int) $request->attributes->get('staff_access_api_key_id')
                    : null,
            );

            return $this->respondWithBrowserSession(
                $request,
                $refreshed['payload'],
                $refreshed['refresh_token'],
            );
        }

        $staffApiKeyId = (int) $request->attributes->get('staff_api_key_id', 0);
        if ($staffApiKeyId <= 0) {
            return $this->unauthorized($request);
        }

        return $this->respond($request, $this->refreshStaffSession->handle($staffApiKeyId));
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->attributes->get('staff_auth_credential_source') === 'refresh_cookie') {
            $refreshStaffApiKeyId = (int) $request->attributes->get('staff_refresh_api_key_id', 0);
            if ($refreshStaffApiKeyId <= 0) {
                return $this->unauthorized($request);
            }

            return $this->respondAndClearBrowserSession(
                $request,
                $this->logoutStaff->handleBrowserSession(
                    $refreshStaffApiKeyId,
                    $request->attributes->get('staff_access_api_key_id') !== null
                        ? (int) $request->attributes->get('staff_access_api_key_id')
                        : null,
                ),
            );
        }

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

    /**
     * @param  array<string,mixed>  $payload
     */
    private function respondWithBrowserSession(Request $request, array $payload, string $refreshToken): JsonResponse
    {
        $response = $this->respond($request, $payload);
        $csrfToken = $this->browserSessionCookies->issueCsrfToken();

        $response->headers->setCookie($this->browserSessionCookies->makeRefreshCookie($refreshToken));
        $response->headers->setCookie($this->browserSessionCookies->makeCsrfCookie($csrfToken));

        return $response;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function respondAndClearBrowserSession(Request $request, array $payload): JsonResponse
    {
        $response = $this->respond($request, $payload);
        foreach ($this->browserSessionCookies->clearCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
