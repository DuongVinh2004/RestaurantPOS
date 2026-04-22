<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Middleware\CustomerOrStaffMiddleware;
use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\IdentityAccess\Infrastructure\Internal\CustomerSessionRouteContract;
use App\Modules\IdentityAccess\Infrastructure\Tokenization\StaffApiKeyActorResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

class CustomerOrStaffMiddlewareSessionContractTest extends TestCase
{
    public function test_post_reservations_session_flow_requires_an_owned_hold_that_resolves_to_a_user(): void
    {
        $resolver = $this->createMock(StaffApiKeyActorResolver::class);
        $resolver->method('extractProvidedKey')->willReturn('');

        $accessService = $this->createMock(ReservationSessionAccessWorkflow::class);
        $accessService->expects(self::once())
            ->method('resolveUserIdFromOwnedHold')
            ->with('hold-1', 'session-1')
            ->willReturn(88);

        $middleware = new CustomerOrStaffMiddleware($resolver, new CustomerSessionRouteContract($accessService));
        $request = Request::create('/api/v1/reservations', 'POST', [
            'hold_id' => 'hold-1',
            'session_id' => 'session-1',
        ]);
        $request->setUserResolver(static fn () => null);
        $this->bindMatchedRoute($request);

        $called = false;
        $response = $middleware->handle($request, function ($request) use (&$called) {
            $called = true;
            self::assertTrue((bool) $request->attributes->get('customer_session_flow'));

            return response()->json(['ok' => true]);
        });

        self::assertTrue($called);
        self::assertSame(200, $response->getStatusCode());
    }

    public function test_post_reservations_session_flow_is_rejected_when_hold_is_not_owned_by_a_real_user(): void
    {
        $resolver = $this->createMock(StaffApiKeyActorResolver::class);
        $resolver->method('extractProvidedKey')->willReturn('');

        $accessService = $this->createMock(ReservationSessionAccessWorkflow::class);
        $accessService->expects(self::once())
            ->method('resolveUserIdFromOwnedHold')
            ->with('guest-hold', 'guest-session')
            ->willReturn(null);

        $middleware = new CustomerOrStaffMiddleware($resolver, new CustomerSessionRouteContract($accessService));
        $request = Request::create('/api/v1/reservations', 'POST', [
            'hold_id' => 'guest-hold',
            'session_id' => 'guest-session',
        ]);
        $request->setUserResolver(static fn () => null);
        $this->bindMatchedRoute($request);

        $called = false;
        $response = $middleware->handle($request, function () use (&$called) {
            $called = true;

            return response()->json(['ok' => true]);
        });

        self::assertFalse($called);
        self::assertSame(401, $response->getStatusCode());
    }

    private function bindMatchedRoute(Request $request): void
    {
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);
    }
}
