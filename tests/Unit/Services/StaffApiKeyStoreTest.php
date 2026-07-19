<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;
use App\Modules\IdentityAccess\Infrastructure\Tokenization\StaffApiKeyActorResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffApiKeyStoreTest extends TestCase
{
    use DatabaseTransactions;

    private const TEST_STAFF_ROLE_ID = 99997;

    private const TEST_NON_STAFF_ROLE_ID = 99998;

    private const TEST_NON_STAFF_USER_ID = 99998;

    private const TEST_STAFF_USER_ID = 99999;

    private const TEST_STAFF_ROLE_NAME = 'TestStaffApiKeyStoreStaff';

    private const TEST_NON_STAFF_ROLE_NAME = 'TestStaffApiKeyStoreCustomer';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'testing');
        config()->set('staff_auth.database_store_enabled', true);
        config()->set('staff_auth.allow_env_fallback', false);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_auth.allow_role_name_fallback', false);
        config()->set('staff_auth.allowed_role_ids', [self::TEST_STAFF_ROLE_ID]);
        config()->set('staff_auth.touch_last_used_at', false);
        config()->set('audit.hash_key', 'staff-api-key-store-audit-test-key');

        $this->ensureStaffAuthTables();
        $this->truncateStaffAuthTables();
        $this->seedStaffUser();
    }

    protected function tearDown(): void
    {
        $this->truncateStaffAuthTables();
        parent::tearDown();
    }

    public function test_issue_key_persists_hashed_secret_and_plaintext_can_be_resolved(): void
    {
        $issued = app(StaffApiKeyStore::class)->issueKey(
            userId: self::TEST_STAFF_USER_ID,
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

        $resolved = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey($plaintextKey);

        $this->assertTrue($resolved['ok']);
        $this->assertSame('database_key', $resolved['mode']);
        $this->assertSame(self::TEST_STAFF_USER_ID, (int) $resolved['user']->user_id);
    }

    public function test_revoke_key_is_idempotent_and_blocks_future_resolution(): void
    {
        $issued = app(StaffApiKeyStore::class)->issueKey(userId: self::TEST_STAFF_USER_ID, label: 'Back office');
        $record = $issued['record'];
        $plaintextKey = $issued['plaintext_key'];

        $revoked = app(StaffApiKeyStore::class)->revokeKey((int) $record->getKey(), 'operator revoked');
        $revokedAgain = app(StaffApiKeyStore::class)->revokeKey((int) $record->getKey(), 'repeat revoke');

        $this->assertNotNull($revoked->revoked_at);
        $this->assertNotNull($revokedAgain->revoked_at);

        $resolved = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey($plaintextKey);

        $this->assertFalse($resolved['ok']);
        $this->assertSame(401, $resolved['status']);
    }

    public function test_rotate_key_revokes_old_secret_and_issues_new_resolvable_secret(): void
    {
        $issued = app(StaffApiKeyStore::class)->issueKey(userId: self::TEST_STAFF_USER_ID, label: 'Cashier iPad');
        $oldRecord = $issued['record'];
        $oldPlaintext = $issued['plaintext_key'];

        $rotated = app(StaffApiKeyStore::class)->rotateKey(
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

        $oldResolved = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey($oldPlaintext);
        $newResolved = app(StaffApiKeyActorResolver::class)->resolveFromProvidedKey($replacementPlaintext);

        $this->assertFalse($oldResolved['ok']);
        $this->assertSame(401, $oldResolved['status']);
        $this->assertTrue($newResolved['ok']);
        $this->assertSame(self::TEST_STAFF_USER_ID, (int) $newResolved['user']->user_id);
    }

    public function test_issue_key_rejects_empty_label(): void
    {
        $this->expectException(ValidationException::class);

        app(StaffApiKeyStore::class)->issueKey(userId: self::TEST_STAFF_USER_ID, label: '   ');
    }

    public function test_issue_key_rejects_non_staff_role_target(): void
    {
        DB::table('roles')->updateOrInsert([
            'role_id' => self::TEST_NON_STAFF_ROLE_ID,
        ], [
            'role_name' => self::TEST_NON_STAFF_ROLE_NAME,
        ]);

        DB::table('users')->updateOrInsert([
            'user_id' => self::TEST_NON_STAFF_USER_ID,
        ], [
            'username' => 'staff-api-key-store-non-staff',
            'full_name' => 'Staff Api Key Store Non Staff User',
            'email' => 'staff-api-key-store-non-staff@example.test',
            'phone' => '0900000001',
            'role_id' => self::TEST_NON_STAFF_ROLE_ID,
            'current_tier_id' => null,
            'language_pref' => 'vi',
            'is_deleted' => 0,
            'password_hash' => null,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->expectException(ValidationException::class);

        app(StaffApiKeyStore::class)->issueKey(userId: self::TEST_NON_STAFF_USER_ID, label: 'Should not issue');
    }

    public function test_rotate_key_preserves_existing_expiry_when_replacement_expiry_is_not_supplied(): void
    {
        $issued = app(StaffApiKeyStore::class)->issueKey(
            userId: self::TEST_STAFF_USER_ID,
            label: 'POS A',
            expiresAt: now('UTC')->addDays(14),
        );

        $rotated = app(StaffApiKeyStore::class)->rotateKey(
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
                $table->string('role_name', 50)->unique('uq_roles__role_name');
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
                $table->bigIncrements('staff_api_key_id');
                $table->unsignedBigInteger('user_id');
                $table->string('label', 100);
                $table->char('key_hash', 64)->unique();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->bigIncrements('audit_id');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('actor_type', 40)->nullable();
                $table->string('actor_key', 120)->nullable();
                $table->string('entity_type', 50);
                $table->string('entity_id', 64);
                $table->string('action', 50);
                $table->json('before_json')->nullable();
                $table->json('after_json')->nullable();
                $table->json('summary_json')->nullable();
                $table->json('meta_json')->nullable();
                $table->string('request_id', 64)->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->dateTime('created_at');
            });
        }

        if (! Schema::hasTable('audit_log_subjects')) {
            Schema::create('audit_log_subjects', function (Blueprint $table): void {
                $table->bigIncrements('audit_subject_id');
                $table->unsignedBigInteger('audit_id');
                $table->string('subject_type', 50);
                $table->string('subject_id', 64);
                $table->string('subject_role', 32)->nullable();
                $table->dateTime('created_at');
            });
        }
    }

    private function truncateStaffAuthTables(): void
    {
        DB::table('staff_api_keys')
            ->whereIn('user_id', [self::TEST_STAFF_USER_ID, self::TEST_NON_STAFF_USER_ID])
            ->delete();

        DB::table('users')
            ->whereIn('user_id', [self::TEST_STAFF_USER_ID, self::TEST_NON_STAFF_USER_ID])
            ->delete();

        DB::table('roles')
            ->whereIn('role_id', [self::TEST_STAFF_ROLE_ID, self::TEST_NON_STAFF_ROLE_ID])
            ->delete();
    }

    private function seedStaffUser(): void
    {
        DB::table('roles')->updateOrInsert([
            'role_id' => self::TEST_STAFF_ROLE_ID,
        ], [
            'role_name' => self::TEST_STAFF_ROLE_NAME,
        ]);

        DB::table('users')->updateOrInsert([
            'user_id' => self::TEST_STAFF_USER_ID,
        ], [
            'username' => 'staff-api-key-store-staff',
            'full_name' => 'Staff Api Key Store Staff User',
            'email' => 'staff-api-key-store-staff@example.test',
            'phone' => '0900000000',
            'role_id' => self::TEST_STAFF_ROLE_ID,
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
