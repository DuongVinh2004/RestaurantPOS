<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use App\Enums\ReservationStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class ReservationStatusHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();
        config()->set('booking.require_redis_for_booking_api', false);
    }

    public function test_staff_can_cancel_confirmed_reservation_with_row_version(): void
    {
        $staffId = $this->createUser(['role_name' => 'Admin']);
        $reservationId = $this->createReservation(['status' => ReservationStatus::Confirmed->value, 'row_version' => 3]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4));
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
        ]);
        $itemId = $this->createMenuItem();
        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'status' => 'Ordered',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey('reservation-status-cancel', $this->staffAuthHeaders($staffId)))
            ->patchJson("/api/v1/reservations/{$reservationId}/status", [
                'status' => ReservationStatus::Cancelled->value,
                'row_version' => 3,
                'cancel_reason' => 'Customer request',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Cancelled->value)
            ->assertJsonPath('data.cancel_reason', 'Customer request');

        self::assertSame('Cancelled', DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('Cancelled', DB::table('reservation_orders')->where('order_id', $orderId)->value('status'));
    }

    public function test_status_update_rejects_stale_row_version(): void
    {
        $staffId = $this->createUser(['role_name' => 'Admin']);
        $reservationId = $this->createReservation(['status' => ReservationStatus::Confirmed->value, 'row_version' => 2]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4));

        $response = $this->withHeaders($this->withIdempotencyKey('reservation-status-stale', $this->staffAuthHeaders($staffId)))
            ->patchJson("/api/v1/reservations/{$reservationId}/status", [
                'status' => ReservationStatus::Cancelled->value,
                'row_version' => 1,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonPath('category_code', 'stale_write')
            ->assertJsonPath('details.errors.row_version.0', 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.');
    }

    public function test_status_update_requires_row_version(): void
    {
        $staffId = $this->createUser(['role_name' => 'Admin']);
        $reservationId = $this->createReservation(['status' => ReservationStatus::Confirmed->value, 'row_version' => 2]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4));

        $response = $this->withHeaders($this->withIdempotencyKey('reservation-status-missing-row-version', $this->staffAuthHeaders($staffId)))
            ->patchJson("/api/v1/reservations/{$reservationId}/status", [
                'status' => ReservationStatus::Cancelled->value,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('details.errors.row_version.0', fn ($value) => is_string($value) && $value !== '');

        self::assertSame(
            ReservationStatus::Confirmed->value,
            DB::table('reservations')->where('reservation_id', $reservationId)->value('status')
        );
    }

    public function test_staff_cannot_update_reservation_status_outside_assigned_branch(): void
    {
        $allowedBranchId = $this->createBranch([
            'branch_code' => 'STATUS-A',
            'branch_name' => 'Status Allowed Branch',
        ]);
        $deniedBranchId = $this->createBranch([
            'branch_code' => 'STATUS-B',
            'branch_name' => 'Status Denied Branch',
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->assignStaffBranch($staffId, $allowedBranchId);

        $reservationId = $this->createReservation([
            'branch_id' => $deniedBranchId,
            'status' => ReservationStatus::Confirmed->value,
            'row_version' => 5,
        ]);
        $this->attachReservationTable(
            $reservationId,
            $this->createRestaurantTableWithSeats(4, ['branch_id' => $deniedBranchId])
        );
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey('reservation-status-branch-denied', $this->staffAuthHeaders($staffId)))
            ->patchJson("/api/v1/reservations/{$reservationId}/status", [
                'status' => ReservationStatus::Cancelled->value,
                'row_version' => 5,
                'cancel_reason' => 'Wrong branch attempt',
            ]);

        $response->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        self::assertSame(
            ReservationStatus::Confirmed->value,
            DB::table('reservations')->where('reservation_id', $reservationId)->value('status')
        );
        self::assertSame('Active', DB::table('reservation_orders')->where('order_id', $orderId)->value('status'));
    }

    public function test_generic_status_endpoint_rejects_completed_transition(): void
    {
        $staffId = $this->createUser(['role_name' => 'Admin']);
        $reservationId = $this->createReservation(['status' => ReservationStatus::Confirmed->value]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4));

        $response = $this->withHeaders($this->withIdempotencyKey('reservation-status-completed', $this->staffAuthHeaders($staffId)))
            ->patchJson("/api/v1/reservations/{$reservationId}/status", [
                'status' => ReservationStatus::Completed->value,
                'row_version' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('details.errors.status.0', fn ($value) => is_string($value) && $value !== '');
    }

    public function test_checked_in_reservation_cancel_requires_force_true(): void
    {
        $staffId = $this->createUser(['role_name' => 'Admin']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => ReservationStatus::checkedInDbValue(),
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(10),
            'row_version' => 2,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($this->withIdempotencyKey('reservation-status-checkedin-cancel-no-force', $this->staffAuthHeaders($staffId)))
            ->patchJson("/api/v1/reservations/{$reservationId}/status", [
                'status' => ReservationStatus::Cancelled->value,
                'row_version' => 2,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('details.errors.status.0', fn ($value) => is_string($value) && $value !== '');
    }

    public function test_checked_in_reservation_force_cancel_releases_tables_when_unpaid(): void
    {
        $staffId = $this->createUser(['role_name' => 'Admin']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => ReservationStatus::checkedInDbValue(),
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(10),
            'row_version' => 2,
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'status' => 'Ordered',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey('reservation-status-checkedin-cancel-force', $this->staffAuthHeaders($staffId)))
            ->patchJson("/api/v1/reservations/{$reservationId}/status", [
                'status' => ReservationStatus::Cancelled->value,
                'row_version' => 2,
                'force' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Cancelled->value);

        self::assertSame('Available', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
        self::assertSame('Cancelled', DB::table('reservation_orders')->where('order_id', $orderId)->value('status'));
    }

    public function test_confirmed_reservation_cancel_rejects_when_final_payments_exist(): void
    {
        $staffId = $this->createUser(['role_name' => 'Admin']);
        $reservationId = $this->createReservation(['status' => ReservationStatus::Confirmed->value, 'row_version' => 1]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4));
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '100000',
            'payment_provider' => 'Cash',
            'payment_method' => 'Cash',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey('reservation-status-cancel-paid', $this->staffAuthHeaders($staffId)))
            ->patchJson("/api/v1/reservations/{$reservationId}/status", [
                'status' => ReservationStatus::Cancelled->value,
                'row_version' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('details.errors.status.0', fn ($value) => is_string($value) && $value !== '');
    }

    public function test_staff_can_mark_confirmed_reservation_as_no_show_and_cancel_active_orders(): void
    {
        $staffId = $this->createUser(['role_name' => 'Admin']);
        $reservationId = $this->createReservation(['status' => ReservationStatus::Confirmed->value, 'row_version' => 4]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4));
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'status' => 'Ordered',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey('reservation-status-noshow', $this->staffAuthHeaders($staffId)))
            ->patchJson("/api/v1/reservations/{$reservationId}/status", [
                'status' => ReservationStatus::NoShow->value,
                'row_version' => 4,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::NoShow->value);

        self::assertSame('NoShow', DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertNotNull(DB::table('reservations')->where('reservation_id', $reservationId)->value('no_show_at'));
        self::assertSame('Cancelled', DB::table('reservation_orders')->where('order_id', $orderId)->value('status'));
    }

    private function assignStaffBranch(int $staffId, int $branchId): void
    {
        DB::table('staff_branch_assignments')->insert([
            'user_id' => $staffId,
            'branch_id' => $branchId,
            'is_primary' => 1,
            'assigned_at' => $this->nowUtc(),
            'revoked_at' => null,
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);
    }
}
