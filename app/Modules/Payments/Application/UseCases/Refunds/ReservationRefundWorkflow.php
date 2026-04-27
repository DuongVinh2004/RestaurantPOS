<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\UseCases\Refunds;

use App\Modules\Cashiering\Application\Workflows\OrderSettlementWorkflow;
use App\Support\Auth\StaffActorGuard;

class ReservationRefundWorkflow
{
    public function __construct(
        private readonly OrderSettlementWorkflow $settlementWorkflow,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function previewRefund(
        int $reservationId,
        string $refundScope = 'all',
        ?float $refundAmount = null,
        string $currency = 'VND',
        ?bool $cancelAfterPayment = null,
        ?int $staffUserId = null,
    ): array {
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        return $this->settlementWorkflow->previewRefund(
            reservationId: $reservationId,
            refundScope: $refundScope,
            refundAmount: $refundAmount,
            currency: $currency,
            cancelAfterPayment: $cancelAfterPayment,
            staffUserId: $staffUserId,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function refundReservation(
        int $reservationId,
        string $paymentMethod,
        string $refundScope = 'all',
        ?float $refundAmount = null,
        string $currency = 'VND',
        string $transactionCode = '',
        string $paymentProvider = '',
        string $notes = '',
        ?string $reason = null,
        ?int $expectedRowVersion = null,
        ?int $staffUserId = null,
        string $idempotencyKey = '',
    ): array {
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        return $this->settlementWorkflow->refundReservation(
            reservationId: $reservationId,
            paymentMethod: $paymentMethod,
            refundScope: $refundScope,
            refundAmount: $refundAmount,
            currency: $currency,
            transactionCode: $transactionCode,
            paymentProvider: $paymentProvider,
            notes: $notes,
            reason: $reason,
            expectedRowVersion: $expectedRowVersion,
            staffUserId: $staffUserId,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function refundAndCancelReservation(
        int $reservationId,
        string $paymentMethod,
        string $refundScope = 'all',
        ?float $refundAmount = null,
        string $currency = 'VND',
        string $transactionCode = '',
        string $paymentProvider = '',
        string $notes = '',
        ?string $reason = null,
        ?string $cancelReason = null,
        ?int $expectedRowVersion = null,
        ?int $staffUserId = null,
        string $idempotencyKey = '',
    ): array {
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        return $this->settlementWorkflow->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: $paymentMethod,
            refundScope: $refundScope,
            refundAmount: $refundAmount,
            currency: $currency,
            transactionCode: $transactionCode,
            paymentProvider: $paymentProvider,
            notes: $notes,
            reason: $reason,
            cancelReason: $cancelReason,
            expectedRowVersion: $expectedRowVersion,
            staffUserId: $staffUserId,
            idempotencyKey: $idempotencyKey,
        );
    }
}
