<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class BuildsBookingScenarioInvariantGuardTest extends TestCase
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

    public function test_create_reservation_rejects_invalid_time_range(): void
    {
        $start = Carbon::parse('2026-04-01T10:00:00Z')->utc();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reservations time-range contract');

        $this->createReservation([
            'start_time' => $start,
            'end_time' => $start->copy()->subMinute(),
        ]);
    }

    public function test_create_payment_rejects_negative_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('payments non-negative amount contract');

        $this->createPayment([
            'amount' => '-1000.00',
        ]);
    }

    public function test_create_waiting_list_entry_rejects_confirmed_arrival_without_accepted_response(): void
    {
        $now = Carbon::parse('2026-04-01T10:00:00Z')->utc();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('waiting_list confirmed-arrival contract');

        $this->createWaitingListEntry([
            'status' => 'Notified',
            'notified_at' => $now->copy()->subMinute(),
            'notify_expires_at' => $now->copy()->addMinutes(10),
            'customer_response_status' => 'Declined',
            'customer_responded_at' => $now,
            'customer_confirmed_arrival_at' => $now,
        ]);
    }

    public function test_create_waiting_list_entry_infers_accepted_response_when_confirmed_arrival_is_present(): void
    {
        $now = Carbon::parse('2026-04-01T10:00:00Z')->utc();

        $waitingId = $this->createWaitingListEntry([
            'status' => 'Seated',
            'notified_at' => $now->copy()->subMinutes(5),
            'seated_at' => $now->copy()->addMinutes(5),
            'customer_confirmed_arrival_at' => $now,
        ]);

        self::assertSame('Accepted', (string) DB::table('waiting_list')->where('waiting_id', $waitingId)->value('customer_response_status'));
        self::assertNotNull(DB::table('waiting_list')->where('waiting_id', $waitingId)->value('customer_responded_at'));
    }

    public function test_create_cashier_shift_respects_mysql_generated_active_cashier_column_contract(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);

        $shiftId = $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'status' => 'Closed',
        ]);

        self::assertGreaterThan(0, $shiftId);
        self::assertSame('Closed', (string) DB::table('cashier_shifts')->where('cashier_shift_id', $shiftId)->value('status'));
    }

    public function test_create_table_hold_rejects_invalid_time_range(): void
    {
        $start = Carbon::parse('2026-04-01T10:00:00Z')->utc();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('table_holds time-range contract');

        $this->createTableHold([
            'start_time' => $start,
            'end_time' => $start->copy(),
        ]);
    }

    public function test_active_reservation_voucher_uniqueness_is_preserved_in_portable_schema(): void
    {
        $userVoucherId = $this->assignVoucher();

        $this->createReservation([
            'applied_user_voucher_id' => $userVoucherId,
            'status' => 'Confirmed',
        ]);

        $this->expectException(QueryException::class);

        $this->createReservation([
            'applied_user_voucher_id' => $userVoucherId,
            'status' => 'Reserved',
        ]);
    }

    public function test_active_waiting_list_owner_uniqueness_is_preserved_in_portable_schema(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $now = Carbon::parse('2026-04-01T10:00:00Z')->utc();

        $this->createWaitingListEntry([
            'user_id' => $ownerId,
            'status' => 'Waiting',
            'requested_at' => $now,
        ]);

        $this->expectException(QueryException::class);

        $this->createWaitingListEntry([
            'user_id' => $ownerId,
            'status' => 'Notified',
            'requested_at' => $now->copy()->addMinute(),
            'notified_at' => $now->copy()->addMinute(),
            'notify_expires_at' => $now->copy()->addMinutes(11),
        ]);
    }
}
