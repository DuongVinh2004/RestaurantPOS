<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class BuildsBookingScenarioGeneratedColumnGuardTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_create_reservation_ignores_generated_column_overrides_when_schema_is_mysql_truth(): void
    {
        if (! Schema::hasColumn('reservations', 'active_applied_user_voucher_id')) {
$this->failOrSkipBookingSchemaContract('Generated reservation active voucher column is not present on this test schema.');
        }

        $reservationId = $this->createReservation([
            'status' => 'Confirmed',
            'active_applied_user_voucher_id' => 999999,
        ]);

        $record = DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->first(['reservation_id', 'applied_user_voucher_id', 'active_applied_user_voucher_id']);

        self::assertNotNull($record);
        self::assertNull($record->applied_user_voucher_id);
        self::assertNull($record->active_applied_user_voucher_id);
    }

    public function test_create_table_hold_ignores_generated_column_overrides_when_schema_is_mysql_truth(): void
    {
        if (! Schema::hasColumn('table_holds', 'active_session_hold_key')) {
$this->failOrSkipBookingSchemaContract('Generated active session hold column is not present on this test schema.');
        }

        $sessionId = 'sess-generated-guard';
        $holdId = $this->createTableHold([
            'session_id' => $sessionId,
            'hold_status' => 'Holding',
            'active_session_hold_key' => 'should-not-be-written',
        ]);

        $record = DB::table('table_holds')
            ->where('hold_id', $holdId)
            ->first(['hold_id', 'session_id', 'hold_status', 'active_session_hold_key']);

        self::assertNotNull($record);
        self::assertSame($sessionId, (string) $record->session_id);
        self::assertSame('Holding', (string) $record->hold_status);
        self::assertSame($sessionId, (string) $record->active_session_hold_key);
    }
}
