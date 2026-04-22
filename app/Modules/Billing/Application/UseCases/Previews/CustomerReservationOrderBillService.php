<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\UseCases\Previews;

use App\Enums\PaymentSessionScope;
use App\Enums\ReservationStatus;
use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Ordering\Application\Queries\StaffOrderReadService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\PaymentProviderRolloutConfig;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\FeatureFlags\Services\FeatureFlagService;
use App\SharedKernel\Money\Money;
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
     * @param  list<string>  $relations
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
            discountAmount: Money::toFloat($reservation->discount_amount ?? 0, true),
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
     * @param  list<string>  $relations
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
     * @param  list<string>  $relations
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
     * @param  list<string>  $relations
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
     * @param  list<string>  $relations
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
        $computedSubtotal = Money::toFloat($computed['subtotal'] ?? 0, true);
        $discountAmount = Money::toFloat($computed['discount'] ?? $reservation->discount_amount ?? 0, true);
        $computedTotalDue = Money::toFloat($computed['total_due'] ?? 0, true);
        $lockedTotalDue = $reservation->final_bill_amount !== null
            ? Money::toFloat($reservation->final_bill_amount, true)
            : null;
        $effectiveTotalDue = $snapshotMode === 'locked' ? (float) $lockedTotalDue : $computedTotalDue;
        $effectiveTotalDueMinor = Money::minorUnits($effectiveTotalDue, true);
        $settlement = $this->settlementAmountCalculator->buildSettlementAmounts($payments, $effectiveTotalDue);
        $settledMinor = Money::minorUnits($settlement['settled_amount'] ?? 0, true);
        $outstandingMinor = array_key_exists('remaining_due', $settlement)
            ? Money::minorUnits($settlement['remaining_due'], true)
            : max(0, $effectiveTotalDueMinor - $settledMinor);
        $settledAmount = Money::minorToFloat($settledMinor);
        $outstandingAmount = Money::minorToFloat($outstandingMinor);
        $paymentStatus = $settledMinor >= $effectiveTotalDueMinor
            ? 'Success'
            : ($settledMinor > 0 ? 'Partial' : 'Failed');

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
            && $outstandingMinor > 0;
        $selfPaymentDisabledReason = (bool) ($selfPaymentRollout['ok'] ?? false)
            ? ((bool) ($selfPaymentFeature['enabled'] ?? false) ? null : (string) ($selfPaymentFeature['message'] ?? ''))
            : (string) ($selfPaymentRollout['message'] ?? '');

        return [
            'snapshot_mode' => $snapshotMode,
            'active_order_present' => $activeOrder instanceof ReservationOrder,
            'billed_at' => $reservation->billed_at?->utc()->toIso8601String(),
            'computed_subtotal_amount' => Money::format($computedSubtotal, true),
            'discount_amount' => Money::format($discountAmount, true),
            'computed_total_due_amount' => Money::format($computedTotalDue, true),
            'locked_total_due_amount' => $lockedTotalDue !== null ? Money::format($lockedTotalDue, true) : null,
            'total_due_amount' => Money::format($effectiveTotalDue, true),
            'deposit_applied_amount' => Money::format($settlement['deposit_applied_amount'] ?? 0, true),
            'deposit_net_amount' => Money::format($settlement['deposit_net_amount'] ?? 0, true),
            'final_paid_amount' => Money::format($settlement['final_paid_amount'] ?? 0, true),
            'settled_amount' => Money::formatMinor($settledMinor),
            'outstanding_amount' => Money::formatMinor($outstandingMinor),
            'currency' => (string) ($currencyMeta['currency'] ?? $computed['currency'] ?? $reservation->bill_currency ?? 'VND'),
            'payment_status' => $paymentStatus,
            'has_mixed_payment_currencies' => $hasMixedCurrencies,
            'payment_summary' => [
                'deposit_captured' => Money::format($paymentSummary['deposit_captured_amount'] ?? 0, true),
                'deposit_refunded' => Money::format($paymentSummary['deposit_refunded_amount'] ?? 0, true),
                'deposit_net' => Money::format($paymentSummary['deposit_net_amount'] ?? 0, true),
                'final_captured' => Money::format($paymentSummary['final_captured_amount'] ?? 0, true),
                'final_refunded' => Money::format($paymentSummary['final_refunded_amount'] ?? 0, true),
                'final_net' => Money::format($paymentSummary['final_net_amount'] ?? 0, true),
                'captured_total' => Money::format($paymentSummary['captured_amount'] ?? 0, true),
                'refunded_total' => Money::format($paymentSummary['refunded_amount'] ?? 0, true),
                'net_paid_total' => Money::format($paymentSummary['net_paid_amount'] ?? 0, true),
            ],
            'loyalty' => $this->loyaltyPointsService->getReservationLoyaltyPreview($reservation, $payments, $computed),
            'applied_voucher' => $reservation->appliedUserVoucher ? [
                'user_voucher_id' => (int) $reservation->appliedUserVoucher->user_voucher_id,
                'voucher_id' => (int) $reservation->appliedUserVoucher->voucher_id,
                'voucher_code' => $reservation->appliedUserVoucher->voucher?->code,
                'description' => $reservation->appliedUserVoucher->voucher?->description,
                'discount_type' => $reservation->appliedUserVoucher->voucher?->discount_type?->value ?? (string) ($reservation->appliedUserVoucher->voucher?->discount_type ?? ''),
                'discount_value' => $reservation->appliedUserVoucher->voucher?->discount_value !== null
                    ? Money::format($reservation->appliedUserVoucher->voucher->discount_value, true)
                    : null,
                'used_amount' => $reservation->appliedUserVoucher->used_amount !== null
                    ? Money::format($reservation->appliedUserVoucher->used_amount, true)
                    : null,
            ] : null,
            'self_payment' => [
                'supported' => $selfPaymentSupported,
                'available' => $selfPaymentAvailable,
                'provider_code' => (string) ($selfPaymentRollout['provider_code'] ?? ''),
                'disabled_reason' => $selfPaymentSupported ? null : $selfPaymentDisabledReason,
                'requires_locked_bill' => $snapshotMode !== 'locked',
                'awaiting_staff_finalization' => $snapshotMode === 'locked'
                    && $outstandingMinor <= 0
                    && Money::minorUnits($paymentSummary['final_net_amount'] ?? 0, true) > 0,
                'next_step' => $this->resolveNextStep(
                    selfPaymentSupported: $selfPaymentSupported,
                    isActionableReservation: $isActionableReservation,
                    snapshotMode: $snapshotMode,
                    hasMixedCurrencies: $hasMixedCurrencies,
                    outstandingAmount: $outstandingAmount,
                    finalNetAmount: Money::toFloat($paymentSummary['final_net_amount'] ?? 0, true),
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

        if (! $selfPaymentSupported && Money::minorUnits($outstandingAmount, true) > 0) {
            return 'staff_settlement_only';
        }

        if ($snapshotMode !== 'locked') {
            return 'awaiting_staff_bill_lock';
        }

        if (Money::minorUnits($outstandingAmount, true) <= 0) {
            return Money::minorUnits($finalNetAmount, true) > 0
                ? 'payment_recorded_awaiting_staff_finalization'
                : 'already_settled';
        }

        return 'awaiting_customer_payment';
    }
}
