<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\CustomerOrStaffMiddleware;
use App\Services\CustomerReservationSessionAccessService;
use App\Support\CustomerSessionRouteContract;
use App\Support\StaffActorResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

final class CustomerOrStaffMiddlewareSessionFlowHotfixTest extends TestCase
{
    public function test_it_allows_session_bound_customer_list_flow_on_runtime_route_contract_without_staff_key(): void
    {
        $middleware = $this->middleware();
        $request = Request::create('/api/v1/reservations', 'GET', ['session_id' => 'sess-list-1']);
        $this->bindMatchedRoute($request);

        $response = $middleware->handle($request, function (Request $handledRequest) {
            return response()->json([
                'ok' => true,
                'is_staff' => (bool) $handledRequest->attributes->get('is_staff', true),
                'customer_session_flow' => (bool) $handledRequest->attributes->get('customer_session_flow', false),
            ]);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(true, $response->getData(true)['ok'] ?? false);
        $this->assertSame(false, $response->getData(true)['is_staff'] ?? true);
        $this->assertSame(true, $response->getData(true)['customer_session_flow'] ?? false);
    }

    public function test_it_allows_session_bound_customer_show_flow_on_runtime_route_contract_without_staff_key(): void
    {
        $middleware = $this->middleware();
        $request = Request::create('/api/v1/reservations/123', 'GET', ['session_id' => 'sess-show-1']);
        $this->bindMatchedRoute($request);

        $response = $middleware->handle($request, function (Request $handledRequest) {
            return response()->json([
                'ok' => true,
                'is_staff' => (bool) $handledRequest->attributes->get('is_staff', true),
                'customer_session_flow' => (bool) $handledRequest->attributes->get('customer_session_flow', false),
            ]);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(true, $response->getData(true)['ok'] ?? false);
        $this->assertSame(false, $response->getData(true)['is_staff'] ?? true);
        $this->assertSame(true, $response->getData(true)['customer_session_flow'] ?? false);
    }

    public function test_it_allows_session_bound_customer_cancel_flow_on_runtime_route_contract_without_staff_key(): void
    {
        $middleware = $this->middleware();
        $request = Request::create('/api/v1/reservations/123/cancel', 'POST', ['session_id' => 'sess-cancel-1']);
        $this->bindMatchedRoute($request);

        $response = $middleware->handle($request, function (Request $handledRequest) {
            return response()->json([
                'ok' => true,
                'is_staff' => (bool) $handledRequest->attributes->get('is_staff', true),
                'customer_session_flow' => (bool) $handledRequest->attributes->get('customer_session_flow', false),
            ]);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(true, $response->getData(true)['ok'] ?? false);
        $this->assertSame(false, $response->getData(true)['is_staff'] ?? true);
        $this->assertSame(true, $response->getData(true)['customer_session_flow'] ?? false);
    }

    public function test_it_does_not_fallback_to_session_flow_when_invalid_staff_key_is_present_for_cancel(): void
    {
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', false);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_auth.api_keys', []);
        config()->set('staff_auth.legacy_key', '');
        config()->set('staff_auth.legacy_user_id', 0);

        $middleware = $this->middleware();
        $request = Request::create('/api/v1/reservations/123/cancel', 'POST', ['session_id' => 'sess-cancel-2']);
        $request->headers->set('X-Staff-Key', 'invalid-staff-key');
        $this->bindMatchedRoute($request);

        $response = $middleware->handle($request, function () {
            $this->fail('Middleware should not downgrade an invalid staff credential into customer session flow.');
        });

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('unauthorized', $response->getData(true)['error_code'] ?? null);
    }

    private function middleware(): CustomerOrStaffMiddleware
    {
        return new CustomerOrStaffMiddleware(
            new StaffActorResolver(),
            new CustomerSessionRouteContract($this->createMock(CustomerReservationSessionAccessService::class)),
        );
    }

    private function bindMatchedRoute(Request $request): void
    {
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);
    }
}
