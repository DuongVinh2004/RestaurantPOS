<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\CustomerOrStaffMiddleware;
use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\IdentityAccess\Infrastructure\Internal\CustomerSessionRouteContract;
use App\Modules\IdentityAccess\Infrastructure\Tokenization\StaffApiKeyActorResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerOrStaffMiddlewareSessionPathTest extends TestCase
{
    public function test_it_allows_session_bound_create_flow_on_runtime_route_contract(): void
    {
        $resolver = $this->mock(StaffApiKeyActorResolver::class);
        $resolver->shouldReceive('extractProvidedKey')->once()->andReturn('');

        $accessService = $this->createMock(ReservationSessionAccessWorkflow::class);
        $accessService->expects(self::once())
            ->method('resolveUserIdFromOwnedHold')
            ->with(self::isType('string'), 'sess-core-flow')
            ->willReturn(88);

        $request = Request::create('/api/v1/reservations', 'POST', [
            'session_id' => 'sess-core-flow',
            'hold_id' => (string) Str::uuid(),
        ]);
        $this->bindMatchedRoute($request);

        $response = $this->middleware($resolver, $accessService)->handle($request, function (Request $request): JsonResponse {
            return response()->json([
                'customer_session_flow' => (bool) $request->attributes->get('customer_session_flow', false),
                'is_staff' => (bool) $request->attributes->get('is_staff', true),
            ]);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['customer_session_flow'] ?? false);
        $this->assertFalse($response->getData(true)['is_staff'] ?? true);
    }

    public function test_it_allows_session_bound_show_flow_on_runtime_route_contract(): void
    {
        $resolver = $this->mock(StaffApiKeyActorResolver::class);
        $resolver->shouldReceive('extractProvidedKey')->once()->andReturn('');

        $request = Request::create('/api/v1/reservations/123', 'GET', [
            'session_id' => 'sess-core-flow',
        ]);
        $this->bindMatchedRoute($request);

        $response = $this->middleware($resolver)->handle($request, function (Request $request): JsonResponse {
            return response()->json([
                'customer_session_flow' => (bool) $request->attributes->get('customer_session_flow', false),
                'is_staff' => (bool) $request->attributes->get('is_staff', true),
            ]);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['customer_session_flow'] ?? false);
        $this->assertFalse($response->getData(true)['is_staff'] ?? true);
    }

    public function test_it_allows_session_bound_deposit_preview_flow_on_runtime_route_contract(): void
    {
        $resolver = $this->mock(StaffApiKeyActorResolver::class);
        $resolver->shouldReceive('extractProvidedKey')->once()->andReturn('');

        $request = Request::create('/api/v1/reservations/123/deposit-preview', 'GET', [
            'session_id' => 'sess-deposit-preview',
        ]);
        $this->bindMatchedRoute($request);

        $response = $this->middleware($resolver)->handle($request, function (Request $request): JsonResponse {
            return response()->json([
                'customer_session_flow' => (bool) $request->attributes->get('customer_session_flow', false),
                'is_staff' => (bool) $request->attributes->get('is_staff', true),
            ]);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['customer_session_flow'] ?? false);
        $this->assertFalse($response->getData(true)['is_staff'] ?? true);
    }

    public function test_it_allows_session_bound_deposit_payment_mutation_flow_on_runtime_route_contract(): void
    {
        $resolver = $this->mock(StaffApiKeyActorResolver::class);
        $resolver->shouldReceive('extractProvidedKey')->once()->andReturn('');

        $request = Request::create('/api/v1/reservations/123/deposit/payment-sessions/99/confirm', 'POST', [
            'session_id' => 'sess-deposit-payment',
            'row_version' => 1,
        ]);
        $this->bindMatchedRoute($request);

        $response = $this->middleware($resolver)->handle($request, function (Request $request): JsonResponse {
            return response()->json([
                'customer_session_flow' => (bool) $request->attributes->get('customer_session_flow', false),
                'is_staff' => (bool) $request->attributes->get('is_staff', true),
            ]);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['customer_session_flow'] ?? false);
        $this->assertFalse($response->getData(true)['is_staff'] ?? true);
    }

    private function middleware(
        StaffApiKeyActorResolver $resolver,
        ?ReservationSessionAccessWorkflow $accessService = null,
    ): CustomerOrStaffMiddleware {
        return new CustomerOrStaffMiddleware(
            $resolver,
            new CustomerSessionRouteContract($accessService ?? $this->createMock(ReservationSessionAccessWorkflow::class)),
        );
    }

    private function bindMatchedRoute(Request $request): void
    {
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);
    }
}
