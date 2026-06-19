<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffBranchScopeSecurityHardeningTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('notifications.outbox.enabled', false);
    }

    public function test_staff_cannot_open_cashier_shift_on_branch_outside_explicit_scope(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->grantStaffCapabilities($staffId, ['cashier.shift.manage']);
        $branchId = $this->createBranch([
            'branch_code' => 'SHIFT-LOCKED',
            'branch_name' => 'Locked Shift Branch',
        ]);

        $response = $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'branch_id' => $branchId,
            'opening_float_amount' => 50000,
            'currency' => 'VND',
        ], $this->withIdempotencyKey('staff-shift-locked-branch-open', $this->staffAuthHeaders($staffId, 'staff-shift-locked-branch')));

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_open_cashier_shift_state_does_not_expand_branch_scoped_read_access(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->grantStaffCapabilities($staffId, ['audit.view', 'settlement.manage']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch([
            'branch_code' => 'SCOPE-ANNEX',
            'branch_name' => 'Scope Annex',
        ]);

        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Completed',
            'final_bill_amount' => '90000',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $conversationId = $this->createConversation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Open',
        ]);
        $paymentId = $this->createPayment([
            'branch_id' => $branchId,
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '90000',
            'currency' => 'VND',
            'created_by' => $staffId,
            'transaction_code' => 'BRANCH-SCOPE-PAY-1',
        ]);

        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
        ]);
        $this->recordAuditLog($staffId, $reservationId, $branchId, $paymentId);

        $headers = $this->staffAuthHeaders($staffId, 'staff-branch-scope-locked');

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/branches')
            ->assertOk()
            ->assertJsonMissing(['branch_id' => $branchId]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/orders/'.$orderId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/conversations?branch_id='.$branchId)
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/audit-trail?branch_id='.$branchId)
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/finance/reconciliation?branch_id='.$branchId)
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');

        self::assertNotNull($conversationId);
    }

    public function test_custom_staff_roles_without_explicit_branch_scope_cannot_inherit_default_branch_access(): void
    {
        $staffId = $this->createUser(['role_name' => 'ScopedOpsNoBranch']);

        config()->set('staff_capabilities.role_capabilities.ScopedOpsNoBranch', [
            'reservation.manage',
            'cashier.shift.manage',
        ]);

        $headers = $this->staffAuthHeaders($staffId, 'staff-scope-no-default');

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/branches')
            ->assertOk()
            ->assertJsonPath('meta.count', 0)
            ->assertJsonPath('meta.accessible_branch_ids', [])
            ->assertJsonPath('meta.branch_access.default_branch_id', 1)
            ->assertJsonPath('meta.branch_access.current_branch_id', null)
            ->assertJsonPath('meta.branch_access.has_default_branch_access', false)
            ->assertJsonPath('meta.branch_access.access_source', 'fallback_branch_scopes');

        $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'branch_id' => 1,
            'opening_float_amount' => 50000,
            'currency' => 'VND',
        ], $this->withIdempotencyKey('staff-shift-no-default-branch-open', $headers))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_explicit_branch_scope_configuration_keeps_legitimate_branch_flow_working(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->grantStaffCapabilities($staffId, ['audit.view', 'cashier.shift.manage', 'settlement.manage']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch([
            'branch_code' => 'SCOPE-ALLOW',
            'branch_name' => 'Allowed Scope Branch',
        ]);

        config()->set('staff_capabilities.role_branch_scopes.Staff', ['default', (string) $branchId]);

        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Completed',
            'final_bill_amount' => '125000',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $conversationId = $this->createConversation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Open',
        ]);
        $paymentId = $this->createPayment([
            'branch_id' => $branchId,
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '125000',
            'currency' => 'VND',
            'created_by' => $staffId,
            'transaction_code' => 'BRANCH-SCOPE-PAY-2',
        ]);
        $this->recordAuditLog($staffId, $reservationId, $branchId, $paymentId);

        $headers = $this->staffAuthHeaders($staffId, 'staff-branch-scope-allowed');

        $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'branch_id' => $branchId,
            'opening_float_amount' => 50000,
            'currency' => 'VND',
        ], $this->withIdempotencyKey('staff-shift-allowed-branch-open', $headers))
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $branchId);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/branches')
            ->assertOk()
            ->assertJsonFragment(['branch_id' => $branchId]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/orders/'.$orderId)
            ->assertOk()
            ->assertJsonPath('data.order.order_id', $orderId);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/conversations?branch_id='.$branchId)
            ->assertOk()
            ->assertJsonPath('meta.filters.branch_id', $branchId)
            ->assertJsonFragment(['conversation_id' => $conversationId]);

        $auditResponse = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/audit-trail?branch_id='.$branchId)
            ->assertOk();

        self::assertTrue(
            collect($auditResponse->json('data', []))
                ->contains(static fn (array $entry): bool => (string) data_get($entry, 'primary_subject.id') === (string) $paymentId),
            'Expected branch-scoped audit trail to include the payment subject created by this test.'
        );

        $reconciliationResponse = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/finance/reconciliation?branch_id='.$branchId)
            ->assertOk();

        self::assertTrue(
            collect($reconciliationResponse->json('data', []))
                ->contains(static fn (array $entry): bool => (int) data_get($entry, 'reservation.reservation_id') === $reservationId),
            'Expected branch-scoped reconciliation to include the reservation created by this test.'
        );
    }

    public function test_staff_cannot_issue_invoice_for_cross_branch_reservation_even_with_existing_shift(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->grantStaffCapabilities($staffId, ['settlement.manage']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch([
            'branch_code' => 'INVOICE-LOCKED',
            'branch_name' => 'Invoice Locked Branch',
        ]);

        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Completed',
            'final_bill_amount' => '90000',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
        ]);
        $this->createPayment([
            'branch_id' => $branchId,
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '90000',
            'currency' => 'VND',
            'created_by' => $staffId,
            'transaction_code' => 'BRANCH-INVOICE-PAY-1',
        ]);
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
        ]);

        $headers = $this->withIdempotencyKey(
            'staff-cross-branch-invoice-issue',
            $this->staffAuthHeaders($staffId, 'staff-cross-branch-invoice')
        );

        $this->postJson('/api/v1/staff/finance/invoices/'.$reservationId.'/issue', [], $headers)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_staff_payment_and_refund_replay_paths_do_not_bypass_branch_scope(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->grantStaffCapabilities($staffId, ['payment.refund', 'settlement.manage']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch([
            'branch_code' => 'REPLAY-LOCKED',
            'branch_name' => 'Replay Locked Branch',
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Completed',
            'final_bill_amount' => '100000',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $finalPaymentId = $this->createPayment([
            'branch_id' => $branchId,
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '100000',
            'currency' => 'VND',
            'created_by' => $staffId,
            'transaction_code' => 'BRANCH-REPLAY-PAY-1',
            'idempotency_key' => 'cross-branch-pay-replay',
        ]);
        $this->createPayment([
            'branch_id' => $branchId,
            'reservation_id' => $reservationId,
            'payment_type' => 'Refund',
            'status' => 'Refunded',
            'amount' => '25000',
            'currency' => 'VND',
            'refund_of_payment_id' => $finalPaymentId,
            'created_by' => $staffId,
            'transaction_code' => 'BRANCH-REPLAY-REFUND-1',
            'idempotency_key' => 'cross-branch-refund-replay',
        ]);

        $headers = $this->staffAuthHeaders($staffId, 'staff-cross-branch-replay');

        $this->postJson('/api/v1/staff/orders/'.$orderId.'/pay', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 100000,
            'currency' => 'VND',
            'transaction_code' => 'BRANCH-REPLAY-PAY-REQUEST',
            'row_version' => 1,
        ], $this->withIdempotencyKey($headers, 'cross-branch-pay-replay'))
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');

        $this->postJson('/api/v1/staff/reservations/'.$reservationId.'/refund', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'refund_scope' => 'final',
            'refund_amount' => 25000,
            'currency' => 'VND',
            'transaction_code' => 'BRANCH-REPLAY-REFUND-REQUEST',
            'row_version' => 1,
        ], $this->withIdempotencyKey($headers, 'cross-branch-refund-replay'))
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    private function recordAuditLog(int $staffId, int $reservationId, int $branchId, int $paymentId): void
    {
        $auditId = (int) DB::table('audit_logs')->insertGetId([
            'actor_user_id' => $staffId,
            'actor_type' => 'staff_user',
            'actor_key' => 'staff_api_key:branch-scope',
            'entity_type' => 'payment',
            'entity_id' => (string) $paymentId,
            'action' => 'payment.refunded',
            'before_json' => null,
            'after_json' => null,
            'summary_json' => null,
            'meta_json' => json_encode([
                'request' => [
                    'branch_id' => $branchId,
                    'method' => 'POST',
                    'path' => '/api/v1/staff/reservations/'.$reservationId.'/refund',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'request_id' => 'req-branch-scope-'.$paymentId,
            'ip' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => $this->nowUtc(),
        ]);

        DB::table('audit_log_subjects')->insert([
            'audit_id' => $auditId,
            'subject_type' => 'reservation',
            'subject_id' => (string) $reservationId,
            'subject_role' => 'reservation',
        ]);
    }

    /**
     * @param  list<string>  $extraCapabilities
     */
    private function grantStaffCapabilities(int $staffId, array $extraCapabilities): void
    {
        $user = DB::table('users')
            ->join('roles', 'roles.role_id', '=', 'users.role_id')
            ->where('users.user_id', $staffId)
            ->first(['users.role_id', 'roles.role_name']);

        $roleId = (int) ($user->role_id ?? 0);
        $roleName = (string) ($user->role_name ?? '');
        $roleIdCapabilities = (array) config('staff_capabilities.role_id_capabilities', []);
        $baseCapabilities = (array) ($roleIdCapabilities[$roleId]
            ?? $roleIdCapabilities[(string) $roleId]
            ?? config('staff_capabilities.role_capabilities.'.$roleName, []));

        $roleIdCapabilities[$roleId] = array_values(array_unique(array_filter(array_map(
            'strval',
            array_merge($baseCapabilities, $extraCapabilities),
        ))));

        sort($roleIdCapabilities[$roleId]);
        config()->set('staff_capabilities.role_id_capabilities', $roleIdCapabilities);
    }
}
