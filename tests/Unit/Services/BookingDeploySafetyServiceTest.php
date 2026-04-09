<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\Attributes\Group;
use App\Services\BookingDeploySafetyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingDeploySafetyServiceTest extends TestCase
{
    private ?string $fullDumpBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:testtesttesttesttesttesttesttesttest=');
        config()->set('booking.idempotency_ttl_hours', 24);
        config()->set('booking.idempotency_required_scopes', ['reservations', 'staff.checkout']);
        config()->set('booking.scheduler_heartbeat_ttl_seconds', 300);
        config()->set('booking.scheduler_heartbeat_stale_seconds', 180);
        config()->set('booking.reservation_lock_ttl_seconds', 60);
        config()->set('booking.reservation_lock_wait_seconds', 10);
        config()->set('booking.reservation_lock_prefix', 'booking:lock:table');
        config()->set('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation');
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('notifications.outbox.enabled', false);
        config()->set('booking.loyalty_enabled', true);
        config()->set('booking.loyalty_redeem_amount_per_point', 1000);
        config()->set('booking.loyalty_earn_amount_per_point', 10000);
        config()->set('booking.loyalty_min_redeem_points', 1);
        config()->set('staff_auth.api_keys', ['staff-key' => 2]);
        config()->set('staff_auth.database_store_enabled', true);
        config()->set('staff_auth.allow_env_fallback', false);
        config()->set('staff_auth.allowed_role_ids', [1, 2]);
        config()->set('booking.ops.staff_api_keys_missing_active_fail_count', 1);
        config()->set('booking.ops.staff_api_keys_never_used_warn_count', 5);
        config()->set('booking.ops.table_state_audit_missing_actor_warn_count', 1);
        config()->set('booking.ops.table_state_audit_missing_context_warn_count', 3);
        config()->set('booking.ops.table_state_audit_recent_window_hours', 24);
        config()->set('customer_auth.enabled', true);
        config()->set('customer_auth.header', 'X-Customer-Token');
        config()->set('customer_auth.allowed_purposes', ['VerifyEmail']);
        config()->set('customer_auth.allowed_role_ids', [3]);

        $this->ensureMigrationRepository();
        $this->ensureDeploySafetyTables();
        $this->truncateDeploySafetyTables();
        DB::table('staff_api_keys')->insert([
            'staff_api_key_id' => 1,
            'user_id' => 2,
            'label' => 'primary',
            'key_hash' => str_repeat('a', 64),
            'last_used_at' => now('UTC'),
            'expires_at' => null,
            'revoked_at' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
        $this->preparePortableFullDumpArtifact();
    }

    protected function tearDown(): void
    {
        $this->restoreFullDumpArtifact();

        parent::tearDown();
    }

    #[Group('booking-smoke')]
    public function test_preflight_passes_when_guard_tables_are_clean(): void
    {
        $report = app(BookingDeploySafetyService::class)->inspect('preflight');

        $this->assertArrayHasKey('checks', $report);
        $this->assertArrayHasKey('data.deposit_status', $report['checks']);
        $this->assertArrayHasKey('artifacts.schema_dump_definers', $report['checks']);
        $this->assertArrayHasKey('artifacts.full_dump_definers', $report['checks']);
        $this->assertArrayHasKey('ops.staff_api_keys', $report['checks']);
        $this->assertArrayHasKey('ops.table_state_audit', $report['checks']);
        $this->assertArrayHasKey('ops.row_version_contract', $report['checks']);
        $this->assertTrue($report['checks']['data.deposit_status']['ok']);
        $this->assertTrue($report['checks']['data.payment_refund_lineage']['ok']);
        $this->assertTrue($report['checks']['data.bank_account_defaults']['ok']);
        $this->assertTrue($report['checks']['data.active_agent_assignments']['ok']);
        $this->assertTrue($report['checks']['data.session_hold_linkage']['ok']);
        $this->assertTrue($report['checks']['artifacts.schema_dump_definers']['ok']);
        $this->assertTrue($report['checks']['artifacts.full_dump_definers']['ok']);
        $this->assertTrue($report['checks']['artifacts.full_dump_contract']['ok']);
        $this->assertTrue($report['checks']['ops.staff_api_keys']['ok']);
        $this->assertTrue($report['checks']['ops.table_state_audit']['ok']);
        $this->assertTrue($report['checks']['ops.row_version_contract']['ok']);
    }

    #[Group('booking-smoke')]
    public function test_preflight_reports_structured_runtime_error_when_data_guards_throw(): void
    {
        $service = new class(
            app(\App\Services\BookingEnvironmentValidator::class),
            app(\App\Services\OperationalInsightsService::class),
        ) extends BookingDeploySafetyService
        {
            protected function inspectDataGuards(): array
            {
                throw new \RuntimeException('mysql runtime unavailable');
            }
        };

        $report = $service->inspect('preflight');

        $this->assertFalse($report['ok']);
        $this->assertArrayHasKey('data.runtime', $report['checks']);
        $this->assertFalse($report['checks']['data.runtime']['ok']);
        $this->assertSame('error', $report['checks']['data.runtime']['severity']);
        $this->assertStringContainsString('Data guard inspection failed: mysql runtime unavailable', $report['checks']['data.runtime']['message']);
    }

    #[Group('booking-ops')]
    public function test_preflight_fails_when_fail_fast_data_is_dirty(): void
    {
        DB::table('reservations')->insert([
            'reservation_id' => 1,
            'status' => 'Cancelled',
            'deposit_status' => 'WeirdState',
            'checked_in_at' => null,
            'checked_out_at' => null,
            'cancelled_at' => null,
            'no_show_at' => null,
        ]);

        DB::table('reservation_order_items')->insert([
            'reservation_order_item_id' => 1,
            'quantity' => 2,
            'unit_price' => 100,
            'line_total' => 150,
        ]);

        DB::table('payments')->insert([
            'payment_id' => 1,
            'payment_type' => 'Refund',
            'status' => 'Pending',
            'refund_of_payment_id' => null,
            'amount' => 10,
        ]);

        DB::table('user_vouchers')->insert([
            'user_voucher_id' => 1,
            'is_used' => 1,
            'used_date' => null,
            'used_reservation_id' => null,
            'used_amount' => -10,
            'lock_token' => 'lock',
            'locked_until' => null,
        ]);


        DB::table('bank_accounts')->insert([
            ['bank_account_id' => 1, 'user_id' => 50, 'is_default' => 1],
            ['bank_account_id' => 2, 'user_id' => 50, 'is_default' => 1],
        ]);

        DB::table('agent_assignments')->insert([
            ['assignment_id' => 1, 'conversation_id' => 'conv-a', 'is_active' => 1],
            ['assignment_id' => 2, 'conversation_id' => 'conv-a', 'is_active' => 1],
        ]);

        DB::table('table_holds')->insert([
            'hold_id' => 'hold-a',
            'session_id' => 'session-a',
            'confirmed_reservation_id' => null,
            'hold_status' => 'Holding',
        ]);
        DB::table('staff_api_keys')->delete();
        DB::table('audit_logs')->insert([
            'audit_id' => 1,
            'actor_user_id' => null,
            'entity_type' => 'restaurant_table',
            'entity_id' => '5',
            'action' => 'table_state_released',
            'before_json' => json_encode(['status' => 'Occupied']),
            'after_json' => json_encode(['status' => 'Available']),
            'created_at' => now('UTC'),
        ]);

        $report = app(BookingDeploySafetyService::class)->inspect('preflight');

        $this->assertFalse($report['ok']);
        $this->assertFalse($report['checks']['data.deposit_status']['ok']);
        $this->assertFalse($report['checks']['data.reservation_order_item_totals']['ok']);
        $this->assertFalse($report['checks']['data.payment_refund_lineage']['ok']);
        $this->assertFalse($report['checks']['data.reservation_lifecycle']['ok']);
        $this->assertFalse($report['checks']['data.user_voucher_state']['ok']);
        $this->assertFalse($report['checks']['data.bank_account_defaults']['ok']);
        $this->assertFalse($report['checks']['data.active_agent_assignments']['ok']);
        $this->assertFalse($report['checks']['data.session_hold_linkage']['ok']);
        $this->assertFalse($report['checks']['ops.staff_api_keys']['ok']);
        $this->assertFalse($report['checks']['ops.table_state_audit']['ok']);
    }

    #[Group('booking-ops')]
    public function test_preflight_fails_when_refunds_exceed_source_payment_amount(): void
    {
        DB::table('payments')->insert([
            [
                'payment_id' => 100,
                'payment_type' => 'Final',
                'status' => 'Success',
                'refund_of_payment_id' => null,
                'amount' => 100,
            ],
            [
                'payment_id' => 101,
                'payment_type' => 'Refund',
                'status' => 'Refunded',
                'refund_of_payment_id' => 100,
                'amount' => 70,
            ],
            [
                'payment_id' => 102,
                'payment_type' => 'Refund',
                'status' => 'Refunded',
                'refund_of_payment_id' => 100,
                'amount' => 40,
            ],
        ]);

        $report = app(BookingDeploySafetyService::class)->inspect('preflight');

        $this->assertFalse($report['checks']['data.payment_refund_lineage']['ok']);
        $this->assertSame(1, $report['checks']['data.payment_refund_lineage']['meta']['over_refund_source_count'] ?? null);
    }



    #[Group('booking-ops')]
    public function test_preflight_fails_when_refund_lineage_crosses_reservation_or_currency_scope(): void
    {
        DB::table('payments')->insert([
            [
                'payment_id' => 200,
                'reservation_id' => 10,
                'payment_type' => 'Final',
                'status' => 'Success',
                'refund_of_payment_id' => null,
                'amount' => 100,
                'currency' => 'VND',
            ],
            [
                'payment_id' => 201,
                'reservation_id' => 11,
                'payment_type' => 'Refund',
                'status' => 'Refunded',
                'refund_of_payment_id' => 200,
                'amount' => 10,
                'currency' => 'USD',
            ],
        ]);

        $report = app(BookingDeploySafetyService::class)->inspect('preflight');

        $this->assertFalse($report['checks']['data.payment_refund_lineage']['ok']);
        $this->assertSame(1, $report['checks']['data.payment_refund_lineage']['meta']['cross_reservation_count'] ?? null);
        $this->assertSame(1, $report['checks']['data.payment_refund_lineage']['meta']['currency_mismatch_count'] ?? null);
    }

    #[Group('booking-smoke')]
    public function test_preflight_fails_when_full_dump_contains_definers(): void
    {
        File::put(base_path('db_all.sql'), "CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bad`() SELECT 1;
");

        $report = app(BookingDeploySafetyService::class)->inspect('preflight');

        $this->assertFalse($report['checks']['artifacts.full_dump_definers']['ok']);
        $this->assertGreaterThan(0, $report['checks']['artifacts.full_dump_definers']['meta']['definer_count'] ?? 0);
    }

    private function preparePortableFullDumpArtifact(): void
    {
        $dumpPath = base_path('db_all.sql');
        $this->fullDumpBackup = File::exists($dumpPath) ? File::get($dumpPath) : null;

        $fragments = array_values(array_filter(
            array_map(static fn ($fragment) => is_scalar($fragment) ? trim((string) $fragment) : '', (array) config('booking_release.artifacts.full_dump.required_fragments', [])),
            static fn (string $fragment): bool => $fragment !== ''
        ));

        File::put($dumpPath, "-- portable test dump\n" . implode("\n", $fragments) . "\n");
    }

    private function restoreFullDumpArtifact(): void
    {
        $dumpPath = base_path('db_all.sql');

        if ($this->fullDumpBackup === null) {
            File::delete($dumpPath);
            return;
        }

        File::put($dumpPath, $this->fullDumpBackup);
    }


    private function ensureMigrationRepository(): void
    {
        if (! Schema::hasTable('migrations')) {
            Schema::create('migrations', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('migration');
                $table->integer('batch');
            });
        }
    }

    private function ensureDeploySafetyTables(): void
    {
        if (! Schema::hasTable('reservations')) {
            Schema::create('reservations', function (Blueprint $table): void {
                $table->unsignedBigInteger('reservation_id')->primary();
                $table->string('status', 30)->nullable();
                $table->string('deposit_status', 30)->nullable();
                $table->timestamp('checked_in_at')->nullable();
                $table->timestamp('checked_out_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('no_show_at')->nullable();
            });
        }

        if (! Schema::hasTable('reservation_order_items')) {
            Schema::create('reservation_order_items', function (Blueprint $table): void {
                $table->unsignedBigInteger('reservation_order_item_id')->primary();
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('line_total', 12, 2)->default(0);
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->unsignedBigInteger('payment_id')->primary();
                $table->unsignedBigInteger('reservation_id')->nullable();
                $table->string('payment_type', 20)->nullable();
                $table->string('status', 20)->nullable();
                $table->unsignedBigInteger('refund_of_payment_id')->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 10)->default('VND');
            });
        }

        if (! Schema::hasTable('user_vouchers')) {
            Schema::create('user_vouchers', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_voucher_id')->primary();
                $table->boolean('is_used')->default(false);
                $table->timestamp('used_date')->nullable();
                $table->unsignedBigInteger('used_reservation_id')->nullable();
                $table->decimal('used_amount', 12, 2)->nullable();
                $table->string('lock_token')->nullable();
                $table->timestamp('locked_until')->nullable();
            });
        }


        if (! Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table): void {
                $table->unsignedBigInteger('bank_account_id')->primary();
                $table->unsignedBigInteger('user_id');
                $table->boolean('is_default')->default(false);
            });
        }

        if (! Schema::hasTable('agent_assignments')) {
            Schema::create('agent_assignments', function (Blueprint $table): void {
                $table->unsignedBigInteger('assignment_id')->primary();
                $table->string('conversation_id');
                $table->boolean('is_active')->default(true);
            });
        }

        if (! Schema::hasTable('table_holds')) {
            Schema::create('table_holds', function (Blueprint $table): void {
                $table->string('hold_id')->primary();
                $table->string('session_id')->nullable();
                $table->unsignedBigInteger('confirmed_reservation_id')->nullable();
                $table->string('hold_status', 30)->nullable();
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

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->unsignedBigInteger('audit_id')->primary();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('entity_type', 50);
                $table->string('entity_id', 64);
                $table->string('action', 50);
                $table->text('before_json')->nullable();
                $table->text('after_json')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    private function truncateDeploySafetyTables(): void
    {
        DB::table('reservations')->delete();
        DB::table('reservation_order_items')->delete();
        DB::table('payments')->delete();
        DB::table('user_vouchers')->delete();
        DB::table('bank_accounts')->delete();
        DB::table('agent_assignments')->delete();
        DB::table('table_holds')->delete();
        DB::table('staff_api_keys')->delete();
        DB::table('audit_logs')->delete();
    }
}
