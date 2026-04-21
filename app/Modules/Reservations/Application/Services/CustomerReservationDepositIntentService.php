<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\Reservations\Application\Services\ReservationDepositSelfServiceStateService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Payments\Application\UseCases\Capture\StaffReservationDepositService;
use App\Support\AuditEvent;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerReservationDepositIntentService
{
    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly StaffReservationDepositService $staffReservationDepositService,
        private readonly ReservationDepositSelfServiceStateService $stateService,
        private readonly ReservationSessionAccessWorkflow $customerSessionAccessService,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function acknowledgeDepositRequirementForOwnedReservation(int $reservationId, int $userId, ?int $expectedRowVersion = null): array
    {
        return $this->mutateAccessibleReservation(
            reservationId: $reservationId,
            userId: $userId,
            sessionId: null,
            expectedRowVersion: $expectedRowVersion,
            action: 'acknowledge',
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function acknowledgeDepositRequirementForAccessibleReservation(int $reservationId, ?int $userId, ?string $sessionId, ?int $expectedRowVersion = null): array
    {
        return $this->mutateAccessibleReservation(
            reservationId: $reservationId,
            userId: $userId,
            sessionId: $sessionId,
            expectedRowVersion: $expectedRowVersion,
            action: 'acknowledge',
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function submitDepositIntentForOwnedReservation(int $reservationId, int $userId, ?int $expectedRowVersion = null): array
    {
        return $this->mutateAccessibleReservation(
            reservationId: $reservationId,
            userId: $userId,
            sessionId: null,
            expectedRowVersion: $expectedRowVersion,
            action: 'submit_intent',
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function submitDepositIntentForAccessibleReservation(int $reservationId, ?int $userId, ?string $sessionId, ?int $expectedRowVersion = null): array
    {
        return $this->mutateAccessibleReservation(
            reservationId: $reservationId,
            userId: $userId,
            sessionId: $sessionId,
            expectedRowVersion: $expectedRowVersion,
            action: 'submit_intent',
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function revokeDepositIntentForOwnedReservation(int $reservationId, int $userId, ?int $expectedRowVersion = null): array
    {
        return $this->mutateAccessibleReservation(
            reservationId: $reservationId,
            userId: $userId,
            sessionId: null,
            expectedRowVersion: $expectedRowVersion,
            action: 'revoke_intent',
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function revokeDepositIntentForAccessibleReservation(int $reservationId, ?int $userId, ?string $sessionId, ?int $expectedRowVersion = null): array
    {
        return $this->mutateAccessibleReservation(
            reservationId: $reservationId,
            userId: $userId,
            sessionId: $sessionId,
            expectedRowVersion: $expectedRowVersion,
            action: 'revoke_intent',
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function mutateAccessibleReservation(int $reservationId, ?int $userId, ?string $sessionId, ?int $expectedRowVersion, string $action): array
    {
        return $this->locks->withReservationLock($reservationId, function () use ($reservationId, $userId, $sessionId, $expectedRowVersion, $action) {
            DB::transaction(function () use ($reservationId, $userId, $sessionId, $expectedRowVersion, $action): void {
                /** @var Reservation $reservation */
                $reservation = $this->findAccessibleReservationForUpdate($reservationId, $userId, $sessionId);

                $this->assertRowVersion($reservation, $expectedRowVersion);

                $payments = Payment::query()
                    ->with('refundOfPayment')
                    ->where('reservation_id', $reservationId)
                    ->orderBy('payment_id')
                    ->lockForUpdate()
                    ->get();

                $paymentSummary = PaymentSummary::fromPayments($payments);
                $beforeVersion = (int) ($reservation->row_version ?? 1);
                $wasDirty = false;
                $now = Carbon::now('UTC');

                switch ($action) {
                    case 'acknowledge':
                        $this->stateService->assertCanAcknowledge($reservation, $paymentSummary);
                        if ($reservation->deposit_requirement_acknowledged_at === null) {
                            $reservation->deposit_requirement_acknowledged_at = $now;
                            $wasDirty = true;
                        }
                        break;

                    case 'submit_intent':
                        $this->stateService->assertCanSubmitIntent($reservation, $paymentSummary);
                        $intentStatus = $this->stateService->resolveIntentStatus($reservation);
                        if ($intentStatus->value !== 'Submitted') {
                            $reservation->deposit_intent_status = 'Submitted';
                            $reservation->deposit_intent_submitted_at = $now;
                            $reservation->deposit_intent_revoked_at = null;
                            $wasDirty = true;
                        }
                        break;

                    case 'revoke_intent':
                        $this->stateService->assertCanRevokeIntent($reservation, $paymentSummary);
                        $intentStatus = $this->stateService->resolveIntentStatus($reservation);
                        if ($intentStatus->value !== 'Revoked') {
                            $reservation->deposit_intent_status = 'Revoked';
                            $reservation->deposit_intent_revoked_at = $now;
                            if ($reservation->deposit_intent_submitted_at === null) {
                                $reservation->deposit_intent_submitted_at = $now;
                            }
                            $wasDirty = true;
                        }
                        break;

                    default:
                        throw ValidationException::withMessages([
                            'action' => ['Unsupported deposit self-service action.'],
                        ]);
                }

                if ($wasDirty) {
                    $reservation->updated_by = $userId;
                    $reservation->save();
                }

                AuditEvent::info('customer.reservation.deposit_self_service_mutated', [
                    'reservation_id' => $reservationId,
                    'user_id' => $userId,
                    'access_scope' => $userId !== null ? 'owner' : 'session',
                    'customer_session_id' => $userId === null ? trim((string) $sessionId) : null,
                    'action' => $action,
                    'before_row_version' => $beforeVersion,
                    'new_row_version' => (int) ($reservation->row_version ?? $beforeVersion),
                    'changed' => $wasDirty,
                    'deposit_intent_status' => (string) ($reservation->deposit_intent_status?->value ?? $reservation->deposit_intent_status ?? 'None'),
                    'deposit_requirement_acknowledged_at' => $reservation->deposit_requirement_acknowledged_at?->utc()->toIso8601String(),
                ]);
            });

            return $this->staffReservationDepositService->previewDeposit($reservationId, 'VND');
        });
    }

    private function findAccessibleReservationForUpdate(int $reservationId, ?int $userId, ?string $sessionId): Reservation
    {
        if ($userId !== null) {
            /** @var Reservation|null $reservation */
            $reservation = Reservation::query()
                ->where('reservation_id', $reservationId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($reservation instanceof Reservation) {
                return $reservation;
            }

            throw (new ModelNotFoundException())->setModel(Reservation::class, [$reservationId]);
        }

        $resolvedSessionId = trim((string) $sessionId);
        /** @var Reservation|null $reservation */
        $reservation = Reservation::query()
            ->where('reservation_id', $reservationId)
            ->lockForUpdate()
            ->first();

        if (! $reservation instanceof Reservation || $resolvedSessionId === '' || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $resolvedSessionId)) {
            throw (new ModelNotFoundException())->setModel(Reservation::class, [$reservationId]);
        }

        return $reservation;
    }

    private function assertRowVersion(Reservation $reservation, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($reservation->row_version ?? 1) !== (int) $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
            ]);
        }
    }
}
