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
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch([
            'branch_code' => 'SCOPE-ANNEX',
            'branch_name' => 'Scope Annex',
        ]);

        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Completed',
            'final_bill_amount' => '90000.00',
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
            'amount' => '90000.00',
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

    public function test_explicit_branch_scope_configuration_keeps_legitimate_branch_flow_working(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
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
            'final_bill_amount' => '125000.00',
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
            'amount' => '125000.00',
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

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/audit-trail?branch_id='.$branchId)
            ->assertOk()
            ->assertJsonPath('data.0.primary_subject.id', (string) $paymentId);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/finance/reconciliation?branch_id='.$branchId)
            ->assertOk()
            ->assertJsonPath('data.0.reservation.reservation_id', $reservationId);
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
}
