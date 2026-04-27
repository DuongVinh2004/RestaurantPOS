<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\InventoryProcurement\Application\Workflows\PurchaseOrderReconciliationService;
use App\Platform\Health\Services\BookingEnvironmentValidator;
use App\Platform\Metrics\Services\OperationalInsightsService;
use App\Platform\Release\Services\BookingDeploySafetyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
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
        $service = new class(app(BookingEnvironmentValidator::class), app(OperationalInsightsService::class), app(PurchaseOrderReconciliationService::class)) extends BookingDeploySafetyService
        {
            protected function inspectOperationalGuards(): array
            {
                return array_merge(parent::inspectOperationalGuards(), [
                    'kitchen_kds' => [
                        'ok' => true,
                        'severity' => 'info',
                        'message' => 'Kitchen/KDS reconciliation looks healthy.',
                    ],
                    'inventory_purchasing' => [
                        'ok' => true,
                        'severity' => 'info',
                        'message' => 'Inventory and purchasing reconciliation looks healthy.',
                    ],
                    'conversation_inbox' => [
                        'ok' => true,
                        'severity' => 'info',
                        'message' => 'Conversation inbox workflow health looks clean.',
                    ],
                ]);
            }
        };

        $report = $service->inspect('preflight');

        $this->assertArrayHasKey('checks', $report);
        $this->assertArrayHasKey('data.deposit_status', $report['checks']);
        $this->assertArrayHasKey('artifacts.schema_dump_definers', $report['checks']);
        $this->assertArrayHasKey('artifacts.full_dump_definers', $report['checks']);
        $this->assertArrayHasKey('ops.staff_api_keys', $report['checks']);
        $this->assertArrayHasKey('ops.table_state_audit', $report['checks']);
        $this->assertArrayHasKey('ops.row_version_contract', $report['checks']);
        $this->assertArrayHasKey('ops.kitchen_kds', $report['checks']);
        $this->assertArrayHasKey('ops.inventory_purchasing', $report['checks']);
        $this->assertArrayHasKey('ops.conversation_inbox', $report['checks']);
        $this->assertTrue($report['checks']['data.deposit_status']['ok']);
        $this->assertTrue($report['checks']['data.payment_refund_lineage']['ok']);
        $this->assertTrue($report['checks']['data.purchase_receipt_lineage_uniqueness']['ok']);
        $this->assertTrue($report['checks']['data.bank_account_defaults']['ok']);
        $this->assertTrue($report['checks']['data.active_agent_assignments']['ok']);
        $this->assertTrue($report['checks']['data.session_hold_linkage']['ok']);
        $this->assertTrue($report['checks']['artifacts.schema_dump_definers']['ok']);
        $this->assertTrue($report['checks']['artifacts.full_dump_definers']['ok']);
        $this->assertTrue($report['checks']['artifacts.full_dump_contract']['ok']);
        $this->assertTrue($report['checks']['ops.staff_api_keys']['ok']);
        $this->assertTrue($report['checks']['ops.table_state_audit']['ok']);
        $this->assertTrue($report['checks']['ops.row_version_contract']['ok']);
        $this->assertTrue($report['checks']['ops.kitchen_kds']['ok']);
        $this->assertTrue($report['checks']['ops.inventory_purchasing']['ok']);
        $this->assertTrue($report['checks']['ops.conversation_inbox']['ok']);
    }

    #[Group('booking-smoke')]
    public function test_preflight_reports_structured_runtime_error_when_data_guards_throw(): void
    {
        $service = new class(app(BookingEnvironmentValidator::class), app(OperationalInsightsService::class), app(PurchaseOrderReconciliationService::class)) extends BookingDeploySafetyService
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

    #[Group('booking-smoke')]
    public function test_preflight_fails_fast_when_database_runtime_is_unavailable(): void
    {
        $service = new class(app(BookingEnvironmentValidator::class), app(OperationalInsightsService::class), app(PurchaseOrderReconciliationService::class)) extends BookingDeploySafetyService
        {
            protected function inspectDatabaseRuntime(): array
            {
                return [
                    'ok' => false,
                    'severity' => 'error',
                    'message' => 'Database runtime is unavailable; skipped database-dependent deploy guards.',
                    'meta' => [
                        'connection' => 'mysql',
                    ],
                ];
            }

            protected function inspectDataGuards(): array
            {
                throw new \RuntimeException('data guards should have been skipped');
            }

            protected function inspectOperationalGuards(): array
            {
                throw new \RuntimeException('operational guards should have been skipped');
            }
        };

        $report = $service->inspect('preflight');

        $this->assertFalse($report['ok']);
        $this->assertArrayHasKey('runtime.database', $report['checks']);
        $this->assertArrayHasKey('data.runtime', $report['checks']);
        $this->assertArrayHasKey('ops.runtime', $report['checks']);
        $this->assertFalse($report['checks']['runtime.database']['ok']);
        $this->assertSame('error', $report['checks']['runtime.database']['severity']);
        $this->assertSame('warning', $report['checks']['data.runtime']['severity']);
        $this->assertSame('warning', $report['checks']['ops.runtime']['severity']);
        $this->assertSame(1, $report['summary']['runtime_error_count'] ?? null);
        $this->assertStringNotContainsString('should have been skipped', implode("\n", $report['errors']));
        $this->assertStringNotContainsString('should have been skipped', implode("\n", $report['warnings']));
    }

    #[Group('booking-ops')]
    public function test_preflight_surfaces_runtime_incompatible_payment_refund_trigger_guard(): void
    {
        $service = new class(app(BookingEnvironmentValidator::class), app(OperationalInsightsService::class), app(PurchaseOrderReconciliationService::class)) extends BookingDeploySafetyService
        {
            protected function inspectDataGuards(): array
            {
                return array_merge(parent::inspectDataGuards(), [
                    'payment_refund_trigger_compatibility' => [
                        'ok' => false,
                        'severity' => 'error',
                        'message' => 'Runtime-incompatible payments refund triggers are still installed; refund execute can fail with MySQL ERROR 1442.',
                        'meta' => [
                            'present_triggers' => ['trg_payments__bi_refund_cap'],
                        ],
                    ],
                ]);
            }
        };

        $report = $service->inspect('preflight');

        $this->assertFalse($report['ok']);
        $this->assertArrayHasKey('data.payment_refund_trigger_compatibility', $report['checks']);
        $this->assertFalse($report['checks']['data.payment_refund_trigger_compatibility']['ok']);
        $this->assertStringContainsString('ERROR 1442', $report['checks']['data.payment_refund_trigger_compatibility']['message']);
    }

    #[Group('booking-ops')]
    public function test_preflight_fails_when_fail_fast_data_is_dirty(): void
    {
        $expectsBankAccountDefaultFailure = ! $this->hasUniqueIndex('bank_accounts', 'uq_bank_accounts__default_user_id');
        $expectsActiveAssignmentFailure = ! $this->hasUniqueIndex('agent_assignments', 'uq_agent_assignments__active_conversation_id');

        $this->withoutForeignKeyChecks(function () use ($expectsBankAccountDefaultFailure, $expectsActiveAssignmentFailure): void {
            $this->withoutCheckConstraintChecks(function () use ($expectsBankAccountDefaultFailure, $expectsActiveAssignmentFailure): void {
                $this->insertDeploySafetyReservation([
                    'reservation_id' => 1,
                    'status' => 'Cancelled',
                    'deposit_status' => 'WeirdState',
                    'cancelled_at' => null,
                ]);

                DB::table('reservation_order_items')->insert($this->payloadForExistingColumns('reservation_order_items', [
                    'reservation_order_item_id' => 1,
                    'order_item_id' => 1,
                    'order_id' => 1,
                    'item_id' => 1,
                    'quantity' => 2,
                    'unit_price' => 100,
                    'currency' => 'VND',
                    'line_total' => 150,
                    'status' => 'Ordered',
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                    'row_version' => 1,
                ]));

                $this->insertDeploySafetyPayments([[
                    'payment_id' => 1,
                    'payment_type' => 'Refund',
                    'status' => 'Pending',
                    'refund_of_payment_id' => null,
                    'amount' => 10,
                ]]);

                DB::table('user_vouchers')->insert($this->payloadForExistingColumns('user_vouchers', [
                    'user_voucher_id' => 1,
                    'user_id' => 50,
                    'voucher_id' => 50,
                    'assigned_date' => now('UTC'),
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                    'is_used' => 1,
                    'used_date' => now('UTC'),
                    'used_reservation_id' => 1,
                    'used_amount' => -10,
                    'lock_token' => 'lock',
                    'locked_until' => now('UTC')->addHour(),
                    'row_version' => 1,
                ]));

                if ($expectsBankAccountDefaultFailure) {
                    DB::table('bank_accounts')->insert([
                        $this->payloadForExistingColumns('bank_accounts', [
                            'bank_account_id' => 1,
                            'user_id' => 50,
                            'bank_account_number' => 'BA-DEPLOY-1',
                            'bank_name' => 'Deploy Bank',
                            'account_holder_name' => 'Deploy User',
                            'is_default' => 1,
                            'created_at' => now('UTC'),
                        ]),
                        $this->payloadForExistingColumns('bank_accounts', [
                            'bank_account_id' => 2,
                            'user_id' => 50,
                            'bank_account_number' => 'BA-DEPLOY-2',
                            'bank_name' => 'Deploy Bank',
                            'account_holder_name' => 'Deploy User',
                            'is_default' => 1,
                            'created_at' => now('UTC'),
                        ]),
                    ]);
                }

                if ($expectsActiveAssignmentFailure) {
                    DB::table('agent_assignments')->insert([
                        $this->payloadForExistingColumns('agent_assignments', [
                            'assignment_id' => 1,
                            'conversation_id' => 'conv-a',
                            'agent_user_id' => 2,
                            'assigned_at' => now('UTC'),
                            'is_active' => 1,
                        ]),
                        $this->payloadForExistingColumns('agent_assignments', [
                            'assignment_id' => 2,
                            'conversation_id' => 'conv-a',
                            'agent_user_id' => 3,
                            'assigned_at' => now('UTC'),
                            'is_active' => 1,
                        ]),
                    ]);
                }

                DB::table('table_holds')->insert($this->payloadForExistingColumns('table_holds', [
                    'hold_id' => 'hold-a',
                    'session_id' => 'session-a',
                    'confirmed_reservation_id' => null,
                    'hold_status' => 'Holding',
                    'start_time' => now('UTC')->addHour(),
                    'end_time' => now('UTC')->addHours(2),
                    'expire_at' => now('UTC')->addMinutes(15),
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                    'row_version' => 1,
                ]));
            });
        });
        DB::table('staff_api_keys')->delete();
        DB::table('audit_logs')->insert([
            'audit_id' => 1,
            'actor_user_id' => null,
            'actor_key' => null,
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
        $this->assertSame(! $expectsBankAccountDefaultFailure, $report['checks']['data.bank_account_defaults']['ok']);
        $this->assertSame(! $expectsActiveAssignmentFailure, $report['checks']['data.active_agent_assignments']['ok']);
        $this->assertFalse($report['checks']['data.session_hold_linkage']['ok']);
        $this->assertFalse($report['checks']['ops.staff_api_keys']['ok']);
        $this->assertFalse($report['checks']['ops.table_state_audit']['ok']);
    }

    #[Group('booking-ops')]
    public function test_preflight_fails_when_purchase_receipt_stock_movement_reference_is_duplicated(): void
    {
        if ($this->hasUniqueIndex('ingredient_stock_movements', 'uq_ingredient_stock_movements__reference')) {
            $this->markTestSkipped('The runtime schema already enforces unique purchase receipt stock movement references.');
        }

        $this->withoutForeignKeyChecks(function (): void {
            DB::table('ingredient_stock_movements')->insert([
                $this->payloadForExistingColumns('ingredient_stock_movements', [
                    'movement_id' => 1,
                    'ingredient_id' => 1,
                    'branch_id' => 1,
                    'movement_type' => 'StockIn',
                    'quantity_delta' => '1.000',
                    'unit_code' => 'kg',
                    'reference_type' => 'PurchaseReceipt',
                    'reference_id' => 'GRN-001:10',
                    'created_at' => now('UTC'),
                ]),
                $this->payloadForExistingColumns('ingredient_stock_movements', [
                    'movement_id' => 2,
                    'ingredient_id' => 1,
                    'branch_id' => 1,
                    'movement_type' => 'StockIn',
                    'quantity_delta' => '1.000',
                    'unit_code' => 'kg',
                    'reference_type' => 'PurchaseReceipt',
                    'reference_id' => 'GRN-001:10',
                    'created_at' => now('UTC'),
                ]),
            ]);
        });

        $service = new class(app(BookingEnvironmentValidator::class), app(OperationalInsightsService::class), app(PurchaseOrderReconciliationService::class)) extends BookingDeploySafetyService
        {
            protected function inspectOperationalGuards(): array
            {
                return [
                    'staff_api_keys' => ['ok' => true, 'severity' => 'info', 'message' => 'ok'],
                    'table_state_audit' => ['ok' => true, 'severity' => 'info', 'message' => 'ok'],
                    'row_version_contract' => ['ok' => true, 'severity' => 'info', 'message' => 'ok'],
                    'reporting_snapshots' => ['ok' => true, 'severity' => 'info', 'message' => 'ok'],
                    'kitchen_kds' => ['ok' => true, 'severity' => 'info', 'message' => 'ok'],
                    'inventory_purchasing' => ['ok' => true, 'severity' => 'info', 'message' => 'ok'],
                    'conversation_inbox' => ['ok' => true, 'severity' => 'info', 'message' => 'ok'],
                    'branch_defaults' => ['ok' => true, 'severity' => 'info', 'message' => 'ok'],
                ];
            }
        };

        $report = $service->inspect('preflight');

        $this->assertFalse($report['ok']);
        $this->assertArrayHasKey('data.purchase_receipt_lineage_uniqueness', $report['checks']);
        $this->assertFalse($report['checks']['data.purchase_receipt_lineage_uniqueness']['ok']);
        $this->assertSame(1, $report['checks']['data.purchase_receipt_lineage_uniqueness']['meta']['duplicate_reference_count'] ?? null);
        $this->assertSame(2, $report['checks']['data.purchase_receipt_lineage_uniqueness']['meta']['duplicate_movement_count'] ?? null);
        $this->assertStringContainsString('2026_04_13_000051_inventory_stock_movement_reference_uniqueness.sql', (string) ($report['checks']['data.purchase_receipt_lineage_uniqueness']['meta']['remediation'] ?? ''));
    }

    #[Group('booking-ops')]
    public function test_preflight_fails_when_refunds_exceed_source_payment_amount(): void
    {
        $this->insertDeploySafetyPayments([
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
        $this->insertDeploySafetyPayments([
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
        File::put(base_path('db_all.sql'), 'CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bad`() SELECT 1;
');

        $report = app(BookingDeploySafetyService::class)->inspect('preflight');

        $this->assertFalse($report['checks']['artifacts.full_dump_definers']['ok']);
        $this->assertGreaterThan(0, $report['checks']['artifacts.full_dump_definers']['meta']['definer_count'] ?? 0);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function insertDeploySafetyReservation(array $overrides = []): void
    {
        $now = now('UTC');
        $reservationId = (int) ($overrides['reservation_id'] ?? 1);
        $start = $now->copy()->addHour();

        DB::table('reservations')->insert($this->payloadForExistingColumns('reservations', array_merge([
            'reservation_id' => $reservationId,
            'user_id' => null,
            'guest_name' => null,
            'guest_phone' => null,
            'guest_email' => null,
            'branch_id' => 1,
            'reservation_code' => 'DEPLOY-RSV-'.$reservationId,
            'reserved_at' => $now,
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'guest_count' => 2,
            'status' => 'Confirmed',
            'source' => 'Online',
            'checked_in_at' => null,
            'checked_out_at' => null,
            'cancelled_at' => null,
            'no_show_at' => null,
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'deposit_intent_status' => 'None',
            'discount_amount' => '0.00',
            'bill_currency' => 'VND',
            'created_at' => $now,
            'updated_at' => $now,
            'row_version' => 1,
        ], $overrides)));
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     */
    private function insertDeploySafetyPayments(array $rows): void
    {
        $reservationIds = [];
        foreach ($rows as $row) {
            $reservationIds[] = (int) ($row['reservation_id'] ?? 1);
        }

        foreach (array_values(array_unique(array_filter($reservationIds, static fn (int $id): bool => $id > 0))) as $reservationId) {
            if (! DB::table('reservations')->where('reservation_id', $reservationId)->exists()) {
                $this->insertDeploySafetyReservation(['reservation_id' => $reservationId]);
            }
        }

        DB::table('payments')->insert(array_map(
            fn (array $row): array => $this->deploySafetyPaymentPayload($row),
            $rows,
        ));
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    private function deploySafetyPaymentPayload(array $overrides): array
    {
        $now = now('UTC');
        $paymentId = (int) ($overrides['payment_id'] ?? 1);

        return $this->payloadForExistingColumns('payments', array_merge([
            'payment_id' => $paymentId,
            'reservation_id' => 1,
            'branch_id' => 1,
            'refund_of_payment_id' => null,
            'amount' => '100.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Other',
            'payment_type' => 'Final',
            'status' => 'Success',
            'transaction_code' => 'DEPLOY-PAY-'.$paymentId,
            'idempotency_key' => null,
            'paid_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => null,
            'updated_by' => null,
            'notes' => null,
            'provider_response_json' => null,
            'row_version' => 1,
        ], $overrides));
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function payloadForExistingColumns(string $table, array $payload): array
    {
        $columns = array_flip(Schema::getColumnListing($table));

        return array_intersect_key($payload, $columns);
    }

    private function hasUniqueIndex(string $table, string $indexName): bool
    {
        $driver = (string) DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return (int) DB::table('information_schema.statistics')
                ->where('table_schema', (string) DB::connection()->getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $indexName)
                ->where('non_unique', 0)
                ->count() > 0;
        }

        return false;
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    private function withoutCheckConstraintChecks(callable $callback): mixed
    {
        $disabled = false;
        if (in_array((string) DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            try {
                DB::statement('SET SESSION check_constraint_checks = 0');
                $disabled = true;
            } catch (\Throwable) {
                $disabled = false;
            }
        }

        try {
            return $callback();
        } finally {
            if ($disabled) {
                DB::statement('SET SESSION check_constraint_checks = 1');
            }
        }
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    private function withoutForeignKeyChecks(callable $callback): mixed
    {
        $disabled = false;
        if (in_array((string) DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            $disabled = true;
        }

        try {
            return $callback();
        } finally {
            if ($disabled) {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            }
        }
    }

    private function preparePortableFullDumpArtifact(): void
    {
        $dumpPath = base_path('db_all.sql');
        $this->fullDumpBackup = File::exists($dumpPath) ? File::get($dumpPath) : null;

        $fragments = array_values(array_filter(
            array_map(static fn ($fragment) => is_scalar($fragment) ? trim((string) $fragment) : '', (array) config('booking_release.artifacts.full_dump.required_fragments', [])),
            static fn (string $fragment): bool => $fragment !== ''
        ));

        File::put($dumpPath, "-- portable test dump\n".implode("\n", $fragments)."\n");
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
                $table->string('actor_key', 120)->nullable();
                $table->string('entity_type', 50);
                $table->string('entity_id', 64);
                $table->string('action', 50);
                $table->text('before_json')->nullable();
                $table->text('after_json')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (Schema::hasTable('audit_logs') && ! Schema::hasColumn('audit_logs', 'actor_key')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->string('actor_key', 120)->nullable()->after('actor_user_id');
            });
        }

        if (! Schema::hasTable('ingredient_stock_movements')) {
            Schema::create('ingredient_stock_movements', function (Blueprint $table): void {
                $table->unsignedBigInteger('movement_id')->primary();
                $table->string('reference_type', 50)->nullable();
                $table->string('reference_id', 120)->nullable();
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
        DB::table('ingredient_stock_movements')->delete();
    }
}
