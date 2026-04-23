<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Support\Auth\RequestActorContext;
use Illuminate\Http\Request;
use Tests\TestCase;

final class RequestActorContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('staff_auth.allowed_role_ids', [1, 2]);
        config()->set('customer_auth.allowed_role_ids', [3]);
    }

    public function test_it_prefers_an_authenticated_customer_owner_over_session_fallback(): void
    {
        $request = Request::create('/api/v1/reservations/10', 'GET', [
            'session_id' => 'sess-customer-owner',
        ]);
        $request->setUserResolver(fn () => $this->makeUser(userId: 41, roleId: 3, roleName: 'Customer'));

        $actor = RequestActorContext::fromRequest($request);

        $this->assertTrue($actor->isCustomerOwner());
        $this->assertSame(41, $actor->customerUserId());
        $this->assertSame('owner', $actor->accessScope());
    }

    public function test_it_resolves_an_authenticated_staff_user_without_bleeding_into_customer_session_flow(): void
    {
        $request = Request::create('/api/v1/reservations/10', 'GET', [
            'session_id' => 'sess-staff-user',
        ]);
        $request->setUserResolver(fn () => $this->makeUser(userId: 12, roleId: 2, roleName: 'Staff'));

        $actor = RequestActorContext::fromRequest($request);

        $this->assertTrue($actor->isStaff());
        $this->assertSame(12, $actor->staffUserId());
        $this->assertNull($actor->customerUserId());
        $this->assertSame('staff', $actor->accessScope());
    }

    public function test_it_preserves_explicit_customer_owner_type_without_reusing_a_staff_user_as_customer_actor(): void
    {
        $request = Request::create('/api/v1/reservations/10', 'GET');
        $request->setUserResolver(fn () => $this->makeUser(userId: 77, roleId: 2, roleName: 'Staff'));
        $request->attributes->set(RequestActorContext::ATTR_TYPE, RequestActorContext::TYPE_CUSTOMER_OWNER);
        $request->attributes->set('customer_actor_user_id', 33);
        $request->attributes->set('customer_auth_mode', 'customer_access_session');
        $request->attributes->set('customer_access_session_id', 7001);

        $actor = RequestActorContext::fromRequest($request);

        $this->assertTrue($actor->isCustomerOwner());
        $this->assertSame(33, $actor->customerUserId());
        $this->assertSame('customer_access_session', $actor->authMode());
        $this->assertSame(7001, $actor->customerAccessSessionId());
        $this->assertNull($actor->staffUserId());
    }

    private function makeUser(int $userId, int $roleId, string $roleName, bool $isDeleted = false): User
    {
        $user = new User;
        $user->user_id = $userId;
        $user->role_id = $roleId;
        $user->is_deleted = $isDeleted;
        $user->setRelation('role', new Role(['role_name' => $roleName]));

        return $user;
    }
}
