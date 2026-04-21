<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\ResolveCustomerAuthMiddleware;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Persistence\CustomerAccessSessionStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CustomerAccessSessionFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('customer_auth.enabled', true);
        config()->set('customer_auth.header', 'X-Customer-Token');
        config()->set('customer_auth.allow_bearer', true);
        config()->set('customer_auth.allow_legacy_user_auth_tokens', false);
        config()->set('customer_auth.allowed_role_ids', [3]);
        config()->set('customer_auth.touch_last_used_at', true);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropIfExists('customer_access_sessions');
        Schema::dropIfExists('user_auth_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('role_id');
            $table->string('role_name')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->string('username')->unique();
            $table->string('password_hash');
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedInteger('role_id')->nullable();
            $table->unsignedInteger('current_tier_id')->nullable();
            $table->string('language_pref', 10)->default('vn');
            $table->boolean('is_deleted')->default(false);
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('customer_access_sessions', function (Blueprint $table): void {
            $table->bigIncrements('access_session_id');
            $table->unsignedInteger('user_id');
            $table->char('token_hash', 64)->unique();
            $table->string('token_last_eight', 8)->nullable();
            $table->text('session_meta_json')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->binary('created_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('user_auth_tokens', function (Blueprint $table): void {
            $table->bigIncrements('token_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('purpose');
            $table->string('channel');
            $table->string('recipient');
            $table->char('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->dateTime('created_at')->nullable();
        });

        DB::table('roles')->insert([
            [
                'role_id' => 3,
                'role_name' => 'Customer',
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ],
            [
                'role_id' => 2,
                'role_name' => 'Staff',
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ],
        ]);
    }

    public function test_dedicated_customer_access_session_token_resolves_customer_context(): void
    {
        $user = $this->createUser(roleId: 3, username: 'customer-auth-1');
        $issued = app(CustomerAccessSessionStore::class)->issueForUser($user, now('UTC')->addHour(), [
            'user_agent' => 'TestAgent/1.0',
        ]);

        $request = Request::create('/api/v1/me/loyalty', 'GET');
        $request->headers->set('X-Customer-Token', $issued['plain_text_token']);

        $response = app(ResolveCustomerAuthMiddleware::class)->handle($request, function (Request $request): JsonResponse {
            return response()->json([
                'user_id' => $request->user()?->user_id,
                'customer_actor_user_id' => $request->attributes->get('customer_actor_user_id'),
                'customer_auth_mode' => $request->attributes->get('customer_auth_mode'),
                'customer_access_session_id' => $request->attributes->get('customer_access_session_id'),
            ]);
        });

        self::assertSame(200, $response->getStatusCode());
        self::assertSame((int) $user->user_id, $response->getData(true)['user_id'] ?? null);
        self::assertSame((int) $user->user_id, $response->getData(true)['customer_actor_user_id'] ?? null);
        self::assertSame('customer_access_session', $response->getData(true)['customer_auth_mode'] ?? null);

        $resolvedSessionId = $response->getData(true)['customer_access_session_id'] ?? null;
        self::assertNotNull($resolvedSessionId);
        self::assertNotNull(DB::table('customer_access_sessions')->where('access_session_id', $resolvedSessionId)->value('last_used_at'));
    }

    public function test_expired_or_revoked_dedicated_customer_access_token_is_rejected(): void
    {
        $user = $this->createUser(roleId: 3, username: 'customer-auth-2');
        $service = app(CustomerAccessSessionStore::class);

        $expired = $service->issueForUser($user, now('UTC')->subMinute());
        $revoked = $service->issueForUser($user, now('UTC')->addHour());
        $service->revokeSession($revoked['access_session']);

        self::assertNull($service->resolveUserFromPlainTextToken($expired['plain_text_token']));
        self::assertNull($service->resolveUserFromPlainTextToken($revoked['plain_text_token']));
    }

    public function test_legacy_user_auth_tokens_remain_disabled_by_default_even_when_matching_row_exists(): void
    {
        $user = $this->createUser(roleId: 3, username: 'customer-auth-3');
        $plainTextToken = 'legacy-bridge-token-001';

        DB::table('user_auth_tokens')->insert([
            'user_id' => $user->user_id,
            'purpose' => 'VerifyEmail',
            'channel' => 'Email',
            'recipient' => 'customer@example.test',
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => now('UTC')->addHour(),
            'used_at' => null,
            'created_at' => now('UTC'),
        ]);

        $request = Request::create('/api/v1/me/loyalty', 'GET');
        $request->headers->set('X-Customer-Token', $plainTextToken);

        $response = app(ResolveCustomerAuthMiddleware::class)->handle($request, function (Request $request): JsonResponse {
            return response()->json([
                'resolved_user_id' => $request->user()?->user_id,
                'customer_auth_mode' => $request->attributes->get('customer_auth_mode'),
            ]);
        });

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($response->getData(true)['resolved_user_id'] ?? null);
        self::assertNull($response->getData(true)['customer_auth_mode'] ?? null);
    }

    public function test_issue_for_user_rejects_non_customer_roles(): void
    {
        $staffUser = $this->createUser(roleId: 2, username: 'customer-auth-staff');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(CustomerAccessSessionStore::class)->issueForUser($staffUser, now('UTC')->addHour());
    }

    private function createUser(int $roleId, string $username): User
    {
        $userId = DB::table('users')->insertGetId([
            'username' => $username,
            'password_hash' => '$2y$12$abcdefghijklmnopqrstuv',
            'full_name' => 'Customer '.$username,
            'email' => $username.'@example.test',
            'phone' => '0900000000',
            'role_id' => $roleId,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        return $user;
    }
}
