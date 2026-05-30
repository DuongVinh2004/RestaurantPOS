<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\UseCases\PaymentSessions;

use App\Enums\PaymentSessionScope;
use App\Enums\ReservationDepositPaymentSessionStatus;
use App\Enums\ReservationDepositPaymentSettlementStatus;
use App\Enums\ReservationStatus;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\Payments\Application\Workflows\ReservationDepositPaymentSessionLifecycleWorkflow;
use App\Modules\Payments\Domain\Guards\PaymentSessionScopeGuard;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\ReservationDepositPaymentSession;
use App\Modules\Payments\Infrastructure\Integrations\CustomerDepositPayment\CustomerDepositPaymentProviderRegistry;
use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\PaymentProviderRolloutConfig;
use App\Modules\Payments\Infrastructure\Internal\PaymentProviderPayloadSanitizer;
use App\Modules\Reservations\Application\Services\ReservationDepositReadService;
use App\Modules\Reservations\Application\Services\ReservationDepositRealtimePublisher;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use App\Support\AuditEvent;
use App\Support\ValidationExceptionFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerReservationDepositPaymentService
{
    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly CustomerDepositPaymentProviderRegistry $providers,
        private readonly ReservationDepositPaymentService $depositPaymentService,
        private readonly ReservationDepositReadService $depositReadService,
        private readonly ReservationDepositPaymentSessionLifecycleWorkflow $sessionLifecycle,
        private readonly ReservationSessionAccessWorkflow $customerSessionAccessService,
        private readonly PaymentSessionScopeGuard $sessionScopeGuard,
        private readonly PaymentProviderRolloutConfig $paymentProviderRolloutConfig,
        private readonly ?ReservationDepositRealtimePublisher $depositRealtimePublisher = null,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     * @return array{reservation:Reservation,deposit:array<string,mixed>,payment_session:ReservationDepositPaymentSession}
     */
    public function createSession(int $reservationId, array $payload, ?int $customerUserId, ?string $sessionId, string $idempotencyKey = ''): array
    {
        return $this->locks->withReservationLock($reservationId, function () use ($reservationId, $payload, $customerUserId, $sessionId, $idempotencyKey) {
            return DB::transaction(function () use ($reservationId, $payload, $customerUserId, $sessionId, $idempotencyKey) {
                $reservation = $this->findAccessibleReservationForUpdate($reservationId, $customerUserId, $sessionId);
                $effectiveCustomerUserId = $this->resolveEffectiveCustomerUserId($reservation, $customerUserId, $sessionId);
                $this->assertReservationRowVersion($reservation, (int) $payload['row_version']);
                $requestFingerprint = $idempotencyKey !== ''
                    ? $this->buildCreateSessionRequestFingerprint($payload)
                    : null;

                if ($idempotencyKey !== '') {
                    $this->assertCreateSessionReplayMatchesRequest($reservationId, $effectiveCustomerUserId, $idempotencyKey, $requestFingerprint ?? '');
                    $replayed = ReservationDepositPaymentSession::query()
                        ->where('reservation_id', $reservationId)
                        ->where('customer_user_id', $effectiveCustomerUserId)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    if ($replayed instanceof ReservationDepositPaymentSession) {
                        return $this->buildResponse($reservation->fresh(), $replayed->fresh());
                    }
                }

                $lockedPayments = $this->loadLockedPayments($reservationId);
                $currency = $this->depositPaymentService->resolveCurrency(
                    $reservation,
                    $lockedPayments,
                    (string) ($payload['currency'] ?? '')
                );
                $summary = PaymentSummary::fromPayments($lockedPayments);
                $amount = $this->resolveRequestedAmount($reservation, $summary, $payload);
                $this->assertCustomerSelfPayReady(isset($payload['provider_code']) ? (string) $payload['provider_code'] : null);

                $provider = $this->providers->resolve(isset($payload['provider_code']) ? (string) $payload['provider_code'] : null);
                $providerSession = $provider->createSession($reservation, $effectiveCustomerUserId, [
                    'payment_method' => (string) ($payload['payment_method'] ?? 'Online'),
                    'currency' => $currency,
                    'amount' => $amount,
                    'notes' => (string) ($payload['notes'] ?? ''),
                    'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                ]);
                $providerCode = (string) ($providerSession['provider_code'] ?? $provider->code());
                $providerSessionCode = (string) ($providerSession['provider_session_code'] ?? '');
                $this->sessionScopeGuard->assertProviderSessionCodeIsAvailable(
                    $providerCode,
                    $providerSessionCode,
                    PaymentSessionScope::Deposit,
                );

                $session = new ReservationDepositPaymentSession;
                $session->reservation_id = (int) $reservation->reservation_id;
                $session->customer_user_id = $effectiveCustomerUserId;
                $session->linked_payment_id = null;
                $session->provider_code = $providerCode;
                $session->provider_session_code = $providerSessionCode;
                $session->provider_payment_code = Arr::get($providerSession, 'provider_payment_code');
                $session->payment_method = trim((string) ($providerSession['payment_method'] ?? $payload['payment_method'] ?? 'Online')) ?: 'Online';
                $session->amount = $amount;
                $session->currency = $currency;
                $session->session_status = (string) ($providerSession['session_status'] ?? 'Created');
                $session->settlement_status = 'NotApplied';
                $session->failure_code = Arr::get($providerSession, 'failure_code');
                $session->failure_message = Arr::get($providerSession, 'failure_message');
                $session->provider_payload_json = $this->mergeRequestMetadata(
                    (array) ($providerSession['provider_payload'] ?? []),
                    $requestFingerprint
                );
                $session->idempotency_key = $idempotencyKey !== '' ? $idempotencyKey : null;
                $session->provider_expires_at = $providerSession['provider_expires_at'] ?? null;
                $session->created_by = $customerUserId;
                $session->updated_by = $customerUserId;
                $session->save();

                AuditEvent::info('customer.deposit_payment_session.created', [
                    'reservation_id' => (int) $reservation->reservation_id,
                    'payment_session_id' => (int) $session->deposit_payment_session_id,
                    'provider_code' => (string) $session->provider_code,
                    'provider_session_code' => (string) $session->provider_session_code,
                    'payment_scope' => PaymentSessionScope::Deposit->value,
                    '_audit' => $this->buildSessionCreatedAuditPayload($reservation, $session, $customerUserId, $sessionId),
                ]);

                return $this->buildResponse($reservation->fresh(), $session->fresh());
            });
        });
    }

    /**
     * @return array{reservation:Reservation,deposit:array<string,mixed>,payment_session:ReservationDepositPaymentSession}
     */
    public function showSession(int $reservationId, int $sessionId, ?int $customerUserId, ?string $sessionAccessId): array
    {
        $reservation = $this->findAccessibleReservation($reservationId, $customerUserId, $sessionAccessId);
        $session = $this->findAccessibleSession($reservationId, $sessionId, $customerUserId, $reservation, $sessionAccessId);

        return $this->buildResponse($reservation, $session);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{reservation:Reservation,deposit:array<string,mixed>,payment_session:ReservationDepositPaymentSession}
     */
    public function refreshSession(int $reservationId, int $sessionId, array $payload, ?int $customerUserId, ?string $sessionAccessId): array
    {
        return $this->mutateSession($reservationId, $sessionId, $payload, $customerUserId, $sessionAccessId, 'refresh');
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{reservation:Reservation,deposit:array<string,mixed>,payment_session:ReservationDepositPaymentSession}
     */
    public function confirmSession(int $reservationId, int $sessionId, array $payload, ?int $customerUserId, ?string $sessionAccessId): array
    {
        return $this->mutateSession($reservationId, $sessionId, $payload, $customerUserId, $sessionAccessId, 'confirm');
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{reservation:Reservation,deposit:array<string,mixed>,payment_session:ReservationDepositPaymentSession}
     */
    private function mutateSession(int $reservationId, int $sessionId, array $payload, ?int $customerUserId, ?string $sessionAccessId, string $mode): array
    {
        $depositPaidRealtimeContext = null;

        $result = $this->locks->withReservationLock($reservationId, function () use ($reservationId, $sessionId, $payload, $customerUserId, $sessionAccessId, $mode, &$depositPaidRealtimeContext) {
            return DB::transaction(function () use ($reservationId, $sessionId, $payload, $customerUserId, $sessionAccessId, $mode, &$depositPaidRealtimeContext) {
                $reservation = $this->findAccessibleReservationForUpdate($reservationId, $customerUserId, $sessionAccessId);
                $session = $this->findAccessibleSessionForUpdate($reservationId, $sessionId, $customerUserId, $reservation, $sessionAccessId);
                $this->assertSessionRowVersion($session, (int) $payload['row_version']);
                $beforeStatus = (string) ($session->session_status?->value ?? $session->session_status);
                $beforeSettlementStatus = (string) ($session->settlement_status?->value ?? $session->settlement_status);
                $beforeLinkedPaymentId = $session->linked_payment_id !== null ? (int) $session->linked_payment_id : null;

                if ($this->sessionIsFinalized($session)) {
                    $lockedPayments = $this->loadLockedPayments($reservationId);
                    if ($this->sessionRequiresSettlementApply($session)) {
                        $appliedPayment = $this->sessionLifecycle->applySucceededSessionIfNeeded($reservation, $session, $lockedPayments, $customerUserId);
                        if ($appliedPayment instanceof Payment) {
                            $depositPaidRealtimeContext ??= [
                                'reservation' => $reservation->fresh(),
                                'payment' => $appliedPayment->fresh(),
                                'payment_summary' => PaymentSummary::fromPayments($lockedPayments),
                            ];
                        }
                    }

                    if ($mode === 'confirm') {
                        AuditEvent::info('customer.deposit_payment_session.confirmed', [
                            'reservation_id' => (int) $reservation->reservation_id,
                            'payment_session_id' => (int) $session->deposit_payment_session_id,
                            'provider_code' => (string) $session->provider_code,
                            'provider_session_code' => (string) $session->provider_session_code,
                            'payment_scope' => PaymentSessionScope::Deposit->value,
                            '_audit' => $this->buildSessionConfirmedAuditPayload(
                                reservation: $reservation,
                                session: $session,
                                beforeStatus: $beforeStatus,
                                beforeSettlementStatus: $beforeSettlementStatus,
                                beforeLinkedPaymentId: $beforeLinkedPaymentId,
                                customerUserId: $customerUserId,
                                sessionId: $sessionAccessId,
                                replayedFinalState: true,
                            ),
                        ]);
                    }

                    return $this->buildResponse($reservation->fresh(), $session->fresh());
                }

                $provider = $this->providers->resolve((string) $session->provider_code);
                $providerResult = $mode === 'confirm'
                    ? $provider->confirmSession($reservation, $session, $payload)
                    : $provider->refreshSession($reservation, $session, $payload);

                $this->sessionLifecycle->applyProviderResult($session, $providerResult, $customerUserId);
                $lockedPayments = $this->loadLockedPayments($reservationId);
                $appliedPayment = $this->sessionLifecycle->applySucceededSessionIfNeeded($reservation, $session, $lockedPayments, $customerUserId);
                if ($appliedPayment instanceof Payment) {
                    $depositPaidRealtimeContext ??= [
                        'reservation' => $reservation->fresh(),
                        'payment' => $appliedPayment->fresh(),
                        'payment_summary' => PaymentSummary::fromPayments($lockedPayments),
                    ];
                }

                if ($mode === 'confirm') {
                    AuditEvent::info('customer.deposit_payment_session.confirmed', [
                        'reservation_id' => (int) $reservation->reservation_id,
                        'payment_session_id' => (int) $session->deposit_payment_session_id,
                        'provider_code' => (string) $session->provider_code,
                        'provider_session_code' => (string) $session->provider_session_code,
                        'payment_scope' => PaymentSessionScope::Deposit->value,
                        '_audit' => $this->buildSessionConfirmedAuditPayload(
                            reservation: $reservation,
                            session: $session,
                            beforeStatus: $beforeStatus,
                            beforeSettlementStatus: $beforeSettlementStatus,
                            beforeLinkedPaymentId: $beforeLinkedPaymentId,
                            customerUserId: $customerUserId,
                            sessionId: $sessionAccessId,
                            replayedFinalState: false,
                        ),
                    ]);
                }

                return $this->buildResponse($reservation->fresh(), $session->fresh());
            });
        });

        $this->publishDepositPaidRealtimeEvent($depositPaidRealtimeContext);

        return $result;
    }

    private function resolveRequestedAmount(Reservation $reservation, array $paymentSummary, array $payload): float
    {
        $this->assertReservationSupportsCustomerDepositPayment($reservation, $paymentSummary);

        $outstanding = $this->calculateOutstandingDeposit($reservation, $paymentSummary);
        $outstandingMinor = Money::minorUnits($outstanding, true);
        $requestedMinor = array_key_exists('amount', $payload) && $payload['amount'] !== null
            ? Money::minorUnits($payload['amount'], true)
            : $outstandingMinor;

        if ($requestedMinor <= 0) {
            throw ValidationExceptionFactory::make([
                'amount' => ['Deposit payment amount must be greater than 0.'],
            ]);
        }

        if ($requestedMinor > $outstandingMinor) {
            throw ValidationExceptionFactory::make([
                'amount' => ['Deposit payment amount exceeds the outstanding deposit balance.'],
            ]);
        }

        return Money::minorToFloat($requestedMinor);
    }

    private function assertReservationSupportsCustomerDepositPayment(Reservation $reservation, array $paymentSummary): void
    {
        if (! ReservationStatus::isActiveDbValue((string) ($reservation->status?->value ?? $reservation->status))) {
            throw ValidationExceptionFactory::make([
                'reservation' => ['Only active reservations support customer-facing deposit payment.'],
            ]);
        }

        $depositRequiredMinor = Money::minorUnits($reservation->deposit_required_amount ?? 0, true);
        if ($depositRequiredMinor <= 0) {
            throw ValidationExceptionFactory::make([
                'deposit' => ['Reservation does not require a deposit payment.'],
            ]);
        }

        if (Money::minorUnits($paymentSummary['final_captured_amount'] ?? 0, true) > 0) {
            throw ValidationExceptionFactory::make([
                'deposit' => ['Reservation already has final settlement captured; deposit payment is not allowed.'],
            ]);
        }

        if (Money::minorUnits($this->calculateOutstandingDeposit($reservation, $paymentSummary), true) <= 0) {
            throw ValidationExceptionFactory::make([
                'deposit' => ['Deposit is already fully paid.'],
            ]);
        }
    }

    private function calculateOutstandingDeposit(Reservation $reservation, array $paymentSummary): float
    {
        $requiredMinor = Money::minorUnits($reservation->deposit_required_amount ?? 0, true);
        $paidMinor = Money::minorUnits($paymentSummary['deposit_net_amount'] ?? 0, true);

        return Money::minorToFloat(max(0, $requiredMinor - $paidMinor));
    }

    private function buildResponse(Reservation $reservation, ReservationDepositPaymentSession $session): array
    {
        $payments = Payment::query()->with('refundOfPayment')->where('reservation_id', (int) $reservation->reservation_id)->orderBy('payment_id')->get();
        $summary = PaymentSummary::fromPayments($payments);
        $paymentSessions = ReservationDepositPaymentSession::query()
            ->where('reservation_id', (int) $reservation->reservation_id)
            ->orderByDesc('deposit_payment_session_id')
            ->get();

        return [
            'reservation' => $reservation,
            'deposit' => $this->depositReadService->buildSnapshot(
                $reservation,
                $payments,
                $paymentSessions,
                $summary,
                $this->depositPaymentService->resolveCurrency($reservation, $payments instanceof Collection ? $payments : collect($payments)),
                true,
            ),
            'payment_session' => $session,
        ];
    }

    private function buildCreateSessionRequestFingerprint(array $payload): string
    {
        $normalized = [
            'amount' => array_key_exists('amount', $payload) && $payload['amount'] !== null
                ? Money::toFloat($payload['amount'], true)
                : null,
            'currency' => trim((string) ($payload['currency'] ?? '')),
            'payment_method' => trim((string) ($payload['payment_method'] ?? 'Online')) ?: 'Online',
            'provider_code' => trim((string) ($payload['provider_code'] ?? '')),
            'notes' => trim((string) ($payload['notes'] ?? '')),
        ];

        return sha1((string) json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function assertCreateSessionReplayMatchesRequest(int $reservationId, ?int $customerUserId, string $idempotencyKey, string $requestFingerprint): void
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            return;
        }

        $existing = ReservationDepositPaymentSession::query()
            ->where('reservation_id', $reservationId)
            ->where('customer_user_id', $customerUserId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $existing instanceof ReservationDepositPaymentSession) {
            return;
        }

        $meta = is_array($existing->provider_payload_json) ? $existing->provider_payload_json : [];
        $requestMeta = is_array($meta['_booking_request'] ?? null) ? $meta['_booking_request'] : [];
        $recordedFingerprint = trim((string) ($requestMeta['fingerprint'] ?? ''));
        if ($recordedFingerprint !== '' && ! hash_equals($recordedFingerprint, trim($requestFingerprint))) {
            throw ValidationExceptionFactory::make([
                'idempotency_key' => ['This idempotency key is already bound to a different deposit payment session request payload.'],
            ]);
        }
    }

    /**
     * @param  array<string,mixed>  $providerPayload
     * @return array<string,mixed>
     */
    private function mergeRequestMetadata(array $providerPayload, ?string $requestFingerprint): array
    {
        $providerPayload['_booking_request'] = [
            'fingerprint' => $requestFingerprint !== null && trim($requestFingerprint) !== '' ? trim($requestFingerprint) : null,
        ];

        return PaymentProviderPayloadSanitizer::sanitizeSessionPayloadForStorage($providerPayload);
    }

    /**
     * @return Collection<int,Payment>
     */
    private function loadLockedPayments(int $reservationId): Collection
    {
        return Payment::query()
            ->where('reservation_id', $reservationId)
            ->lockForUpdate()
            ->get();
    }

    private function findAccessibleReservation(int $reservationId, ?int $customerUserId, ?string $sessionAccessId): Reservation
    {
        if ($customerUserId !== null) {
            $reservation = Reservation::query()
                ->whereKey($reservationId)
                ->where('user_id', $customerUserId)
                ->first();

            if ($reservation instanceof Reservation) {
                return $reservation;
            }
        }

        $resolvedSessionId = trim((string) $sessionAccessId);
        $reservation = Reservation::query()->find($reservationId);
        if (! $reservation instanceof Reservation || $resolvedSessionId === '' || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $resolvedSessionId)) {
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        return $reservation;
    }

    private function findAccessibleReservationForUpdate(int $reservationId, ?int $customerUserId, ?string $sessionAccessId): Reservation
    {
        if ($customerUserId !== null) {
            $reservation = Reservation::query()
                ->whereKey($reservationId)
                ->where('user_id', $customerUserId)
                ->lockForUpdate()
                ->first();

            if ($reservation instanceof Reservation) {
                return $reservation;
            }
        }

        $resolvedSessionId = trim((string) $sessionAccessId);
        $reservation = Reservation::query()->whereKey($reservationId)->lockForUpdate()->first();
        if (! $reservation instanceof Reservation || $resolvedSessionId === '' || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $resolvedSessionId)) {
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        return $reservation;
    }

    private function findAccessibleSession(int $reservationId, int $sessionId, ?int $customerUserId, Reservation $reservation, ?string $sessionAccessId): ReservationDepositPaymentSession
    {
        $effectiveCustomerUserId = $this->resolveEffectiveCustomerUserIdForSessionLookup($reservation, $customerUserId, $sessionAccessId);
        $session = ReservationDepositPaymentSession::query()
            ->whereKey($sessionId)
            ->where('reservation_id', $reservationId)
            ->where('customer_user_id', $effectiveCustomerUserId)
            ->first();

        if ($session instanceof ReservationDepositPaymentSession) {
            return $session;
        }

        throw (new ModelNotFoundException)->setModel(ReservationDepositPaymentSession::class, [$sessionId]);
    }

    private function findAccessibleSessionForUpdate(int $reservationId, int $sessionId, ?int $customerUserId, Reservation $reservation, ?string $sessionAccessId): ReservationDepositPaymentSession
    {
        $effectiveCustomerUserId = $this->resolveEffectiveCustomerUserIdForSessionLookup($reservation, $customerUserId, $sessionAccessId);
        $session = ReservationDepositPaymentSession::query()
            ->whereKey($sessionId)
            ->where('reservation_id', $reservationId)
            ->where('customer_user_id', $effectiveCustomerUserId)
            ->lockForUpdate()
            ->first();

        if ($session instanceof ReservationDepositPaymentSession) {
            return $session;
        }

        throw (new ModelNotFoundException)->setModel(ReservationDepositPaymentSession::class, [$sessionId]);
    }

    private function resolveEffectiveCustomerUserId(Reservation $reservation, ?int $customerUserId, ?string $sessionId): int
    {
        if ($customerUserId !== null) {
            return $customerUserId;
        }

        if (trim((string) $sessionId) === '' || $reservation->user_id === null) {
            throw ValidationExceptionFactory::make([
                'reservation' => ['Guest session-linked deposit payment requires a reservation owned by a customer account.'],
            ]);
        }

        return (int) $reservation->user_id;
    }

    private function resolveEffectiveCustomerUserIdForSessionLookup(Reservation $reservation, ?int $customerUserId, ?string $sessionId): int
    {
        if ($customerUserId !== null) {
            return $customerUserId;
        }

        if (trim((string) $sessionId) === '' || $reservation->user_id === null) {
            throw (new ModelNotFoundException)->setModel(ReservationDepositPaymentSession::class, []);
        }

        return (int) $reservation->user_id;
    }

    private function assertReservationRowVersion(Reservation $reservation, int $expectedRowVersion): void
    {
        if ((int) ($reservation->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationExceptionFactory::make([
                'row_version' => ['Reservation row version does not match the latest state.'],
            ]);
        }
    }

    private function assertSessionRowVersion(ReservationDepositPaymentSession $session, int $expectedRowVersion): void
    {
        if ((int) ($session->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationExceptionFactory::make([
                'row_version' => ['Deposit payment session row version does not match the latest state.'],
            ]);
        }
    }

    private function assertCustomerSelfPayReady(?string $providerCode): void
    {
        $rollout = $this->paymentProviderRolloutConfig->customerSelfPayStatus(PaymentSessionScope::Deposit, $providerCode);
        if (! ($rollout['ok'] ?? false)) {
            throw ValidationExceptionFactory::make([
                'provider_code' => [(string) ($rollout['message'] ?? 'Customer self-pay is not available.')],
            ]);
        }
    }

    private function sessionIsFinalized(ReservationDepositPaymentSession $session): bool
    {
        $settlementStatus = $this->resolveSettlementStatus($session);
        if (in_array($settlementStatus, [
            ReservationDepositPaymentSettlementStatus::Applied,
            ReservationDepositPaymentSettlementStatus::Skipped,
        ], true)) {
            return true;
        }

        $sessionStatus = $this->resolveSessionStatus($session);

        return $sessionStatus !== null && $sessionStatus->isTerminal();
    }

    private function sessionRequiresSettlementApply(ReservationDepositPaymentSession $session): bool
    {
        return $this->resolveSessionStatus($session) === ReservationDepositPaymentSessionStatus::Succeeded
            && $this->resolveSettlementStatus($session) === ReservationDepositPaymentSettlementStatus::NotApplied;
    }

    private function resolveSessionStatus(ReservationDepositPaymentSession $session): ?ReservationDepositPaymentSessionStatus
    {
        $raw = trim((string) ($session->session_status?->value ?? $session->session_status));

        return $raw !== '' ? ReservationDepositPaymentSessionStatus::tryFrom($raw) : null;
    }

    private function resolveSettlementStatus(ReservationDepositPaymentSession $session): ReservationDepositPaymentSettlementStatus
    {
        $raw = trim((string) ($session->settlement_status?->value ?? $session->settlement_status));

        return ReservationDepositPaymentSettlementStatus::tryFrom($raw) ?? ReservationDepositPaymentSettlementStatus::NotApplied;
    }

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

    /**
     * @return array<string,mixed>
     */
    private function buildSessionCreatedAuditPayload(
        Reservation $reservation,
        ReservationDepositPaymentSession $session,
        ?int $customerUserId,
        ?string $sessionId,
    ): array {
        return [
            'action' => 'payment_session.created',
            'entity_type' => 'reservation',
            'entity_id' => (string) $reservation->reservation_id,
            'subjects' => [
                ['type' => 'deposit_payment_session', 'id' => (string) $session->deposit_payment_session_id, 'role' => 'payment_session'],
            ],
            'after' => $this->presentSessionState($session),
            'summary' => [
                'payment_scope' => PaymentSessionScope::Deposit->value,
                'provider_code' => (string) $session->provider_code,
                'amount' => Money::toFloat($session->amount, true),
                'currency' => (string) $session->currency,
            ],
            'actor' => $this->resolveCustomerAuditActor($customerUserId, $sessionId),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSessionConfirmedAuditPayload(
        Reservation $reservation,
        ReservationDepositPaymentSession $session,
        string $beforeStatus,
        string $beforeSettlementStatus,
        ?int $beforeLinkedPaymentId,
        ?int $customerUserId,
        ?string $sessionId,
        bool $replayedFinalState,
    ): array {
        $subjects = [
            ['type' => 'deposit_payment_session', 'id' => (string) $session->deposit_payment_session_id, 'role' => 'payment_session'],
        ];

        if ($session->linked_payment_id !== null) {
            $subjects[] = ['type' => 'payment', 'id' => (string) $session->linked_payment_id, 'role' => 'payment'];
        }

        return [
            'action' => 'payment_session.confirmed',
            'entity_type' => 'reservation',
            'entity_id' => (string) $reservation->reservation_id,
            'subjects' => $subjects,
            'before' => [
                'session_status' => $beforeStatus,
                'settlement_status' => $beforeSettlementStatus,
                'linked_payment_id' => $beforeLinkedPaymentId,
            ],
            'after' => $this->presentSessionState($session),
            'summary' => [
                'payment_scope' => PaymentSessionScope::Deposit->value,
                'provider_code' => (string) $session->provider_code,
                'replayed_final_state' => $replayedFinalState,
            ],
            'actor' => $this->resolveCustomerAuditActor($customerUserId, $sessionId),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function presentSessionState(ReservationDepositPaymentSession $session): array
    {
        return [
            'session_status' => (string) ($session->session_status?->value ?? $session->session_status),
            'settlement_status' => (string) ($session->settlement_status?->value ?? $session->settlement_status),
            'linked_payment_id' => $session->linked_payment_id !== null ? (int) $session->linked_payment_id : null,
            'amount' => Money::toFloat($session->amount, true),
            'currency' => (string) $session->currency,
            'provider_code' => (string) $session->provider_code,
            'provider_session_code' => (string) $session->provider_session_code,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveCustomerAuditActor(?int $customerUserId, ?string $sessionId): ?array
    {
        if ($customerUserId !== null && $customerUserId > 0) {
            return [
                'type' => 'customer_account',
                'user_id' => $customerUserId,
                'key' => 'customer_user:'.$customerUserId,
            ];
        }

        $resolvedSessionId = trim((string) $sessionId);
        if ($resolvedSessionId === '') {
            return null;
        }

        return [
            'type' => 'customer_session',
            'key' => $resolvedSessionId,
        ];
    }
}
