<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffCashierShiftHttpFlowTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();
        config()->set('notifications.outbox.enabled', false);
    }

    public function test_staff_can_open_current_show_and_close_cashier_shift_with_payment_backed_summary(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();
        $headers = $this->withIdempotencyKey('idem-cashier-shift-open-a', $this->staffAuthHeaders($staffId, 'staff-cashier-shift-a'));

        $open = $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'opening_float_amount' => 100000,
            'currency' => 'VND',
            'terminal_code' => 'POS-01',
            'notes' => 'Opening shift',
        ], $headers);

        $open->assertCreated()
            ->assertJsonPath('data.status', 'Open')
            ->assertJsonPath('data.currency', 'VND')
            ->assertJsonPath('data.opening_float_amount', '100000.00')
            ->assertJsonPath('data.summary.cash.expected_cash_amount', '100000.00')
            ->assertJsonPath('data.summary.payments.captured_total', '0.00');

        $shiftId = (int) $open->json('data.cashier_shift_id');
        $rowVersion = (int) $open->json('data.row_version');

        $openedLog = $this->assertAuditLogRecorded('cashier_shift.opened', 'cashier_shift', $shiftId);
        self::assertSame($staffId, $openedLog->actor_user_id);
        self::assertSame('staff_user', $openedLog->actor_type);
        self::assertSame(100000.0, (float) data_get($openedLog->summary_json, 'opening_float_amount'));

        $this->getJson('/api/v1/staff/cashier/shifts/current', $headers)
            ->assertOk()
            ->assertJsonPath('data.cashier_shift_id', $shiftId)
            ->assertJsonPath('data.status', 'Open');

        $this->postJson('/api/v1/staff/orders/'.$orderId.'/pay', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 40000,
            'currency' => 'VND',
            'transaction_code' => 'SHIFT-PAY-CASH-1',
            'row_version' => 1,
        ], $this->withIdempotencyKey('idem-cashier-shift-pay-cash-a', $headers))->assertOk();

        $this->postJson('/api/v1/staff/orders/'.$orderId.'/pay', [
            'payment_method' => 'Card',
            'payment_provider' => 'Card',
            'paid_amount' => 60000,
            'currency' => 'VND',
            'transaction_code' => 'SHIFT-PAY-CARD-1',
            'row_version' => 1,
        ], $this->withIdempotencyKey('idem-cashier-shift-pay-card-a', $headers))->assertOk();

        self::assertSame(2, (int) DB::table('payments')->where('reservation_id', $reservationId)->count());

        $showOpen = $this->getJson('/api/v1/staff/cashier/shifts/'.$shiftId, $headers);
        $showOpen->assertOk()
            ->assertJsonPath('data.summary.payments.captured_total', '100000.00')
            ->assertJsonPath('data.summary.payments.final_net', '100000.00')
            ->assertJsonPath('data.summary.cash.expected_cash_amount', '140000.00');

        $close = $this->postJson('/api/v1/staff/cashier/shifts/'.$shiftId.'/close', [
            'actual_cash_amount' => 139500,
            'row_version' => $rowVersion,
            'notes' => 'Closing shift',
        ], $this->withIdempotencyKey('idem-cashier-shift-close-a', $headers));

        $close->assertOk()
            ->assertJsonPath('data.status', 'Closed')
            ->assertJsonPath('data.expected_cash_amount', '140000.00')
            ->assertJsonPath('data.actual_cash_amount', '139500.00')
            ->assertJsonPath('data.cash_discrepancy_amount', '-500.00')
            ->assertJsonPath('data.summary.payments.captured_total', '100000.00')
            ->assertJsonPath('data.summary.payments.net_paid_total', '100000.00')
            ->assertJsonPath('data.summary.cash.captured_amount', '40000.00')
            ->assertJsonPath('data.summary.cash.refunded_amount', '0.00')
            ->assertJsonPath('data.summary.cash.expected_cash_amount', '140000.00');

        self::assertSame('Closed', (string) DB::table('cashier_shifts')->where('cashier_shift_id', $shiftId)->value('status'));
        self::assertSame('140000.00', number_format((float) DB::table('cashier_shifts')->where('cashier_shift_id', $shiftId)->value('expected_cash_amount'), 2, '.', ''));

        $closedLog = $this->assertAuditLogRecorded('cashier_shift.closed', 'cashier_shift', $shiftId);
        self::assertSame($staffId, $closedLog->actor_user_id);
        self::assertSame('staff_user', $closedLog->actor_type);
        self::assertSame(140000.0, (float) data_get($closedLog->summary_json, 'expected_cash_amount'));
        self::assertSame(-500.0, (float) data_get($closedLog->summary_json, 'cash_discrepancy_amount'));

        $this->getJson('/api/v1/staff/cashier/shifts/current', $headers)
            ->assertStatus(404);
    }

    public function test_staff_can_list_authenticated_cashier_shift_history_with_filters(): void
    {
        [$staffId] = $this->seedActiveOrderScenario();
        $otherStaffId = $this->createUser(['role_name' => 'Cashier']);
        $branchA = $this->createBranch(['branch_code' => 'SHIFTLOOKA', 'branch_name' => 'Shift Lookup A']);
        $branchB = $this->createBranch(['branch_code' => 'SHIFTLOOKB', 'branch_name' => 'Shift Lookup B']);
        $this->allowCashierBranchScope($branchA, $branchB);
        $headers = $this->withIdempotencyKey('idem-cashier-shift-lookup-open-a', $this->staffAuthHeaders($staffId, 'staff-cashier-shift-lookup-a'));

        $firstOpen = $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'branch_id' => $branchA,
            'opening_float_amount' => 50000,
            'currency' => 'VND',
            'terminal_code' => 'LOOKUP-A',
        ], $headers)->assertCreated();

        $firstShiftId = (int) $firstOpen->json('data.cashier_shift_id');
        $firstRowVersion = (int) $firstOpen->json('data.row_version');

        $this->postJson('/api/v1/staff/cashier/shifts/'.$firstShiftId.'/close', [
            'actual_cash_amount' => 50000,
            'row_version' => $firstRowVersion,
        ], $this->withIdempotencyKey('idem-cashier-shift-lookup-close-a', $headers))->assertOk();

        $secondOpen = $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'branch_id' => $branchB,
            'opening_float_amount' => 75000,
            'currency' => 'VND',
            'terminal_code' => 'LOOKUP-B',
        ], $this->withIdempotencyKey('idem-cashier-shift-lookup-open-b', $headers))->assertCreated();

        $secondShiftId = (int) $secondOpen->json('data.cashier_shift_id');

        $otherHeaders = $this->withIdempotencyKey('idem-cashier-shift-lookup-other', $this->staffAuthHeaders($otherStaffId, 'staff-cashier-shift-lookup-other'));
        $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'branch_id' => $branchA,
            'opening_float_amount' => 30000,
            'currency' => 'VND',
            'terminal_code' => 'LOOKUP-OTHER',
        ], $otherHeaders)->assertCreated();

        $response = $this->getJson('/api/v1/staff/cashier/shifts?branch_id='.$branchA.'&status=Closed', $headers);

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'cashier_shift_lookup')
            ->assertJsonPath('meta.scope', 'authenticated_cashier')
            ->assertJsonPath('meta.filters.branch_id', $branchA)
            ->assertJsonPath('meta.filters.status', 'Closed')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cashier_shift_id', $firstShiftId);

        $allResponse = $this->getJson('/api/v1/staff/cashier/shifts', $headers);

        $allResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.cashier_shift_id', $secondShiftId);
    }

    public function test_current_cashier_shift_can_be_scoped_to_the_shell_branch(): void
    {
        [$staffId] = $this->seedActiveOrderScenario();
        $branchA = $this->createBranch(['branch_code' => 'CURRA', 'branch_name' => 'Current A']);
        $branchB = $this->createBranch(['branch_code' => 'CURRB', 'branch_name' => 'Current B']);
        $this->allowCashierBranchScope($branchA);
        $headers = $this->withIdempotencyKey('idem-cashier-shift-current-branch-open', $this->staffAuthHeaders($staffId, 'staff-cashier-shift-current-branch'));

        $open = $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'branch_id' => $branchA,
            'opening_float_amount' => 25000,
            'currency' => 'VND',
        ], $headers);

        $open->assertCreated()
            ->assertJsonPath('data.branch_id', $branchA);

        $this->getJson('/api/v1/staff/cashier/shifts/current?branch_id='.$branchA, $headers)
            ->assertOk()
            ->assertJsonPath('data.branch_id', $branchA)
            ->assertJsonPath('meta.branch_id', $branchA);

        $this->getJson('/api/v1/staff/cashier/shifts/current?branch_id='.$branchB, $headers)
            ->assertStatus(404);
    }

    public function test_cashier_shift_reads_and_close_fail_closed_outside_actor_branch_scope(): void
    {
        [$staffId] = $this->seedActiveOrderScenario();
        $branchA = $this->createBranch(['branch_code' => 'SHIFTALLOW', 'branch_name' => 'Shift Allowed']);
        $branchB = $this->createBranch(['branch_code' => 'SHIFTBLOCK', 'branch_name' => 'Shift Blocked']);
        $this->allowCashierBranchScope($branchA);
        $headers = $this->staffAuthHeaders($staffId, 'staff-cashier-shift-branch-deny');

        $blockedShiftId = $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchB,
            'status' => 'Open',
            'shift_code' => 'CSH-BRANCH-BLOCKED',
            'terminal_code' => 'BLOCKED-POS',
            'row_version' => 1,
        ]);

        $this->getJson('/api/v1/staff/cashier/shifts/current', $headers)
            ->assertStatus(404);

        $this->getJson('/api/v1/staff/cashier/shifts/current?branch_id='.$branchB, $headers)
            ->assertStatus(404);

        $this->getJson('/api/v1/staff/cashier/shifts', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/staff/cashier/shifts/'.$blockedShiftId, $headers)
            ->assertStatus(404);

        $this->postJson('/api/v1/staff/cashier/shifts/'.$blockedShiftId.'/close', [
            'actual_cash_amount' => 0,
            'row_version' => 1,
        ], $this->withIdempotencyKey('idem-cashier-shift-branch-deny-close', $headers))
            ->assertStatus(404);

        self::assertSame('Open', (string) DB::table('cashier_shifts')->where('cashier_shift_id', $blockedShiftId)->value('status'));
    }

    public function test_staff_cannot_show_or_close_another_cashiers_shift(): void
    {
        [$ownerStaffId] = $this->seedActiveOrderScenario();
        $otherStaffId = $this->createUser(['role_name' => 'Cashier']);
        $ownerHeaders = $this->withIdempotencyKey('idem-cashier-shift-owner-open', $this->staffAuthHeaders($ownerStaffId, 'staff-cashier-owner'));
        $otherHeaders = $this->withIdempotencyKey('idem-cashier-shift-other-close', $this->staffAuthHeaders($otherStaffId, 'staff-cashier-other'));

        $open = $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'opening_float_amount' => 25000,
            'currency' => 'VND',
        ], $ownerHeaders)->assertCreated();

        $shiftId = (int) $open->json('data.cashier_shift_id');
        $rowVersion = (int) $open->json('data.row_version');

        $this->getJson('/api/v1/staff/cashier/shifts/'.$shiftId, $otherHeaders)
            ->assertStatus(404);

        $this->postJson('/api/v1/staff/cashier/shifts/'.$shiftId.'/close', [
            'actual_cash_amount' => 25000,
            'row_version' => $rowVersion,
        ], $otherHeaders)->assertStatus(404);

        self::assertSame('Open', (string) DB::table('cashier_shifts')->where('cashier_shift_id', $shiftId)->value('status'));
    }

    public function test_cashier_shift_lookup_rejects_invalid_listing_filters(): void
    {
        [$staffId] = $this->seedActiveOrderScenario();

        $this->getJson('/api/v1/staff/cashier/shifts?status=Invalid', $this->staffAuthHeaders($staffId, 'staff-cashier-shift-invalid-filter'))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');
    }

    public function test_cashier_shift_summary_only_includes_same_branch_and_currency_payments(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Cashier']);
        $headers = $this->withIdempotencyKey('idem-cashier-shift-open-scope', $this->staffAuthHeaders($staffId, 'staff-cashier-shift-scope'));
        $branchA = $this->createBranch(['branch_code' => 'A1', 'branch_name' => 'Branch A']);
        $branchB = $this->createBranch(['branch_code' => 'B1', 'branch_name' => 'Branch B']);
        $this->allowCashierBranchScope($branchA);

        $open = $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'branch_id' => $branchA,
            'opening_float_amount' => 100000,
            'currency' => 'VND',
        ], $headers);

        $open->assertCreated();
        $shiftId = (int) $open->json('data.cashier_shift_id');
        $rowVersion = (int) $open->json('data.row_version');

        $reservationA = $this->createReservation([
            'branch_id' => $branchA,
            'user_id' => $customerId,
            'status' => 'Completed',
            'bill_currency' => 'VND',
        ]);
        $reservationB = $this->createReservation([
            'branch_id' => $branchB,
            'user_id' => $customerId,
            'status' => 'Completed',
            'bill_currency' => 'VND',
        ]);
        $reservationUsd = $this->createReservation([
            'branch_id' => $branchA,
            'user_id' => $customerId,
            'status' => 'Completed',
            'bill_currency' => 'USD',
        ]);

        $this->createPayment([
            'branch_id' => $branchA,
            'reservation_id' => $reservationA,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '40000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'transaction_code' => 'SHIFT-SCOPE-A',
        ]);

        $this->createPayment([
            'branch_id' => $branchB,
            'reservation_id' => $reservationB,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '70000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'transaction_code' => 'SHIFT-SCOPE-B',
        ]);

        $this->createPayment([
            'branch_id' => $branchA,
            'reservation_id' => $reservationUsd,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '10.00',
            'currency' => 'USD',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'transaction_code' => 'SHIFT-SCOPE-USD',
        ]);

        $show = $this->getJson('/api/v1/staff/cashier/shifts/'.$shiftId, $headers);
        $show->assertOk()
            ->assertJsonPath('data.branch_id', $branchA)
            ->assertJsonPath('data.summary.payments.captured_total', '40000.00')
            ->assertJsonPath('data.summary.cash.captured_amount', '40000.00')
            ->assertJsonPath('data.summary.cash.expected_cash_amount', '140000.00');

        $close = $this->postJson('/api/v1/staff/cashier/shifts/'.$shiftId.'/close', [
            'actual_cash_amount' => 140000,
            'row_version' => $rowVersion,
        ], $this->withIdempotencyKey('idem-cashier-shift-close-scope', $headers));

        $close->assertOk()
            ->assertJsonPath('data.expected_cash_amount', '140000.00')
            ->assertJsonPath('data.summary.payments.captured_total', '40000.00');
    }

    public function test_staff_cannot_open_second_shift_and_cannot_close_the_same_shift_twice(): void
    {
        [$staffId] = $this->seedActiveOrderScenario();
        $headers = $this->withIdempotencyKey('idem-cashier-shift-open-b', $this->staffAuthHeaders($staffId, 'staff-cashier-shift-b'));

        $first = $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'opening_float_amount' => 50000,
            'currency' => 'VND',
        ], $headers);

        $first->assertCreated();
        $shiftId = (int) $first->json('data.cashier_shift_id');
        $rowVersion = (int) $first->json('data.row_version');

        $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'opening_float_amount' => 20000,
            'currency' => 'VND',
        ], $this->withIdempotencyKey('idem-cashier-shift-open-c', $headers))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        $this->postJson('/api/v1/staff/cashier/shifts/'.$shiftId.'/close', [
            'actual_cash_amount' => 50000,
            'row_version' => $rowVersion,
        ], $this->withIdempotencyKey('idem-cashier-shift-close-b', $headers))
            ->assertOk()
            ->assertJsonPath('data.status', 'Closed');

        $this->postJson('/api/v1/staff/cashier/shifts/'.$shiftId.'/close', [
            'actual_cash_amount' => 50000,
            'row_version' => $rowVersion,
        ], $this->withIdempotencyKey('idem-cashier-shift-close-c', $headers))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');
    }

    public function test_close_cashier_shift_rejects_stale_row_version_with_clear_message(): void
    {
        [$staffId] = $this->seedActiveOrderScenario();
        $headers = $this->withIdempotencyKey('idem-cashier-shift-open-stale', $this->staffAuthHeaders($staffId, 'staff-cashier-shift-stale'));

        $open = $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'opening_float_amount' => 50000,
            'currency' => 'VND',
        ], $headers);

        $open->assertCreated();
        $shiftId = (int) $open->json('data.cashier_shift_id');
        $staleRowVersion = (int) $open->json('data.row_version');

        DB::table('cashier_shifts')
            ->where('cashier_shift_id', $shiftId)
            ->update(['row_version' => $staleRowVersion + 1]);

        $response = $this->postJson('/api/v1/staff/cashier/shifts/'.$shiftId.'/close', [
            'actual_cash_amount' => 50000,
            'row_version' => $staleRowVersion,
        ], $this->withIdempotencyKey('idem-cashier-shift-close-stale', $headers));

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonPath('category_code', 'stale_write')
            ->assertJsonPath('details.errors.row_version.0', 'The row_version is stale (row_version mismatch). Reload the resource and try again.');

        self::assertSame('Open', (string) DB::table('cashier_shifts')->where('cashier_shift_id', $shiftId)->value('status'));
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function seedActiveOrderScenario(): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Cashier']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 2,
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '100000.00',
        ]);

        return [$staffId, $orderId, $reservationId];
    }

    private function allowCashierBranchScope(int ...$branchIds): void
    {
        $tokens = ['default'];

        foreach ($branchIds as $branchId) {
            $tokens[] = (string) $branchId;
        }

        config()->set('staff_capabilities.role_branch_scopes.Cashier', array_values(array_unique($tokens)));
    }
}
