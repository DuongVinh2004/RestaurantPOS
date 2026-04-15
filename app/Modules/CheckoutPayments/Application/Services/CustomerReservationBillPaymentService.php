<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Application\Services;

use App\Enums\PaymentSessionScope;
use App\Enums\ReservationBillPaymentSessionStatus;
use App\Enums\ReservationBillPaymentSettlementStatus;
use App\Enums\ReservationStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\CheckoutPayments\Domain\Models\Payment;
use App\Modules\CheckoutPayments\Domain\Models\ReservationBillPaymentSession;
use App\Modules\CheckoutPayments\Infrastructure\CustomerBillPayment\CustomerBillPaymentProviderRegistry;
use App\Modules\CheckoutPayments\Infrastructure\PaymentProviders\PaymentProviderRolloutConfig;
use App\Modules\CheckoutPayments\Infrastructure\PaymentProviders\PaymentProviderSessionScopeGuard;
use App\Modules\CheckoutPayments\Infrastructure\PaymentProviders\ReservationBillPaymentSessionLifecycleService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Services\CustomerReservationSessionAccessService;
use App\Platform\FeatureFlags\Services\FeatureFlagService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Support\AuditEvent;
use App\Support\ValidationExceptionFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerReservationBillPaymentService
{
    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly CustomerBillPaymentProviderRegistry $providers,
        private readonly ReservationBillPaymentService $billPaymentService,
        private readonly ReservationBillPaymentSessionLifecycleService $sessionLifecycle,
        private readonly CustomerReservationSessionAccessService $customerSessionAccessService,
        private readonly PaymentProviderSessionScopeGuard $sessionScopeGuard,
        private readonly PaymentProviderRolloutConfig $paymentProviderRolloutConfig,
        private readonly FeatureFlagService $featureFlags,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     * @return array{reservation:Reservation,bill:array<string,mixed>,payment_session:ReservationBillPaymentSession}
     */
    public function createSession(int $reservationId, array $payload, ?int $customerUserId, ?string $sessionId, string $idempotencyKey = ''): array
    {
        $lockKeys = $this->reservationLockKeys($reservationId);

        return $this->locks->withLockKeys($lockKeys, function () use ($reservationId, $payload, $customerUserId, $sessionId, $idempotencyKey) {
            return DB::transaction(function () use ($reservationId, $payload, $customerUserId, $sessionId, $idempotencyKey) {
                $reservation = $this->findAccessibleReservationForUpdate($reservationId, $customerUserId, $sessionId);
                $effectiveCustomerUserId = $this->resolveEffectiveCustomerUserId($reservation, $customerUserId, $sessionId);
                $this->assertReservationRowVersion($reservation, (int) $payload['row_version']);
                $requestFingerprint = $idempotencyKey !== ''
                    ? $this->buildCreateSessionRequestFingerprint($payload)
                    : null;

                if ($idempotencyKey !== '') {
                    $this->assertCreateSessionReplayMatchesRequest($reservationId, $effectiveCustomerUserId, $idempotencyKey, $requestFingerprint ?? '');
                    $replayed = ReservationBillPaymentSession::query()
                        ->where('reservation_id', $reservationId)
                        ->where('customer_user_id', $effectiveCustomerUserId)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    if ($replayed instanceof ReservationBillPaymentSession) {
                        return $this->buildResponse($reservation->fresh(), $replayed->fresh());
                    }
                }

                $lockedPayments = $this->loadLockedPayments($reservationId);
                $this->assertReservationSupportsCustomerBillPayment($reservation, $lockedPayments);
                $currency = $this->billPaymentService->resolveCurrency(
                    $reservation,
                    $lockedPayments,
                    (string) ($payload['currency'] ?? '')
                );
                $bill = $this->billPaymentService->summarizeLockedBill($reservation, $lockedPayments, $currency);
                $amount = $this->resolveRequestedAmount($bill, $payload);
                $anchorOrder = $this->resolveAnchorOrder($reservationId);
                $this->assertCustomerSelfPayReady(
                    $reservation,
                    isset($payload['provider_code']) ? (string) $payload['provider_code'] : null,
                );

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
                    PaymentSessionScope::Bill,
                );

                $session = new ReservationBillPaymentSession;
                $session->reservation_id = (int) $reservation->reservation_id;
                $session->order_id = $anchorOrder?->order_id;
                $session->customer_user_id = $effectiveCustomerUserId;
                $session->linked_payment_id = null;
                $session->provider_code = $providerCode;
                $session->provider_session_code = $providerSessionCode;
                $session->provider_payment_code = Arr::get($providerSession, 'provider_payment_code');
                $session->payment_method = trim((string) ($providerSession['payment_method'] ?? $payload['payment_method'] ?? 'Online')) ?: 'Online';
                $session->amount = $amount;
                $session->currency = $currency;
                $session->session_status = (string) ($providerSession['session_status'] ?? ReservationBillPaymentSessionStatus::Created->value);
                $session->settlement_status = ReservationBillPaymentSettlementStatus::NotApplied;
                $session->failure_code = Arr::get($providerSession, 'failure_code');
                $session->failure_message = Arr::get($providerSession, 'failure_message');
                $session->provider_payload_json = $this->mergeRequestMetadata(
                    (array) ($providerSession['provider_payload'] ?? []),
                    $idempotencyKey,
                    $requestFingerprint
                );
                $session->idempotency_key = $idempotencyKey !== '' ? $idempotencyKey : null;
                $session->provider_expires_at = $providerSession['provider_expires_at'] ?? null;
                $session->created_by = $customerUserId;
                $session->updated_by = $customerUserId;
                $session->save();

                AuditEvent::info('customer.bill_payment_session.created', [
                    'reservation_id' => (int) $reservation->reservation_id,
                    'payment_session_id' => (int) $session->bill_payment_session_id,
                    'provider_code' => (string) $session->provider_code,
                    'provider_session_code' => (string) $session->provider_session_code,
                    'payment_scope' => PaymentSessionScope::Bill->value,
                    '_audit' => $this->buildSessionCreatedAuditPayload($reservation, $session, $customerUserId, $sessionId),
                ]);

                return $this->buildResponse($reservation->fresh(), $session->fresh());
            });
        });
    }

    /**
     * @return array{reservation:Reservation,bill:array<string,mixed>,payment_session:ReservationBillPaymentSession}
     */
    public function showSession(int $reservationId, int $sessionId, ?int $customerUserId, ?string $sessionAccessId): array
    {
        $reservation = $this->findAccessibleReservation($reservationId, $customerUserId, $sessionAccessId);
        $session = $this->findAccessibleSession($reservationId, $sessionId, $customerUserId, $reservation, $sessionAccessId);

        return $this->buildResponse($reservation, $session);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{reservation:Reservation,bill:array<string,mixed>,payment_session:ReservationBillPaymentSession}
     */
    public function refreshSession(int $reservationId, int $sessionId, array $payload, ?int $customerUserId, ?string $sessionAccessId): array
    {
        return $this->mutateSession($reservationId, $sessionId, $payload, $customerUserId, $sessionAccessId, 'refresh');
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{reservation:Reservation,bill:array<string,mixed>,payment_session:ReservationBillPaymentSession}
     */
    public function confirmSession(int $reservationId, int $sessionId, array $payload, ?int $customerUserId, ?string $sessionAccessId): array
    {
        return $this->mutateSession($reservationId, $sessionId, $payload, $customerUserId, $sessionAccessId, 'confirm');
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{reservation:Reservation,bill:array<string,mixed>,payment_session:ReservationBillPaymentSession}
     */
    private function mutateSession(int $reservationId, int $sessionId, array $payload, ?int $customerUserId, ?string $sessionAccessId, string $mode): array
    {
        $lockKeys = $this->reservationLockKeys($reservationId);

        return $this->locks->withLockKeys($lockKeys, function () use ($reservationId, $sessionId, $payload, $customerUserId, $sessionAccessId, $mode) {
            return DB::transaction(function () use ($reservationId, $sessionId, $payload, $customerUserId, $sessionAccessId, $mode) {
                $reservation = $this->findAccessibleReservationForUpdate($reservationId, $customerUserId, $sessionAccessId);
                $session = $this->findAccessibleSessionForUpdate($reservationId, $sessionId, $customerUserId, $reservation, $sessionAccessId);
                $this->assertSessionRowVersion($session, (int) $payload['row_version']);
                $beforeStatus = (string) ($session->session_status?->value ?? $session->session_status);
                $beforeSettlementStatus = (string) ($session->settlement_status?->value ?? $session->settlement_status);
                $beforeLinkedPaymentId = $session->linked_payment_id !== null ? (int) $session->linked_payment_id : null;

                if ($this->sessionIsFinalized($session)) {
                    $lockedPayments = $this->loadLockedPayments($reservationId);
                    if ($this->sessionRequiresSettlementApply($session)) {
                        $this->sessionLifecycle->applySucceededSessionIfNeeded($reservation, $session, $lockedPayments, $customerUserId);
                    }

                    if ($mode === 'confirm') {
                        AuditEvent::info('customer.bill_payment_session.confirmed', [
                            'reservation_id' => (int) $reservation->reservation_id,
                            'payment_session_id' => (int) $session->bill_payment_session_id,
                            'provider_code' => (string) $session->provider_code,
                            'provider_session_code' => (string) $session->provider_session_code,
                            'payment_scope' => PaymentSessionScope::Bill->value,
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
                $this->sessionLifecycle->applySucceededSessionIfNeeded($reservation, $session, $lockedPayments, $customerUserId);

                if ($mode === 'confirm') {
                    AuditEvent::info('customer.bill_payment_session.confirmed', [
                        'reservation_id' => (int) $reservation->reservation_id,
                        'payment_session_id' => (int) $session->bill_payment_session_id,
                        'provider_code' => (string) $session->provider_code,
                        'provider_session_code' => (string) $session->provider_session_code,
                        'payment_scope' => PaymentSessionScope::Bill->value,
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
    }

    /**
     * @param  array<string,mixed>  $bill
     * @param  array<string,mixed>  $payload
     */
    private function resolveRequestedAmount(array $bill, array $payload): float
    {
        $outstanding = round(max(0.0, (float) ($bill['outstanding_amount'] ?? 0.0)), 2);
        $requested = array_key_exists('amount', $payload) && $payload['amount'] !== null
            ? round(max(0.0, (float) $payload['amount']), 2)
            : $outstanding;

        if ($requested <= 0.0001) {
            throw ValidationExceptionFactory::make([
                'amount' => ['Bill payment amount must be greater than 0.'],
            ]);
        }

        if ($requested - $outstanding > 0.0001) {
            throw ValidationExceptionFactory::make([
                'amount' => ['Bill payment amount exceeds the outstanding bill balance.'],
            ]);
        }

        return $requested;
    }

    /**
     * @param  Collection<int,Payment>  $lockedPayments
     */
    private function assertReservationSupportsCustomerBillPayment(Reservation $reservation, Collection $lockedPayments): void
    {
        if (! ReservationStatus::isActiveDbValue((string) ($reservation->status?->value ?? $reservation->status))) {
            throw ValidationExceptionFactory::make([
                'reservation' => ['Only active reservations support customer-facing bill payment.'],
            ]);
        }

        if ($reservation->billed_at === null || $reservation->final_bill_amount === null) {
            throw ValidationExceptionFactory::make([
                'bill' => ['Staff must lock the bill before customer self-payment can be created.'],
            ]);
        }

        $this->billPaymentService->summarizeLockedBill($reservation, $lockedPayments, '');
    }

    /**
     * @return array{reservation:Reservation,bill:array<string,mixed>,payment_session:ReservationBillPaymentSession}
     */
    private function buildResponse(Reservation $reservation, ReservationBillPaymentSession $session): array
    {
        $payments = Payment::query()->where('reservation_id', (int) $reservation->reservation_id)->get();

        return [
            'reservation' => $reservation,
            'bill' => $this->billPaymentService->summarizeLockedBill($reservation, $payments instanceof Collection ? $payments : collect($payments), (string) ($session->currency ?? '')),
            'payment_session' => $session,
        ];
    }

    private function buildCreateSessionRequestFingerprint(array $payload): string
    {
        $normalized = [
            'amount' => array_key_exists('amount', $payload) && $payload['amount'] !== null
                ? round(max(0.0, (float) $payload['amount']), 2)
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

        $existing = ReservationBillPaymentSession::query()
            ->where('reservation_id', $reservationId)
            ->where('customer_user_id', $customerUserId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $existing instanceof ReservationBillPaymentSession) {
            return;
        }

        $meta = is_array($existing->provider_payload_json) ? $existing->provider_payload_json : [];
        $requestMeta = is_array($meta['_booking_request'] ?? null) ? $meta['_booking_request'] : [];
        $recordedFingerprint = trim((string) ($requestMeta['fingerprint'] ?? ''));
        if ($recordedFingerprint !== '' && ! hash_equals($recordedFingerprint, trim($requestFingerprint))) {
            throw ValidationExceptionFactory::make([
                'idempotency_key' => ['This idempotency key is already bound to a different bill payment session request payload.'],
            ]);
        }
    }

    /**
     * @param  array<string,mixed>  $providerPayload
     * @return array<string,mixed>
     */
    private function mergeRequestMetadata(array $providerPayload, string $idempotencyKey, ?string $requestFingerprint): array
    {
        $providerPayload['_booking_request'] = [
            'idempotency_key' => $idempotencyKey !== '' ? trim($idempotencyKey) : null,
            'fingerprint' => $requestFingerprint !== null && trim($requestFingerprint) !== '' ? trim($requestFingerprint) : null,
        ];

        return $providerPayload;
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
            return $this->findOwnedReservation($reservationId, $customerUserId);
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
            return $this->findOwnedReservationForUpdate($reservationId, $customerUserId);
        }

        $resolvedSessionId = trim((string) $sessionAccessId);
        $reservation = Reservation::query()->whereKey($reservationId)->lockForUpdate()->first();
        if (! $reservation instanceof Reservation || $resolvedSessionId === '' || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $resolvedSessionId)) {
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        return $reservation;
    }

    private function findAccessibleSession(int $reservationId, int $sessionId, ?int $customerUserId, Reservation $reservation, ?string $sessionAccessId): ReservationBillPaymentSession
    {
        $effectiveCustomerUserId = $this->resolveEffectiveCustomerUserIdForSessionLookup($reservation, $customerUserId, $sessionAccessId);
        $session = ReservationBillPaymentSession::query()
            ->whereKey($sessionId)
            ->where('reservation_id', $reservationId)
            ->where('customer_user_id', $effectiveCustomerUserId)
            ->first();

        if ($session instanceof ReservationBillPaymentSession) {
            return $session;
        }

        throw (new ModelNotFoundException)->setModel(ReservationBillPaymentSession::class, [$sessionId]);
    }

    private function findAccessibleSessionForUpdate(int $reservationId, int $sessionId, ?int $customerUserId, Reservation $reservation, ?string $sessionAccessId): ReservationBillPaymentSession
    {
        $effectiveCustomerUserId = $this->resolveEffectiveCustomerUserIdForSessionLookup($reservation, $customerUserId, $sessionAccessId);
        $session = ReservationBillPaymentSession::query()
            ->whereKey($sessionId)
            ->where('reservation_id', $reservationId)
            ->where('customer_user_id', $effectiveCustomerUserId)
            ->lockForUpdate()
            ->first();

        if ($session instanceof ReservationBillPaymentSession) {
            return $session;
        }

        throw (new ModelNotFoundException)->setModel(ReservationBillPaymentSession::class, [$sessionId]);
    }

    private function resolveEffectiveCustomerUserId(Reservation $reservation, ?int $customerUserId, ?string $sessionId): int
    {
        if ($customerUserId !== null) {
            return $customerUserId;
        }

        if (trim((string) $sessionId) === '' || $reservation->user_id === null) {
            throw ValidationExceptionFactory::make([
                'reservation' => ['Guest session-linked bill payment requires a reservation owned by a customer account.'],
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
            throw (new ModelNotFoundException)->setModel(ReservationBillPaymentSession::class, []);
        }

        return (int) $reservation->user_id;
    }

    private function findOwnedReservation(int $reservationId, int $customerUserId): Reservation
    {
        $reservation = Reservation::query()
            ->whereKey($reservationId)
            ->where('user_id', $customerUserId)
            ->first();

        if (! $reservation instanceof Reservation) {
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        return $reservation;
    }

    private function findOwnedReservationForUpdate(int $reservationId, int $customerUserId): Reservation
    {
        $reservation = Reservation::query()
            ->whereKey($reservationId)
            ->where('user_id', $customerUserId)
            ->lockForUpdate()
            ->first();

        if (! $reservation instanceof Reservation) {
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        return $reservation;
    }

    private function findOwnedSession(int $reservationId, int $sessionId, int $customerUserId): ReservationBillPaymentSession
    {
        $session = ReservationBillPaymentSession::query()
            ->whereKey($sessionId)
            ->where('reservation_id', $reservationId)
            ->where('customer_user_id', $customerUserId)
            ->first();

        if (! $session instanceof ReservationBillPaymentSession) {
            throw (new ModelNotFoundException)->setModel(ReservationBillPaymentSession::class, [$sessionId]);
        }

        return $session;
    }

    private function findOwnedSessionForUpdate(int $reservationId, int $sessionId, int $customerUserId): ReservationBillPaymentSession
    {
        $session = ReservationBillPaymentSession::query()
            ->whereKey($sessionId)
            ->where('reservation_id', $reservationId)
            ->where('customer_user_id', $customerUserId)
            ->lockForUpdate()
            ->first();

        if (! $session instanceof ReservationBillPaymentSession) {
            throw (new ModelNotFoundException)->setModel(ReservationBillPaymentSession::class, [$sessionId]);
        }

        return $session;
    }

    private function resolveAnchorOrder(int $reservationId): ?ReservationOrder
    {
        return ReservationOrder::query()
            ->where('reservation_id', $reservationId)
            ->orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN order_type = 'OnSpot' THEN 0 ELSE 1 END")
            ->orderByDesc('order_id')
            ->first();
    }

    /**
     * @return list<string>
     */
    private function reservationLockKeys(int $reservationId): array
    {
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

        return $lockKeys;
    }

    private function assertReservationRowVersion(Reservation $reservation, int $expectedRowVersion): void
    {
        if ((int) ($reservation->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationExceptionFactory::make([
                'row_version' => ['Reservation row version does not match the latest state.'],
            ]);
        }
    }

    private function assertSessionRowVersion(ReservationBillPaymentSession $session, int $expectedRowVersion): void
    {
        if ((int) ($session->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationExceptionFactory::make([
                'row_version' => ['Bill payment session row version does not match the latest state.'],
            ]);
        }
    }

    private function sessionIsFinalized(ReservationBillPaymentSession $session): bool
    {
        $settlementStatus = $this->resolveSettlementStatus($session);
        if (in_array($settlementStatus, [
            ReservationBillPaymentSettlementStatus::Applied,
            ReservationBillPaymentSettlementStatus::Skipped,
        ], true)) {
            return true;
        }

        $sessionStatus = $this->resolveSessionStatus($session);

        return $sessionStatus !== null && $sessionStatus->isTerminal();
    }

    private function sessionRequiresSettlementApply(ReservationBillPaymentSession $session): bool
    {
        return $this->resolveSessionStatus($session) === ReservationBillPaymentSessionStatus::Succeeded
            && $this->resolveSettlementStatus($session) === ReservationBillPaymentSettlementStatus::NotApplied;
    }

    private function resolveSessionStatus(ReservationBillPaymentSession $session): ?ReservationBillPaymentSessionStatus
    {
        $raw = trim((string) ($session->session_status?->value ?? $session->session_status));

        return $raw !== '' ? ReservationBillPaymentSessionStatus::tryFrom($raw) : null;
    }

    private function resolveSettlementStatus(ReservationBillPaymentSession $session): ReservationBillPaymentSettlementStatus
    {
        $raw = trim((string) ($session->settlement_status?->value ?? $session->settlement_status));

        return ReservationBillPaymentSettlementStatus::tryFrom($raw) ?? ReservationBillPaymentSettlementStatus::NotApplied;
    }

    private function bumpRowVersion(ReservationBillPaymentSession $session): void
    {
        $session->row_version = max(1, (int) ($session->row_version ?? 1)) + 1;
    }

    private function assertCustomerSelfPayReady(Reservation $reservation, ?string $providerCode): void
    {
        $feature = $this->featureFlags->resolve(
            'customer.bill_self_payment',
            $reservation->branch_id !== null ? (int) $reservation->branch_id : null,
        );
        if (! ($feature['enabled'] ?? false)) {
            throw ValidationExceptionFactory::make([
                'provider_code' => [(string) ($feature['message'] ?? 'Customer self-pay is not available.')],
            ]);
        }

        $rollout = $this->paymentProviderRolloutConfig->customerSelfPayStatus(PaymentSessionScope::Bill, $providerCode);
        if (! ($rollout['ok'] ?? false)) {
            throw ValidationExceptionFactory::make([
                'provider_code' => [(string) ($rollout['message'] ?? 'Customer self-pay is not available.')],
            ]);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSessionCreatedAuditPayload(
        Reservation $reservation,
        ReservationBillPaymentSession $session,
        ?int $customerUserId,
        ?string $sessionId,
    ): array {
        return [
            'action' => 'payment_session.created',
            'entity_type' => 'reservation',
            'entity_id' => (string) $reservation->reservation_id,
            'subjects' => [
                ['type' => 'bill_payment_session', 'id' => (string) $session->bill_payment_session_id, 'role' => 'payment_session'],
            ],
            'after' => $this->presentSessionState($session),
            'summary' => [
                'payment_scope' => PaymentSessionScope::Bill->value,
                'provider_code' => (string) $session->provider_code,
                'amount' => round((float) $session->amount, 2),
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
        ReservationBillPaymentSession $session,
        string $beforeStatus,
        string $beforeSettlementStatus,
        ?int $beforeLinkedPaymentId,
        ?int $customerUserId,
        ?string $sessionId,
        bool $replayedFinalState,
    ): array {
        $subjects = [
            ['type' => 'bill_payment_session', 'id' => (string) $session->bill_payment_session_id, 'role' => 'payment_session'],
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
                'payment_scope' => PaymentSessionScope::Bill->value,
                'provider_code' => (string) $session->provider_code,
                'replayed_final_state' => $replayedFinalState,
            ],
            'actor' => $this->resolveCustomerAuditActor($customerUserId, $sessionId),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function presentSessionState(ReservationBillPaymentSession $session): array
    {
        return [
            'session_status' => (string) ($session->session_status?->value ?? $session->session_status),
            'settlement_status' => (string) ($session->settlement_status?->value ?? $session->settlement_status),
            'linked_payment_id' => $session->linked_payment_id !== null ? (int) $session->linked_payment_id : null,
            'amount' => round((float) $session->amount, 2),
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
