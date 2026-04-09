<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Enums\PaymentSessionScope;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationOrder;
use App\Services\FeatureFlagService;
use App\Services\LoyaltyPointsService;
use App\Services\PaymentIntegration\PaymentProviderRolloutConfig;
use App\Services\ReservationFinancialSyncService;
use App\Services\Staff\SettlementAmountCalculator;
use App\Services\Staff\StaffOrderReadService;
use App\Support\PaymentSummary;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class CustomerReservationOrderBillService
{
    private const ACTIVE_ORDER_RESERVATION_RELATIONS = [
        'tables',
        'payments.refundOfPayment',
    ];

    private const BILL_PREVIEW_RESERVATION_RELATIONS = [
        'tables',
        'payments.refundOfPayment',
        'appliedUserVoucher.voucher',
    ];

    public function __construct(
        private readonly StaffOrderReadService $staffOrderReadService,
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
        private readonly SettlementAmountCalculator $settlementAmountCalculator,
        private readonly LoyaltyPointsService $loyaltyPointsService,
        private readonly PaymentProviderRolloutConfig $paymentProviderRolloutConfig,
        private readonly FeatureFlagService $featureFlags,
    ) {}

    /**
     * @return array{reservation:Reservation,active_order:?ReservationOrder}
     */
    public function showOwnedActiveOrder(int $reservationId, int $customerUserId): array
    {
        return $this->buildAccessibleActiveOrderView(
            $this->loadOwnedReservation($reservationId, $customerUserId, self::ACTIVE_ORDER_RESERVATION_RELATIONS),
        );
    }

    /**
     * @return array{reservation:Reservation,active_order:?ReservationOrder}
     */
    public function showAccessibleActiveOrder(Reservation $reservation): array
    {
        return $this->buildAccessibleActiveOrderView(
            $this->preloadAccessibleReservation($reservation, self::ACTIVE_ORDER_RESERVATION_RELATIONS),
        );
    }

    /**
     * @return array{reservation:Reservation,active_order:?ReservationOrder,bill_preview:array<string,mixed>}
     */
    public function previewOwnedBill(int $reservationId, int $customerUserId): array
    {
        return $this->buildAccessibleBillView(
            $this->loadOwnedReservation($reservationId, $customerUserId, self::BILL_PREVIEW_RESERVATION_RELATIONS),
        );
    }

    /**
     * @return array{reservation:Reservation,active_order:?ReservationOrder,bill_preview:array<string,mixed>}
     */
    public function previewAccessibleBill(Reservation $reservation): array
    {
        return $this->buildAccessibleBillView(
            $this->preloadAccessibleReservation($reservation, self::BILL_PREVIEW_RESERVATION_RELATIONS),
        );
    }

    /**
     * @param list<string> $relations
     */
    private function loadOwnedReservation(int $reservationId, int $customerUserId, array $relations): Reservation
    {
        $reservation = Reservation::query()
            ->with($relations)
            ->whereKey($reservationId)
            ->where('user_id', $customerUserId)
            ->first();

        if (! $reservation instanceof Reservation) {
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        return $reservation;
    }

    /**
     * @return array{reservation:Reservation,active_order:?ReservationOrder,bill_preview:array<string,mixed>}
     */
    private function buildAccessibleBillView(Reservation $reservation): array
    {
        $computed = $this->reservationFinancialSyncService->computeReservationBillSnapshot(
            reservationId: (int) $reservation->reservation_id,
            discountAmount: round(max(0.0, (float) ($reservation->discount_amount ?? 0.0)), 2),
            lockOrders: false,
        );
        $activeOrder = $this->staffOrderReadService->findActiveOrderForReservationModel($reservation, $computed);

        return [
            'reservation' => $reservation,
            'active_order' => $activeOrder,
            'bill_preview' => $this->buildBillPreview($reservation, $activeOrder, $computed),
        ];
    }

    /**
     * @return array{reservation:Reservation,active_order:?ReservationOrder}
     */
    private function buildAccessibleActiveOrderView(Reservation $reservation): array
    {
        return [
            'reservation' => $reservation,
            'active_order' => $this->staffOrderReadService->findActiveOrderForReservationModel($reservation),
        ];
    }

    /**
     * @param list<string> $relations
     */
    private function preloadAccessibleReservation(Reservation $reservation, array $relations): Reservation
    {
        if (! $reservation->exists) {
            $this->seedInMemoryReservationRelations($reservation, $relations);

            return $reservation;
        }

        $reservation->load($relations);

        return $reservation;
    }

    /**
     * @param list<string> $relations
     */
    private function seedInMemoryReservationRelations(Reservation $reservation, array $relations): void
    {
        if (in_array('tables', $relations, true) && ! $reservation->relationLoaded('tables')) {
            $reservation->setRelation('tables', collect());
        }

        if ($this->requiresPaymentsRelation($relations) && ! $reservation->relationLoaded('payments')) {
            $reservation->setRelation('payments', collect());
        }

        if ($this->requiresAppliedVoucherRelation($relations) && ! $reservation->relationLoaded('appliedUserVoucher')) {
            $reservation->setRelation('appliedUserVoucher', null);
        }
    }

    /**
     * @param list<string> $relations
     */
    private function requiresPaymentsRelation(array $relations): bool
    {
        foreach ($relations as $relation) {
            if ($relation === 'payments' || str_starts_with($relation, 'payments.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $relations
     */
    private function requiresAppliedVoucherRelation(array $relations): bool
    {
        foreach ($relations as $relation) {
            if ($relation === 'appliedUserVoucher' || str_starts_with($relation, 'appliedUserVoucher.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildBillPreview(Reservation $reservation, ?ReservationOrder $activeOrder, array $computed): array
    {
        /** @var Collection<int,mixed> $payments */
        $payments = $reservation->payments;
        $paymentSummary = PaymentSummary::fromPayments($payments);
        $currencyMeta = PaymentSummary::summarizeCurrencies($payments, (string) ($computed['currency'] ?? $reservation->bill_currency ?? 'VND'));

        $snapshotMode = $reservation->billed_at !== null && $reservation->final_bill_amount !== null ? 'locked' : 'provisional';
        $computedSubtotal = round(max(0.0, (float) ($computed['subtotal'] ?? 0.0)), 2);
        $discountAmount = round(max(0.0, (float) ($computed['discount'] ?? $reservation->discount_amount ?? 0.0)), 2);
        $computedTotalDue = round(max(0.0, (float) ($computed['total_due'] ?? 0.0)), 2);
        $lockedTotalDue = $reservation->final_bill_amount !== null
            ? round(max(0.0, (float) $reservation->final_bill_amount), 2)
            : null;
        $effectiveTotalDue = $snapshotMode === 'locked' ? (float) $lockedTotalDue : $computedTotalDue;
        $settlement = $this->settlementAmountCalculator->buildSettlementAmounts($payments, $effectiveTotalDue);
        $settledAmount = round((float) ($settlement['settled_amount'] ?? 0.0), 2);
        $outstandingAmount = round((float) ($settlement['remaining_due'] ?? max(0.0, $effectiveTotalDue - $settledAmount)), 2);
        $paymentStatus = $settledAmount + 0.0001 >= $effectiveTotalDue
            ? 'Success'
            : ($settledAmount > 0.0001 ? 'Partial' : 'Failed');

        $reservationStatus = (string) ($reservation->status?->value ?? $reservation->status ?? '');
        $isActionableReservation = in_array($reservationStatus, ReservationStatus::activeDbValues(), true);
        $hasMixedCurrencies = (bool) ($currencyMeta['has_mixed_currencies'] ?? false);
        $selfPaymentRollout = $this->paymentProviderRolloutConfig->customerSelfPayStatus(PaymentSessionScope::Bill);
        $selfPaymentFeature = $this->featureFlags->resolve(
            'customer.bill_self_payment',
            $reservation->branch_id !== null ? (int) $reservation->branch_id : null,
        );
        $selfPaymentSupported = (bool) ($selfPaymentRollout['ok'] ?? false)
            && (bool) ($selfPaymentFeature['enabled'] ?? false);
        $selfPaymentAvailable = $selfPaymentSupported
            && $snapshotMode === 'locked'
            && $isActionableReservation
            && ! $hasMixedCurrencies
            && $outstandingAmount > 0.0001;
        $selfPaymentDisabledReason = (bool) ($selfPaymentRollout['ok'] ?? false)
            ? ((bool) ($selfPaymentFeature['enabled'] ?? false) ? null : (string) ($selfPaymentFeature['message'] ?? ''))
            : (string) ($selfPaymentRollout['message'] ?? '');

        return [
            'snapshot_mode' => $snapshotMode,
            'active_order_present' => $activeOrder instanceof ReservationOrder,
            'billed_at' => $reservation->billed_at?->utc()->toIso8601String(),
            'computed_subtotal_amount' => number_format($computedSubtotal, 2, '.', ''),
            'discount_amount' => number_format($discountAmount, 2, '.', ''),
            'computed_total_due_amount' => number_format($computedTotalDue, 2, '.', ''),
            'locked_total_due_amount' => $lockedTotalDue !== null ? number_format($lockedTotalDue, 2, '.', '') : null,
            'total_due_amount' => number_format($effectiveTotalDue, 2, '.', ''),
            'deposit_applied_amount' => number_format((float) ($settlement['deposit_applied_amount'] ?? 0.0), 2, '.', ''),
            'deposit_net_amount' => number_format((float) ($settlement['deposit_net_amount'] ?? 0.0), 2, '.', ''),
            'final_paid_amount' => number_format((float) ($settlement['final_paid_amount'] ?? 0.0), 2, '.', ''),
            'settled_amount' => number_format($settledAmount, 2, '.', ''),
            'outstanding_amount' => number_format($outstandingAmount, 2, '.', ''),
            'currency' => (string) ($currencyMeta['currency'] ?? $computed['currency'] ?? $reservation->bill_currency ?? 'VND'),
            'payment_status' => $paymentStatus,
            'has_mixed_payment_currencies' => $hasMixedCurrencies,
            'payment_summary' => [
                'deposit_captured' => number_format((float) ($paymentSummary['deposit_captured_amount'] ?? 0.0), 2, '.', ''),
                'deposit_refunded' => number_format((float) ($paymentSummary['deposit_refunded_amount'] ?? 0.0), 2, '.', ''),
                'deposit_net' => number_format((float) ($paymentSummary['deposit_net_amount'] ?? 0.0), 2, '.', ''),
                'final_captured' => number_format((float) ($paymentSummary['final_captured_amount'] ?? 0.0), 2, '.', ''),
                'final_refunded' => number_format((float) ($paymentSummary['final_refunded_amount'] ?? 0.0), 2, '.', ''),
                'final_net' => number_format((float) ($paymentSummary['final_net_amount'] ?? 0.0), 2, '.', ''),
                'captured_total' => number_format((float) ($paymentSummary['captured_amount'] ?? 0.0), 2, '.', ''),
                'refunded_total' => number_format((float) ($paymentSummary['refunded_amount'] ?? 0.0), 2, '.', ''),
                'net_paid_total' => number_format((float) ($paymentSummary['net_paid_amount'] ?? 0.0), 2, '.', ''),
            ],
            'loyalty' => $this->loyaltyPointsService->getReservationLoyaltyPreview($reservation, $payments, $computed),
            'applied_voucher' => $reservation->appliedUserVoucher ? [
                'user_voucher_id' => (int) $reservation->appliedUserVoucher->user_voucher_id,
                'voucher_id' => (int) $reservation->appliedUserVoucher->voucher_id,
                'voucher_code' => $reservation->appliedUserVoucher->voucher?->code,
                'description' => $reservation->appliedUserVoucher->voucher?->description,
                'discount_type' => $reservation->appliedUserVoucher->voucher?->discount_type?->value ?? (string) ($reservation->appliedUserVoucher->voucher?->discount_type ?? ''),
                'discount_value' => $reservation->appliedUserVoucher->voucher?->discount_value !== null
                    ? number_format((float) $reservation->appliedUserVoucher->voucher->discount_value, 2, '.', '')
                    : null,
                'used_amount' => $reservation->appliedUserVoucher->used_amount !== null
                    ? number_format((float) $reservation->appliedUserVoucher->used_amount, 2, '.', '')
                    : null,
            ] : null,
            'self_payment' => [
                'supported' => $selfPaymentSupported,
                'available' => $selfPaymentAvailable,
                'provider_code' => (string) ($selfPaymentRollout['provider_code'] ?? ''),
                'disabled_reason' => $selfPaymentSupported ? null : $selfPaymentDisabledReason,
                'requires_locked_bill' => $snapshotMode !== 'locked',
                'awaiting_staff_finalization' => $snapshotMode === 'locked'
                    && $outstandingAmount <= 0.0001
                    && (float) ($paymentSummary['final_net_amount'] ?? 0.0) > 0.0001,
                'next_step' => $this->resolveNextStep(
                    selfPaymentSupported: $selfPaymentSupported,
                    isActionableReservation: $isActionableReservation,
                    snapshotMode: $snapshotMode,
                    hasMixedCurrencies: $hasMixedCurrencies,
                    outstandingAmount: $outstandingAmount,
                    finalNetAmount: (float) ($paymentSummary['final_net_amount'] ?? 0.0),
                ),
            ],
        ];
    }

    private function resolveNextStep(
        bool $selfPaymentSupported,
        bool $isActionableReservation,
        string $snapshotMode,
        bool $hasMixedCurrencies,
        float $outstandingAmount,
        float $finalNetAmount,
    ): string {
        if (! $isActionableReservation) {
            return 'reservation_not_actionable';
        }

        if ($hasMixedCurrencies) {
            return 'currency_reconciliation_required';
        }

        if (! $selfPaymentSupported && $outstandingAmount > 0.0001) {
            return 'staff_settlement_only';
        }

        if ($snapshotMode !== 'locked') {
            return 'awaiting_staff_bill_lock';
        }

        if ($outstandingAmount <= 0.0001) {
            return $finalNetAmount > 0.0001
                ? 'payment_recorded_awaiting_staff_finalization'
                : 'already_settled';
        }

        return 'awaiting_customer_payment';
    }
}
