<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffCapabilityHttpGuardTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();

        $adminRoleId = $this->ensureRole('Admin');
        $staffRoleId = $this->ensureRole('Staff');

        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_auth.allow_role_name_fallback', false);
        config()->set('staff_auth.allowed_role_ids', [$adminRoleId, $staffRoleId]);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_normal_staff_is_forbidden_from_refund_and_loyalty_adjust_routes_but_can_access_declared_voucher_capability(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
        ]);
        $targetUserId = $this->createUser(['role_name' => 'Customer']);
        $this->ensureUserPoints($targetUserId, 100);

        $staffId = $this->createUser(['role_name' => 'Staff']);
        config()->set('staff_auth.api_keys', ['staff-capability-key' => $staffId]);
        config()->set('staff_capabilities.role_capabilities', [
            'Admin' => ['*'],
            'Staff' => ['voucher.manage'],
        ]);

        $this->withHeaders($this->staffHeaders('staff-capability-key'))
            ->getJson('/api/v1/staff/reservations/'.$reservationId.'/refund-preview')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'payment.refund');

        $this->withHeaders($this->staffHeaders('staff-capability-key'))
            ->postJson('/api/v1/staff/users/'.$targetUserId.'/loyalty/adjust', [
                'points' => 5,
                'reason' => 'manual_adjust_test',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'loyalty.adjust');

        $this->withHeaders($this->staffHeaders('staff-capability-key'))
            ->getJson('/api/v1/staff/reservations/'.$reservationId.'/vouchers')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'reservation',
                    'available_vouchers',
                ],
            ]);

        $this->assertSame(100, (int) DB::table('user_points')->where('user_id', $targetUserId)->value('total_points'));
    }

    public function test_admin_can_pass_staff_capability_guards_for_sensitive_routes(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Completed',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
        ]);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-CAPABILITY-PREVIEW-1',
        ]);
        $targetUserId = $this->createUser(['role_name' => 'Customer']);
        $this->ensureUserPoints($targetUserId, 100);

        $adminId = $this->createUser(['role_name' => 'Admin']);
        config()->set('staff_auth.api_keys', ['admin-capability-key' => $adminId]);

        $this->withHeaders($this->staffHeaders('admin-capability-key'))
            ->getJson('/api/v1/staff/reservations/'.$reservationId.'/refund-preview')
            ->assertOk()
            ->assertJsonPath('meta.action', 'refund_preview');

        $this->withHeaders($this->staffHeaders('admin-capability-key'))
            ->getJson('/api/v1/staff/reporting/daily-sales?start_date=2026-03-01&end_date=2026-03-01')
            ->assertOk()
            ->assertJsonPath('meta.snapshot_health.family', 'sales');

        $this->withHeaders($this->staffHeaders('admin-capability-key'))
            ->getJson('/api/v1/staff/kitchen/stations')
            ->assertOk()
            ->assertJsonPath('meta.realtime.topic', 'kitchen');

        $this->withHeaders($this->staffHeaders('admin-capability-key'))
            ->getJson('/api/v1/admin/kitchen/stations')
            ->assertOk()
            ->assertJsonPath('meta.count', 0);

        $this->withHeaders($this->staffHeaders('admin-capability-key'))
            ->getJson('/api/v1/staff/reservations/'.$reservationId.'/vouchers')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'reservation',
                    'available_vouchers',
                ],
            ]);

        $this->withHeaders($this->staffHeaders('admin-capability-key', 'idem-staff-capability-loyalty-adjust-1'))
            ->postJson('/api/v1/staff/users/'.$targetUserId.'/loyalty/adjust', [
                'points' => 5,
                'reason' => 'manual_adjust_test',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.user_id', $targetUserId)
            ->assertJsonPath('data.user.total_points', 105);

        $this->assertSame(105, (int) DB::table('user_points')->where('user_id', $targetUserId)->value('total_points'));
    }

    public function test_staff_routes_still_require_valid_staff_authentication_before_capability_checks(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
        ]);
        $branchId = $this->createBranch([
            'branch_code' => 'AUTHDENY',
            'branch_name' => 'Auth Deny Branch',
        ]);

        $this->withHeaders($this->staffHeaders('unknown-key'))
            ->getJson('/api/v1/staff/reservations/'.$reservationId.'/vouchers')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');

        $this->withHeaders($this->staffHeaders('unknown-key', 'idem-invalid-staff-waiting-list'))
            ->postJson('/api/v1/staff/waiting-list', [
                'branch_id' => $branchId,
                'guest_count' => 2,
                'guest_name' => 'Unauthorized queue attempt',
            ])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonMissingPath('required_capability');

        $this->withHeaders($this->staffHeaders('unknown-key'))
            ->getJson('/api/v1/admin/settings/branches')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonMissingPath('required_capability');
    }

    public function test_status_mutation_route_outside_staff_prefix_still_uses_staff_capability_guard(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
        ]);

        $staffId = $this->createUser(['role_name' => 'Staff']);
        config()->set('staff_auth.api_keys', ['staff-status-key' => $staffId]);
        config()->set('staff_capabilities.role_capabilities', [
            'Admin' => ['*'],
            'Staff' => [],
        ]);

        $this->withHeaders($this->staffHeaders('unknown-key'))
            ->patchJson('/api/v1/reservations/'.$reservationId.'/status', [
                'status' => 'Reserved',
                'row_version' => 1,
            ])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');

        $forbiddenHeaders = array_merge($this->staffHeaders('staff-status-key'), [
            'X-Request-Id' => 'req-staff-capability-status-forbidden',
        ]);

        $this->withHeaders($forbiddenHeaders)
            ->patchJson('/api/v1/reservations/'.$reservationId.'/status', [
                'status' => 'Reserved',
                'row_version' => 1,
            ])
            ->assertStatus(403)
            ->assertHeader('X-Request-Id', 'req-staff-capability-status-forbidden')
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('request_id', 'req-staff-capability-status-forbidden')
            ->assertJsonPath('required_capability', 'reservation.manage');
    }

    public function test_customer_auth_context_does_not_authenticate_staff_or_admin_routes(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch([
            'branch_code' => 'CUSTDENY',
            'branch_name' => 'Customer Bleed Deny Branch',
        ]);
        $customerHeaders = $this->customerAuthHeaders($customerId, 'sess-customer-bleed-deny');

        $this->withHeaders($customerHeaders)
            ->getJson('/api/v1/staff/conversations')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonMissingPath('required_capability');

        $this->withHeaders($this->withIdempotencyKey($customerHeaders, 'cust-bleed-staff-waiting-list'))
            ->postJson('/api/v1/staff/waiting-list', [
                'branch_id' => $branchId,
                'guest_count' => 2,
                'guest_name' => 'Customer auth should not pass staff boundary',
            ])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonMissingPath('required_capability');

        $this->withHeaders($customerHeaders)
            ->getJson('/api/v1/admin/settings/branches')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonMissingPath('required_capability');
    }

    public function test_customer_role_mapped_to_staff_key_is_forbidden_before_capability_checks(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch([
            'branch_code' => 'ROLEFAIL',
            'branch_name' => 'Wrong Role Branch',
        ]);
        config()->set('staff_auth.api_keys', ['customer-mapped-staff-key' => $customerId]);

        $this->withHeaders($this->staffHeaders('customer-mapped-staff-key'))
            ->getJson('/api/v1/staff/conversations')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonMissingPath('required_capability');

        $this->withHeaders($this->staffHeaders('customer-mapped-staff-key', 'idem-customer-mapped-waiting-list'))
            ->postJson('/api/v1/staff/waiting-list', [
                'branch_id' => $branchId,
                'guest_count' => 2,
                'guest_name' => 'Wrong role must not pass staff boundary',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonMissingPath('required_capability');

        $this->withHeaders($this->staffHeaders('customer-mapped-staff-key'))
            ->getJson('/api/v1/admin/settings/branches')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonMissingPath('required_capability');
    }

    public function test_staff_without_representative_capabilities_is_forbidden_from_multiple_high_risk_route_families(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        config()->set('staff_auth.api_keys', ['staff-limited-key' => $staffId]);
        config()->set('staff_capabilities.role_capabilities', [
            'Admin' => ['*'],
            'Staff' => ['voucher.manage'],
        ]);

        $expectations = [
            ['/api/v1/staff/tables/board', 'table.board.view'],
            ['/api/v1/staff/reservations', 'reservation.manage'],
            ['/api/v1/staff/reservations/999999', 'reservation.manage'],
            ['/api/v1/staff/reservations/999999/orders', 'order.manage'],
            ['/api/v1/staff/menu/items', 'order.manage'],
            ['/api/v1/staff/orders/999999', 'order.manage'],
            ['/api/v1/staff/orders/999999/settlement-preview', 'settlement.manage'],
            ['/api/v1/staff/cashier/shifts', 'cashier.shift.manage'],
            ['/api/v1/staff/reporting/daily-sales?start_date=2026-03-01&end_date=2026-03-01', 'reporting.view'],
            ['/api/v1/staff/kitchen/stations', 'kitchen.manage'],
            ['/api/v1/staff/waiting-list', 'waiting_list.manage'],
            ['/api/v1/staff/conversations', 'conversation.manage'],
            ['/api/v1/admin/kitchen/stations', 'settings.manage'],
            ['/api/v1/admin/settings/branches', 'settings.manage'],
            ['/api/v1/admin/inventory/ingredients', 'inventory.manage'],
        ];

        foreach ($expectations as [$path, $requiredCapability]) {
            $this->withHeaders($this->staffHeaders('staff-limited-key'))
                ->getJson($path)
                ->assertStatus(403)
                ->assertJsonPath('error_code', 'forbidden')
                ->assertJsonPath('required_capability', $requiredCapability)
                ->assertJsonPath('staff_role_name', 'Staff');
        }
    }

    /**
     * @return array<string,string>
     */
    private function staffHeaders(string $key, ?string $idempotencyKey = null): array
    {
        $headers = [
            'Accept' => 'application/json',
            'X-Staff-Key' => $key,
        ];

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }
}
