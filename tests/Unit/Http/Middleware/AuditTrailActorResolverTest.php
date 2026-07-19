<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Support\AuditTrail\AuditTrailActorResolver;
use Tests\TestCase;

final class AuditTrailActorResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('staff_auth.allowed_role_ids', [1, 2]);
        config()->set('customer_auth.allowed_role_ids', [3]);
    }

    public function test_it_resolves_authenticated_staff_users_as_staff_audit_principals(): void
    {
        $request = request();
        $request->attributes->replace([]);
        $request->headers->replace([]);
        $request->request->replace([]);
        $request->query->replace([]);
        $request->setUserResolver(fn () => $this->makeUser(userId: 21, roleId: 2, roleName: 'Staff'));

        $resolved = app(AuditTrailActorResolver::class)->resolve();

        $this->assertSame('staff_user', $resolved['type']);
        $this->assertSame('staff_user:21', $resolved['key']);
        $this->assertSame(21, $resolved['user_id']);
    }

    public function test_it_resolves_customer_access_session_principals_from_normalized_request_actor_context(): void
    {
        $request = request();
        $request->attributes->replace([]);
        $request->headers->replace([]);
        $request->request->replace([]);
        $request->query->replace([]);
        $request->setUserResolver(fn () => $this->makeUser(userId: 44, roleId: 3, roleName: 'Customer'));
        $request->attributes->set('customer_access_session_id', 9021);

        $resolved = app(AuditTrailActorResolver::class)->resolve();

        $this->assertSame('customer_access_session', $resolved['type']);
        $this->assertSame('customer_access_session:9021', $resolved['key']);
        $this->assertSame(44, $resolved['user_id']);
    }

    public function test_it_hashes_customer_session_actor_keys(): void
    {
        config()->set('audit.hash_key', 'audit-actor-resolver-test-key');
        $request = request();
        $request->attributes->replace([]);
        $request->headers->replace([]);
        $request->request->replace([]);
        $request->query->replace([]);
        $request->setUserResolver(static fn () => null);
        $request->attributes->set('customer_session_id', 'sess-audit-123');

        $resolved = app(AuditTrailActorResolver::class)->resolve();

        $this->assertSame('customer_session', $resolved['type']);
        $this->assertSame(
            'hmac-sha256:'.hash_hmac('sha256', 'sess-audit-123', 'audit-actor-resolver-test-key'),
            $resolved['key'],
        );
        $this->assertNull($resolved['user_id']);
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
