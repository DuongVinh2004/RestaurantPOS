<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class SharedReservationDetailBranchIsolationTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
    }

    public function test_staff_branch_scoped_actor_can_show_same_branch_reservation_via_shared_route(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'RSV-SHARED-A',
            'branch_name' => 'Shared Route A',
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $this->assignStaffBranch($staffId, $branchId);

        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'notes' => 'Same branch staff read',
        ]);
        $tableId = $this->attachReservationTable(
            $reservationId,
            $this->createRestaurantTableWithSeats(4, ['branch_id' => $branchId]),
        );

        $response = $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-shared-route-same-branch'))
            ->getJson('/api/v1/reservations/'.$reservationId);

        $response->assertOk()
            ->assertJsonPath('data.access_scope', 'staff')
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.branch_id', $branchId)
            ->assertJsonPath('data.user_id', $customerId)
            ->assertJsonPath('data.table_ids.0', $tableId);
    }

    public function test_staff_branch_scoped_actor_cannot_show_other_branch_reservation_via_shared_customer_route(): void
    {
        $allowedBranchId = $this->createBranch([
            'branch_code' => 'RSV-SHARED-B1',
            'branch_name' => 'Shared Route B1',
        ]);
        $deniedBranchId = $this->createBranch([
            'branch_code' => 'RSV-SHARED-B2',
            'branch_name' => 'Shared Route B2',
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $this->assignStaffBranch($staffId, $allowedBranchId);

        $reservationId = $this->createReservation([
            'branch_id' => $deniedBranchId,
            'user_id' => $customerId,
            'notes' => 'Denied branch staff read',
        ]);
        $this->attachReservationTable(
            $reservationId,
            $this->createRestaurantTableWithSeats(4, ['branch_id' => $deniedBranchId]),
        );

        $response = $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-shared-route-other-branch'))
            ->getJson('/api/v1/reservations/'.$reservationId);

        $response->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');
    }

    public function test_staff_without_reservation_manage_cannot_show_reservation(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'RSV-SHARED-C',
            'branch_name' => 'Shared Route C',
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $this->assignStaffBranch($staffId, $branchId);

        config()->set('staff_capabilities.role_capabilities', [
            'Admin' => ['*'],
            'Staff' => ['table.board.view'],
        ]);

        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
        ]);

        $response = $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-shared-route-no-capability'))
            ->getJson('/api/v1/reservations/'.$reservationId);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'reservation.manage')
            ->assertJsonMissingPath('data');
    }

    public function test_customer_can_show_own_reservation(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $customer = User::query()->findOrFail($customerId);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'notes' => 'Owner can read',
        ]);

        $response = $this->actingAs($customer)->getJson('/api/v1/reservations/'.$reservationId);

        $response->assertOk()
            ->assertJsonPath('data.access_scope', 'owner')
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.user_id', $customerId)
            ->assertJsonPath('data.user.email', (string) $customer->email);
    }

    public function test_customer_cannot_show_other_customer_reservation(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $viewerId = $this->createUser(['role_name' => 'Customer']);
        $viewer = User::query()->findOrFail($viewerId);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'notes' => 'Foreign owner read attempt',
        ]);

        $response = $this->actingAs($viewer)->getJson('/api/v1/reservations/'.$reservationId);

        $response->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');
    }

    public function test_denied_staff_response_does_not_include_customer_payment_order_voucher_fields(): void
    {
        $allowedBranchId = $this->createBranch([
            'branch_code' => 'RSV-SHARED-D1',
            'branch_name' => 'Shared Route D1',
        ]);
        $deniedBranchId = $this->createBranch([
            'branch_code' => 'RSV-SHARED-D2',
            'branch_name' => 'Shared Route D2',
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'email' => 'branch-denied-customer@example.test',
            'full_name' => 'Branch Denied Customer',
        ]);
        $this->assignStaffBranch($staffId, $allowedBranchId);

        $voucherId = $this->createVoucher(['code' => 'DENIED-BRANCH-VOUCHER']);
        $userVoucherId = $this->assignVoucher([
            'user_id' => $customerId,
            'voucher_id' => $voucherId,
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => $deniedBranchId,
            'user_id' => $customerId,
            'applied_user_voucher_id' => $userVoucherId,
            'notes' => 'Sensitive denied branch reservation',
            'final_bill_amount' => '250000.00',
            'billed_at' => $this->nowUtc(),
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'notes' => 'Sensitive denied order',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'notes' => 'Sensitive denied order item',
        ]);
        $this->createPayment([
            'branch_id' => $deniedBranchId,
            'reservation_id' => $reservationId,
            'transaction_code' => 'DENIED-BRANCH-TX-1',
            'notes' => 'Sensitive denied payment',
        ]);

        $response = $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-shared-route-no-leak'))
            ->getJson('/api/v1/reservations/'.$reservationId);

        $response->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        $content = (string) $response->getContent();
        self::assertStringNotContainsString('branch-denied-customer@example.test', $content);
        self::assertStringNotContainsString('Branch Denied Customer', $content);
        self::assertStringNotContainsString('DENIED-BRANCH-TX-1', $content);
        self::assertStringNotContainsString('DENIED-BRANCH-VOUCHER', $content);
        self::assertStringNotContainsString('"orders"', $content);
        self::assertStringNotContainsString('"payments"', $content);
        self::assertStringNotContainsString('"applied_voucher"', $content);
        self::assertStringNotContainsString('"user"', $content);
    }

    private function assignStaffBranch(int $staffId, int $branchId, bool $primary = true): void
    {
        DB::table('staff_branch_assignments')->insert([
            'user_id' => $staffId,
            'branch_id' => $branchId,
            'is_primary' => $primary ? 1 : 0,
            'assigned_at' => $this->nowUtc(),
            'revoked_at' => null,
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);
    }
}
