<?php

declare(strict_types=1);

namespace Tests\Feature\WaitingList;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerWaitingListOwnerResponseFlowTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        config()->set('customer_auth.enabled', true);
        config()->set('customer_auth.allow_legacy_user_auth_tokens', true);
        config()->set('customer_auth.legacy_user_auth_tokens_allowed_environments', ['testing']);
        config()->set('customer_auth.allowed_role_ids', []);
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');

        $this->requireBookingSchema();
        $this->ensureWaitingListSchema();
        $this->ensureCustomerAuthTokenSchema();
        $this->ensureTableHoldSchema();
    }

    public function test_owner_can_list_and_show_only_owned_waiting_entries(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $otherId = $this->createUser(['role_name' => 'Customer']);
        $token = $this->issueCustomerToken($ownerId, 'token-owner-list');

        $ownedWaitingId = $this->createWaitingEntry([
            'user_id' => $ownerId,
            'guest_name' => 'Owner Entry',
            'status' => 'Waiting',
        ]);
        $otherWaitingId = $this->createWaitingEntry([
            'user_id' => $otherId,
            'guest_name' => 'Other Entry',
            'status' => 'Waiting',
        ]);

        $this->withHeader('X-Customer-Token', $token)
            ->getJson('/api/v1/waiting-list')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.waiting_id', $ownedWaitingId)
            ->assertJsonMissing(['waiting_id' => $otherWaitingId]);

        $this->withHeader('X-Customer-Token', $token)
            ->getJson('/api/v1/waiting-list/'.$ownedWaitingId)
            ->assertOk()
            ->assertJsonPath('data.waiting_id', $ownedWaitingId)
            ->assertJsonPath('data.available_actions.cancel', true);

        $this->withHeader('X-Customer-Token', $token)
            ->getJson('/api/v1/waiting-list/'.$otherWaitingId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_owner_can_accept_notified_entry_within_open_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-28T12:00:00Z')->utc());

        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $token = $this->issueCustomerToken($ownerId, 'token-owner-accept');
        $waitingId = $this->createWaitingEntry([
            'user_id' => $ownerId,
            'status' => 'Notified',
            'notified_at' => Carbon::now('UTC')->copy()->subMinute(),
            'notify_expires_at' => Carbon::now('UTC')->copy()->addMinutes(9),
            'updated_by' => null,
        ]);

        $this->withHeaders([
            'X-Customer-Token' => $token,
            'Idempotency-Key' => 'waiting-accept-1',
        ])->postJson('/api/v1/waiting-list/'.$waitingId.'/accept', [
            'row_version' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Notified')
            ->assertJsonPath('data.available_actions.accept', true);

        $this->assertSame('Accepted', (string) DB::table('waiting_list')->where('waiting_id', $waitingId)->value('customer_response_status'));
        $this->assertNotNull(DB::table('waiting_list')->where('waiting_id', $waitingId)->value('customer_responded_at'));
        $this->assertNull(DB::table('waiting_list')->where('waiting_id', $waitingId)->value('customer_confirmed_arrival_at'));
        $this->assertSame($ownerId, (int) DB::table('waiting_list')->where('waiting_id', $waitingId)->value('updated_by'));

        $log = $this->assertAuditLogRecorded('waiting_list.accepted', 'waiting_list', $waitingId);
        self::assertSame($ownerId, $log->actor_user_id);
        self::assertSame('customer_account', $log->actor_type);
        self::assertSame('Accepted', (string) data_get($log->after_json, 'customer_response_status'));

        Carbon::setTestNow();
    }

    public function test_owner_can_decline_notified_entry_and_active_hold_is_cancelled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-28T12:00:00Z')->utc());

        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $token = $this->issueCustomerToken($ownerId, 'token-owner-decline');
        $waitingId = $this->createWaitingEntry([
            'user_id' => $ownerId,
            'status' => 'Notified',
            'notified_at' => Carbon::now('UTC')->copy()->subMinutes(2),
            'notify_expires_at' => Carbon::now('UTC')->copy()->addMinutes(8),
        ]);
        $this->createWaitingHold($waitingId, $ownerId, 'Holding');

        $this->withHeaders([
            'X-Customer-Token' => $token,
            'Idempotency-Key' => 'waiting-decline-1',
        ])->postJson('/api/v1/waiting-list/'.$waitingId.'/decline', [
            'row_version' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Cancelled')
            ->assertJsonPath('data.cancel_reason', 'Declined by customer');

        $this->assertSame('Declined', (string) DB::table('waiting_list')->where('waiting_id', $waitingId)->value('customer_response_status'));
        $this->assertNotNull(DB::table('waiting_list')->where('waiting_id', $waitingId)->value('customer_responded_at'));
        $this->assertNull(DB::table('waiting_list')->where('waiting_id', $waitingId)->value('customer_confirmed_arrival_at'));
        $this->assertSame('Cancelled', (string) DB::table('table_holds')->where('session_id', 'waiting-list:'.$waitingId)->value('hold_status'));

        $log = $this->assertAuditLogRecorded('waiting_list.declined', 'waiting_list', $waitingId);
        self::assertSame($ownerId, $log->actor_user_id);
        self::assertSame('customer_account', $log->actor_type);
        self::assertSame('Cancelled', (string) data_get($log->after_json, 'status'));

        Carbon::setTestNow();
    }

    public function test_owner_can_cancel_waiting_entry_before_notify(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $token = $this->issueCustomerToken($ownerId, 'token-owner-cancel');
        $waitingId = $this->createWaitingEntry([
            'user_id' => $ownerId,
            'status' => 'Waiting',
            'row_version' => 3,
        ]);

        $this->withHeaders([
            'X-Customer-Token' => $token,
            'Idempotency-Key' => 'waiting-cancel-1',
        ])->postJson('/api/v1/waiting-list/'.$waitingId.'/cancel', [
            'row_version' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Cancelled')
            ->assertJsonPath('data.cancel_reason', 'Cancelled by customer');
    }

    public function test_owner_response_rejects_non_owner_expired_window_and_invalid_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-28T12:00:00Z')->utc());

        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $otherId = $this->createUser(['role_name' => 'Customer']);
        $ownerToken = $this->issueCustomerToken($ownerId, 'token-owner-reject');
        $otherToken = $this->issueCustomerToken($otherId, 'token-other-reject');

        $expiredWaitingId = $this->createWaitingEntry([
            'user_id' => $ownerId,
            'status' => 'Notified',
            'notified_at' => Carbon::now('UTC')->copy()->subMinutes(11),
            'notify_expires_at' => Carbon::now('UTC')->copy()->subMinute(),
        ]);
        $waitingStateId = $this->createWaitingEntry([
            'user_id' => $ownerId,
            'status' => 'Cancelled',
            'cancelled_at' => Carbon::now('UTC')->copy()->subMinute(),
            'row_version' => 2,
        ]);

        $this->withHeaders([
            'X-Customer-Token' => $otherToken,
            'Idempotency-Key' => 'waiting-non-owner-1',
        ])->postJson('/api/v1/waiting-list/'.$expiredWaitingId.'/decline', [
            'row_version' => 1,
        ])->assertNotFound();

        $this->withHeaders([
            'X-Customer-Token' => $ownerToken,
            'Idempotency-Key' => 'waiting-expired-1',
        ])->postJson('/api/v1/waiting-list/'.$expiredWaitingId.'/decline', [
            'row_version' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonPath('details.errors.notify_window.0', 'Notify window đã hết hạn hoặc không còn hợp lệ cho waiting entry này.');

        $this->withHeaders([
            'X-Customer-Token' => $ownerToken,
            'Idempotency-Key' => 'waiting-invalid-state-1',
        ])->postJson('/api/v1/waiting-list/'.$waitingStateId.'/accept', [
            'row_version' => 2,
        ])
            ->assertStatus(422)
            ->assertJsonPath('details.errors.status.0', 'Chỉ có entry ở trạng thái Notified mới cho phép customer response này.');

        Carbon::setTestNow();
    }

    private function ensureWaitingListSchema(): void
    {
        if (Schema::hasTable('waiting_list')) {
            return;
        }

        Schema::create('waiting_list', function (Blueprint $table): void {
            $table->increments('waiting_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('guest_name', 200)->nullable();
            $table->string('phone', 30)->nullable();
            $table->unsignedInteger('guest_count');
            $table->dateTime('requested_at');
            $table->string('status', 20)->default('Waiting');
            $table->integer('priority')->default(0);
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('notify_expires_at')->nullable();
            $table->unsignedInteger('notified_by')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('seated_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });
    }

    private function ensureCustomerAuthTokenSchema(): void
    {
        if (Schema::hasTable('user_auth_tokens')) {
            return;
        }

        Schema::create('user_auth_tokens', function (Blueprint $table): void {
            $table->increments('token_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('channel')->default('Email');
            $table->string('recipient')->default('customer@example.test');
            $table->string('token_hash')->unique();
            $table->string('purpose', 50)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(5);
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->dateTime('created_at')->nullable();
        });
    }

    private function ensureTableHoldSchema(): void
    {
        if (Schema::hasTable('table_holds')) {
            return;
        }

        Schema::create('table_holds', function (Blueprint $table): void {
            $table->string('hold_id')->primary();
            $table->string('session_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('confirmed_reservation_id')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('hold_status', 30);
            $table->dateTime('expire_at')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    private function issueCustomerToken(int $userId, string $plainToken): string
    {
        $payload = [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $plainToken),
            'purpose' => 'VerifyEmail',
            'expires_at' => Carbon::now('UTC')->addHour(),
            'used_at' => null,
            'created_at' => Carbon::now('UTC'),
        ];

        if (Schema::hasColumn('user_auth_tokens', 'channel')) {
            $payload['channel'] = 'Email';
        }

        if (Schema::hasColumn('user_auth_tokens', 'recipient')) {
            $payload['recipient'] = (string) (DB::table('users')->where('user_id', $userId)->value('email') ?? 'customer@example.test');
        }

        if (Schema::hasColumn('user_auth_tokens', 'attempt_count')) {
            $payload['attempt_count'] = 0;
        }

        if (Schema::hasColumn('user_auth_tokens', 'max_attempts')) {
            $payload['max_attempts'] = 5;
        }

        DB::table('user_auth_tokens')->insert($payload);

        return $plainToken;
    }

    /** @param array<string,mixed> $overrides */
    private function createWaitingEntry(array $overrides = []): int
    {
        $now = Carbon::now('UTC');

        return (int) DB::table('waiting_list')->insertGetId(array_merge([
            'user_id' => $this->createUser(['role_name' => 'Customer']),
            'guest_name' => 'Walk-in Customer',
            'phone' => '0900000000',
            'guest_count' => 2,
            'requested_at' => $now,
            'status' => 'Waiting',
            'priority' => 0,
            'notified_at' => null,
            'notify_expires_at' => null,
            'notified_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'seated_at' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
            'notes' => null,
            'updated_by' => null,
            'row_version' => 1,
        ], $overrides));
    }

    private function createWaitingHold(int $waitingId, int $ownerId, string $holdStatus): void
    {
        $now = Carbon::now('UTC');

        DB::table('table_holds')->insert([
            'hold_id' => 'hold-'.$waitingId,
            'session_id' => 'waiting-list:'.$waitingId,
            'user_id' => $ownerId,
            'confirmed_reservation_id' => null,
            'start_time' => $now,
            'end_time' => $now->copy()->addMinutes(10),
            'duration_minutes' => 10,
            'hold_status' => $holdStatus,
            'expire_at' => $now->copy()->addMinutes(10),
            'updated_by' => null,
            'row_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
