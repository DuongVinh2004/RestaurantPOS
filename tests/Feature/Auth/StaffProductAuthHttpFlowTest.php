<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class StaffProductAuthHttpFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('staff_auth.database_store_enabled', true);
        config()->set('staff_auth.allow_env_fallback', false);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_auth.allowed_role_ids', [1, 2]);
        config()->set('staff_auth.session_ttl_minutes', 30);
        config()->set('staff_auth.login_throttle_limit', 5);
        config()->set('staff_auth.login_throttle_window_seconds', 60);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        foreach (['cashier_shifts', 'branches', 'staff_api_keys', 'customer_access_sessions', 'users', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('branches', function (Blueprint $table): void {
            $table->increments('branch_id');
            $table->string('branch_code', 50)->unique();
            $table->string('branch_name', 150);
            $table->string('description', 400)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('currency', 10)->default('VND');
            $table->text('business_hours')->nullable();
            $table->text('closure_windows')->nullable();
            $table->text('booking_policy')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('role_id');
            $table->string('role_name')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->string('username')->unique();
            $table->string('password_hash')->nullable();
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

        Schema::create('cashier_shifts', function (Blueprint $table): void {
            $table->increments('cashier_shift_id');
            $table->unsignedInteger('branch_id')->default(1);
            $table->string('shift_code', 50)->unique();
            $table->unsignedInteger('cashier_user_id');
            $table->unsignedInteger('active_cashier_user_id')->nullable();
            $table->string('status', 20)->default('Open');
            $table->string('currency', 10)->default('VND');
            $table->string('terminal_code', 50)->nullable();
            $table->decimal('opening_float_amount', 14, 2)->default(0);
            $table->decimal('expected_cash_amount', 14, 2)->nullable();
            $table->decimal('actual_cash_amount', 14, 2)->nullable();
            $table->decimal('cash_discrepancy_amount', 14, 2)->nullable();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->unsignedInteger('opened_by')->nullable();
            $table->unsignedInteger('closed_by')->nullable();
            $table->string('opening_note', 500)->nullable();
            $table->string('closing_note', 500)->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        DB::table('branches')->insert([
            'branch_id' => 1,
            'branch_code' => 'MAIN',
            'branch_name' => 'Chi nhanh chinh',
            'description' => 'Default branch',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
            'business_hours' => null,
            'closure_windows' => null,
            'booking_policy' => null,
            'is_active' => 1,
            'is_default' => 1,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('roles')->insert([
            ['role_id' => 1, 'role_name' => 'Admin', 'created_at' => now('UTC'), 'updated_at' => now('UTC')],
            ['role_id' => 2, 'role_name' => 'Staff', 'created_at' => now('UTC'), 'updated_at' => now('UTC')],
            ['role_id' => 3, 'role_name' => 'Customer', 'created_at' => now('UTC'), 'updated_at' => now('UTC')],
        ]);
    }

    public function test_staff_can_login_fetch_current_session_refresh_and_logout(): void
    {
        $staffId = DB::table('users')->insertGetId([
            'username' => 'staff-auth-http',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Staff HTTP',
            'email' => 'staff.auth@example.test',
            'phone' => '0903000000',
            'role_id' => 2,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('cashier_shifts')->insert([
            'cashier_shift_id' => 44,
            'branch_id' => 1,
            'shift_code' => 'SHIFT-STAFF-AUTH',
            'cashier_user_id' => $staffId,
            'active_cashier_user_id' => $staffId,
            'status' => 'Open',
            'currency' => 'VND',
            'terminal_code' => 'POS-01',
            'opening_float_amount' => '100000.00',
            'expected_cash_amount' => null,
            'actual_cash_amount' => null,
            'cash_discrepancy_amount' => null,
            'opened_at' => now('UTC'),
            'closed_at' => null,
            'opened_by' => $staffId,
            'closed_by' => null,
            'opening_note' => 'Open for startup bootstrap',
            'closing_note' => null,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $login = $this->postJson('/api/v1/auth/staff/login', [
            'identifier' => 'staff-auth-http',
            'password' => 'secret-123',
            'device_name' => 'front-desk',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.auth_mode', 'staff_api_key')
            ->assertJsonPath('data.auth_header', 'X-Staff-Key')
            ->assertJsonPath('data.user.user_id', $staffId)
            ->assertJsonPath('data.capability_source', 'role_capabilities')
            ->assertJsonPath('data.startup.primary_workspace', 'ops')
            ->assertJsonPath('data.startup.available_workspaces.0', 'ops')
            ->assertJsonPath('data.startup.default_branch_id', 1)
            ->assertJsonPath('data.startup.allowed_branch_ids.0', 1)
            ->assertJsonPath('data.startup.assigned_station_ids', [])
            ->assertJsonPath('data.startup.default_branch.branch_code', 'MAIN')
            ->assertJsonPath('data.startup.branch_access.accessible_branch_ids.0', 1)
            ->assertJsonPath('data.startup.branch_access.default_branch_id', 1)
            ->assertJsonPath('data.startup.branch_access.current_branch_id', 1)
            ->assertJsonPath('data.startup.branch_access.has_default_branch_access', true)
            ->assertJsonPath('data.startup.branch_access.has_multi_branch_access', false)
            ->assertJsonPath('data.startup.branch_access.branch_selector_enabled', false)
            ->assertJsonPath('data.startup.branch_access.access_source', 'role_branch_scopes')
            ->assertJsonPath('data.startup.branch_access.branches_uri', '/api/v1/staff/branches')
            ->assertJsonPath('data.startup.active_cashier_shift.shift_code', 'SHIFT-STAFF-AUTH')
            ->assertJsonPath('data.startup.readiness.branch', 'ready')
            ->assertJsonPath('data.startup.readiness.operator_ready', true);

        $loginCapabilities = (array) $login->json('data.capabilities');
        $loginKnownCapabilities = (array) $login->json('data.known_capabilities');
        self::assertContains($login->json('data.startup.readiness.cashier_shift'), ['ready', 'not_applicable']);
        self::assertIsBool($login->json('data.startup.readiness.requires_cashier_shift'));
        self::assertContains('conversation.manage', $loginCapabilities);
        self::assertContains('payment.refund', $loginKnownCapabilities);

        $token = (string) $login->json('data.access_token');
        $staffApiKeyId = (int) $login->json('data.staff_api_key_id');
        $expiresAt = Carbon::parse((string) $login->json('data.expires_at_utc'))->utc();

        self::assertTrue(
            $expiresAt->betweenIncluded(now('UTC')->addMinutes(29), now('UTC')->addMinutes(31)),
            'Staff browser auth session should expire within the configured short TTL window.'
        );

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => $token,
        ])->getJson('/api/v1/auth/staff/me')
            ->assertOk()
            ->assertJsonPath('data.staff_api_key_id', $staffApiKeyId)
            ->assertJsonPath('data.user.user_id', $staffId)
            ->assertJsonPath('data.capability_source', 'role_capabilities')
            ->assertJsonPath('data.startup.primary_workspace', 'ops')
            ->assertJsonPath('data.startup.default_branch_id', 1)
            ->assertJsonPath('data.startup.default_branch.branch_name', 'Chi nhanh chinh')
            ->assertJsonPath('data.startup.branch_access.current_branch_id', 1)
            ->assertJsonPath('data.startup.active_cashier_shift.cashier_shift_id', 44)
            ->assertJsonPath('data.startup.readiness.granted_capability_count', count($loginCapabilities));

        $refresh = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => $token,
        ])->postJson('/api/v1/auth/staff/refresh');

        $refresh->assertOk()
            ->assertJsonPath('data.auth_mode', 'staff_api_key')
            ->assertJsonPath('data.user.user_id', $staffId)
            ->assertJsonPath('data.capability_source', 'role_capabilities')
            ->assertJsonPath('data.startup.available_workspaces.0', 'ops')
            ->assertJsonPath('data.startup.default_branch.timezone', 'Asia/Ho_Chi_Minh')
            ->assertJsonPath('data.startup.branch_access.has_default_branch_access', true)
            ->assertJsonPath('data.startup.active_cashier_shift.branch.branch_code', 'MAIN')
            ->assertJsonPath('data.startup.readiness.access', 'ready');

        $replacementToken = (string) $refresh->json('data.access_token');
        $replacementStaffApiKeyId = (int) $refresh->json('data.staff_api_key_id');
        self::assertNotSame($token, $replacementToken);
        self::assertNotSame($staffApiKeyId, $replacementStaffApiKeyId);
        self::assertNotNull(DB::table('staff_api_keys')->where('staff_api_key_id', $staffApiKeyId)->value('revoked_at'));

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => $token,
        ])->getJson('/api/v1/auth/staff/me')
            ->assertStatus(401);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => $replacementToken,
        ])->postJson('/api/v1/auth/staff/logout')
            ->assertOk()
            ->assertJsonPath('data.staff_api_key_id', $replacementStaffApiKeyId);

        self::assertNotNull(DB::table('staff_api_keys')->where('staff_api_key_id', $replacementStaffApiKeyId)->value('revoked_at'));

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => $replacementToken,
        ])->getJson('/api/v1/auth/staff/me')
            ->assertStatus(401);
    }

    public function test_expired_staff_session_token_is_rejected_for_current_session_and_refresh(): void
    {
        $staffId = DB::table('users')->insertGetId([
            'username' => 'expired-staff-session',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Expired Staff Session',
            'email' => 'expired.staff@example.test',
            'phone' => '0903000099',
            'role_id' => 2,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $login = $this->postJson('/api/v1/auth/staff/login', [
            'identifier' => 'expired-staff-session',
            'password' => 'secret-123',
            'device_name' => 'front-desk',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.user.user_id', $staffId);

        $token = (string) $login->json('data.access_token');
        $staffApiKeyId = (int) $login->json('data.staff_api_key_id');

        DB::table('staff_api_keys')
            ->where('staff_api_key_id', $staffApiKeyId)
            ->update([
                'expires_at' => now('UTC')->subMinute(),
                'updated_at' => now('UTC'),
            ]);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => $token,
        ])->getJson('/api/v1/auth/staff/me')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('category_code', 'authentication_required');

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => $token,
        ])->postJson('/api/v1/auth/staff/refresh')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('category_code', 'authentication_required');
    }

    public function test_staff_browser_refresh_cookie_flow_survives_reload_without_returning_refresh_secret(): void
    {
        config()->set('staff_auth.browser_session.enabled', true);
        config()->set('staff_auth.browser_session.access_ttl_minutes', 5);
        config()->set('staff_auth.browser_session.secure', true);
        config()->set('staff_auth.browser_session.same_site', 'lax');

        $staffId = DB::table('users')->insertGetId([
            'username' => 'staff-cookie-session',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Staff Cookie Session',
            'email' => 'staff.cookie@example.test',
            'phone' => '0903000101',
            'role_id' => 2,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $login = $this->postJson('/api/v1/auth/staff/login', [
            'identifier' => 'staff-cookie-session',
            'password' => 'secret-123',
            'device_name' => 'front-desk-cookie',
            'session_transport' => 'refresh_cookie',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.auth_mode', 'staff_browser_session')
            ->assertJsonPath('data.session_transport', 'refresh_cookie')
            ->assertJsonPath('data.auth_header', 'X-Staff-Key')
            ->assertJsonPath('data.user.user_id', $staffId);

        $loginPayload = (array) $login->json('data');
        self::assertArrayNotHasKey('refresh_token', $loginPayload);
        self::assertArrayNotHasKey('refresh_cookie', $loginPayload);

        $accessToken = (string) $login->json('data.access_token');
        self::assertNotSame('', $accessToken);

        $refreshCookie = $this->responseCookie($login, 'staff_web_refresh');
        $csrfCookie = $this->responseCookie($login, 'staff_web_csrf');

        self::assertTrue($refreshCookie->isHttpOnly());
        self::assertTrue($refreshCookie->isSecure());
        self::assertSame('lax', $refreshCookie->getSameSite());
        self::assertSame('/api/v1/auth/staff', $refreshCookie->getPath());
        self::assertFalse($csrfCookie->isHttpOnly());
        self::assertTrue($csrfCookie->isSecure());
        self::assertSame('/', $csrfCookie->getPath());
        self::assertNotSame($accessToken, $refreshCookie->getValue());

        $refreshStaffApiKeyId = (int) DB::table('staff_api_keys')
            ->where('key_hash', hash('sha256', (string) $refreshCookie->getValue()))
            ->value('staff_api_key_id');
        self::assertGreaterThan(0, $refreshStaffApiKeyId);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => (string) $refreshCookie->getValue(),
        ])->getJson('/api/v1/auth/staff/me')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');

        $refresh = $this->postJsonWithCookies('/api/v1/auth/staff/refresh', [
            'staff_web_refresh' => (string) $refreshCookie->getValue(),
            'staff_web_csrf' => (string) $csrfCookie->getValue(),
        ], [
            'X-Staff-CSRF' => (string) $csrfCookie->getValue(),
        ]);

        $refresh->assertOk()
            ->assertJsonPath('data.auth_mode', 'staff_browser_session')
            ->assertJsonPath('data.session_transport', 'refresh_cookie')
            ->assertJsonPath('data.user.user_id', $staffId);

        $oldRefresh = $this->postJsonWithCookies('/api/v1/auth/staff/refresh', [
            'staff_web_refresh' => (string) $refreshCookie->getValue(),
            'staff_web_csrf' => (string) $csrfCookie->getValue(),
        ], [
            'X-Staff-CSRF' => (string) $csrfCookie->getValue(),
        ]);

        $oldRefresh->assertStatus(401);

        $this->assertNotNull(DB::table('staff_api_keys')->where('staff_api_key_id', $refreshStaffApiKeyId)->value('revoked_at'));
    }

    public function test_staff_browser_refresh_cookie_requires_csrf_header_and_logout_clears_cookie(): void
    {
        config()->set('staff_auth.browser_session.enabled', true);
        config()->set('staff_auth.browser_session.secure', true);

        DB::table('users')->insert([
            'user_id' => 132,
            'username' => 'staff-cookie-csrf',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Staff Cookie CSRF',
            'email' => 'staff.cookie.csrf@example.test',
            'phone' => '0903000132',
            'role_id' => 2,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $login = $this->postJson('/api/v1/auth/staff/login', [
            'identifier' => 'staff-cookie-csrf',
            'password' => 'secret-123',
            'device_name' => 'csrf-test',
            'session_transport' => 'refresh_cookie',
        ]);

        $refreshCookie = $this->responseCookie($login, 'staff_web_refresh');
        $csrfCookie = $this->responseCookie($login, 'staff_web_csrf');

        $this->postJsonWithCookies('/api/v1/auth/staff/refresh', [
            'staff_web_refresh' => (string) $refreshCookie->getValue(),
            'staff_web_csrf' => (string) $csrfCookie->getValue(),
        ])
            ->assertStatus(419)
            ->assertJsonPath('error_code', 'csrf_token_mismatch');

        $refresh = $this->postJsonWithCookies('/api/v1/auth/staff/refresh', [
            'staff_web_refresh' => (string) $refreshCookie->getValue(),
            'staff_web_csrf' => (string) $csrfCookie->getValue(),
        ], [
            'X-Staff-CSRF' => (string) $csrfCookie->getValue(),
        ]);

        $refresh->assertOk();
        $rotatedRefreshCookie = $this->responseCookie($refresh, 'staff_web_refresh');
        $rotatedCsrfCookie = $this->responseCookie($refresh, 'staff_web_csrf');
        $accessToken = (string) $refresh->json('data.access_token');
        $rotatedRefreshStaffApiKeyId = (int) DB::table('staff_api_keys')
            ->where('key_hash', hash('sha256', (string) $rotatedRefreshCookie->getValue()))
            ->value('staff_api_key_id');

        $this->postJsonWithCookies('/api/v1/auth/staff/logout', [
            'staff_web_refresh' => (string) $rotatedRefreshCookie->getValue(),
            'staff_web_csrf' => (string) $rotatedCsrfCookie->getValue(),
        ], [
            'X-Staff-Key' => $accessToken,
        ])
            ->assertStatus(419)
            ->assertJsonPath('error_code', 'csrf_token_mismatch');

        $logout = $this->postJsonWithCookies('/api/v1/auth/staff/logout', [
            'staff_web_refresh' => (string) $rotatedRefreshCookie->getValue(),
            'staff_web_csrf' => (string) $rotatedCsrfCookie->getValue(),
        ], [
            'X-Staff-Key' => $accessToken,
            'X-Staff-CSRF' => (string) $rotatedCsrfCookie->getValue(),
        ]);

        $logout->assertOk()
            ->assertJsonPath('data.auth_mode', 'staff_browser_session')
            ->assertJsonPath('data.session_transport', 'refresh_cookie');

        self::assertLessThanOrEqual(time(), $this->responseCookie($logout, 'staff_web_refresh')->getExpiresTime());
        self::assertLessThanOrEqual(time(), $this->responseCookie($logout, 'staff_web_csrf')->getExpiresTime());
        $this->assertNotNull(DB::table('staff_api_keys')->where('staff_api_key_id', $rotatedRefreshStaffApiKeyId)->value('revoked_at'));
    }

    public function test_staff_login_rejects_customer_role_accounts(): void
    {
        DB::table('users')->insert([
            'user_id' => 30,
            'username' => 'customer-account',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Customer Account',
            'email' => 'customer.account@example.test',
            'phone' => '0904000000',
            'role_id' => 3,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->postJson('/api/v1/auth/staff/login', [
            'identifier' => 'customer-account',
            'password' => 'secret-123',
        ])->assertStatus(422)
            ->assertJsonPath('errors.identifier.0', 'Invalid credentials.');
    }

    public function test_staff_login_fails_closed_when_staff_role_contract_is_missing(): void
    {
        DB::table('users')->insert([
            'user_id' => 32,
            'username' => 'staff-role-config-missing',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Staff Role Config Missing',
            'email' => 'staff.role.config@example.test',
            'phone' => '0904000002',
            'role_id' => 2,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        config()->set('staff_auth.allowed_role_ids', []);
        config()->set('staff_auth.allow_role_name_fallback', false);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Request-Id' => 'req-staff-role-config-missing',
        ])->postJson('/api/v1/auth/staff/login', [
            'identifier' => 'staff-role-config-missing',
            'password' => 'secret-123',
        ])->assertStatus(503)
            ->assertHeader('X-Request-Id', 'req-staff-role-config-missing')
            ->assertJsonPath('error_code', 'staff_role_configuration_missing')
            ->assertJsonPath('category_code', 'staff_role_configuration_missing')
            ->assertJsonPath('state_reason', 'staff_role_configuration_missing')
            ->assertJsonPath('request_id', 'req-staff-role-config-missing');
    }

    public function test_staff_login_blocks_role_name_fallback_in_production_like_environment(): void
    {
        DB::table('users')->insert([
            'user_id' => 33,
            'username' => 'staff-role-fallback-blocked',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Staff Role Fallback Blocked',
            'email' => 'staff.role.fallback@example.test',
            'phone' => '0904000003',
            'role_id' => 2,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        config()->set('app.env', 'production');
        config()->set('staff_auth.allowed_role_ids', []);
        config()->set('staff_auth.allow_role_name_fallback', true);
        config()->set('staff_auth.production_like_environments', ['production']);
        config()->set('staff_auth.deny_role_name_fallback_in_production_like', true);

        $this->postJson('/api/v1/auth/staff/login', [
            'identifier' => 'staff-role-fallback-blocked',
            'password' => 'secret-123',
        ])->assertStatus(503)
            ->assertJsonPath('error_code', 'staff_role_name_fallback_blocked')
            ->assertJsonPath('category_code', 'staff_role_name_fallback_blocked')
            ->assertJsonPath('state_reason', 'staff_role_name_fallback_blocked');
    }

    public function test_staff_api_key_env_fallback_is_blocked_in_production_like_environment(): void
    {
        DB::table('users')->insert([
            'user_id' => 34,
            'username' => 'staff-env-fallback-blocked',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Staff Env Fallback Blocked',
            'email' => 'staff.env.fallback@example.test',
            'phone' => '0904000004',
            'role_id' => 2,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        config()->set('app.env', 'production');
        config()->set('staff_auth.api_keys', ['prod-env-fallback-key' => 34]);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.env_fallback_allowed_environments', ['production']);
        config()->set('staff_auth.production_like_environments', ['production']);
        config()->set('staff_auth.deny_env_fallback_in_production_like', true);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => 'prod-env-fallback-key',
        ])->getJson('/api/v1/auth/staff/me')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('category_code', 'authentication_required');

        $this->assertSame(0, DB::table('staff_api_keys')->count());
    }

    public function test_staff_startup_contract_resolves_ops_only_actor(): void
    {
        $login = $this->loginWorkspaceActor(11, 'OpsOnly', [
            'table.board.view',
            'reservation.manage',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.startup.primary_workspace', 'ops')
            ->assertJsonPath('data.startup.available_workspaces', ['ops'])
            ->assertJsonPath('data.startup.default_branch_id', 1)
            ->assertJsonPath('data.startup.allowed_branch_ids', [1])
            ->assertJsonPath('data.startup.assigned_station_ids', [])
            ->assertJsonPath('data.startup.readiness.branch', 'ready');
    }

    public function test_staff_startup_contract_resolves_kitchen_only_actor(): void
    {
        $this->createKitchenStationsTable();

        $login = $this->loginWorkspaceActor(12, 'KitchenOnly', [
            'kitchen.manage',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.startup.primary_workspace', 'kitchen')
            ->assertJsonPath('data.startup.available_workspaces', ['kitchen'])
            ->assertJsonPath('data.startup.default_branch_id', 1)
            ->assertJsonPath('data.startup.allowed_branch_ids', [1])
            ->assertJsonPath('data.startup.assigned_station_ids', [501])
            ->assertJsonPath('data.startup.readiness.cashier_shift', 'not_applicable');
    }

    public function test_staff_startup_contract_resolves_admin_only_actor(): void
    {
        $login = $this->loginWorkspaceActor(13, 'AdminOnly', [
            'reporting.view',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.startup.primary_workspace', 'admin')
            ->assertJsonPath('data.startup.available_workspaces', ['admin'])
            ->assertJsonPath('data.startup.default_branch_id', 1)
            ->assertJsonPath('data.startup.allowed_branch_ids', [1])
            ->assertJsonPath('data.startup.assigned_station_ids', []);
    }

    public function test_staff_startup_contract_resolves_multi_workspace_actor_with_ops_primary(): void
    {
        $login = $this->loginWorkspaceActor(14, 'MultiWorkspace', [
            'kitchen.manage',
            'reporting.view',
            'reservation.manage',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.startup.primary_workspace', 'ops')
            ->assertJsonPath('data.startup.available_workspaces', ['ops', 'kitchen', 'admin'])
            ->assertJsonPath('data.startup.default_branch_id', 1)
            ->assertJsonPath('data.startup.allowed_branch_ids', [1]);
    }

    public function test_staff_startup_contract_handles_missing_branch_and_station_context(): void
    {
        Schema::dropIfExists('cashier_shifts');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('kitchen_stations');

        $login = $this->loginWorkspaceActor(15, 'KitchenNoContext', [
            'kitchen.manage',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.startup.primary_workspace', 'kitchen')
            ->assertJsonPath('data.startup.available_workspaces', ['kitchen'])
            ->assertJsonPath('data.startup.default_branch_id', null)
            ->assertJsonPath('data.startup.allowed_branch_ids', [])
            ->assertJsonPath('data.startup.assigned_station_ids', [])
            ->assertJsonPath('data.startup.default_branch', null)
            ->assertJsonPath('data.startup.active_cashier_shift', null)
            ->assertJsonPath('data.startup.readiness.branch', 'missing')
            ->assertJsonPath('data.startup.readiness.operator_ready', false);
    }

    public function test_staff_startup_contract_does_not_grant_implicit_default_branch_access_to_custom_roles_without_branch_scope(): void
    {
        $roleId = 16;
        $roleName = 'ScopedOpsNoBranch';
        $username = 'scoped-ops-no-branch';

        config()->set('staff_auth.allowed_role_ids', array_values(array_unique(array_merge(
            array_map('intval', (array) config('staff_auth.allowed_role_ids', [])),
            [$roleId],
        ))));

        $roleCapabilities = (array) config('staff_capabilities.role_capabilities', []);
        $roleCapabilities[$roleName] = [
            'reservation.manage',
        ];
        config()->set('staff_capabilities.role_capabilities', $roleCapabilities);

        DB::table('roles')->insert([
            'role_id' => $roleId,
            'role_name' => $roleName,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('users')->insert([
            'user_id' => 1016,
            'username' => $username,
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Scoped Ops No Branch',
            'email' => 'scoped.ops.no.branch@example.test',
            'phone' => '0905000016',
            'role_id' => $roleId,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $login = $this->postJson('/api/v1/auth/staff/login', [
            'identifier' => $username,
            'password' => 'secret-123',
            'device_name' => 'contract-test',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.capability_source', 'role_capabilities')
            ->assertJsonPath('data.startup.allowed_branch_ids', [])
            ->assertJsonPath('data.startup.branch_access.accessible_branch_ids', [])
            ->assertJsonPath('data.startup.branch_access.default_branch_id', 1)
            ->assertJsonPath('data.startup.branch_access.current_branch_id', null)
            ->assertJsonPath('data.startup.branch_access.has_default_branch_access', false)
            ->assertJsonPath('data.startup.branch_access.has_multi_branch_access', false)
            ->assertJsonPath('data.startup.branch_access.branch_selector_enabled', false)
            ->assertJsonPath('data.startup.branch_access.access_source', 'fallback_branch_scopes')
            ->assertJsonPath('data.startup.readiness.branch', 'missing')
            ->assertJsonPath('data.startup.readiness.operator_ready', false);
    }

    public function test_customer_access_tokens_do_not_authenticate_staff_product_routes(): void
    {
        config()->set('customer_auth.enabled', true);
        config()->set('customer_auth.allowed_role_ids', [3]);

        DB::table('users')->insert([
            'user_id' => 31,
            'username' => 'customer-auth-token',
            'password_hash' => Hash::make('secret-123'),
            'full_name' => 'Customer Token',
            'email' => 'customer.token@example.test',
            'phone' => '0904000001',
            'role_id' => 3,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $login = $this->postJson('/api/v1/auth/customer/login', [
            'identifier' => 'customer-auth-token',
            'password' => 'secret-123',
            'session_label' => 'web',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.auth_mode', 'customer_access_session');

        $customerToken = (string) $login->json('data.access_token');

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Customer-Token' => $customerToken,
        ])->getJson('/api/v1/auth/staff/me')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Customer-Token' => $customerToken,
        ])->postJson('/api/v1/auth/staff/refresh')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Customer-Token' => $customerToken,
        ])->postJson('/api/v1/auth/staff/logout')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    /**
     * @param  list<string>  $capabilities
     * @param  list<string>  $branchScopes
     */
    private function loginWorkspaceActor(int $roleId, string $roleName, array $capabilities, array $branchScopes = ['default']): TestResponse
    {
        $allowedRoleIds = array_values(array_unique(array_merge(
            array_map('intval', (array) config('staff_auth.allowed_role_ids', [])),
            [$roleId],
        )));
        config()->set('staff_auth.allowed_role_ids', $allowedRoleIds);

        $roleCapabilities = (array) config('staff_capabilities.role_capabilities', []);
        $roleCapabilities[$roleName] = $capabilities;
        config()->set('staff_capabilities.role_capabilities', $roleCapabilities);

        $roleBranchScopes = (array) config('staff_capabilities.role_branch_scopes', []);
        $roleBranchScopes[$roleName] = $branchScopes;
        config()->set('staff_capabilities.role_branch_scopes', $roleBranchScopes);

        DB::table('roles')->insert([
            'role_id' => $roleId,
            'role_name' => $roleName,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $username = mb_strtolower($roleName).'-actor';

        DB::table('users')->insert([
            'user_id' => 1000 + $roleId,
            'username' => $username,
            'password_hash' => Hash::make('secret-123'),
            'full_name' => $roleName.' Actor',
            'email' => mb_strtolower($roleName).'@example.test',
            'phone' => '090500'.str_pad((string) $roleId, 4, '0', STR_PAD_LEFT),
            'role_id' => $roleId,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return $this->postJson('/api/v1/auth/staff/login', [
            'identifier' => $username,
            'password' => 'secret-123',
            'device_name' => 'contract-test',
        ]);
    }

    private function createKitchenStationsTable(): void
    {
        Schema::dropIfExists('kitchen_stations');
        Schema::create('kitchen_stations', function (Blueprint $table): void {
            $table->increments('station_id');
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('output_mode', 20)->default('KDS');
            $table->string('printer_target', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        DB::table('kitchen_stations')->insert([
            [
                'station_id' => 501,
                'code' => 'HOT-PASS',
                'name' => 'Hot Pass',
                'description' => null,
                'output_mode' => 'KDS',
                'printer_target' => null,
                'is_active' => 1,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ],
            [
                'station_id' => 502,
                'code' => 'CLOSED-BAR',
                'name' => 'Closed Bar',
                'description' => null,
                'output_mode' => 'KDS',
                'printer_target' => null,
                'is_active' => 0,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ],
        ]);
    }

    private function responseCookie(TestResponse $response, string $name): Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        self::fail("Expected response cookie [{$name}] to be set.");
    }

    /**
     * @param  array<string,string>  $cookies
     * @param  array<string,string>  $headers
     */
    private function postJsonWithCookies(string $uri, array $cookies, array $headers = []): TestResponse
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', $uri, [], $cookies, [], $server, '{}');
    }
}
