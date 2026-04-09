<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class CustomerAccessSessionBootstrapCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('customer_auth.enabled', true);
        config()->set('customer_auth.allowed_role_ids', [3]);
        config()->set('customer_auth.access_session_ttl_minutes', 120);
        config()->set('customer_auth.allow_legacy_user_auth_tokens', false);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropIfExists('customer_access_sessions');
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

        Schema::create('customer_access_sessions', function (Blueprint $table): void {
            $table->bigIncrements('access_session_id');
            $table->unsignedInteger('user_id');
            $table->string('session_id', 64)->nullable();
            $table->string('guest_name')->nullable();
            $table->string('phone')->nullable();
            $table->char('token_hash', 64)->unique();
            $table->string('token_last_eight', 8)->nullable();
            $table->json('session_meta_json')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->binary('created_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        DB::table('roles')->insert([
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
    public function test_customer_access_session_console_bootstrap_supports_issue_show_list_and_revoke(): void
    {
        $customerId = $this->createUser('customer-bootstrap', 3);

        $issueExitCode = Artisan::call('customer-auth:access-sessions:issue', [
            'user_id' => $customerId,
            '--session-id' => 'cust-session-bootstrap-1',
            '--guest-name' => 'Bootstrap Customer',
            '--phone' => '0900000001',
            '--source' => 'uat.bootstrap',
            '--json' => true,
        ]);

        $this->assertSame(0, $issueExitCode);
        $issued = $this->decodeArtisanOutput();
        $this->assertTrue($issued['ok']);
        $this->assertStringStartsWith('cust-session-bootstrap-1', (string) ($issued['data']['access_session']['session_id'] ?? 'cust-session-bootstrap-1'));
        $this->assertNotSame('', (string) ($issued['data']['plain_text_token'] ?? ''));

        $accessSessionId = (int) $issued['data']['access_session']['access_session_id'];

        $showExitCode = Artisan::call('customer-auth:access-sessions:show', [
            'access_session_id' => $accessSessionId,
            '--json' => true,
        ]);
        $this->assertSame(0, $showExitCode);
        $shown = $this->decodeArtisanOutput();
        $this->assertSame($customerId, (int) $shown['data']['user_id']);
        $this->assertSame('Bootstrap Customer', (string) $shown['data']['guest_name']);
        $this->assertTrue((bool) $shown['data']['is_active']);

        $listExitCode = Artisan::call('customer-auth:access-sessions:list', [
            '--user-id' => $customerId,
            '--json' => true,
        ]);
        $this->assertSame(0, $listExitCode);
        $listed = $this->decodeArtisanOutput();
        $this->assertCount(1, $listed['data']);
        $this->assertSame($accessSessionId, (int) $listed['data'][0]['access_session_id']);

        $revokeExitCode = Artisan::call('customer-auth:access-sessions:revoke', [
            'access_session_id' => $accessSessionId,
            '--json' => true,
        ]);
        $this->assertSame(0, $revokeExitCode);
        $revoked = $this->decodeArtisanOutput();
        $this->assertFalse((bool) $revoked['data']['is_active']);
        $this->assertNotNull($revoked['data']['revoked_at_utc']);

        $activeListExitCode = Artisan::call('customer-auth:access-sessions:list', [
            '--user-id' => $customerId,
            '--json' => true,
        ]);
        $this->assertSame(0, $activeListExitCode);
        $activeList = $this->decodeArtisanOutput();
        $this->assertCount(0, $activeList['data']);

        $allListExitCode = Artisan::call('customer-auth:access-sessions:list', [
            '--user-id' => $customerId,
            '--include-revoked' => true,
            '--json' => true,
        ]);
        $this->assertSame(0, $allListExitCode);
        $allList = $this->decodeArtisanOutput();
        $this->assertCount(1, $allList['data']);
        $this->assertSame($accessSessionId, (int) $allList['data'][0]['access_session_id']);
    }

    #[Group('booking-ops')]
    public function test_customer_access_session_console_bootstrap_rejects_non_customer_roles(): void
    {
        $staffUserId = $this->createUser('staff-bootstrap', 2);

        $exitCode = Artisan::call('customer-auth:access-sessions:issue', [
            'user_id' => $staffUserId,
            '--json' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $payload = $this->decodeArtisanOutput();
        $this->assertSame('validation_error', $payload['error']);
        $this->assertSame(
            'Customer access sessions can only be issued for configured customer roles.',
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
