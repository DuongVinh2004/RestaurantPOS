<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\UseCases\Capture;

use App\Modules\Billing\Application\UseCases\Previews\SettlementAmountCalculator;
use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Policies\PaymentStatusTransitionPolicy;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\Reservations\Application\Services\ReservationDepositReadService;
use App\Modules\Reservations\Application\Services\ReservationDepositRealtimePublisher;
use App\Modules\Reservations\Application\Services\ReservationDepositSelfServiceStateService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Support\AuditEvent;
use App\Support\DatabaseWriteConflictMapper;
use App\SharedKernel\Money\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffReservationDepositService
{
    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
        private readonly ?SettlementAmountCalculator $settlementAmountCalculator = null,
        private readonly ?ReservationDepositSelfServiceStateService $depositSelfServiceStateService = null,
        private readonly ?ReservationDepositReadService $depositReadService = null,
        private readonly ?ReservationDepositRealtimePublisher $depositRealtimePublisher = null,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function previewDeposit(int $reservationId, string $fallbackCurrency = 'VND'): array
    {
        $reservation = $this->loadReservationSnapshot($reservationId);

        return $this->buildDepositResponse($reservation, null, $fallbackCurrency);
    }

    /**
     * @return array<string,mixed>
     */
    public function payDeposit(
        int $reservationId,
        float $amount,
        string $paymentMethod,
        string $currency = 'VND',
        string $transactionCode = '',
        string $paymentProvider = '',
        string $notes = '',
        ?int $expectedRowVersion = null,
        ?int $staffUserId = null,
        string $idempotencyKey = ''
    ): array {
        $paymentMethod = trim($paymentMethod);
        if ($paymentMethod === '') {
            throw ValidationException::withMessages(['payment_method' => 'payment_method is required']);
        }

        $normalizedCurrency = trim($currency) !== '' ? trim($currency) : 'VND';
        $trimmedNotes = trim($notes);
        $idempotencyKey = trim($idempotencyKey);
        $this->assertPaymentIdempotencyKeyFitsStorage($idempotencyKey);
        $requestFingerprint = $idempotencyKey !== ''
            ? $this->buildDepositPaymentRequestFingerprint(
                paymentMethod: $paymentMethod,
                amount: $amount,
                currency: $normalizedCurrency,
                transactionCode: $transactionCode,
                paymentProvider: $paymentProvider,
                notes: $trimmedNotes,
            )
            : null;

        if ($idempotencyKey !== '') {
            $this->assertDepositPaymentReplayMatchesRequest($reservationId, $idempotencyKey, $requestFingerprint ?? '');
            $replayed = $this->findExistingDepositPaymentReplay($reservationId, $idempotencyKey, $normalizedCurrency);
            if ($replayed !== null) {
                return $replayed;
            }
        }

        $context = $this->getReservationLockContext($reservationId);

        $depositPaidRealtimeContext = null;

        try {
            $result = $this->locks->withLockKeys($context['lock_keys'], function () use (
                $reservationId,
                $amount,
                $paymentMethod,
                $normalizedCurrency,
                $transactionCode,
                $paymentProvider,
                $trimmedNotes,
                $expectedRowVersion,
                $staffUserId,
                $idempotencyKey,
                $requestFingerprint,
                &$depositPaidRealtimeContext
            ) {
                return DB::transaction(function () use (
                    $reservationId,
                    $amount,
                    $paymentMethod,
                    $normalizedCurrency,
                    $transactionCode,
                    $paymentProvider,
                    $trimmedNotes,
                    $expectedRowVersion,
                    $staffUserId,
                    $idempotencyKey,
                    $requestFingerprint,
                    &$depositPaidRealtimeContext
                ) {
                    /** @var Reservation $reservation */
                    $reservation = Reservation::query()
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->assertExpectedReservationRowVersion($reservation, $expectedRowVersion);
                    $this->assertReservationCanCollectDeposit($reservation);

                    /** @var Collection<int,Payment> $payments */
                    $payments = Payment::query()
                        ->with('refundOfPayment')
                        ->where('reservation_id', $reservationId)
                        ->orderBy('payment_id')
                        ->lockForUpdate()
                        ->get();

                    $paymentSummary = PaymentSummary::fromPayments($payments);
                    if (Money::minorUnits($paymentSummary['final_net_amount'] ?? 0, true) > 0) {
                        throw ValidationException::withMessages([
                            'reservation' => ['Cannot collect deposit after final payment has been recorded.'],
                        ]);
                    }

                    $existingCurrency = $this->settlementAmountCalculator()->assertPaymentsSingleCurrency($payments, null, 'currency');
                    $reservationCurrency = trim((string) ($reservation->bill_currency ?? ''));
                    $fallbackCurrency = $reservationCurrency !== '' ? $reservationCurrency : ($existingCurrency ?: 'VND');
                    $paymentCurrency = $this->settlementAmountCalculator()->normalizeCurrencyCode($normalizedCurrency, $fallbackCurrency);
                    if ($existingCurrency !== null && $paymentCurrency !== $existingCurrency) {
                        throw ValidationException::withMessages([
                            'currency' => ['Payment currency must match the reservation payment currency.'],
                        ]);
                    }
                    if ($reservationCurrency !== '' && $paymentCurrency !== $reservationCurrency) {
                        throw ValidationException::withMessages([
                            'currency' => ['Payment currency must match reservation bill currency.'],
                        ]);
                    }

                    $requiredAmountMinor = Money::minorUnits($reservation->deposit_required_amount ?? 0, true);
                    $requiredAmount = Money::minorToFloat($requiredAmountMinor);
                    if ($requiredAmountMinor <= 0) {
                        throw ValidationException::withMessages([
                            'reservation' => ['This reservation does not require a deposit.'],
                        ]);
                    }

                    $outstandingAmount = $this->computeOutstandingDeposit($reservation, $paymentSummary);
                    $outstandingAmountMinor = Money::minorUnits($outstandingAmount, true);
                    if ($outstandingAmountMinor <= 0) {
                        throw ValidationException::withMessages([
                            'amount' => ['Deposit has already been fully paid.'],
                        ]);
                    }

                    $normalizedAmountMinor = Money::minorUnits($amount, true);
                    $normalizedAmount = Money::minorToFloat($normalizedAmountMinor);
                    if ($normalizedAmountMinor <= 0) {
                        throw ValidationException::withMessages([
                            'amount' => ['amount must be greater than 0.'],
                        ]);
                    }
                    if ($normalizedAmountMinor > $outstandingAmountMinor) {
                        throw ValidationException::withMessages([
                            'amount' => ['amount cannot exceed outstanding deposit amount.'],
                        ]);
                    }

                    $payment = new Payment;
                    $payment->reservation_id = $reservationId;
                    $payment->amount = Money::formatMinor($normalizedAmountMinor);
                    $payment->currency = $paymentCurrency;
                    $payment->payment_method = $paymentMethod;
                    $payment->payment_provider = trim($paymentProvider) !== '' ? trim($paymentProvider) : 'Other';
                    $payment->payment_type = 'Deposit';
                    $payment->transaction_code = trim($transactionCode) !== '' ? trim($transactionCode) : null;
                    $payment->idempotency_key = $idempotencyKey !== '' ? $idempotencyKey : null;
                    $payment->created_by = $staffUserId;
                    $payment->notes = $trimmedNotes !== '' ? $trimmedNotes : null;
                    $payment->paid_at = Carbon::now('UTC');
                    $payment->status = PaymentStatusTransitionPolicy::captureStatusForAppliedAmount(
                        Money::minorToFloat($normalizedAmountMinor),
                        Money::minorToFloat($outstandingAmountMinor)
                    );
                    $payment->provider_response_json = [
                        'action' => 'deposit_capture',
                        'request_idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                        'request_fingerprint' => $requestFingerprint !== null && trim($requestFingerprint) !== '' ? trim($requestFingerprint) : null,
                        'reservation_id' => $reservationId,
                        'payment_provider' => trim($paymentProvider) !== '' ? trim($paymentProvider) : 'Other',
                        'payment_method' => $paymentMethod,
                    ];

                    try {
                        $payment->save();
                    } catch (QueryException $e) {
                        if ($idempotencyKey !== '' && $this->isDuplicatePaymentIdempotencyConstraint($e)) {
                            $existing = Payment::query()
                                ->where('reservation_id', $reservationId)
                                ->where('payment_type', 'Deposit')
                                ->where('idempotency_key', $idempotencyKey)
                                ->orderBy('payment_id')
                                ->first();

                            if ($existing) {
                                $meta = is_array($existing->provider_response_json) ? $existing->provider_response_json : [];
                                $recordedFingerprint = trim((string) ($meta['request_fingerprint'] ?? ''));
                                if ($requestFingerprint !== null && $recordedFingerprint !== '' && ! hash_equals($recordedFingerprint, $requestFingerprint)) {
                                    throw ValidationException::withMessages([
                                        'idempotency_key' => ['This idempotency key is already bound to a different deposit payment request payload.'],
                                    ]);
                                }
                            }

                            $replayed = $this->findExistingDepositPaymentReplay($reservationId, $idempotencyKey, $paymentCurrency);
                            if ($replayed !== null) {
                                return $replayed;
                            }
                        }

                        $this->throwIfDuplicatePaymentConstraint($e);
                        throw $e;
                    }

                    /** @var Collection<int,Payment> $paymentsAfter */
                    $paymentsAfter = Payment::query()
                        ->with('refundOfPayment')
                        ->where('reservation_id', $reservationId)
                        ->orderBy('payment_id')
                        ->lockForUpdate()
                        ->get();
                    $this->settlementAmountCalculator()->assertPaymentsSingleCurrency($paymentsAfter, $paymentCurrency, 'currency');
                    $summaryAfter = PaymentSummary::fromPayments($paymentsAfter);

                    if ($reservationCurrency === '') {
                        $reservation->bill_currency = $paymentCurrency;
                    }
                    $reservation->updated_by = $staffUserId;
                    $this->reservationFinancialSyncService->syncDepositSnapshot($reservation, $summaryAfter, false);
                    $reservation->save();

                    AuditEvent::info('staff.reservation.deposit_paid', [
                        'reservation_id' => $reservationId,
                        'payment_id' => (int) $payment->payment_id,
                        'payment_status' => (string) ($payment->status?->value ?? $payment->status),
                        'deposit_required_amount' => $requiredAmount,
                        'deposit_paid_amount' => (float) ($reservation->deposit_paid_amount ?? 0.0),
                        'deposit_outstanding_amount' => $this->computeOutstandingDeposit($reservation, $summaryAfter),
                        'currency' => $paymentCurrency,
                        'actor_user_id' => $staffUserId,
                    ]);

                    $snapshotReservation = $this->loadReservationSnapshot($reservationId);
                    $createdPayment = Payment::query()
                        ->with('refundOfPayment')
                        ->findOrFail($payment->payment_id);
                    $depositPaidRealtimeContext ??= [
                        'reservation' => $snapshotReservation,
                        'payment' => $createdPayment,
                        'payment_summary' => $summaryAfter,
                    ];

                    return $this->buildDepositResponse($snapshotReservation, $createdPayment, $paymentCurrency);
                });
            });

            $this->publishDepositPaidRealtimeEvent($depositPaidRealtimeContext);

            return $result;
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }
    }

    private function loadReservationSnapshot(int $reservationId): Reservation
    {
        /** @var Reservation $reservation */
        $reservation = Reservation::query()
            ->with(['user', 'tables', 'orders.items.item', 'payments.refundOfPayment', 'appliedUserVoucher.voucher'])
            ->findOrFail($reservationId);

        return $reservation;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildDepositResponse(Reservation $reservation, ?Payment $payment = null, string $fallbackCurrency = 'VND'): array
    {
        /** @var Collection<int,Payment> $payments */
        $payments = $reservation->relationLoaded('payments')
            ? $reservation->payments
            : Payment::query()->with('refundOfPayment')->where('reservation_id', $reservation->reservation_id)->orderBy('payment_id')->get();

        $summary = PaymentSummary::fromPayments($payments);
        $outstandingAmount = $this->computeOutstandingDeposit($reservation, $summary);
        $outstandingAmountMinor = Money::minorUnits($outstandingAmount, true);
        $status = (string) ($reservation->status?->value ?? $reservation->status);
        $canAcceptPayment = in_array($status, ReservationStatus::activeDbValues(), true)
            && $outstandingAmountMinor > 0
            && Money::minorUnits($summary['final_net_amount'] ?? 0, true) <= 0;
        $paymentSessions = $reservation->relationLoaded('depositPaymentSessions')
            ? $reservation->depositPaymentSessions
            : $reservation->depositPaymentSessions()->orderByDesc('deposit_payment_session_id')->get();

        $depositSnapshot = $this->depositReadService()->buildSnapshot(
            $reservation,
            $payments,
            $paymentSessions,
            $summary,
            $fallbackCurrency,
            true,
        );
        $depositSnapshot['can_accept_payment'] = $canAcceptPayment;

        return [
            'reservation' => $reservation,
            'payment' => $payment,
            'deposit' => $depositSnapshot,
        ];
    }

    /**
     * @param  array<string,mixed>  $paymentSummary
     */
    private function computeOutstandingDeposit(Reservation $reservation, array $paymentSummary): float
    {
        $requiredMinor = Money::minorUnits($reservation->deposit_required_amount ?? 0, true);
        $paidMinor = Money::minorUnits($paymentSummary['deposit_net_amount'] ?? $reservation->deposit_paid_amount ?? 0, true);

        return Money::minorToFloat(max(0, $requiredMinor - $paidMinor));
    }

    private function assertReservationCanCollectDeposit(Reservation $reservation): void
    {
        $status = (string) ($reservation->status?->value ?? $reservation->status);
        if (! in_array($status, ReservationStatus::activeDbValues(), true)) {
            throw ValidationException::withMessages([
                'reservation' => ['Only active reservations (Confirmed or Reserved) can collect deposit payments.'],
            ]);
        }
    }

    private function assertExpectedReservationRowVersion(Reservation $reservation, ?int $expectedRowVersion): void
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

    private function getReservationLockContext(int $reservationId): array
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

        $lockKeys = [config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation').':'.$reservationId];
        foreach ($tableIds as $tableId) {
            $lockKeys[] = config('booking.reservation_lock_prefix', 'booking:lock:table').':'.$tableId;
        }

        return [
            'reservation_id' => $reservationId,
            'table_ids' => $tableIds,
            'lock_keys' => $lockKeys,
        ];
    }

    private function buildDepositPaymentRequestFingerprint(
        string $paymentMethod,
        float $amount,
        string $currency,
        string $transactionCode,
        string $paymentProvider,
        string $notes
    ): string {
        $payload = [
            'payment_method' => trim($paymentMethod),
            'amount' => Money::toFloat($amount, true),
            'currency' => trim($currency) !== '' ? trim($currency) : 'VND',
            'transaction_code' => trim($transactionCode),
            'payment_provider' => trim($paymentProvider) !== '' ? trim($paymentProvider) : 'Other',
            'notes' => trim($notes),
        ];

        return sha1((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function assertDepositPaymentReplayMatchesRequest(int $reservationId, string $idempotencyKey, string $requestFingerprint): void
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            return;
        }

        $existing = Payment::query()
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Deposit')
            ->where(function ($query) use ($idempotencyKey) {
                $query->where('provider_response_json->request_idempotency_key', $idempotencyKey)
                    ->orWhere('idempotency_key', $idempotencyKey);
            })
            ->orderBy('payment_id')
            ->first();

        if (! $existing) {
            return;
        }

        $meta = is_array($existing->provider_response_json) ? $existing->provider_response_json : [];
        $recordedFingerprint = trim((string) ($meta['request_fingerprint'] ?? ''));
        if ($recordedFingerprint !== '' && ! hash_equals($recordedFingerprint, trim($requestFingerprint))) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['This idempotency key is already bound to a different deposit payment request payload.'],
            ]);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findExistingDepositPaymentReplay(int $reservationId, string $idempotencyKey, string $fallbackCurrency = 'VND'): ?array
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            return null;
        }

        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->with('refundOfPayment')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Deposit')
            ->where(function ($query) use ($idempotencyKey) {
                $query->where('provider_response_json->request_idempotency_key', $idempotencyKey)
                    ->orWhere('idempotency_key', $idempotencyKey);
            })
            ->orderBy('payment_id')
            ->first();

        if (! $payment) {
            return null;
        }

        $reservation = $this->loadReservationSnapshot($reservationId);

        return $this->buildDepositResponse($reservation, $payment, $fallbackCurrency);
    }

    private function settlementAmountCalculator(): SettlementAmountCalculator
    {
        return $this->settlementAmountCalculator ?? new SettlementAmountCalculator;
    }

    private function depositSelfServiceStateService(): ReservationDepositSelfServiceStateService
    {
        return $this->depositSelfServiceStateService ?? new ReservationDepositSelfServiceStateService;
    }

    private function depositReadService(): ReservationDepositReadService
    {
        return $this->depositReadService ?? app(ReservationDepositReadService::class);
    }

    private function isDuplicatePaymentIdempotencyConstraint(QueryException $e): bool
    {
        return DatabaseWriteConflictMapper::isPaymentIdempotencyConflict($e);
    }

    private function assertPaymentIdempotencyKeyFitsStorage(string $idempotencyKey): void
    {
        if ($idempotencyKey === '') {
            return;
        }

        if (mb_strlen($idempotencyKey) > Payment::IDEMPOTENCY_KEY_MAX_LENGTH) {
            throw ValidationException::withMessages([
                'idempotency_key' => [
                    sprintf(
                        'Idempotency-Key may not exceed %d characters for payment capture.',
                        Payment::IDEMPOTENCY_KEY_MAX_LENGTH,
                    ),
                ],
            ]);
        }
    }

    private function throwIfDuplicatePaymentConstraint(QueryException $e): void
    {
        $mapped = DatabaseWriteConflictMapper::toValidationException($e);
        if ($mapped !== null) {
            throw $mapped;
        }

        $message = (string) $e->getMessage();
        if (DatabaseWriteConflictMapper::isPaymentProviderTransactionConflict($e)
            || str_contains($message, 'uq_payments__transaction_code')
            || str_contains($message, 'uq_payments_transaction_code')
        ) {
            throw ValidationException::withMessages(['transaction_code' => 'transaction_code already exists.']);
        }
        if ($this->isDuplicatePaymentIdempotencyConstraint($e)) {
            throw ValidationException::withMessages(['idempotency_key' => 'idempotency key already used.']);
        }
    }

    /**
    /**
     * @param  array{reservation:Reservation,payment:Payment,payment_summary:array<string,mixed>}|null  $context
     */
    private function publishDepositPaidRealtimeEvent(?array $context): void
    {
        if ($context === null || $context === []) {
            return;
        }

        $this->depositRealtimePublisher()->publishDepositPaid(
            $context['reservation'],
            $context['payment'],
            $context['payment_summary']
        );
    }

    private function depositRealtimePublisher(): ReservationDepositRealtimePublisher
    {
        return $this->depositRealtimePublisher ?? app(ReservationDepositRealtimePublisher::class);
    }
}
