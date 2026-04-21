<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Workflows;

use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Reservations\Domain\Models\Reservation;

class CustomerReservationLoyaltyWorkflow
{
    public function __construct(
        private readonly LoyaltyPointsService $loyaltyPointsService,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function redeemPointsForOwnedReservation(int $reservationId, int $userId, array $payload): array
    {
        $this->assertOwnedReservation($reservationId, $userId);

        return $this->loyaltyPointsService->redeemReservationPoints(
            reservationId: $reservationId,
            points: (int) $payload['points'],
            reason: isset($payload['reason']) ? (string) $payload['reason'] : null,
            expectedRowVersion: isset($payload['row_version']) ? (int) $payload['row_version'] : null,
            staffUserId: $userId,
        );
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function releasePointsForOwnedReservation(int $reservationId, int $userId, array $payload): array
    {
        $this->assertOwnedReservation($reservationId, $userId);

        return $this->loyaltyPointsService->releaseReservationRedemption(
            reservationId: $reservationId,
            reason: isset($payload['reason']) ? (string) $payload['reason'] : null,
            expectedRowVersion: isset($payload['row_version']) ? (int) $payload['row_version'] : null,
            staffUserId: $userId,
        );
    }

    private function assertOwnedReservation(int $reservationId, int $userId): Reservation
    {
        /** @var Reservation $reservation */
        $reservation = Reservation::query()
            ->where('reservation_id', $reservationId)
            ->where('user_id', $userId)
            ->firstOrFail();

        return $reservation;
    }
}
