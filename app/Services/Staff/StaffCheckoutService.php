<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\PaymentStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationOrder;
use App\Models\ReservationOrderItem;
use App\Models\UserVoucher;
use App\Services\Branch\BranchContextService;
use App\Services\LoyaltyPointsService;
use App\Services\NotificationOutboxService;
use App\Services\ReservationFinancialSyncService;
use App\Services\ReservationLockService;
use App\Services\RestaurantTableStateService;
use App\Support\AuditEvent;
use App\Support\DatabaseWriteConflictMapper;
use App\Support\PaymentSummary;
use App\Support\ReservationVoucherLifecycleSupport;
use App\Support\VoucherRedemptionSupport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class StaffCheckoutService
{
    private const SETTLEMENT_READ_RESERVATION_RELATIONS = [
        'payments.refundOfPayment',
    ];

    private ReservationLockService $locks;
    private NotificationOutboxService $notificationOutboxService;
    private LoyaltyPointsService $loyaltyPointsService;
    private RestaurantTableStateService $tableStateService;
    private ReservationFinancialSyncService $reservationFinancialSyncService;
    private BillLockService $billLockService;
    private PaymentCaptureService $paymentCaptureService;
    private RefundExecutionService $refundExecutionService;
    private SettlementAmountCalculator $settlementAmountCalculator;
    private SettlementFinalizerService $settlementFinalizerService;
    private CheckoutResponseFactory $checkoutResponseFactory;
    private BranchContextService $branchContextService;
    private StaffBranchContextService $staffBranchContextService;
    private StaffCashierShiftService $cashierShiftService;

    public function __construct(
        ReservationLockService $locks,
        NotificationOutboxService $notificationOutboxService,
        LoyaltyPointsService $loyaltyPointsService,
        RestaurantTableStateService $tableStateService,
        ReservationFinancialSyncService $reservationFinancialSyncService,
        ?BillLockService $billLockService = null,
        ?PaymentCaptureService $paymentCaptureService = null,
        ?RefundExecutionService $refundExecutionService = null,
        ?SettlementAmountCalculator $settlementAmountCalculator = null,
        ?SettlementFinalizerService $settlementFinalizerService = null,
        ?CheckoutResponseFactory $checkoutResponseFactory = null,
        ?BranchContextService $branchContextService = null,
        ?StaffCashierShiftService $cashierShiftService = null,
        ?StaffBranchContextService $staffBranchContextService = null,
    ) {
        $this->locks = $locks;
        $this->notificationOutboxService = $notificationOutboxService;
        $this->loyaltyPointsService = $loyaltyPointsService;
        $this->tableStateService = $tableStateService;
        $this->reservationFinancialSyncService = $reservationFinancialSyncService;
        $this->settlementAmountCalculator = $settlementAmountCalculator ?? app(SettlementAmountCalculator::class);
        $this->checkoutResponseFactory = $checkoutResponseFactory ?? app(CheckoutResponseFactory::class);
        $this->billLockService = $billLockService ?? app(BillLockService::class);
        $this->paymentCaptureService = $paymentCaptureService ?? app(PaymentCaptureService::class);
        $this->refundExecutionService = $refundExecutionService ?? app(RefundExecutionService::class);
        $this->settlementFinalizerService = $settlementFinalizerService ?? app(SettlementFinalizerService::class);
        $this->branchContextService = $branchContextService ?? app(BranchContextService::class);
        $this->staffBranchContextService = $staffBranchContextService ?? app(StaffBranchContextService::class);
        $this->cashierShiftService = $cashierShiftService ?? app(StaffCashierShiftService::class);
    }

    /**
     * Legacy single-step checkout: close + pay in one locked flow.
     *
     * @return array<string,mixed>
     */
    public function checkout(
        int $orderId,
        string $paymentMethod,
        float $paidAmount,
        string $currency = 'VND',
        string $transactionCode = '',
        string $paymentProvider = '',
        string $notes = '',
        ?float $discountAmount = null,
        ?int $expectedRowVersion = null,
        ?int $staffUserId = null,
        string $idempotencyKey = ''
    ): array {
        $idempotencyKey = trim($idempotencyKey);
        $settlementCompletedRealtimePayload = null;
        $requestFingerprint = $idempotencyKey !== ''
            ? $this->buildCheckoutRequestFingerprint(
                paymentMethod: $paymentMethod,
                paidAmount: $paidAmount,
                currency: $currency,
                transactionCode: $transactionCode,
                paymentProvider: $paymentProvider,
                notes: $notes,
                discountAmount: $discountAmount,
            )
            : null;
        if ($idempotencyKey !== '') {
            $this->assertCheckoutReplayMatchesRequest($orderId, $idempotencyKey, $requestFingerprint ?? '');
            $replayed = $this->findExistingCheckoutReplay($orderId, $idempotencyKey, $currency);
            if ($replayed !== null) {
                return $replayed;
            }
        }

        $context = $this->getReservationLockContextForOrder($orderId);

        $result = $this->locks->withLockKeys($context['lock_keys'], function () use (
            $orderId,
            $paymentMethod,
            $paidAmount,
            $currency,
            $transactionCode,
            $paymentProvider,
            $notes,
            $discountAmount,
            $expectedRowVersion,
            $staffUserId,
            $idempotencyKey,
            $requestFingerprint,
            &$settlementCompletedRealtimePayload,
        ) {
            return DB::transaction(function () use (
                $orderId,
                $paymentMethod,
                $paidAmount,
                $currency,
                $transactionCode,
                $paymentProvider,
                $notes,
                $discountAmount,
                $expectedRowVersion,
                $staffUserId,
                $idempotencyKey,
                $requestFingerprint,
                &$settlementCompletedRealtimePayload,
            ) {
                $lockedOrder = ReservationOrder::query()->where('order_id', $orderId)->lockForUpdate()->firstOrFail();
                $this->assertExpectedOrderRowVersion($lockedOrder, $expectedRowVersion);

                $lockedReservation = Reservation::query()
                    ->where('reservation_id', (int) $lockedOrder->reservation_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $branchId = $this->ensureReservationBranchScopeLocked($lockedReservation, $staffUserId);
                $this->assertOpenCashierShiftForBranch($staffUserId, $branchId);

                $orderStatus = (string) ($lockedOrder->status?->value ?? $lockedOrder->status);
                $reservationStatus = (string) ($lockedReservation->status?->value ?? $lockedReservation->status);
                $orderType = (string) ($lockedOrder->order_type?->value ?? $lockedOrder->order_type);
                if (
                    $orderStatus !== ReservationOrderStatus::Active->value
                    || $reservationStatus !== ReservationStatus::Reserved->value
                    || $orderType !== ReservationOrderType::OnSpot->value
                ) {
                    if ($idempotencyKey !== '') {
                        $this->assertCheckoutReplayMatchesRequest($orderId, $idempotencyKey, $requestFingerprint ?? '');
                        $replayed = $this->findExistingCheckoutReplay($orderId, $idempotencyKey, $currency);
                        if ($replayed !== null) {
                            return $replayed;
                        }
                    }
                }

                $this->prepareCheckoutBillStateLocked($lockedReservation, $discountAmount, $staffUserId);
                $order = $this->paymentCaptureService->executeLocked(
                    order: $lockedOrder,
                    reservation: $lockedReservation,
                    computeReservationBillSnapshot: fn (int $reservationId, float $discount): array => $this->computeReservationBillSnapshot($reservationId, $discount),
                    findExistingOrderPaymentReplay: fn (int $replayOrderId, string $replayKey): ?ReservationOrder => $this->findExistingOrderPaymentReplay($replayOrderId, $replayKey),
                    paymentReplayCachePut: fn (int $replayOrderId, string $replayKey, string $value, int $ttlSeconds) => $this->paymentReplayCachePut($replayOrderId, $replayKey, $value, $ttlSeconds),
                    isDuplicatePaymentIdempotencyConstraint: fn (QueryException $e): bool => $this->isDuplicatePaymentIdempotencyConstraint($e),
                    throwIfDuplicatePaymentConstraint: fn (QueryException $e) => $this->throwIfDuplicatePaymentConstraint($e),
                    completeReservationSettlement: function (Reservation $reservation, ?int $actorUserId) use ($orderId, &$settlementCompletedRealtimePayload): void {
                        $this->completeReservationSettlement($reservation, $actorUserId);
                        $settlementCompletedRealtimePayload ??= $this->buildSettlementCompletedRealtimePayload(
                            $reservation,
                            $orderId,
                        );
                    },
                    touchFinancialMutation: fn (Reservation $reservation, ?int $actorUserId) => $this->reservationFinancialSyncService->touchFinancialMutation($reservation, $actorUserId),
                    paymentMethod: $paymentMethod,
                    paidAmount: $paidAmount,
                    currency: $currency,
                    transactionCode: $transactionCode,
                    paymentProvider: $paymentProvider,
                    notes: $notes,
                    staffUserId: $staffUserId,
                    idempotencyKey: $idempotencyKey,
                    requestFingerprint: $requestFingerprint,
                );

                return $this->buildCheckoutResponse($order, $currency);
            });
        });

        $this->publishSettlementCompletedRealtimeEvent($settlementCompletedRealtimePayload);

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildCheckoutResponse(ReservationOrder $order, string $fallbackCurrency = 'VND'): array
    {
        return $this->checkoutResponseFactory->buildCheckoutResponse($order, $fallbackCurrency);
    }

    /**
     * Locks the current bill snapshot for payment.
     *
     * This does not mark the order as completed or paid. It captures a stable bill
     * snapshot on the reservation and starts enforcing no-further-mutation rules
     * through billed_at/final_bill_amount guards.
     */
    public function lockBill(int $orderId, ?float $discountAmount = null, string $notes = '', ?int $expectedRowVersion = null, ?int $staffUserId = null): ReservationOrder
    {
        $context = $this->getReservationLockContextForOrder($orderId);

        return $this->locks->withLockKeys($context['lock_keys'], function () use ($orderId, $discountAmount, $notes, $expectedRowVersion, $staffUserId) {
            return $this->lockBillLocked($orderId, $discountAmount, $notes, $staffUserId, true, $expectedRowVersion);
        });
    }

    /**
     * @deprecated Use lockBill() for the canonical bill-lock semantics.
     */
    public function closeOrder(int $orderId, ?float $discountAmount = null, string $notes = '', ?int $expectedRowVersion = null, ?int $staffUserId = null): ReservationOrder
    {
        return $this->lockBill($orderId, $discountAmount, $notes, $expectedRowVersion, $staffUserId);
    }

    /**
     * @return array<string,mixed>
     */
    public function previewSettlement(int $orderId, string $fallbackCurrency = 'VND', ?int $staffUserId = null): array
    {
        $order = $this->loadSettlementReadOrder($orderId);

        if ($order->reservation instanceof Reservation) {
            $this->ensureReservationBranchScopeLocked($order->reservation, $staffUserId);
        }

        $order = $this->attachTotals($order);

        return $this->buildCheckoutResponse($order, $fallbackCurrency);
    }

    /**
     * @return array<string,mixed>
     */
    public function previewRefund(
        int $reservationId,
        string $refundScope = 'all',
        ?float $refundAmount = null,
        string $currency = 'VND',
        ?bool $cancelAfterPayment = null,
        ?int $staffUserId = null
    ): array
    {
        /** @var Reservation $reservation */
        $reservation = Reservation::query()
            ->with(['user', 'tables', 'orders.items.item', 'payments', 'appliedUserVoucher.voucher'])
            ->findOrFail($reservationId);

        $this->ensureReservationBranchScopeLocked($reservation, $staffUserId);

        $currentStatus = (string) ($reservation->status?->value ?? $reservation->status);
        $resolvedCancelAfterPayment = $this->resolveRefundPreviewCancelAfterPayment($currentStatus, $cancelAfterPayment);
        $this->assertRefundableStatus($currentStatus, $resolvedCancelAfterPayment);

        $summary = PaymentSummary::fromPayments($reservation->payments);
        $expectedPaymentCurrency = trim((string) ($reservation->bill_currency ?? '')) !== ''
            ? (string) $reservation->bill_currency
            : $currency;
        $effectivePaymentCurrency = $this->assertPaymentsSingleCurrency(
            $reservation->payments,
            $expectedPaymentCurrency,
            'currency'
        ) ?? $this->normalizeCurrencyCode($expectedPaymentCurrency, 'VND');
        if (trim($currency) !== '' && $this->normalizeCurrencyCode($currency, 'VND') !== $effectivePaymentCurrency) {
            throw ValidationException::withMessages([
                'currency' => ['Refund currency must match the reservation payment currency.'],
            ]);
        }

        $refundPlan = $this->refundExecutionService->buildRefundPlan(
            refundScope: strtolower(trim($refundScope)),
            requestedRefundAmount: $refundAmount,
            availableByScope: [
                'deposit' => (float) ($summary['deposit_net_amount'] ?? 0.0),
                'final' => (float) ($summary['final_net_amount'] ?? 0.0),
            ],
            cancelAfterPayment: $resolvedCancelAfterPayment,
        );

        $plannedFinalRefund = round((float) ($refundPlan['final'] ?? 0.0), 2);
        $finalNetAfter = max(0.0, round((float) ($summary['final_net_amount'] ?? 0.0) - $plannedFinalRefund, 2));
        if ($resolvedCancelAfterPayment && $finalNetAfter > 0.0001) {
            throw ValidationException::withMessages([
                'refund_amount' => 'cancel-after-payment requires all remaining final payments to be refunded first.',
            ]);
        }

        return $this->buildRefundResponse(
            reservation: $reservation,
            summary: $summary,
            refundPaymentIds: [],
            refundAmountThisCall: round((float) ($refundPlan['total'] ?? 0.0), 2),
            refundScope: strtolower(trim($refundScope)),
            cancelled: $resolvedCancelAfterPayment,
            currency: $effectivePaymentCurrency,
        );
    }

    public function payOrder(
        int $orderId,
        string $paymentMethod,
        float $paidAmount,
        string $currency = 'VND',
        string $transactionCode = '',
        string $paymentProvider = '',
        string $notes = '',
        ?int $expectedRowVersion = null,
        ?int $staffUserId = null,
        string $idempotencyKey = '',
        bool $useTransaction = true
    ): ReservationOrder {
        $idempotencyKey = trim($idempotencyKey);
        $settlementCompletedRealtimePayload = null;
        $requestFingerprint = $idempotencyKey !== ''
            ? $this->buildCheckoutRequestFingerprint(
                paymentMethod: $paymentMethod,
                paidAmount: $paidAmount,
                currency: $currency,
                transactionCode: $transactionCode,
                paymentProvider: $paymentProvider,
                notes: $notes,
                discountAmount: null,
            )
            : null;
        if ($idempotencyKey !== '') {
            $this->assertCheckoutReplayMatchesRequest($orderId, $idempotencyKey, $requestFingerprint ?? '');
            $hit = $this->paymentReplayCacheGet($orderId, $idempotencyKey);
            if (is_numeric($hit)) {
                $existing = $this->findSettlementReadOrder((int) $hit);
                if ($existing) {
                    return $this->attachTotals($existing);
                }
            }

            $replayed = $this->findExistingOrderPaymentReplay($orderId, $idempotencyKey);
            if ($replayed !== null) {
                return $replayed;
            }
        }

        $context = $this->getReservationLockContextForOrder($orderId);

        $result = $this->locks->withLockKeys($context['lock_keys'], function () use (
            $orderId,
            $paymentMethod,
            $paidAmount,
            $currency,
            $transactionCode,
            $paymentProvider,
            $notes,
            $expectedRowVersion,
            $staffUserId,
            $idempotencyKey,
            $requestFingerprint,
            &$settlementCompletedRealtimePayload,
        ) {
            return $this->payOrderLocked(
                $orderId,
                $paymentMethod,
                $paidAmount,
                $currency,
                $transactionCode,
                $paymentProvider,
                $notes,
                $staffUserId,
                $idempotencyKey,
                $requestFingerprint,
                $settlementCompletedRealtimePayload,
                true,
                $expectedRowVersion
            );
        });

        $this->publishSettlementCompletedRealtimeEvent($settlementCompletedRealtimePayload);

        return $result;
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
        string $idempotencyKey = ''
    ): array {
        $context = $this->getReservationLockContextForReservation($reservationId);

        return $this->locks->withLockKeys($context['lock_keys'], function () use (
            $reservationId,
            $paymentMethod,
            $refundScope,
            $refundAmount,
            $currency,
            $transactionCode,
            $paymentProvider,
            $notes,
            $reason,
            $expectedRowVersion,
            $staffUserId,
            $idempotencyKey
        ) {
            return $this->executeRefundFlow(
                reservationId: $reservationId,
                paymentMethod: $paymentMethod,
                refundScope: $refundScope,
                refundAmount: $refundAmount,
                currency: $currency,
                transactionCode: $transactionCode,
                paymentProvider: $paymentProvider,
                notes: $notes,
                reason: $reason,
                cancelAfterPayment: false,
                cancelReason: null,
                expectedRowVersion: $expectedRowVersion,
                staffUserId: $staffUserId,
                idempotencyKey: $idempotencyKey
            );
        });
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
        string $idempotencyKey = ''
    ): array {
        $context = $this->getReservationLockContextForReservation($reservationId);

        return $this->locks->withLockKeys($context['lock_keys'], function () use (
            $reservationId,
            $paymentMethod,
            $refundScope,
            $refundAmount,
            $currency,
            $transactionCode,
            $paymentProvider,
            $notes,
            $reason,
            $cancelReason,
            $expectedRowVersion,
            $staffUserId,
            $idempotencyKey
        ) {
            return $this->executeRefundFlow(
                reservationId: $reservationId,
                paymentMethod: $paymentMethod,
                refundScope: $refundScope,
                refundAmount: $refundAmount,
                currency: $currency,
                transactionCode: $transactionCode,
                paymentProvider: $paymentProvider,
                notes: $notes,
                reason: $reason,
                cancelAfterPayment: true,
                cancelReason: $cancelReason,
                expectedRowVersion: $expectedRowVersion,
                staffUserId: $staffUserId,
                idempotencyKey: $idempotencyKey
            );
        });
    }

    private function lockBillLocked(int $orderId, ?float $discountAmount = null, string $notes = '', ?int $staffUserId = null, bool $useTransaction = true, ?int $expectedRowVersion = null, bool $bumpReservationVersion = true): ReservationOrder
    {
        $runner = fn () => $this->billLockService->lockBill(
            orderId: $orderId,
            discountAmount: $discountAmount,
            notes: $notes,
            staffUserId: $staffUserId,
            expectedRowVersion: $expectedRowVersion,
            assertExpectedOrderRowVersion: fn (ReservationOrder $order, ?int $rowVersion) => $this->assertExpectedOrderRowVersion($order, $rowVersion),
            currentLoyaltyDiscountAmount: fn (int $reservationId): float => $this->currentLoyaltyDiscountAmount($reservationId),
            currentVoucherDiscountAmount: fn (int $reservationId, bool $lock = false): float => $this->currentVoucherDiscountAmount($reservationId, $lock),
            attachTotals: fn (ReservationOrder $order, ?float $subtotal = null, ?float $discount = null, ?float $totalDue = null, ?string $currency = null): ReservationOrder => $this->attachTotals($order, $subtotal, $discount, $totalDue, $currency),
            bumpReservationVersion: $bumpReservationVersion,
        );

        return $useTransaction ? DB::transaction($runner) : $runner();
    }


    private function prepareCheckoutBillStateLocked(Reservation $reservation, ?float $discountAmount = null, ?int $staffUserId = null): void
    {
        $reservationId = (int) $reservation->reservation_id;
        $loyaltyDiscount = max(0.0, round($this->currentLoyaltyDiscountAmount($reservationId), 2));
        $voucherDiscount = max(0.0, round($this->currentVoucherDiscountAmount($reservationId, true), 2));
        $currentNonLoyaltyDiscount = max(0.0, round((float) ($reservation->discount_amount ?? 0.0) - $loyaltyDiscount, 2));
        $requestedNonLoyaltyDiscount = $discountAmount !== null ? max(0.0, round($discountAmount, 2)) : $currentNonLoyaltyDiscount;
        $effectiveNonLoyaltyDiscount = max($requestedNonLoyaltyDiscount, $voucherDiscount);
        $effectiveDiscount = round($effectiveNonLoyaltyDiscount + $loyaltyDiscount, 2);
        [$subtotal, $discount, $totalDue, $currencyCode] = $this->computeReservationBillSnapshot($reservationId, $effectiveDiscount);

        $reservation->discount_amount = $discount;
        $reservation->final_bill_amount = $totalDue;
        $reservation->bill_currency = $currencyCode;
        $reservation->billed_at = Carbon::now('UTC');
        $reservation->updated_by = $staffUserId;
    }

    private function payOrderLocked(
        int $orderId,
        string $paymentMethod,
        float $paidAmount,
        string $currency = 'VND',
        string $transactionCode = '',
        string $paymentProvider = '',
        string $notes = '',
        ?int $staffUserId = null,
        string $idempotencyKey = '',
        ?string $requestFingerprint = null,
        ?array &$settlementCompletedRealtimePayload = null,
        bool $useTransaction = true,
        ?int $expectedRowVersion = null
    ): ReservationOrder {
        $paymentMethod = trim($paymentMethod);
        if ($paymentMethod === '') {
            throw ValidationException::withMessages(['payment_method' => 'payment_method is required']);
        }

        $runner = function () use ($orderId, $paymentMethod, $paidAmount, $currency, $transactionCode, $paymentProvider, $notes, $staffUserId, $idempotencyKey, $requestFingerprint, $expectedRowVersion, &$settlementCompletedRealtimePayload) {
            /** @var ReservationOrder $order */
            $order = ReservationOrder::query()->where('order_id', $orderId)->lockForUpdate()->firstOrFail();
            $this->assertExpectedOrderRowVersion($order, $expectedRowVersion);
            /** @var Reservation $reservation */
            $reservation = Reservation::query()->where('reservation_id', $order->reservation_id)->lockForUpdate()->firstOrFail();
            $branchId = $this->ensureReservationBranchScopeLocked($reservation, $staffUserId);
            $this->assertOpenCashierShiftForBranch($staffUserId, $branchId);

            return $this->paymentCaptureService->executeLocked(
                order: $order,
                reservation: $reservation,
                computeReservationBillSnapshot: fn (int $reservationId, float $discountAmount): array => $this->computeReservationBillSnapshot($reservationId, $discountAmount),
                findExistingOrderPaymentReplay: fn (int $replayOrderId, string $replayKey): ?ReservationOrder => $this->findExistingOrderPaymentReplay($replayOrderId, $replayKey),
                paymentReplayCachePut: fn (int $replayOrderId, string $replayKey, string $value, int $ttlSeconds) => $this->paymentReplayCachePut($replayOrderId, $replayKey, $value, $ttlSeconds),
                isDuplicatePaymentIdempotencyConstraint: fn (QueryException $e): bool => $this->isDuplicatePaymentIdempotencyConstraint($e),
                throwIfDuplicatePaymentConstraint: fn (QueryException $e) => $this->throwIfDuplicatePaymentConstraint($e),
                completeReservationSettlement: function (Reservation $lockedReservation, ?int $actorUserId) use ($orderId, &$settlementCompletedRealtimePayload): void {
                    $this->completeReservationSettlement($lockedReservation, $actorUserId);
                    $settlementCompletedRealtimePayload ??= $this->buildSettlementCompletedRealtimePayload(
                        $lockedReservation,
                        $orderId,
                    );
                },
                touchFinancialMutation: fn (Reservation $lockedReservation, ?int $actorUserId) => $this->reservationFinancialSyncService->touchFinancialMutation($lockedReservation, $actorUserId),
                paymentMethod: $paymentMethod,
                paidAmount: $paidAmount,
                currency: $currency,
                transactionCode: $transactionCode,
                paymentProvider: $paymentProvider,
                notes: $notes,
                staffUserId: $staffUserId,
                idempotencyKey: $idempotencyKey,
                requestFingerprint: $requestFingerprint,
            );
        };

        return $useTransaction ? DB::transaction($runner) : $runner();
    }

    /**
     * @return array<string,mixed>
     */
    private function executeRefundFlow(
        int $reservationId,
        string $paymentMethod,
        string $refundScope,
        ?float $refundAmount,
        string $currency,
        string $transactionCode,
        string $paymentProvider,
        string $notes,
        ?string $reason,
        bool $cancelAfterPayment,
        ?string $cancelReason,
        ?int $expectedRowVersion,
        ?int $staffUserId,
        string $idempotencyKey
    ): array {
        $paymentMethod = trim($paymentMethod);
        $refundCancelledRealtimePayload = null;
        if ($paymentMethod === '') {
            throw ValidationException::withMessages(['payment_method' => 'payment_method is required']);
        }

        $normalizedScope = strtolower(trim($refundScope));
        if (! in_array($normalizedScope, ['deposit', 'final', 'all'], true)) {
            throw ValidationException::withMessages(['refund_scope' => 'refund_scope must be one of deposit, final, all.']);
        }

        $notes = trim($notes);
        $reason = $reason !== null ? trim($reason) : null;
        $cancelReason = $cancelReason !== null ? trim($cancelReason) : null;
        $baseCurrency = trim($currency) !== '' ? trim($currency) : 'VND';
        $idempotencyKey = trim($idempotencyKey);
        $requestFingerprint = $idempotencyKey !== ''
            ? $this->buildRefundRequestFingerprint(
                paymentMethod: $paymentMethod,
                refundScope: $normalizedScope,
                refundAmount: $refundAmount,
                currency: $baseCurrency,
                transactionCode: $transactionCode,
                paymentProvider: $paymentProvider,
                notes: $notes,
                reason: $reason,
                cancelAfterPayment: $cancelAfterPayment,
                cancelReason: $cancelReason,
            )
            : null;

        if ($idempotencyKey !== '') {
            $this->assertRefundReplayMatchesRequest($reservationId, $idempotencyKey, $requestFingerprint ?? '');
            $replayed = $this->findExistingRefundReplay($reservationId, $idempotencyKey);
            if ($replayed !== null) {
                return $replayed;
            }
        }

        try {
            $result = DB::transaction(function () use (
            $reservationId,
            $paymentMethod,
            $normalizedScope,
            $refundAmount,
            $baseCurrency,
            $transactionCode,
            $paymentProvider,
            $notes,
            $reason,
            $cancelAfterPayment,
            $cancelReason,
            $expectedRowVersion,
            $staffUserId,
            $idempotencyKey,
            $requestFingerprint,
            &$refundCancelledRealtimePayload
        ) {
            /** @var Reservation $reservation */
            $reservation = Reservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
            $currentStatus = (string) ($reservation->status?->value ?? $reservation->status);

            $this->assertRefundableStatus($currentStatus, $cancelAfterPayment);

            $beforeVersion = (int) ($reservation->row_version ?? 1);
            if ($expectedRowVersion !== null && $beforeVersion !== (int) $expectedRowVersion) {
                throw ValidationException::withMessages([
                    'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
                ]);
            }

            /** @var Collection<int,ReservationOrder> $orders */
            $orders = ReservationOrder::query()
                ->where('reservation_id', $reservationId)
                ->with('items')
                ->lockForUpdate()
                ->get();

            /** @var Collection<int,Payment> $payments */
            $payments = Payment::query()
                ->where('reservation_id', $reservationId)
                ->orderBy('payment_id')
                ->lockForUpdate()
                ->get();

            $expectedPaymentCurrency = trim((string) ($reservation->bill_currency ?? '')) !== ''
                ? (string) $reservation->bill_currency
                : $baseCurrency;
            $effectivePaymentCurrency = $this->assertPaymentsSingleCurrency(
                $payments,
                $expectedPaymentCurrency,
                'currency'
            ) ?? $this->normalizeCurrencyCode($expectedPaymentCurrency, 'VND');
            if (trim($baseCurrency) !== '' && $this->normalizeCurrencyCode($baseCurrency, 'VND') !== $effectivePaymentCurrency) {
                throw ValidationException::withMessages([
                    'currency' => ['Refund currency must match the reservation payment currency.'],
                ]);
            }

            $tableIds = DB::table('reservation_tables')
                ->where('reservation_id', $reservationId)
                ->orderBy('table_id')
                ->lockForUpdate()
                ->pluck('table_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (! empty($tableIds)) {
                DB::table('restaurant_tables')
                    ->whereIn('table_id', $tableIds)
                    ->lockForUpdate()
                    ->get();
            }

            $branchId = $this->ensureReservationBranchScopeLocked($reservation, $staffUserId, $tableIds);
            $this->assertOpenCashierShiftForBranch($staffUserId, $branchId);

            $result = $this->refundExecutionService->executeLocked(
                reservation: $reservation,
                orders: $orders,
                payments: $payments,
                tableIds: $tableIds,
                paymentMethod: $paymentMethod,
                refundScope: $normalizedScope,
                refundAmount: $refundAmount,
                baseCurrency: $baseCurrency,
                transactionCode: $transactionCode,
                paymentProvider: $paymentProvider,
                notes: $notes,
                reason: $reason,
                cancelAfterPayment: $cancelAfterPayment,
                cancelReason: $cancelReason,
                staffUserId: $staffUserId,
                idempotencyKey: $idempotencyKey,
                requestFingerprint: $requestFingerprint,
                syncDepositSnapshot: function (Reservation $lockedReservation, array $paymentSummary, bool $cancelled): void {
                    $this->reservationFinancialSyncService->syncDepositSnapshot($lockedReservation, $paymentSummary, $cancelled);
                },
                releaseAppliedVoucherLocked: fn (Reservation $lockedReservation, Collection $lockedOrders, ?int $actorUserId, bool $detachReservation = true): float
                    => $this->releaseAppliedVoucherLocked($lockedReservation, $lockedOrders, $actorUserId, $detachReservation),
                cancelReservationLocked: function (Reservation $lockedReservation, Collection $lockedOrders, array $lockedTableIds, ?int $actorUserId, ?string $refundCancelReason): void {
                    $this->cancelReservationLocked($lockedReservation, $lockedOrders, $lockedTableIds, $actorUserId, $refundCancelReason);
                },
                isDuplicatePaymentIdempotencyConstraint: fn (QueryException $e): bool
                    => $this->isDuplicatePaymentIdempotencyConstraint($e),
                throwIfDuplicatePaymentConstraint: fn (QueryException $e)
                    => $this->throwIfDuplicatePaymentConstraint($e),
            );

            if ($cancelAfterPayment) {
                $refundCancelledRealtimePayload ??= $this->buildRefundCancelledRealtimePayload(
                    $reservation,
                    $tableIds,
                    (array) ($result['refund_payment_ids'] ?? []),
                    $cancelReason,
                );
            }

            return $result;
        });

        } catch (QueryException $e) {
            if ($idempotencyKey !== '' && $this->isDuplicatePaymentIdempotencyConstraint($e)) {
                $this->assertRefundReplayMatchesRequest($reservationId, $idempotencyKey, $requestFingerprint ?? '');
                $replayed = $this->findExistingRefundReplay($reservationId, $idempotencyKey);
                if ($replayed !== null) {
                    return $replayed;
                }
            }

            $this->throwIfDuplicatePaymentConstraint($e);
            throw $e;
        }

        /** @var Reservation $freshReservation */
        $freshReservation = Reservation::query()
            ->with(['user', 'tables', 'orders.items.item', 'payments', 'appliedUserVoucher.voucher'])
            ->findOrFail($reservationId);

        if ($cancelAfterPayment) {
            $this->notificationOutboxService->enqueueReservationCancelled($freshReservation);
        } elseif (($result['refund_payment_ids'] ?? []) !== []) {
            $this->notificationOutboxService->enqueuePaymentRefunded($freshReservation, [
                'refund_payment_ids' => $result['refund_payment_ids'],
                'refund_amount' => (float) ($result['refund_amount_this_call'] ?? 0.0),
                'refund_scope' => $normalizedScope,
                'currency' => (string) ($result['currency'] ?? $baseCurrency),
            ]);
        }

        $response = $this->buildRefundResponse(
            reservation: $freshReservation,
            summary: (array) ($result['summary'] ?? []),
            refundPaymentIds: (array) ($result['refund_payment_ids'] ?? []),
            refundAmountThisCall: (float) ($result['refund_amount_this_call'] ?? 0.0),
            refundScope: $normalizedScope,
            cancelled: $cancelAfterPayment,
            currency: (string) ($result['currency'] ?? $baseCurrency)
        );

        if ($cancelAfterPayment) {
            $this->publishRefundCancelledRealtimeEvent($refundCancelledRealtimePayload);
        }

        return $response;
    }

    private function assertExpectedOrderRowVersion(ReservationOrder $order, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($order->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
            ]);
        }
    }

    private function getReservationLockContextForOrder(int $orderId): array
    {
        $reservationId = (int) ReservationOrder::query()->where('order_id', $orderId)->value('reservation_id');
        if ($reservationId <= 0) {
            throw ValidationException::withMessages(['order_id' => 'Order not found.']);
        }

        return $this->getReservationLockContextForReservation($reservationId);
    }

    private function getReservationLockContextForReservation(int $reservationId): array
    {
        $exists = Reservation::query()->where('reservation_id', $reservationId)->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['reservation_id' => 'Reservation not found.']);
        }

        $tableIds = DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $lockKeys = [config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation') . ':' . $reservationId];
        foreach ($tableIds as $tableId) {
            $lockKeys[] = config('booking.reservation_lock_prefix', 'booking:lock:table') . ':' . $tableId;
        }

        return [
            'reservation_id' => $reservationId,
            'table_ids' => $tableIds,
            'lock_keys' => $lockKeys,
        ];
    }

    /**
     * @param array<int,int>|null $tableIds
     */
    private function ensureReservationBranchScopeLocked(Reservation $reservation, ?int $staffUserId = null, ?array $tableIds = null): int
    {
        $tableIds = $tableIds ?? DB::table('reservation_tables')
            ->where('reservation_id', (int) $reservation->reservation_id)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($tableIds !== []) {
            $tables = DB::table('restaurant_tables')
                ->whereIn('table_id', $tableIds)
                ->lockForUpdate()
                ->get(['table_id', 'branch_id']);

            if ($tables->count() !== count($tableIds)) {
                throw ValidationException::withMessages([
                    'reservation_id' => 'Reservation has assigned tables that no longer exist.',
                ]);
            }

            $tableBranchId = $this->branchContextService->assertSingleBranch(
                $tables->pluck('branch_id')->all(),
                'Reservation tables must belong to a single branch.',
                'reservation_id',
                false
            );

            if ($reservation->branch_id === null || $reservation->branch_id === '') {
                $this->assertOperationalBranchAccessible($tableBranchId, $staffUserId);
                $reservation->branch_id = $tableBranchId;
                $reservation->updated_by = $staffUserId;

                return $tableBranchId;
            }

            $branchId = $this->branchContextService->assertSameBranch(
                $reservation->branch_id,
                $tableBranchId,
                'Reservation branch does not match its assigned tables.',
                'reservation_id',
                false
            );
            $this->assertOperationalBranchAccessible($branchId, $staffUserId);

            return $branchId;
        }

        $branchId = $reservation->branch_id !== null && $reservation->branch_id !== ''
            ? $this->branchContextService->resolveBranchId($reservation->branch_id, false)
            : $this->branchContextService->resolveBranchId(null, false);
        $this->assertOperationalBranchAccessible($branchId, $staffUserId);

        return $branchId;
    }

    private function assertOperationalBranchAccessible(int $branchId, ?int $staffUserId): void
    {
        if ($staffUserId === null || $staffUserId <= 0) {
            return;
        }

        $this->staffBranchContextService->assertAccessibleBranch($staffUserId, $branchId);
    }

    private function assertOpenCashierShiftForBranch(?int $staffUserId, int $branchId): void
    {
        if ($staffUserId === null || $staffUserId <= 0) {
            return;
        }

        if ($this->cashierShiftService->currentOpenShift($staffUserId, $branchId) !== null) {
            return;
        }

        throw ValidationException::withMessages([
            'cashier_shift' => ['Open a cashier shift for this branch before recording settlement or refund mutations.'],
        ]);
    }

    private function computeReservationBillSnapshot(int $reservationId, float $discountAmount): array
    {
        $snapshot = $this->reservationFinancialSyncService->computeReservationBillSnapshot($reservationId, $discountAmount, true);

        return [
            (float) ($snapshot['subtotal'] ?? 0.0),
            (float) ($snapshot['discount'] ?? 0.0),
            (float) ($snapshot['total_due'] ?? 0.0),
            (string) ($snapshot['currency'] ?? 'VND'),
        ];
    }

    private function loadSettlementReadOrder(int $orderId): ReservationOrder
    {
        /** @var ReservationOrder $order */
        $order = ReservationOrder::query()
            ->with([
                'reservation' => function ($query): void {
                    $query->with(self::SETTLEMENT_READ_RESERVATION_RELATIONS);
                },
            ])
            ->findOrFail($orderId);

        return $order;
    }

    private function findSettlementReadOrder(int $orderId): ?ReservationOrder
    {
        /** @var ReservationOrder|null $order */
        $order = ReservationOrder::query()
            ->with([
                'reservation' => function ($query): void {
                    $query->with(self::SETTLEMENT_READ_RESERVATION_RELATIONS);
                },
            ])
            ->find($orderId);

        return $order;
    }

    private function completeReservationSettlement(Reservation $reservation, ?int $staffUserId = null): void
    {
        $this->settlementFinalizerService->completeReservationSettlement(
            reservation: $reservation,
            staffUserId: $staffUserId,
            consumeAppliedVoucherLocked: fn (Reservation $lockedReservation, Collection $orders, ?int $actorUserId)
                => $this->consumeAppliedVoucherLocked($lockedReservation, $orders, $actorUserId),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSettlementCompletedRealtimePayload(Reservation $reservation, int $orderId): array
    {
        $reservationId = (int) $reservation->reservation_id;
        $checkedOutAt = $reservation->checked_out_at instanceof Carbon
            ? $reservation->checked_out_at->copy()->setTimezone('UTC')->toIso8601String()
            : null;

        return [
            'reservation_id' => $reservationId,
            'order_id' => $orderId,
            'table_ids' => DB::table('reservation_tables')
                ->where('reservation_id', $reservationId)
                ->orderBy('table_id')
                ->pluck('table_id')
                ->map(static fn ($id): int => (int) $id)
                ->all(),
            'reservation_status' => (string) ($reservation->status?->value ?? $reservation->status),
            'checked_out_at' => $checkedOutAt,
        ];
    }

    /**
     * @param array<string,mixed>|null $payload
     */
    private function publishSettlementCompletedRealtimeEvent(?array $payload): void
    {
        if ($payload === null || $payload === []) {
            return;
        }

        app(StaffOperationalRealtimeService::class)->publishBoardEvent(
            'reservation.settlement_completed',
            $payload,
            ['board', 'timeline']
        );
    }

    /**
     * @param array<int,int> $tableIds
     * @param array<int,int> $refundPaymentIds
     * @return array<string,mixed>
     */
    private function buildRefundCancelledRealtimePayload(
        Reservation $reservation,
        array $tableIds,
        array $refundPaymentIds,
        ?string $cancelReason
    ): array {
        $cancelledAt = $reservation->cancelled_at instanceof Carbon
            ? $reservation->cancelled_at->copy()->setTimezone('UTC')->toIso8601String()
            : null;

        return [
            'reservation_id' => (int) $reservation->reservation_id,
            'table_ids' => array_values(array_map('intval', $tableIds)),
            'refund_payment_ids' => array_values(array_map('intval', $refundPaymentIds)),
            'reservation_status' => (string) ($reservation->status?->value ?? $reservation->status),
            'cancelled_at' => $cancelledAt,
            'cancel_reason' => $cancelReason !== null && trim($cancelReason) !== ''
                ? trim($cancelReason)
                : (string) ($reservation->cancel_reason ?? ''),
        ];
    }

    /**
     * @param array<string,mixed>|null $payload
     */
    private function publishRefundCancelledRealtimeEvent(?array $payload): void
    {
        if ($payload === null || $payload === []) {
            return;
        }

        app(StaffOperationalRealtimeService::class)->publishBoardEvent(
            'reservation.refund_cancelled',
            $payload,
            ['board', 'timeline']
        );
    }

    /**
     * @param Collection<int,Payment>|null $payments
     * @return array{deposit_net_amount:float,deposit_applied_amount:float,final_paid_amount:float,settled_amount:float,remaining_due:float}
     */
    private function buildSettlementAmounts(?Collection $payments, float $totalDue, ?int $reservationId = null): array
    {
        if ($payments === null) {
            if ($reservationId === null || $reservationId <= 0) {
                throw ValidationException::withMessages([
                    'reservation_id' => ['reservation_id is required to compute settlement amounts.'],
                ]);
            }

            /** @var Collection<int,Payment> $payments */
            $payments = Payment::query()
                ->where('reservation_id', $reservationId)
                ->get(['amount', 'payment_type', 'status', 'provider_response_json', 'currency', 'refund_of_payment_id']);
        }

        $this->assertPaymentsSingleCurrency($payments, null, 'currency');
        $summary = PaymentSummary::fromPayments($payments);
        $depositNet = round(max(0.0, (float) ($summary['deposit_net_amount'] ?? 0.0)), 2);
        $finalPaid = round(max(0.0, (float) ($summary['final_net_amount'] ?? 0.0)), 2);
        $depositApplied = round(min(max(0.0, $totalDue), $depositNet), 2);
        $settled = round(min(max(0.0, $totalDue), $depositApplied + $finalPaid), 2);

        return [
            'deposit_net_amount' => $depositNet,
            'deposit_applied_amount' => $depositApplied,
            'final_paid_amount' => $finalPaid,
            'settled_amount' => $settled,
            'remaining_due' => round(max(0.0, $totalDue - $settled), 2),
        ];
    }

    private function attachTotals(ReservationOrder $order, ?float $subtotal = null, ?float $discount = null, ?float $totalDue = null, ?string $currency = null): ReservationOrder
    {
        $reservationId = (int) $order->reservation_id;
        $reservation = $order->relationLoaded('reservation') && $order->reservation instanceof Reservation
            ? $order->reservation
            : null;

        if ($subtotal === null || $discount === null || $totalDue === null || $currency === null) {
            $discountAmount = $reservation instanceof Reservation
                ? (float) ($reservation->discount_amount ?? 0.0)
                : (float) Reservation::query()->where('reservation_id', $reservationId)->value('discount_amount');
            [$subtotal, $discount, $totalDue, $currency] = $this->computeReservationBillSnapshot($reservationId, $discountAmount);
        }

        $payments = $reservation instanceof Reservation && $reservation->relationLoaded('payments')
            ? $reservation->payments
            : null;
        $settlement = $this->buildSettlementAmounts($payments, (float) $totalDue, $payments === null ? $reservationId : null);
        $paid = (float) ($settlement['settled_amount'] ?? 0.0);
        $depositApplied = (float) ($settlement['deposit_applied_amount'] ?? 0.0);
        $depositNet = (float) ($settlement['deposit_net_amount'] ?? 0.0);
        $finalPaid = (float) ($settlement['final_paid_amount'] ?? 0.0);
        $outstanding = (float) ($settlement['remaining_due'] ?? max(0.0, (float) $totalDue - $paid));

        $order->setAttribute('subtotal_amount', round($subtotal, 2));
        $order->setAttribute('discount_amount', round($discount, 2));
        $order->setAttribute('total_due_amount', round($totalDue, 2));
        $order->setAttribute('paid_amount', round($paid, 2));
        $order->setAttribute('deposit_applied_amount', round($depositApplied, 2));
        $order->setAttribute('deposit_net_amount', round($depositNet, 2));
        $order->setAttribute('final_paid_amount', round($finalPaid, 2));
        $order->setAttribute('outstanding_amount', round($outstanding, 2));
        $order->setAttribute('currency', $currency ?: 'VND');
        $order->setAttribute('payment_status', $paid + 0.0001 >= $totalDue ? PaymentStatus::Success->value : ($paid > 0 ? PaymentStatus::Partial->value : PaymentStatus::Failed->value));

        return $order;
    }

    private function assertRefundableStatus(string $currentStatus, bool $cancelAfterPayment): void
    {
        if ($cancelAfterPayment) {
            if (! in_array($currentStatus, [
                ReservationStatus::Confirmed->value,
                ReservationStatus::Reserved->value,
                ReservationStatus::Completed->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'reservation' => ['Only Confirmed, Reserved, or Completed reservations can be refunded and cancelled.'],
                ]);
            }

            return;
        }

        if ($currentStatus !== ReservationStatus::Completed->value) {
            throw ValidationException::withMessages([
                'reservation' => ['Only Completed reservations can be refunded without cancellation.'],
            ]);
        }
    }

    private function resolveRefundPreviewCancelAfterPayment(string $currentStatus, ?bool $cancelAfterPayment): bool
    {
        if ($cancelAfterPayment !== null) {
            return $cancelAfterPayment;
        }

        return in_array($currentStatus, [
            ReservationStatus::Confirmed->value,
            ReservationStatus::Reserved->value,
        ], true);
    }

    private function normalizeCurrencyCode(?string $currency, string $fallback = 'VND'): string
    {
        $normalized = strtoupper(trim((string) $currency));
        if ($normalized !== '') {
            return $normalized;
        }

        $fallback = strtoupper(trim($fallback));

        return $fallback !== '' ? $fallback : 'VND';
    }

    /**
     * @param iterable<int|mixed,mixed> $payments
     */
    private function assertPaymentsSingleCurrency(iterable $payments, ?string $expectedCurrency = null, string $field = 'currency'): ?string
    {
        $currencies = [];
        foreach ($payments as $payment) {
            $currency = $this->normalizeCurrencyCode((string) ($payment->currency ?? ''), '');
            if ($currency === '') {
                continue;
            }
            $currencies[$currency] = true;
        }

        $codes = array_keys($currencies);
        if (count($codes) > 1) {
            throw ValidationException::withMessages([
                $field => ['Payments for the same reservation must use a single currency.'],
            ]);
        }

        $actual = $codes[0] ?? null;
        $expected = $expectedCurrency !== null ? $this->normalizeCurrencyCode($expectedCurrency, '') : null;
        if ($expected !== null && $expected !== '' && $actual !== null && $actual !== $expected) {
            throw ValidationException::withMessages([
                $field => ['Payment currency does not match reservation bill currency.'],
            ]);
        }

        return $actual;
    }
    /**
     * @param Collection<int,ReservationOrder> $orders
     * @param array<int,int> $tableIds
     */
    private function cancelReservationLocked(Reservation $reservation, Collection $orders, array $tableIds, ?int $staffUserId, ?string $cancelReason): void
    {
        $currentStatus = (string) ($reservation->status?->value ?? $reservation->status);
        $now = Carbon::now('UTC');

        if (! in_array($currentStatus, [
            ReservationStatus::Confirmed->value,
            ReservationStatus::Reserved->value,
            ReservationStatus::Completed->value,
        ], true)) {
            throw ValidationException::withMessages([
                'reservation' => 'cancel-after-payment only supports Confirmed, Reserved, or Completed reservations.',
            ]);
        }

        $this->cancelActiveOrders($orders, $staffUserId, $now);
        if ($currentStatus === ReservationStatus::Reserved->value) {
            $this->releaseTables($tableIds, $staffUserId, (int) $reservation->reservation_id);
        }

        $reservation->status = ReservationStatus::Cancelled;
        $reservation->checked_in_at = null;
        $reservation->checked_out_at = null;
        $reservation->no_show_at = null;
        $reservation->cancelled_at = $now;
        $reservation->cancelled_by = $staffUserId;
        $reservation->cancel_reason = $cancelReason !== null && $cancelReason !== ''
            ? $cancelReason
            : ($reservation->cancel_reason ?: 'Cancelled after payment/refund flow');
    }

    /**
     * @param Collection<int,ReservationOrder> $orders
     */
    private function cancelActiveOrders(Collection $orders, ?int $actorUserId, Carbon $now): void
    {
        foreach ($orders as $order) {
            if ((string) ($order->status?->value ?? $order->status) !== ReservationOrderStatus::Active->value) {
                continue;
            }

            ReservationOrderItem::query()
                ->where('order_id', $order->order_id)
                ->whereNotIn('status', ['Cancelled', 'Served'])
                ->update([
                    'status' => 'Cancelled',
                    'updated_by' => $actorUserId,
                    'updated_at' => $now,
                ]);

            $order->status = ReservationOrderStatus::Cancelled;
            $order->updated_by = $actorUserId;
            $order->updated_at = $now;
            $order->save();
        }
    }

    /**
     * @param array<int,int> $tableIds
     */
    private function releaseTables(array $tableIds, ?int $staffUserId = null, ?int $reservationId = null): void
    {
        $this->tableStateService->releaseTablesSafely($tableIds, Carbon::now('UTC'), $staffUserId, [
            'reservation_id' => $reservationId,
            'source' => 'staff_checkout_refund',
            'reason' => 'refund_cancel_after_payment',
        ]);
    }

    /**
     * @param array<string,float> $summary
     * @param array<int,int> $refundPaymentIds
     * @return array<string,mixed>
     */
    private function buildRefundResponse(Reservation $reservation, array $summary, array $refundPaymentIds, float $refundAmountThisCall, string $refundScope, bool $cancelled, string $currency): array
    {
        return $this->checkoutResponseFactory->buildRefundResponse(
            reservation: $reservation,
            summary: $summary,
            refundPaymentIds: $refundPaymentIds,
            refundAmountThisCall: $refundAmountThisCall,
            refundScope: $refundScope,
            cancelled: $cancelled,
            currency: $currency,
        );
    }


    /**
     * @param Collection<int,ReservationOrder> $orders
     */
    private function releaseAppliedVoucherLocked(Reservation $reservation, Collection $orders, ?int $staffUserId = null, bool $detachReservation = true): float
    {
        $userVoucherId = (int) ($reservation->applied_user_voucher_id ?? 0);
        if ($userVoucherId <= 0) {
            return 0.0;
        }

        /** @var UserVoucher|null $userVoucher */
        $userVoucher = UserVoucher::query()
            ->with('voucher')
            ->where('user_voucher_id', $userVoucherId)
            ->lockForUpdate()
            ->first();

        return ReservationVoucherLifecycleSupport::releaseVoucherAndDiscountSnapshot(
            reservation: $reservation,
            userVoucher: $userVoucher,
            orders: $orders,
            reservationFinancialSyncService: $this->reservationFinancialSyncService,
            actorUserId: $staffUserId,
            detachReservation: $detachReservation,
            persistReservation: false,
        );
    }

    /**
     * @param Collection<int,ReservationOrder> $orders
     */
    private function consumeAppliedVoucherLocked(Reservation $reservation, Collection $orders, ?int $staffUserId = null): void
    {
        $userVoucherId = (int) ($reservation->applied_user_voucher_id ?? 0);
        if ($userVoucherId <= 0) {
            return;
        }

        /** @var UserVoucher|null $userVoucher */
        $userVoucher = UserVoucher::query()
            ->with('voucher')
            ->where('user_voucher_id', $userVoucherId)
            ->lockForUpdate()
            ->first();

        if (! $userVoucher || (bool) ($userVoucher->is_used ?? false)) {
            return;
        }

        $usedAmount = $userVoucher->voucher
            ? round((float) (VoucherRedemptionSupport::calculateDiscount($userVoucher->voucher, $orders)['discount_amount'] ?? 0.0), 2)
            : 0.0;

        $userVoucher->is_used = true;
        $userVoucher->used_date = Carbon::now('UTC');
        $userVoucher->used_reservation_id = (int) $reservation->reservation_id;
        $userVoucher->used_amount = $usedAmount;
        $userVoucher->lock_token = null;
        $userVoucher->locked_until = null;
        $userVoucher->updated_by = $staffUserId;
        $userVoucher->save();
    }

    private function currentVoucherDiscountAmount(int $reservationId, bool $lock = false): float
    {
        /** @var Reservation|null $reservation */
        $reservationQuery = Reservation::query()
            ->with(['appliedUserVoucher.voucher'])
            ->where('reservation_id', $reservationId);

        if ($lock) {
            $reservationQuery->lockForUpdate();
        }

        $reservation = $reservationQuery->first();
        if (! $reservation || ! $reservation->appliedUserVoucher || ! $reservation->appliedUserVoucher->voucher) {
            return 0.0;
        }

        if ($lock) {
            UserVoucher::query()
                ->where('user_voucher_id', (int) $reservation->appliedUserVoucher->user_voucher_id)
                ->lockForUpdate()
                ->first();
        }

        $ordersQuery = ReservationOrder::query()
            ->where('reservation_id', $reservationId)
            ->whereIn('status', [ReservationOrderStatus::Active->value, ReservationOrderStatus::Completed->value])
            ->with('items');

        if ($lock) {
            $ordersQuery->lockForUpdate();
        }

        $orders = $ordersQuery->get();

        return round((float) (VoucherRedemptionSupport::calculateDiscount($reservation->appliedUserVoucher->voucher, $orders)['discount_amount'] ?? 0.0), 2);
    }


    private function currentLoyaltyDiscountAmount(int $reservationId): float
    {
        $transactions = DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('txn_type', 'Redeem')
                        ->where('reason', 'like', 'redeem.apply%');
                })->orWhere(function ($q) {
                    $q->where('txn_type', 'Adjust')
                        ->where('reason', 'like', 'redeem.release%');
                });
            })
            ->orderBy('txn_id')
            ->get(['txn_type', 'amount_basis']);

        $amount = 0.0;
        foreach ($transactions as $tx) {
            $basis = round(max(0.0, (float) ($tx->amount_basis ?? 0.0)), 2);
            if ($basis <= 0.0001) {
                continue;
            }

            if ((string) ($tx->txn_type ?? '') === 'Redeem') {
                $amount += $basis;
                continue;
            }

            $amount -= $basis;
        }

        return round(max(0.0, $amount), 2);
    }


    private function paymentReplayCacheKey(int $orderId, string $idempotencyKey): string
    {
        return sprintf('booking:idem:staff_pay:%s:%d', trim($idempotencyKey), $orderId);
    }

    private function paymentReplayCacheGet(int $orderId, string $idempotencyKey): mixed
    {
        $key = $this->paymentReplayCacheKey($orderId, $idempotencyKey);

        try {
            return Cache::store('redis')->get($key);
        } catch (Throwable) {
            $defaultStore = (string) config('cache.default', 'array');

            try {
                return Cache::store($defaultStore)->get($key);
            } catch (Throwable) {
                return Cache::store('array')->get($key);
            }
        }
    }

    private function paymentReplayCachePut(int $orderId, string $idempotencyKey, string $value, int $ttlSeconds = 3600): void
    {
        $key = $this->paymentReplayCacheKey($orderId, $idempotencyKey);

        try {
            Cache::store('redis')->put($key, $value, $ttlSeconds);

            return;
        } catch (Throwable) {
            $defaultStore = (string) config('cache.default', 'array');

            try {
                Cache::store($defaultStore)->put($key, $value, $ttlSeconds);

                return;
            } catch (Throwable) {
                Cache::store('array')->put($key, $value, $ttlSeconds);
            }
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findExistingCheckoutReplay(int $orderId, string $idempotencyKey, string $fallbackCurrency = 'VND'): ?array
    {
        $order = $this->findExistingOrderPaymentReplay($orderId, $idempotencyKey);
        if (! $order) {
            return null;
        }

        return $this->buildCheckoutResponse($order, $fallbackCurrency);
    }

    private function findExistingOrderPaymentReplay(int $orderId, string $idempotencyKey): ?ReservationOrder
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            return null;
        }

        /** @var ReservationOrder|null $order */
        $order = ReservationOrder::query()->where('order_id', $orderId)->first(['order_id', 'reservation_id']);
        if (! $order) {
            return null;
        }

        $exists = Payment::query()
            ->where('reservation_id', (int) $order->reservation_id)
            ->where('payment_type', 'Final')
            ->where(function ($query) use ($idempotencyKey) {
                $query->where('provider_response_json->request_idempotency_key', $idempotencyKey)
                    ->orWhere('idempotency_key', $idempotencyKey);
            })
            ->exists();

        if (! $exists) {
            return null;
        }

        $hydrated = $this->findSettlementReadOrder((int) $order->order_id);

        return $hydrated instanceof ReservationOrder
            ? $this->attachTotals($hydrated)
            : null;
    }

    private function buildCheckoutRequestFingerprint(
        string $paymentMethod,
        float $paidAmount,
        string $currency,
        string $transactionCode,
        string $paymentProvider,
        string $notes,
        ?float $discountAmount = null
    ): string {
        $payload = [
            'payment_method' => trim($paymentMethod),
            'paid_amount' => round(max(0.0, $paidAmount), 2),
            'currency' => trim($currency) !== '' ? trim($currency) : 'VND',
            'transaction_code' => trim($transactionCode),
            'payment_provider' => trim($paymentProvider) !== '' ? trim($paymentProvider) : 'Other',
            'notes' => trim($notes),
            'discount_amount' => $discountAmount !== null ? round(max(0.0, $discountAmount), 2) : null,
        ];

        return sha1((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function assertCheckoutReplayMatchesRequest(int $orderId, string $idempotencyKey, string $requestFingerprint): void
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            return;
        }

        /** @var ReservationOrder|null $order */
        $order = ReservationOrder::query()->where('order_id', $orderId)->first();
        if (! $order instanceof ReservationOrder) {
            return;
        }

        $existing = Payment::query()
            ->where('reservation_id', (int) $order->reservation_id)
            ->where('payment_type', 'Final')
            ->where(function ($query) use ($idempotencyKey) {
                $query->where('provider_response_json->request_idempotency_key', $idempotencyKey)
                    ->orWhere('idempotency_key', $idempotencyKey);
            })
            ->orderBy('payment_id')
            ->first();

        if (! $existing instanceof Payment) {
            return;
        }

        $meta = is_array($existing->provider_response_json) ? $existing->provider_response_json : [];
        $recordedFingerprint = trim((string) ($meta['request_fingerprint'] ?? ''));
        if ($recordedFingerprint !== '' && ! hash_equals($recordedFingerprint, trim($requestFingerprint))) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['This idempotency key is already bound to a different payment request payload.'],
            ]);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findExistingRefundReplay(int $reservationId, string $idempotencyKey): ?array
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            return null;
        }

        /** @var Collection<int,Payment> $refundPayments */
        $refundPayments = Payment::query()
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Refund')
            ->where(function ($query) use ($idempotencyKey) {
                $query->where('provider_response_json->request_idempotency_key', $idempotencyKey)
                    ->orWhere('idempotency_key', $idempotencyKey)
                    ->orWhere('idempotency_key', 'like', $idempotencyKey . '.%')
                    ->orWhere('idempotency_key', 'like', $idempotencyKey . ':%');
            })
            ->orderBy('payment_id')
            ->get();

        if ($refundPayments->isEmpty()) {
            return null;
        }

        /** @var Reservation $reservation */
        $reservation = Reservation::query()
            ->with(['user', 'tables', 'orders.items.item', 'payments', 'appliedUserVoucher.voucher'])
            ->findOrFail($reservationId);

        $summary = PaymentSummary::fromPayments($reservation->payments);
        $firstRefund = $refundPayments->first();
        $firstMeta = is_array($firstRefund?->provider_response_json) ? $firstRefund->provider_response_json : [];

        return $this->buildRefundResponse(
            reservation: $reservation,
            summary: $summary,
            refundPaymentIds: $refundPayments->pluck('payment_id')->map(fn ($id) => (int) $id)->all(),
            refundAmountThisCall: round((float) $refundPayments->sum(fn (Payment $payment) => (float) ($payment->amount ?? 0.0)), 2),
            refundScope: (string) ($firstMeta['requested_refund_scope'] ?? 'all'),
            cancelled: (bool) ($firstMeta['cancel_after_payment'] ?? false),
            currency: (string) ($firstRefund?->currency ?? $reservation->bill_currency ?? 'VND')
        );
    }

    private function buildRefundRequestFingerprint(
        string $paymentMethod,
        string $refundScope,
        ?float $refundAmount,
        string $currency,
        string $transactionCode,
        string $paymentProvider,
        string $notes,
        ?string $reason,
        bool $cancelAfterPayment,
        ?string $cancelReason
    ): string {
        $payload = [
            'payment_method' => trim($paymentMethod),
            'refund_scope' => strtolower(trim($refundScope)),
            'refund_amount' => $refundAmount !== null ? round(max(0.0, $refundAmount), 2) : null,
            'currency' => trim($currency) !== '' ? trim($currency) : 'VND',
            'transaction_code' => trim($transactionCode),
            'payment_provider' => trim($paymentProvider) !== '' ? trim($paymentProvider) : 'Other',
            'notes' => trim($notes),
            'reason' => $reason !== null ? trim($reason) : null,
            'cancel_after_payment' => $cancelAfterPayment,
            'cancel_reason' => $cancelReason !== null ? trim($cancelReason) : null,
        ];

        return sha1((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function assertRefundReplayMatchesRequest(int $reservationId, string $idempotencyKey, string $requestFingerprint): void
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            return;
        }

        $existing = Payment::query()
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Refund')
            ->where(function ($query) use ($idempotencyKey) {
                $query->where('provider_response_json->request_idempotency_key', $idempotencyKey)
                    ->orWhere('idempotency_key', $idempotencyKey)
                    ->orWhere('idempotency_key', 'like', $idempotencyKey . '.%')
                    ->orWhere('idempotency_key', 'like', $idempotencyKey . ':%');
            })
            ->orderBy('payment_id')
            ->first();

        if (! $existing instanceof Payment) {
            return;
        }

        $meta = is_array($existing->provider_response_json) ? $existing->provider_response_json : [];
        $recordedFingerprint = trim((string) ($meta['request_fingerprint'] ?? ''));
        if ($recordedFingerprint !== '' && ! hash_equals($recordedFingerprint, trim($requestFingerprint))) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['This idempotency key is already bound to a different refund request payload.'],
            ]);
        }
    }

    private function isDuplicatePaymentIdempotencyConstraint(QueryException $e): bool
    {
        return DatabaseWriteConflictMapper::isPaymentIdempotencyConflict($e);
    }

    private function throwIfDuplicatePaymentConstraint(QueryException $e): void
    {
        $mapped = DatabaseWriteConflictMapper::toValidationException($e);
        if ($mapped !== null) {
            throw $mapped;
        }

        $message = strtolower((string) $e->getMessage());
        if (
            str_contains($message, 'uq_payments__transaction_code')
            || str_contains($message, 'uq_payments_transaction_code')
            || DatabaseWriteConflictMapper::isPaymentProviderTransactionConflict($e)
        ) {
            throw ValidationException::withMessages(['transaction_code' => 'Mã giao dịch này đã tồn tại cho payment provider hiện tại. Vui lòng kiểm tra lại đối soát hoặc dùng mã khác.']);
        }
        if (DatabaseWriteConflictMapper::isPaymentIdempotencyConflict($e)) {
            throw ValidationException::withMessages(['idempotency_key' => 'idempotency key already used.']);
        }
    }
}
