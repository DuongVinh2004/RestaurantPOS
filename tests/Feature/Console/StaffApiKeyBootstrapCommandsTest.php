<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class StaffApiKeyBootstrapCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('staff_auth.database_store_enabled', true);
        config()->set('staff_auth.allow_env_fallback', false);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_auth.allow_role_name_fallback', false);
        config()->set('staff_auth.allowed_role_ids', [1, 2]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropIfExists('staff_api_keys');
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
            $table->increments('staff_api_key_id');
            $table->unsignedInteger('user_id');
            $table->string('label', 100);
            $table->char('key_hash', 64)->unique();
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        DB::table('roles')->insert([
            [
                'role_id' => 1,
                'role_name' => 'Admin',
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ],
            [
                'role_id' => 2,
                'role_name' => 'Staff',
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ],
            [
                'role_id' => 3,
                'role_name' => 'Customer',
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ],
        ]);
    }

    #[Group('booking-ops')]
    public function test_staff_api_key_console_bootstrap_supports_issue_list_rotate_and_revoke(): void
    {
        $staffUserId = $this->createUser('staff-terminal', 2);

        $issueExitCode = Artisan::call('staff-auth:api-keys:issue', [
            'user_id' => $staffUserId,
            'label' => 'Main cashier terminal',
            '--expires-at' => '2026-06-01T00:00:00Z',
            '--json' => true,
        ]);

        $this->assertSame(0, $issueExitCode);
        $issued = $this->decodeArtisanOutput();
        $this->assertTrue($issued['ok']);
        $this->assertStringStartsWith('spk_', (string) $issued['data']['plaintext_key']);

        $firstKeyId = (int) $issued['data']['staff_api_key']['staff_api_key_id'];
        $firstKeyExpiry = (string) $issued['data']['staff_api_key']['expires_at_utc'];

        $listExitCode = Artisan::call('staff-auth:api-keys:list', [
            '--user-id' => $staffUserId,
            '--json' => true,
        ]);
        $this->assertSame(0, $listExitCode);
        $listed = $this->decodeArtisanOutput();
        $this->assertCount(1, $listed['data']);
        $this->assertSame($firstKeyId, (int) $listed['data'][0]['staff_api_key_id']);

        $rotateExitCode = Artisan::call('staff-auth:api-keys:rotate', [
            'staff_api_key_id' => $firstKeyId,
            '--label' => 'Main cashier terminal rotated',
            '--json' => true,
        ]);
        $this->assertSame(0, $rotateExitCode);
        $rotated = $this->decodeArtisanOutput();
        $this->assertStringStartsWith('spk_', (string) $rotated['data']['plaintext_key']);
        $this->assertNotSame($firstKeyId, (int) $rotated['data']['replacement']['staff_api_key_id']);
        $this->assertSame($firstKeyExpiry, (string) $rotated['data']['replacement']['expires_at_utc']);
        $this->assertNotNull($rotated['data']['revoked']['revoked_at_utc']);

        $replacementKeyId = (int) $rotated['data']['replacement']['staff_api_key_id'];

        $activeListExitCode = Artisan::call('staff-auth:api-keys:list', [
            '--user-id' => $staffUserId,
            '--json' => true,
        ]);
        $this->assertSame(0, $activeListExitCode);
        $activeList = $this->decodeArtisanOutput();
        $this->assertCount(1, $activeList['data']);
        $this->assertSame($replacementKeyId, (int) $activeList['data'][0]['staff_api_key_id']);

        $allListExitCode = Artisan::call('staff-auth:api-keys:list', [
            '--user-id' => $staffUserId,
            '--include-revoked' => true,
            '--json' => true,
        ]);
        $this->assertSame(0, $allListExitCode);
        $allList = $this->decodeArtisanOutput();
        $this->assertCount(2, $allList['data']);

        $revokeExitCode = Artisan::call('staff-auth:api-keys:revoke', [
            'staff_api_key_id' => $replacementKeyId,
            '--reason' => 'terminal decommissioned',
            '--json' => true,
        ]);
        $this->assertSame(0, $revokeExitCode);
        $revoked = $this->decodeArtisanOutput();
        $this->assertNotNull($revoked['data']['revoked_at_utc']);
        $this->assertFalse((bool) $revoked['data']['is_active']);

        $finalActiveListExitCode = Artisan::call('staff-auth:api-keys:list', [
            '--user-id' => $staffUserId,
            '--json' => true,
        ]);
        $this->assertSame(0, $finalActiveListExitCode);
        $finalActiveList = $this->decodeArtisanOutput();
        $this->assertCount(0, $finalActiveList['data']);
    }

    #[Group('booking-ops')]
    public function test_staff_api_key_console_bootstrap_rejects_non_staff_roles(): void
    {
        $customerUserId = $this->createUser('customer-terminal', 3);

        $exitCode = Artisan::call('staff-auth:api-keys:issue', [
            'user_id' => $customerUserId,
            'label' => 'Should fail',
            '--json' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $payload = $this->decodeArtisanOutput();
        $this->assertSame('validation_error', $payload['error']);
        $this->assertSame(
            'Staff API keys can only be issued for configured staff/admin roles.',
            $payload['errors']['user_id'][0] ?? null
        );
    }

    private function createUser(string $username, int $roleId): int
    {
        return (int) DB::table('users')->insertGetId([
            'username' => $username,
            'password_hash' => null,
            'full_name' => ucfirst($username),
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
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeArtisanOutput(): array
    {
        /** @var array<string,mixed> $payload */
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }
}
