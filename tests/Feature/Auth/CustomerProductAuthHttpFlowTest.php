<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerProductAuthHttpFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('customer_auth.enabled', true);
        config()->set('customer_auth.allowed_role_ids', [3]);
        config()->set('customer_auth.login_throttle_limit', 5);
        config()->set('customer_auth.login_throttle_window_seconds', 60);
        config()->set('customer_auth.register_throttle_limit', 100);
        config()->set('customer_auth.register_throttle_window_seconds', 60);
        config()->set('customer_auth.register_password_min_length', 8);

        $this->withoutMiddleware(ThrottleRequests::class);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        foreach (['customer_access_sessions', 'staff_api_keys', 'users', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }

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
            $table->string('session_id', 100)->nullable();
            $table->string('guest_name')->nullable();
            $table->string('phone')->nullable();
            $table->char('token_hash', 64)->unique();
            $table->string('token_last_eight', 8)->nullable();
            $table->text('session_meta_json')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->string('created_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('staff_api_keys', function (Blueprint $table): void {
            $table->bigIncrements('staff_api_key_id');
            $table->unsignedInteger('user_id');
            $table->string('label', 100);
            $table->char('key_hash', 64);
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        DB::table('roles')->insert([
            ['role_id' => 3, 'role_name' => 'Customer', 'created_at' => now('UTC'), 'updated_at' => now('UTC')],
            ['role_id' => 2, 'role_name' => 'Staff', 'created_at' => now('UTC'), 'updated_at' => now('UTC')],
        ]);
    }

    public function test_customer_can_login_fetch_current_session_refresh_and_logout(): void
    {
        $userId = DB::table('users')->insertGetId([
            'username' => 'customer-auth-http',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Customer HTTP',
            'email' => 'customer.auth@example.test',
            'phone' => '0901000000',
            'role_id' => 3,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $login = $this->postJson('/api/v1/auth/customer/login', [
            'identifier' => 'customer-auth-http',
            'password' => 'secret-123',
            'session_label' => 'web',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.auth_mode', 'customer_access_session')
            ->assertJsonPath('data.auth_header', 'X-Customer-Token')
            ->assertJsonPath('data.user.user_id', $userId);

        $token = (string) $login->json('data.access_token');
        $accessSessionId = (int) $login->json('data.access_session_id');

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Customer-Token' => $token,
        ])->getJson('/api/v1/auth/customer/me')
            ->assertOk()
            ->assertJsonPath('data.access_session_id', $accessSessionId)
            ->assertJsonPath('data.user.user_id', $userId);

        $refresh = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Customer-Token' => $token,
        ])->postJson('/api/v1/auth/customer/refresh');

        $refresh->assertOk()
            ->assertJsonPath('data.auth_mode', 'customer_access_session')
            ->assertJsonPath('data.user.user_id', $userId);

        $replacementToken = (string) $refresh->json('data.access_token');
        $replacementSessionId = (int) $refresh->json('data.access_session_id');
        self::assertNotSame($token, $replacementToken);
        self::assertNotSame($accessSessionId, $replacementSessionId);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Customer-Token' => $token,
        ])->getJson('/api/v1/auth/customer/me')
            ->assertStatus(401);

        $logout = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Customer-Token' => $replacementToken,
        ])->postJson('/api/v1/auth/customer/logout');

        $logout->assertOk()
            ->assertJsonPath('data.access_session_id', $replacementSessionId);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Customer-Token' => $replacementToken,
        ])->getJson('/api/v1/auth/customer/me')
            ->assertStatus(401);
    }

    public function test_customer_can_register_and_then_login_with_created_account(): void
    {
        $register = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Session-Id' => 'sess-register-header',
        ])->postJson('/api/v1/auth/customer/register', [
            'full_name' => 'Registered Customer',
            'email' => 'registered.customer@example.test',
            'phone' => '0904000000',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
            'session_label' => 'web-register',
            'role_id' => 2,
        ]);

        $register->assertOk()
            ->assertJsonPath('data.auth_mode', 'customer_access_session')
            ->assertJsonPath('data.auth_header', 'X-Customer-Token')
            ->assertJsonPath('data.session_id', 'sess-register-header')
            ->assertJsonPath('data.user.full_name', 'Registered Customer')
            ->assertJsonPath('data.user.email', 'registered.customer@example.test')
            ->assertJsonPath('data.user.role_id', 3);

        $userId = (int) $register->json('data.user.user_id');
        $user = DB::table('users')->where('user_id', $userId)->first();

        self::assertNotNull($user);
        self::assertSame(3, (int) $user->role_id);
        self::assertSame('registered.customer@example.test', (string) $user->username);
        self::assertTrue(Hash::check('secret-123', (string) $user->password_hash));

        $this->postJson('/api/v1/auth/customer/login', [
            'identifier' => 'registered.customer@example.test',
            'password' => 'secret-123',
            'session_label' => 'web-login-after-register',
        ])->assertOk()
            ->assertJsonPath('data.user.user_id', $userId);
    }

    public function test_customer_register_rejects_duplicate_email_and_phone(): void
    {
        DB::table('users')->insert([
            'user_id' => 40,
            'username' => 'existing-customer',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Existing Customer',
            'email' => 'existing.customer@example.test',
            'phone' => '0905000000',
            'role_id' => 3,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->postJson('/api/v1/auth/customer/register', [
            'full_name' => 'Duplicate Email',
            'email' => 'existing.customer@example.test',
            'phone' => '0905000001',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/v1/auth/customer/register', [
            'full_name' => 'Duplicate Phone',
            'email' => 'new.customer@example.test',
            'phone' => '0905000000',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_customer_register_requires_contact_and_strong_enough_password(): void
    {
        $this->postJson('/api/v1/auth/customer/register', [
            'full_name' => 'Missing Contact',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'phone']);

        $this->postJson('/api/v1/auth/customer/register', [
            'full_name' => 'Weak Password',
            'email' => 'weak.password@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_customer_register_fails_closed_when_customer_role_contract_is_missing_or_auth_disabled(): void
    {
        config()->set('customer_auth.allowed_role_ids', []);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Request-Id' => 'req-customer-register-role-config-missing',
        ])->postJson('/api/v1/auth/customer/register', [
            'full_name' => 'Role Missing',
            'email' => 'role.missing@example.test',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
        ])->assertStatus(503)
            ->assertHeader('X-Request-Id', 'req-customer-register-role-config-missing')
            ->assertJsonPath('error_code', 'customer_role_configuration_missing')
            ->assertJsonPath('state_reason', 'customer_role_configuration_missing');

        config()->set('customer_auth.allowed_role_ids', [3]);
        config()->set('customer_auth.enabled', false);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Request-Id' => 'req-customer-register-auth-disabled',
        ])->postJson('/api/v1/auth/customer/register', [
            'full_name' => 'Auth Disabled',
            'email' => 'auth.disabled@example.test',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
        ])->assertStatus(503)
            ->assertHeader('X-Request-Id', 'req-customer-register-auth-disabled')
            ->assertJsonPath('error_code', 'customer_auth_disabled')
            ->assertJsonPath('state_reason', 'customer_auth_disabled');
    }

    public function test_customer_login_uses_request_session_header_when_body_session_id_is_missing(): void
    {
        DB::table('users')->insert([
            'user_id' => 30,
            'username' => 'customer-auth-session-header',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Customer Session Header',
            'email' => 'customer.session.header@example.test',
            'phone' => '0903000000',
            'role_id' => 3,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Session-Id' => 'sess-auth-header-bridge',
        ])->postJson('/api/v1/auth/customer/login', [
            'identifier' => 'customer-auth-session-header',
            'password' => 'secret-123',
            'session_label' => 'web',
        ])->assertOk()
            ->assertJsonPath('data.auth_mode', 'customer_access_session')
            ->assertJsonPath('data.session_id', 'sess-auth-header-bridge')
            ->assertJsonPath('data.user.user_id', 30);
    }

    public function test_customer_login_rejects_invalid_credentials_and_non_customer_roles(): void
    {
        DB::table('users')->insert([
            'user_id' => 20,
            'username' => 'staff-account',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Staff Account',
            'email' => 'staff.auth@example.test',
            'phone' => '0902000000',
            'role_id' => 2,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->postJson('/api/v1/auth/customer/login', [
            'identifier' => 'missing-user',
            'password' => 'wrong',
        ])->assertStatus(422)
            ->assertJsonPath('errors.identifier.0', 'Invalid credentials.');

        $this->postJson('/api/v1/auth/customer/login', [
            'identifier' => 'staff-account',
            'password' => 'secret-123',
        ])->assertStatus(422)
            ->assertJsonPath('errors.identifier.0', 'Invalid credentials.');
    }

    public function test_customer_login_fails_closed_when_customer_role_contract_is_missing(): void
    {
        DB::table('users')->insert([
            'user_id' => 32,
            'username' => 'customer-role-config-missing',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Customer Role Config Missing',
            'email' => 'customer.role.config@example.test',
            'phone' => '0902000002',
            'role_id' => 3,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        config()->set('customer_auth.allowed_role_ids', []);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Request-Id' => 'req-customer-role-config-missing',
        ])->postJson('/api/v1/auth/customer/login', [
            'identifier' => 'customer-role-config-missing',
            'password' => 'secret-123',
        ])->assertStatus(503)
            ->assertHeader('X-Request-Id', 'req-customer-role-config-missing')
            ->assertJsonPath('error_code', 'customer_role_configuration_missing')
            ->assertJsonPath('category_code', 'customer_role_configuration_missing')
            ->assertJsonPath('state_reason', 'customer_role_configuration_missing')
            ->assertJsonPath('request_id', 'req-customer-role-config-missing');
    }

    public function test_staff_api_authentication_does_not_bleed_into_customer_product_routes(): void
    {
        DB::table('users')->insert([
            'user_id' => 20,
            'username' => 'staff-auth-guard',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Staff Guard',
            'email' => 'staff.guard@example.test',
            'phone' => '0902000001',
            'role_id' => 2,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_auth.allowed_role_ids', [2]);
        config()->set('staff_auth.api_keys', ['staff-wrong-scope-key' => 20]);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => 'staff-wrong-scope-key',
        ])->getJson('/api/v1/auth/customer/me')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => 'staff-wrong-scope-key',
        ])->postJson('/api/v1/auth/customer/refresh')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => 'staff-wrong-scope-key',
        ])->postJson('/api/v1/auth/customer/logout')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');
    }
}
