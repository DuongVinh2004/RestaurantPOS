<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\Workflows;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Promotions\Application\UseCases\Benefits\CustomerBenefitsService;
use App\Modules\Reservations\Domain\Models\Reservation;

class CustomerReservationPromotionWorkflow
{
    public function __construct(
        private readonly CustomerBenefitsService $customerBenefitsService,
        private readonly ReservationVoucherWorkflow $voucherService,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
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
            staffUserId: null,
            customerUserId: $userId,
        );

        return [
            'preview' => $this->customerBenefitsService->previewOwnedReservationBenefits($reservationId, $userId),
            'voucher' => $result['voucher'] ?? null,
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function removeVoucherForOwnedReservation(int $reservationId, int $userId, array $payload): array
    {
        $this->assertOwnedReservation($reservationId, $userId);

        $result = $this->voucherService->removeVoucher(
            reservationId: $reservationId,
            expectedRowVersion: isset($payload['row_version']) ? (int) $payload['row_version'] : null,
            staffUserId: null,
        );

        return [
            'preview' => $this->customerBenefitsService->previewOwnedReservationBenefits($reservationId, $userId),
            'removed_voucher' => $result['removed_voucher'] ?? null,
        ];
    }

    private function assertOwnedReservation(int $reservationId, int $userId): Reservation
    {
        $user = User::find($userId);

        /** @var Reservation $reservation */
        $reservation = Reservation::query()
            ->where('reservation_id', $reservationId)
            ->where('user_id', $userId)
            ->firstOrFail();

        return $reservation;
    }
}
