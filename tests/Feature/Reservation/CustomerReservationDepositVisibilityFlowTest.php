<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerReservationDepositVisibilityFlowTest extends TestCase
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

    public function test_authenticated_customer_can_preview_owned_reservation_deposit_snapshot(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'deposit_required_amount' => '100000',
            'deposit_paid_amount' => '0',
            'deposit_status' => 'Pending',
        ]);
        $this->attachReservationTable($reservationId);

        $user = User::query()->findOrFail($userId);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/reservations/{$reservationId}/deposit-preview");

        $response->assertOk()
            ->assertJsonPath('meta.action', 'customer_reservation_deposit_preview')
            ->assertJsonPath('meta.intent_supported', true)
            ->assertJsonPath('data.reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.deposit.status', 'Pending')
            ->assertJsonPath('data.deposit.required_amount', '100000')
            ->assertJsonPath('data.deposit.paid_amount', '0')
            ->assertJsonPath('data.deposit.outstanding_amount', '100000')
            ->assertJsonPath('data.deposit.deposit_required', true)
            ->assertJsonPath('data.deposit.payment_summary.deposit_net', '0')
            ->assertJsonPath('data.deposit.self_service.supported', true)
            ->assertJsonPath('data.deposit.self_service.requirement_acknowledged', false)
            ->assertJsonPath('data.deposit.self_service.intent_status', 'None')
            ->assertJsonPath('data.deposit.self_service.can_acknowledge', true)
            ->assertJsonPath('data.deposit.self_service.can_submit_intent', false);
    }

    public function test_session_linked_customer_can_preview_accessible_reservation_deposit_snapshot(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'deposit_required_amount' => '100000',
            'deposit_paid_amount' => '0',
            'deposit_status' => 'Pending',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $sessionId = 'sess-deposit-preview-link';
        $this->createTableHold([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'confirmed_reservation_id' => $reservationId,
        ], [$tableId]);

        $response = $this->getJson("/api/v1/reservations/{$reservationId}/deposit-preview?session_id={$sessionId}");

        $response->assertOk()
            ->assertJsonPath('meta.action', 'customer_reservation_deposit_preview')
            ->assertJsonPath('data.reservation.access_scope', 'session')
            ->assertJsonPath('data.reservation.user_id', null)
            ->assertJsonPath('data.deposit.status', 'Pending')
            ->assertJsonPath('data.deposit.outstanding_amount', '100000');
    }

    public function test_customer_session_cannot_preview_deposit_snapshot_for_unlinked_reservation(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'deposit_required_amount' => '100000',
            'deposit_paid_amount' => '0',
            'deposit_status' => 'Pending',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->getJson("/api/v1/reservations/{$reservationId}/deposit-preview?session_id=sess-other-preview");

        $response->assertNotFound();
    }

    public function test_customer_cannot_preview_another_users_reservation_deposit_snapshot(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $otherId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'deposit_required_amount' => '80000',
            'deposit_status' => 'Pending',
        ]);

        $other = User::query()->findOrFail($otherId);

        $response = $this->actingAs($other)
            ->getJson("/api/v1/reservations/{$reservationId}/deposit-preview");

        $response->assertNotFound();
    }

    public function test_customer_preview_returns_not_required_snapshot_when_reservation_has_no_deposit_requirement(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'deposit_required_amount' => '0',
            'deposit_paid_amount' => '0',
            'deposit_status' => 'NotRequired',
        ]);

        $user = User::query()->findOrFail($userId);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/reservations/{$reservationId}/deposit-preview");

        $response->assertOk()
            ->assertJsonPath('data.deposit.status', 'NotRequired')
            ->assertJsonPath('data.deposit.required_amount', '0')
            ->assertJsonPath('data.deposit.outstanding_amount', '0')
            ->assertJsonPath('data.deposit.deposit_required', false);
    }

    public function test_customer_preview_reflects_existing_deposit_payment_summary(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'deposit_required_amount' => '100000',
            'deposit_paid_amount' => '0',
            'deposit_status' => 'Pending',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '60000',
            'payment_provider' => 'POS',
            'payment_method' => 'Card',
        ]);
        $this->syncReservationDepositSnapshot($reservationId);

        $user = User::query()->findOrFail($userId);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/reservations/{$reservationId}/deposit-preview");

        $response->assertOk()
            ->assertJsonPath('data.deposit.status', 'Pending')
            ->assertJsonPath('data.deposit.required_amount', '100000')
            ->assertJsonPath('data.deposit.paid_amount', '60000')
            ->assertJsonPath('data.deposit.outstanding_amount', '40000')
            ->assertJsonPath('data.deposit.payment_summary.deposit_captured', '60000')
            ->assertJsonPath('data.deposit.payment_summary.deposit_net', '60000')
            ->assertJsonCount(1, 'data.deposit.payments')
            ->assertJsonPath('data.deposit.payments.0.payment_type', 'Deposit');
    }

    public function test_unauthenticated_customer_deposit_preview_is_rejected(): void
    {
        $reservationId = $this->createReservation([
            'deposit_required_amount' => '50000',
            'deposit_status' => 'Pending',
        ]);

        $response = $this->getJson("/api/v1/reservations/{$reservationId}/deposit-preview");

        $response->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_staff_cannot_use_customer_deposit_preview_endpoint(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'deposit_required_amount' => '50000',
            'deposit_status' => 'Pending',
        ]);
        config()->set('staff_auth.api_keys', ['customer-deposit-staff-key' => $staffId]);

        $response = $this->withHeaders([
            'X-Staff-Key' => 'customer-deposit-staff-key',
        ])->getJson("/api/v1/reservations/{$reservationId}/deposit-preview");

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }

    private function syncReservationDepositSnapshot(int $reservationId): void
    {
        $reservation = Reservation::query()->findOrFail($reservationId);
        $payments = Payment::query()
            ->with('refundOfPayment')
            ->where('reservation_id', $reservationId)
            ->orderBy('payment_id')
            ->get();

        app(ReservationFinancialSyncService::class)->syncDepositSnapshot(
            $reservation,
            PaymentSummary::fromPayments($payments),
            false,
        );
        $reservation->save();

        DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->update(['updated_at' => $this->nowUtc()]);
    }
}
