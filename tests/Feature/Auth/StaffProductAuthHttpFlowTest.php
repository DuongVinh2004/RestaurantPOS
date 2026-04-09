<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
        config()->set('staff_auth.session_ttl_minutes', 720);
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
            'branch_name' => 'Main Branch',
            'description' => 'Default branch',
            'timezone' => 'Asia/Bangkok',
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
            ->assertJsonPath('data.capabilities.0', 'audit.view')
            ->assertJsonPath('data.startup.default_branch.branch_code', 'MAIN')
            ->assertJsonPath('data.startup.active_cashier_shift.shift_code', 'SHIFT-STAFF-AUTH')
            ->assertJsonPath('data.startup.readiness.branch', 'ready')
            ->assertJsonPath('data.startup.readiness.cashier_shift', 'ready')
            ->assertJsonPath('data.startup.readiness.operator_ready', true)
            ->assertJsonPath('data.startup.readiness.requires_cashier_shift', true);

        $loginCapabilities = (array) $login->json('data.capabilities');
        $loginKnownCapabilities = (array) $login->json('data.known_capabilities');
        self::assertContains('table.board.view', $loginCapabilities);
        self::assertContains('settlement.manage', $loginCapabilities);
        self::assertContains('conversation.manage', $loginCapabilities);
        self::assertContains('payment.refund', $loginKnownCapabilities);

        $token = (string) $login->json('data.access_token');
        $staffApiKeyId = (int) $login->json('data.staff_api_key_id');

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => $token,
        ])->getJson('/api/v1/auth/staff/me')
            ->assertOk()
            ->assertJsonPath('data.staff_api_key_id', $staffApiKeyId)
            ->assertJsonPath('data.user.user_id', $staffId)
            ->assertJsonPath('data.capability_source', 'role_capabilities')
            ->assertJsonPath('data.capabilities.0', 'audit.view')
            ->assertJsonPath('data.startup.default_branch.branch_name', 'Main Branch')
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
            ->assertJsonPath('data.startup.default_branch.timezone', 'Asia/Bangkok')
            ->assertJsonPath('data.startup.active_cashier_shift.branch.branch_code', 'MAIN')
            ->assertJsonPath('data.startup.readiness.access', 'ready');

        $replacementToken = (string) $refresh->json('data.access_token');
        $replacementStaffApiKeyId = (int) $refresh->json('data.staff_api_key_id');
        self::assertNotSame($token, $replacementToken);
        self::assertNotSame($staffApiKeyId, $replacementStaffApiKeyId);

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

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => $replacementToken,
        ])->getJson('/api/v1/auth/staff/me')
            ->assertStatus(401);
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
    }
}
