<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffCoreReadModelsHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
    }

    public function test_staff_can_show_reservation_detail_with_orders_and_payments(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-reservation-show');
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'full_name' => 'Reservation Detail Guest',
            'phone' => '0901234000',
            'email' => 'detail.guest@example.test',
        ]);
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'RD-01', 'zone' => 'Main']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'reservation_code' => 'RSV-DETAIL-001',
            'status' => 'Reserved',
            'deposit_required_amount' => '120000.00',
            'deposit_paid_amount' => '120000.00',
            'deposit_status' => 'Paid',
            'final_bill_amount' => '250000.00',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $itemId = $this->createMenuItem([
            'name' => 'Grilled Salmon',
            'code' => 'SALMON-01',
        ]);
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '250000.00',
            'currency' => 'VND',
            'effective_from' => now('UTC')->subDay(),
        ]);

        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'unit_price' => '250000.00',
            'currency' => 'VND',
            'line_total' => '250000.00',
            'status' => 'InProgress',
            'notes' => 'No onion',
        ]);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '120000.00',
            'currency' => 'VND',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/reservations/'.$reservationId);

        $response->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.user.full_name', 'Reservation Detail Guest')
            ->assertJsonPath('data.tables.0.table_id', $tableId)
            ->assertJsonPath('data.orders.0.order_id', $orderId)
            ->assertJsonPath('data.orders.0.items.0.item.name', 'Grilled Salmon')
            ->assertJsonPath('data.payments.0.payment_type', 'Deposit')
            ->assertJsonPath('data.deposit_summary.status', 'Paid');
    }

    public function test_staff_reservation_detail_falls_back_to_guest_snapshot_when_user_is_missing(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-reservation-show-guest');
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'RD-GUEST-01', 'zone' => 'Main']);
        $reservationId = $this->createReservation([
            'user_id' => null,
            'guest_name' => 'Reservation Guest Snapshot',
            'guest_phone' => '0904567000',
            'guest_email' => 'reservation.snapshot@example.test',
            'reservation_code' => 'RSV-DETAIL-GUEST-001',
            'status' => 'Confirmed',
            'source' => 'Offline',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/reservations/'.$reservationId);

        $response->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.user.user_id', null)
            ->assertJsonPath('data.user.full_name', 'Reservation Guest Snapshot')
            ->assertJsonPath('data.user.phone', '0904567000')
            ->assertJsonPath('data.user.email', 'reservation.snapshot@example.test')
            ->assertJsonPath('data.guest.full_name', 'Reservation Guest Snapshot')
            ->assertJsonPath('data.tables.0.table_id', $tableId);
    }

    public function test_staff_can_list_menu_items_for_ordering(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-menu-items');
        $serviceTime = now('UTC')->addHours(2);
        $categoryId = $this->ensureMenuCategory('Staff Ordering');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'Pho Tai Staff Filter',
            'code' => 'PHO-STAFF-01',
            'is_preorder_enabled' => 1,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '95000.00',
            'currency' => 'VND',
            'effective_from' => $serviceTime->copy()->subDay(),
        ]);

        $response = $this->withHeaders($headers)->getJson(
            '/api/v1/staff/menu/items?service_time='.urlencode($serviceTime->toIso8601String()).'&q=PHO-STAFF-01'
        );

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.item_id', $itemId)
            ->assertJsonPath('data.0.name', 'Pho Tai Staff Filter')
            ->assertJsonPath('data.0.price.amount', '95000.00');
    }

    public function test_staff_can_list_active_branches_for_context_switching(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-branches');

        $activeBranchId = $this->createBranch([
            'branch_code' => 'ANNEX-A',
            'branch_name' => 'Annex A',
            'is_active' => 1,
            'is_default' => 0,
        ]);
        $this->createBranch([
            'branch_code' => 'CLOSED-B',
            'branch_name' => 'Closed B',
            'is_active' => 0,
            'is_default' => 0,
        ]);
        config()->set('staff_capabilities.role_branch_scopes.Staff', ['default', (string) $activeBranchId]);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/branches');

        $response->assertOk()
            ->assertJsonPath('meta.action', 'staff_branch_context')
            ->assertJsonPath('meta.branch_access.default_branch_id', 1)
            ->assertJsonPath('meta.branch_access.current_branch_id', 1)
            ->assertJsonPath('meta.branch_access.has_default_branch_access', true)
            ->assertJsonPath('meta.branch_access.has_multi_branch_access', true)
            ->assertJsonPath('meta.branch_access.branch_selector_enabled', true)
            ->assertJsonPath('meta.branch_access.access_source', 'role_branch_scopes')
            ->assertJsonPath('meta.branch_access.branches_uri', '/api/v1/staff/branches')
            ->assertJsonPath('meta.has_multi_branch_access', true);

        $branchIds = collect($response->json('data'))->pluck('branch_id')->all();
        $accessibleBranchIds = (array) $response->json('meta.branch_access.accessible_branch_ids');
        self::assertContains(1, $branchIds);
        self::assertContains($activeBranchId, $branchIds);
        self::assertContains(1, $accessibleBranchIds);
        self::assertContains($activeBranchId, $accessibleBranchIds);
        self::assertCount(2, $branchIds);
    }

    public function test_staff_branch_context_requires_reservation_manage_capability(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);

        config()->set('staff_capabilities.role_capabilities', [
            'Admin' => ['*'],
            'Staff' => [],
        ]);

        $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-branches-capability'))
            ->getJson('/api/v1/staff/branches')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'reservation.manage');
    }

    public function test_staff_reservation_detail_returns_not_found_for_inaccessible_branch_scope(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-reservation-scope-deny');
        $accessibleBranchId = $this->createBranch([
            'branch_code' => 'RSCOPEA',
            'branch_name' => 'Reservation Scope A',
            'is_active' => 1,
            'is_default' => 0,
        ]);
        $inaccessibleBranchId = $this->createBranch([
            'branch_code' => 'RSCOPEB',
            'branch_name' => 'Reservation Scope B',
            'is_active' => 1,
            'is_default' => 0,
        ]);
        config()->set('staff_capabilities.role_branch_scopes.Staff', ['default', (string) $accessibleBranchId]);

        $reservationId = $this->createReservation([
            'branch_id' => $inaccessibleBranchId,
            'status' => 'Confirmed',
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/reservations/'.$reservationId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('message', 'Reservation not found.');
    }
}
