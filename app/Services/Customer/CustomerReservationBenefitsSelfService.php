<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Models\Reservation;
use App\Services\LoyaltyPointsService;
use App\Services\Staff\StaffReservationVoucherService;

class CustomerReservationBenefitsSelfService
{
    public function __construct(
        private readonly CustomerBenefitsService $customerBenefitsService,
        private readonly StaffReservationVoucherService $voucherService,
        private readonly LoyaltyPointsService $loyaltyPointsService,
    ) {}

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function applyVoucherForOwnedReservation(int $reservationId, int $userId, array $payload): array
    {
        $this->assertOwnedReservation($reservationId, $userId);

        $result = $this->voucherService->applyVoucher(
            reservationId: $reservationId,
            userVoucherId: isset($payload['user_voucher_id']) ? (int) $payload['user_voucher_id'] : null,
            voucherCode: isset($payload['voucher_code']) ? (string) $payload['voucher_code'] : null,
            expectedRowVersion: isset($payload['row_version']) ? (int) $payload['row_version'] : null,
            staffUserId: $userId,
        );

        return [
            'preview' => $this->customerBenefitsService->previewOwnedReservationBenefits($reservationId, $userId),
            'voucher' => $result['voucher'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function removeVoucherForOwnedReservation(int $reservationId, int $userId, array $payload): array
    {
        $this->assertOwnedReservation($reservationId, $userId);

        $result = $this->voucherService->removeVoucher(
            reservationId: $reservationId,
            expectedRowVersion: isset($payload['row_version']) ? (int) $payload['row_version'] : null,
            staffUserId: $userId,
        );

        return [
            'preview' => $this->customerBenefitsService->previewOwnedReservationBenefits($reservationId, $userId),
            'removed_voucher' => $result['removed_voucher'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $payload
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
     * @param array<string,mixed> $payload
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
