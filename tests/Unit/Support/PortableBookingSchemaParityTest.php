<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class PortableBookingSchemaParityTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->requireBookingSchema();
    }

    public function test_portable_sqlite_schema_keeps_critical_db_sensitive_columns_and_indexes(): void
    {
        self::assertSame('sqlite', DB::getDriverName());

        self::assertTrue(Schema::hasColumn('reservations', 'active_applied_user_voucher_id'));
        self::assertTrue(Schema::hasColumn('table_holds', 'branch_id'));
        self::assertTrue(Schema::hasColumn('waiting_list', 'customer_response_status'));
        self::assertTrue(Schema::hasColumn('waiting_list', 'customer_responded_at'));
        self::assertTrue(Schema::hasColumn('waiting_list', 'customer_confirmed_arrival_at'));

        $this->assertIndexExists('reservations', 'uq_reservations__active_applied_user_voucher_id');
        $this->assertIndexExists('reservation_tables', 'uq_reservation_tables__reservation_id__table_id');
        $this->assertIndexExists('table_hold_details', 'uq_table_hold_details__hold_id__table_id');
        $this->assertIndexExists('payments', 'uq_payments__idempotency_key');
        $this->assertIndexExists('payments', 'uq_payments__payment_provider__transaction_code');
        $this->assertIndexExists('table_holds', 'uq_table_holds__active_session_hold_key');
        $this->assertIndexExists('table_holds', 'idx_table_holds__session_id__confirmed_reservation_id');
        $this->assertIndexExists('reservation_deposit_payment_sessions', 'uq_reservation_deposit_payment_sessions__linked_payment_id');
        $this->assertIndexExists('reservation_bill_payment_sessions', 'uq_reservation_bill_payment_sessions__linked_payment_id');
        $this->assertIndexExists('finance_replay_records', 'uq_finance_replay_records__scope_aggregate_key');
        $this->assertIndexExists('waiting_list', 'idx_waiting_list__branch_id__status__priority__requested_at');
    }

    public function test_portable_sqlite_schema_preserves_active_hold_uniqueness_for_live_sessions(): void
    {
        $this->createTableHold([
            'session_id' => 'session-parity-live-hold',
            'hold_status' => 'Holding',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->createTableHold([
            'session_id' => 'session-parity-live-hold',
            'hold_status' => 'Pending',
        ]);
    }

    private function assertIndexExists(string $table, string $indexName): void
    {
        $rows = DB::select(sprintf("PRAGMA index_list('%s')", str_replace("'", "''", $table)));

        foreach ($rows as $row) {
            if ((string) ($row->name ?? '') === $indexName) {
                self::assertTrue(true);

                return;
            }
        }

        self::fail(sprintf('Expected sqlite index [%s] on table [%s].', $indexName, $table));
    }
}
