<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\Payments\Application\UseCases\Capture\StaffReservationDepositService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CustomerReservationDepositService
{
    public function __construct(
        private readonly StaffReservationDepositService $staffReservationDepositService,
        private readonly ReservationSessionAccessWorkflow $customerSessionAccessService,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function previewAccessibleReservationDeposit(int $reservationId, ?int $userId, ?string $sessionId, string $fallbackCurrency = 'VND'): array
    {
        if ($userId !== null) {
            return $this->previewOwnedReservationDeposit($reservationId, $userId, $fallbackCurrency);
        }

        $resolvedSessionId = trim((string) $sessionId);
        $reservation = Reservation::query()->where('reservation_id', $reservationId)->first();
        if (! $reservation instanceof Reservation || $resolvedSessionId === '' || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $resolvedSessionId)) {
            throw (new ModelNotFoundException())->setModel(Reservation::class, [$reservationId]);
        }

        return $this->staffReservationDepositService->previewDeposit($reservationId, $fallbackCurrency);
    }

    /**
     * @return array<string,mixed>
     */
    public function previewOwnedReservationDeposit(int $reservationId, int $userId, string $fallbackCurrency = 'VND'): array
    {
        Reservation::query()
            ->where('reservation_id', $reservationId)
            ->where('user_id', $userId)
            ->firstOrFail();

        return $this->staffReservationDepositService->previewDeposit($reservationId, $fallbackCurrency);
    }
}
