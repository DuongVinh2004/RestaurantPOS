<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\StaffApiKeyGovernanceService;
use App\Support\StaffActorResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffApiKeyGovernanceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'testing');
        config()->set('staff_auth.database_store_enabled', true);
        config()->set('staff_auth.allow_env_fallback', false);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_auth.allow_role_name_fallback', false);
        config()->set('staff_auth.allowed_role_ids', [2]);
        config()->set('staff_auth.touch_last_used_at', false);

        $this->ensureStaffAuthTables();
        $this->truncateStaffAuthTables();
        $this->seedStaffUser();
    }

    public function test_issue_key_persists_hashed_secret_and_plaintext_can_be_resolved(): void
    {
        $issued = app(StaffApiKeyGovernanceService::class)->issueKey(
            userId: 2,
            label: 'Primary POS terminal',
            expiresAt: now('UTC')->addDays(30),
        );

        $record = $issued['record'];
        $plaintextKey = $issued['plaintext_key'];

        $this->assertNotSame('', $plaintextKey);
        $this->assertStringStartsWith('spk_', $plaintextKey);
        $this->assertSame(hash('sha256', $plaintextKey), (string) $record->key_hash);
        $this->assertNotSame($plaintextKey, (string) $record->key_hash);
        $this->assertNull($record->revoked_at);

        $resolved = app(StaffActorResolver::class)->resolveFromProvidedKey($plaintextKey);

        $this->assertTrue($resolved['ok']);
        $this->assertSame('database_key', $resolved['mode']);
        $this->assertSame(2, (int) $resolved['user']->user_id);
    }

    public function test_revoke_key_is_idempotent_and_blocks_future_resolution(): void
    {
        $issued = app(StaffApiKeyGovernanceService::class)->issueKey(userId: 2, label: 'Back office');
        $record = $issued['record'];
        $plaintextKey = $issued['plaintext_key'];

        $revoked = app(StaffApiKeyGovernanceService::class)->revokeKey((int) $record->getKey(), 'operator revoked');
        $revokedAgain = app(StaffApiKeyGovernanceService::class)->revokeKey((int) $record->getKey(), 'repeat revoke');

        $this->assertNotNull($revoked->revoked_at);
        $this->assertNotNull($revokedAgain->revoked_at);

        $resolved = app(StaffActorResolver::class)->resolveFromProvidedKey($plaintextKey);

        $this->assertFalse($resolved['ok']);
        $this->assertSame(401, $resolved['status']);
    }

    public function test_rotate_key_revokes_old_secret_and_issues_new_resolvable_secret(): void
    {
        $issued = app(StaffApiKeyGovernanceService::class)->issueKey(userId: 2, label: 'Cashier iPad');
        $oldRecord = $issued['record'];
        $oldPlaintext = $issued['plaintext_key'];

        $rotated = app(StaffApiKeyGovernanceService::class)->rotateKey(
            staffApiKeyId: (int) $oldRecord->getKey(),
            replacementLabel: 'Cashier iPad Rotated',
            expiresAt: now('UTC')->addDays(7),
        );

        $replacement = $rotated['record'];
        $replacementPlaintext = $rotated['plaintext_key'];

        $this->assertNotSame((int) $oldRecord->getKey(), (int) $replacement->getKey());
        $this->assertNotNull($rotated['revoked']->revoked_at);
        $this->assertSame('Cashier iPad Rotated', (string) $replacement->label);
        $this->assertSame(hash('sha256', $replacementPlaintext), (string) $replacement->key_hash);

        $oldResolved = app(StaffActorResolver::class)->resolveFromProvidedKey($oldPlaintext);
        $newResolved = app(StaffActorResolver::class)->resolveFromProvidedKey($replacementPlaintext);

        $this->assertFalse($oldResolved['ok']);
        $this->assertSame(401, $oldResolved['status']);
        $this->assertTrue($newResolved['ok']);
        $this->assertSame(2, (int) $newResolved['user']->user_id);
    }

    public function test_issue_key_rejects_empty_label(): void
    {
        $this->expectException(ValidationException::class);

        app(StaffApiKeyGovernanceService::class)->issueKey(userId: 2, label: '   ');
    }

    public function test_issue_key_rejects_non_staff_role_target(): void
    {
        DB::table('roles')->insert([
            'role_id' => 3,
            'role_name' => 'Customer',
        ]);

        DB::table('users')->insert([
            'user_id' => 3,
            'username' => 'customer',
            'full_name' => 'Customer User',
            'email' => 'customer@example.test',
            'phone' => '0900000001',
            'role_id' => 3,
            'current_tier_id' => null,
            'language_pref' => 'vi',
            'is_deleted' => 0,
            'password_hash' => null,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->expectException(ValidationException::class);

        app(StaffApiKeyGovernanceService::class)->issueKey(userId: 3, label: 'Should not issue');
    }

    public function test_rotate_key_preserves_existing_expiry_when_replacement_expiry_is_not_supplied(): void
    {
        $issued = app(StaffApiKeyGovernanceService::class)->issueKey(
            userId: 2,
            label: 'POS A',
            expiresAt: now('UTC')->addDays(14),
        );

        $rotated = app(StaffApiKeyGovernanceService::class)->rotateKey(
            staffApiKeyId: (int) $issued['record']->getKey(),
            replacementLabel: 'POS A rotated',
            expiresAt: null,
        );

        $this->assertNotNull($rotated['record']->expires_at);
        $this->assertSame(
            $issued['record']->expires_at?->utc()->toIso8601String(),
            $rotated['record']->expires_at?->utc()->toIso8601String()
        );
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
                $table->string('label', 100);
                $table->char('key_hash', 64)->unique();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }
    }

    private function truncateStaffAuthTables(): void
    {
        DB::table('staff_api_keys')->delete();
        DB::table('users')->delete();
        DB::table('roles')->delete();
    }

    private function seedStaffUser(): void
    {
        DB::table('roles')->insert([
            'role_id' => 2,
            'role_name' => 'Staff',
        ]);

        DB::table('users')->insert([
            'user_id' => 2,
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
