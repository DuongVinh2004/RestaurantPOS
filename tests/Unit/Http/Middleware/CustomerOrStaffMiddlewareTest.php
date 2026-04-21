<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\CustomerOrStaffMiddleware;
use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\IdentityAccess\Infrastructure\Internal\CustomerSessionRouteContract;
use App\Modules\IdentityAccess\Infrastructure\Tokenization\StaffApiKeyActorResolver;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

final class CustomerOrStaffMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_allows_session_bound_customer_flow_when_no_staff_key_is_provided(): void
    {
        $resolver = Mockery::mock(StaffApiKeyActorResolver::class);
        $resolver->shouldReceive('extractProvidedKey')
            ->once()
            ->andReturn('');

        $request = Request::create('/api/v1/reservations/10', 'GET', [
            'session_id' => 'sess-route-bound-1',
        ]);
        $this->bindMatchedRoute($request);

        $response = $this->middleware($resolver)->handle($request, function (Request $request) {
            return response()->json([
                'is_staff' => (bool) $request->attributes->get('is_staff', true),
                'customer_session_flow' => (bool) $request->attributes->get('customer_session_flow', false),
            ], 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(false, $response->getData(true)['is_staff'] ?? null);
        $this->assertSame(true, $response->getData(true)['customer_session_flow'] ?? null);
    }

    public function test_it_does_not_silently_downgrade_to_customer_context_when_a_staff_key_is_invalid(): void
    {
        $resolver = Mockery::mock(StaffApiKeyActorResolver::class);
        $resolver->shouldReceive('extractProvidedKey')
            ->once()
            ->andReturn('invalid-staff-key');
        $resolver->shouldReceive('resolveFromProvidedKey')
            ->once()
            ->with('invalid-staff-key')
            ->andReturn([
                'ok' => false,
                'status' => 401,
                'error_code' => 'unauthorized',
                'message' => 'Unauthorized.',
            ]);

        $customer = new User();
        $customer->user_id = 42;
        $customer->role_id = 3;
        $customer->setRelation('role', new Role(['role_name' => 'Customer']));

        $request = Request::create('/api/v1/reservations/10', 'GET');
        $request->headers->set('X-Staff-Key', 'invalid-staff-key');
        $request->setUserResolver(static fn () => $customer);

        $response = $this->middleware($resolver)->handle($request, function () {
            $this->fail('Middleware should not treat an invalid staff key as an authenticated customer flow.');
        });

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('unauthorized', $response->getData(true)['error_code'] ?? null);
    }

    public function test_it_prefers_a_valid_staff_key_over_a_pre_resolved_customer_actor(): void
    {
        $resolver = Mockery::mock(StaffApiKeyActorResolver::class);
        $resolver->shouldReceive('extractProvidedKey')
            ->once()
            ->andReturn('valid-staff-key');

        $staff = new User();
        $staff->user_id = 200;
        $staff->role_id = 2;
        $staff->setRelation('role', new Role(['role_name' => 'Staff']));

        $resolver->shouldReceive('resolveFromProvidedKey')
            ->once()
            ->with('valid-staff-key')
            ->andReturn([
                'ok' => true,
                'status' => 200,
                'user' => $staff,
                'mode' => 'mapped_key',
            ]);

        $customer = new User();
        $customer->user_id = 42;
        $customer->role_id = 3;
        $customer->setRelation('role', new Role(['role_name' => 'Customer']));

        $request = Request::create('/api/v1/reservations/10', 'GET');
        $request->headers->set('X-Staff-Key', 'valid-staff-key');
        $request->setUserResolver(static fn () => $customer);
        $request->attributes->set('customer_actor_user_id', 42);
        $request->attributes->set('customer_auth_mode', 'customer_access_session');

        $response = $this->middleware($resolver)->handle($request, function (Request $request) {
            return response()->json([
                'is_staff' => (bool) $request->attributes->get('is_staff', false),
                'staff_actor_user_id' => (int) $request->attributes->get('staff_actor_user_id', 0),
                'staff_actor_role_name' => (string) $request->attributes->get('staff_actor_role_name', ''),
                'customer_session_flow' => (bool) $request->attributes->get('customer_session_flow', false),
                'customer_actor_user_id' => $request->attributes->get('customer_actor_user_id'),
                'customer_auth_mode' => $request->attributes->get('customer_auth_mode'),
                'resolved_user_id' => $request->user()?->user_id,
            ], 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(true, $response->getData(true)['is_staff'] ?? null);
        $this->assertSame(200, $response->getData(true)['staff_actor_user_id'] ?? null);
        $this->assertSame('Staff', $response->getData(true)['staff_actor_role_name'] ?? null);
        $this->assertSame(false, $response->getData(true)['customer_session_flow'] ?? null);
        $this->assertNull($response->getData(true)['customer_actor_user_id'] ?? null);
        $this->assertNull($response->getData(true)['customer_auth_mode'] ?? null);
        $this->assertSame(200, $response->getData(true)['resolved_user_id'] ?? null);
    }

    public function test_it_does_not_fallback_to_session_flow_when_staff_key_is_invalid(): void
    {
        $resolver = Mockery::mock(StaffApiKeyActorResolver::class);
        $resolver->shouldReceive('extractProvidedKey')
            ->once()
            ->andReturn('invalid-staff-key');
        $resolver->shouldReceive('resolveFromProvidedKey')
            ->once()
            ->with('invalid-staff-key')
            ->andReturn([
                'ok' => false,
                'status' => 401,
                'error_code' => 'unauthorized',
                'message' => 'Unauthorized.',
            ]);

        $request = Request::create('/api/v1/reservations/10', 'GET', [
            'session_id' => 'sess-should-not-downgrade',
        ]);
        $request->headers->set('X-Staff-Key', 'invalid-staff-key');

        $response = $this->middleware($resolver)->handle($request, function () {
            $this->fail('Middleware should not silently downgrade an invalid staff credential into session flow.');
        });

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('unauthorized', $response->getData(true)['error_code'] ?? null);
    }

    public function test_it_prefers_staff_actor_resolution_over_session_flow_when_staff_key_is_valid(): void
    {
        $resolver = Mockery::mock(StaffApiKeyActorResolver::class);
        $resolver->shouldReceive('extractProvidedKey')
            ->once()
            ->andReturn('valid-staff-key');

        $user = new User();
        $user->user_id = 200;
        $user->role_id = 2;
        $user->setRelation('role', new Role(['role_name' => 'Staff']));

        $resolver->shouldReceive('resolveFromProvidedKey')
            ->once()
            ->with('valid-staff-key')
            ->andReturn([
                'ok' => true,
                'status' => 200,
                'user' => $user,
                'mode' => 'mapped_key',
            ]);

        $request = Request::create('/api/v1/reservations/10', 'GET', [
            'session_id' => 'sess-staff-should-win',
        ]);
        $request->headers->set('X-Staff-Key', 'valid-staff-key');

        $response = $this->middleware($resolver)->handle($request, function (Request $request) {
            return response()->json([
                'is_staff' => (bool) $request->attributes->get('is_staff', false),
                'staff_actor_user_id' => (int) $request->attributes->get('staff_actor_user_id', 0),
                'customer_session_flow' => (bool) $request->attributes->get('customer_session_flow', false),
            ], 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(true, $response->getData(true)['is_staff'] ?? null);
        $this->assertSame(200, $response->getData(true)['staff_actor_user_id'] ?? null);
        $this->assertSame(false, $response->getData(true)['customer_session_flow'] ?? null);
    }

    private function middleware(StaffApiKeyActorResolver $resolver): CustomerOrStaffMiddleware
    {
        return new CustomerOrStaffMiddleware(
            $resolver,
            new CustomerSessionRouteContract($this->createMock(ReservationSessionAccessWorkflow::class)),
        );
    }

    private function bindMatchedRoute(Request $request): void
    {
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);
    }
}
