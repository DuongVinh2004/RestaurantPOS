<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Modules\IdentityAccess\Infrastructure\Tokenization\StaffApiKeyActorResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffApiKeyActorResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'testing');
        config()->set('staff_auth.database_store_enabled', true);
        config()->set('staff_auth.allow_bearer', false);
        config()->set('staff_auth.allow_env_fallback', false);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_auth.env_fallback_allowed_environments', ['local', 'testing']);
        config()->set('staff_auth.production_like_environments', ['production', 'staging']);
        config()->set('staff_auth.deny_env_fallback_in_production_like', true);
        config()->set('staff_auth.deny_role_name_fallback_in_production_like', true);
        config()->set('staff_auth.api_keys', ['staff-key' => 99999]);
        config()->set('staff_auth.legacy_key', 'legacy-secret');
        config()->set('staff_auth.legacy_user_id', 99999);
        config()->set('staff_auth.allowed_role_ids', [2]);
        config()->set('staff_auth.allow_role_name_fallback', false);

        $this->ensureStaffAuthTables();
        $this->truncateStaffAuthTables();
        $this->seedStaffUser();
    }

    protected function tearDown(): void
    {
        $this->truncateStaffAuthTables();
        parent::tearDown();
    }

    public function test_it_resolves_database_backed_staff_key_and_touches_last_used_at(): void
    {
        DB::table('staff_api_keys')->insert([
            'staff_api_key_id' => 99991,
            'user_id' => 99999,
            'label' => 'Primary Key',
            'key_hash' => hash('sha256', 'db-staff-key'),
            'last_used_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $result = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey('db-staff-key');

        $this->assertTrue($result['ok']);
        $this->assertSame('database_key', $result['mode']);
        $this->assertSame(99999, (int) $result['user']->user_id);
        $this->assertSame(99991, (int) ($result['staff_api_key_id'] ?? 0));
        $this->assertNotNull(DB::table('staff_api_keys')->where('staff_api_key_id', 99991)->value('last_used_at'));
    }

    public function test_it_rejects_revoked_database_backed_staff_key(): void
    {
        DB::table('staff_api_keys')->insert([
            'staff_api_key_id' => 99992,
            'user_id' => 99999,
            'label' => 'Revoked Key',
            'key_hash' => hash('sha256', 'revoked-key'),
            'last_used_at' => null,
            'expires_at' => null,
            'revoked_at' => now('UTC'),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $result = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey('revoked-key');

        $this->assertFalse($result['ok']);
        $this->assertSame(401, $result['status']);
    }

    public function test_it_rejects_expired_database_backed_staff_key(): void
    {
        DB::table('staff_api_keys')->insert([
            'staff_api_key_id' => 99993,
            'user_id' => 99999,
            'label' => 'Expired Key',
            'key_hash' => hash('sha256', 'expired-key'),
            'last_used_at' => null,
            'expires_at' => now('UTC')->subMinute(),
            'revoked_at' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $result = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey('expired-key');

        $this->assertFalse($result['ok']);
        $this->assertSame(401, $result['status']);
    }

    public function test_it_does_not_use_env_fallback_when_flag_is_disabled_even_in_allowed_environment(): void
    {
        $result = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey('staff-key');

        $this->assertFalse($result['ok']);
        $this->assertSame(401, $result['status']);
    }

    public function test_it_uses_env_fallback_when_explicitly_enabled_in_allowed_environment(): void
    {
        config()->set('staff_auth.allow_env_fallback', true);

        $result = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey('staff-key');

        $this->assertTrue($result['ok']);
        $this->assertSame('mapped_key_fallback', $result['mode']);
        $this->assertSame(99999, (int) $result['user']->user_id);
    }

    public function test_it_blocks_runtime_env_fallback_outside_allow_list_even_when_flag_is_enabled(): void
    {
        config()->set('app.env', 'production');
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.env_fallback_allowed_environments', ['local', 'testing']);

        $result = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey('staff-key');

        $this->assertFalse($result['ok']);
        $this->assertSame(401, $result['status']);
    }

    public function test_it_blocks_env_fallback_in_production_like_environment_even_when_allow_listed(): void
    {
        config()->set('app.env', 'production');
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.env_fallback_allowed_environments', ['production']);

        $result = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey('staff-key');

        $this->assertFalse($result['ok']);
        $this->assertSame(401, $result['status']);
    }

    public function test_it_uses_database_store_unavailable_fallback_only_when_explicitly_enabled_and_allow_listed(): void
    {
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', true);
        config()->set('staff_auth.database_store_enabled', false);

        $result = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey('legacy-secret');

        $this->assertTrue($result['ok']);
        $this->assertSame('legacy_key_fallback', $result['mode']);
        $this->assertSame(99999, (int) $result['user']->user_id);
    }

    public function test_it_blocks_database_store_unavailable_fallback_outside_allow_list(): void
    {
        config()->set('app.env', 'production');
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', true);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.env_fallback_allowed_environments', ['local', 'testing']);

        $result = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey('legacy-secret');

        $this->assertFalse($result['ok']);
        $this->assertSame(401, $result['status']);
    }

    public function test_it_blocks_role_name_fallback_in_production_like_environment(): void
    {
        DB::table('staff_api_keys')->insert([
            'staff_api_key_id' => 99994,
            'user_id' => 99999,
            'label' => 'Role Name Fallback Blocked',
            'key_hash' => hash('sha256', 'db-role-fallback-key'),
            'last_used_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        config()->set('app.env', 'production');
        config()->set('staff_auth.allowed_role_ids', []);
        config()->set('staff_auth.allow_role_name_fallback', true);

        $result = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey('db-role-fallback-key');

        $this->assertFalse($result['ok']);
        $this->assertSame(503, $result['status']);
        $this->assertSame('staff_role_name_fallback_blocked', $result['error_code']);
    }

    private function ensureStaffAuthTables(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->unsignedBigInteger('role_id')->primary();
                $table->string('role_name', 50);
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->primary();
                $table->string('username', 100)->nullable();
                $table->string('full_name', 150)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('phone', 50)->nullable();
                $table->unsignedBigInteger('role_id')->nullable();
                $table->unsignedBigInteger('current_tier_id')->nullable();
                $table->string('language_pref', 20)->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->string('password_hash', 255)->nullable();
                $table->unsignedBigInteger('row_version')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_api_keys')) {
            Schema::create('staff_api_keys', function (Blueprint $table): void {
                $table->unsignedBigInteger('staff_api_key_id')->primary();
                $table->unsignedBigInteger('user_id');
                $table->string('label', 100)->nullable();
                $table->char('key_hash', 64);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }
    }

    private function truncateStaffAuthTables(): void
    {
        DB::table('staff_api_keys')->where('user_id', 99999)->delete();
        DB::table('users')->where('user_id', 99999)->delete();
    }

    private function seedStaffUser(): void
    {
        DB::table('roles')->updateOrInsert([
            'role_id' => 2,
        ], [
            'role_name' => 'Staff',
        ]);

        DB::table('users')->updateOrInsert([
            'user_id' => 99999,
        ], [
            'username' => 'staff',
            'full_name' => 'Staff User',
            'email' => 'staff@example.test',
            'phone' => '0900000000',
            'role_id' => 2,
            'current_tier_id' => null,
            'language_pref' => 'vi',
            'is_deleted' => 0,
            'password_hash' => null,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }
}
