<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Workflows;

use App\Enums\PaymentProviderWebhookReceiptStatus;
use App\Enums\PaymentSessionScope;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\Payments\Domain\Guards\PaymentSessionScopeGuard;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentProviderWebhookReceipt;
use App\Modules\Payments\Domain\Models\ReservationBillPaymentSession;
use App\Modules\Payments\Domain\Models\ReservationDepositPaymentSession;
use App\Modules\Payments\Domain\Policies\PaymentSessionStatusTransitionPolicy;
use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\PaymentProviderRegistry;
use App\Modules\Payments\Infrastructure\Internal\PaymentProviderPayloadSanitizer;
use App\Modules\Reservations\Application\Services\ReservationDepositRealtimePublisher;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Support\AuditEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cua vao webhook thanh toan:
 * xac minh chu ky, dedupe receipt, route theo scope, va dong bo session/payment vao he thong.
 */
class PaymentWebhookIngestionWorkflow
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly ReservationLockService $locks,
        private readonly ReservationDepositPaymentSessionLifecycleWorkflow $depositLifecycle,
        private readonly ReservationBillPaymentSessionLifecycleWorkflow $billLifecycle,
        private readonly PaymentSessionScopeGuard $sessionScopeGuard,
        private readonly ?ReservationDepositRealtimePublisher $depositRealtimePublisher = null,
    ) {}

    /**
     * @param  array<string,string>  $headers
     * @return array<string,mixed>
     */
    public function ingest(string $providerCode, string $rawBody, array $headers): array
    {
        // Webhook bi chan ngay neu signature khong hop le de tranh fake event di sau vao finance flow.
        $provider = $this->providers->resolve($providerCode);
        if (! $provider->verifyWebhookSignature($rawBody, $headers)) {
            $this->recordWebhookOutcome('failed', [
                'provider_code' => $provider->code(),
                'delivery_status' => PaymentProviderWebhookReceiptStatus::Failed->value,
                'failure_message' => 'Webhook signature verification failed.',
                'ignored_reason' => 'invalid_signature',
            ]);

            throw ValidationException::withMessages([
                'signature' => ['Webhook signature verification failed.'],
            ]);
        }

        // Pha 1: parse raw webhook thanh event chuan hoa de he thong route theo scope/session.
        $event = $provider->parseWebhook($rawBody, $headers);
        $receipt = $this->createReceipt($provider->code(), $event, $headers, $rawBody);
        $isDuplicateDelivery = ($receipt['duplicate'] ?? false) === true;
        // Receipt la "so nhan event" de webhook duplicate/retry co the duoc xu ly idempotent.
        if (($receipt['duplicate'] ?? false) === true) {
            /** @var PaymentProviderWebhookReceipt $existing */
            $existing = $receipt['receipt'];

            if (! $this->shouldResumeIncompleteDuplicateReceipt($existing)) {
                $response = $this->buildDuplicateResponse($provider->code(), $existing);

                $this->recordWebhookOutcome('duplicate', $response);

                return $response;
            }
        }

        /** @var PaymentProviderWebhookReceipt $storedReceipt */
        $storedReceipt = $receipt['receipt'];

        try {
            // Pha 2: resolve scope va event type truoc khi route vao lifecycle workflow tuong ung.
            $scope = $this->resolveScope($provider->code(), (string) $event['provider_session_code'], $event['payment_scope'] ?? null);
            $eventType = trim((string) ($event['event_type'] ?? 'payment.session.updated')) ?: 'payment.session.updated';

            if (! $provider->supportsWebhookEventType($eventType)) {
                return $this->ignoreReceipt(
                    receipt: $storedReceipt,
                    providerCode: $provider->code(),
                    scope: $scope,
                    eventCode: (string) $event['provider_event_code'],
                    sessionCode: (string) $event['provider_session_code'],
                    eventType: $eventType,
                    reason: 'unsupported_event_type',
                    message: sprintf('Unsupported webhook event type [%s].', $eventType),
                );
            }

            $result = match ($scope) {
                PaymentSessionScope::Deposit => $this->handleDepositWebhook($provider->code(), $event, $storedReceipt),
                PaymentSessionScope::Bill => $this->handleBillWebhook($provider->code(), $event, $storedReceipt),
            };

            // Duplicate delivery hop le van tra ket qua hien tai, nhung danh dau de audit/ops doc duoc.
            if ($isDuplicateDelivery) {
                $result['duplicate'] = true;
                $result['resumed_incomplete_delivery'] = true;
            }

            return $result;
        } catch (ValidationException $exception) {
            $result = $this->failReceipt(
                $storedReceipt,
                $this->firstValidationMessage($exception),
                $this->webhookFailureResponseContext($event),
            );

            if ($isDuplicateDelivery) {
                $result['duplicate'] = true;
                $result['resumed_incomplete_delivery'] = true;
            }

            return $result;
        }
    }

    /**
     * @param  array<string,mixed>  $event
     * @param  array<string,string>  $headers
     * @return array{duplicate:bool,receipt:PaymentProviderWebhookReceipt}
     */
    private function createReceipt(string $providerCode, array $event, array $headers, string $rawBody): array
    {
        // Moi event duoc persist receipt truoc khi xu ly business logic de co dau vet retries/failures.
        try {
            $receipt = new PaymentProviderWebhookReceipt;
            $receipt->provider_code = $providerCode;
            $receipt->provider_event_code = (string) $event['provider_event_code'];
            $receipt->provider_session_code = (string) $event['provider_session_code'];
            $receipt->payment_scope = $this->paymentScopeForStorage($event['payment_scope'] ?? null);
            $receipt->event_type = (string) ($event['event_type'] ?? 'payment.session.updated');
            $receipt->delivery_status = PaymentProviderWebhookReceiptStatus::Received;
            $receipt->request_signature = PaymentProviderPayloadSanitizer::signatureDigest($event['request_signature'] ?? null);
            $receipt->request_headers_json = PaymentProviderPayloadSanitizer::sanitizeWebhookHeaders($headers);
            $receipt->request_body = PaymentProviderPayloadSanitizer::summarizeWebhookRequestBody($rawBody, $event);
            $receipt->provider_payload_json = PaymentProviderPayloadSanitizer::sanitizeWebhookPayloadForStorage(
                (array) ($event['provider_payload'] ?? []),
                $headers,
                $rawBody,
                $event,
            );
            $receipt->save();

            return ['duplicate' => false, 'receipt' => $receipt];
        } catch (QueryException $exception) {
            if (! $this->isDuplicateWebhookReceiptException($exception)) {
                throw $exception;
            }

            $existing = PaymentProviderWebhookReceipt::query()
                ->where('provider_code', $providerCode)
                ->where('provider_event_code', (string) $event['provider_event_code'])
                ->firstOrFail();

            $this->assertDuplicateReceiptMatchesIncoming($existing, $providerCode, $event, $rawBody);

            return ['duplicate' => true, 'receipt' => $existing];
        }
    }

    /**
     * @param  array<string,mixed>  $event
     */
    private function assertDuplicateReceiptMatchesIncoming(
        PaymentProviderWebhookReceipt $receipt,
        string $providerCode,
        array $event,
        string $rawBody,
    ): void {
        $messages = [];
        $incomingSessionCode = trim((string) ($event['provider_session_code'] ?? ''));
        $storedSessionCode = trim((string) ($receipt->provider_session_code ?? ''));
        if ($storedSessionCode !== $incomingSessionCode) {
            $messages[] = 'Webhook provider_event_code is already bound to a different provider_session_code.';
        }

        $incomingScope = $this->paymentScopeForStorage($event['payment_scope'] ?? null);
        $storedScope = $this->normalizeNullableString($receipt->payment_scope);
        if ($storedScope !== $incomingScope) {
            $messages[] = 'Webhook provider_event_code is already bound to a different payment_scope.';
        }

        $incomingEventType = trim((string) ($event['event_type'] ?? 'payment.session.updated')) ?: 'payment.session.updated';
        $storedEventType = trim((string) ($receipt->event_type ?? 'payment.session.updated')) ?: 'payment.session.updated';
        if ($storedEventType !== $incomingEventType) {
            $messages[] = 'Webhook provider_event_code is already bound to a different event_type.';
        }

        $storedFingerprint = PaymentProviderPayloadSanitizer::storedWebhookBodyFingerprint($receipt->provider_payload_json);
        $incomingFingerprint = PaymentProviderPayloadSanitizer::webhookBodyFingerprint($rawBody);
        if ($storedFingerprint !== '') {
            if (! hash_equals($storedFingerprint, $incomingFingerprint)) {
                $messages[] = 'Webhook provider_event_code is already bound to a different webhook payload.';
            }
        } elseif (! $this->sameWebhookRequestBody($receipt->request_body, $rawBody)) {
            $messages[] = 'Webhook provider_event_code is already bound to a different webhook payload.';
        }

        if ($messages === []) {
            return;
        }

        $this->recordWebhookOutcome('failed', [
            'provider_code' => $providerCode,
            'provider_event_code' => (string) ($event['provider_event_code'] ?? $receipt->provider_event_code),
            'provider_session_code' => $incomingSessionCode !== '' ? $incomingSessionCode : $storedSessionCode,
            'payment_scope' => $incomingScope ?? $storedScope,
            'delivery_status' => (string) ($receipt->delivery_status?->value ?? $receipt->delivery_status),
            'duplicate' => true,
            'ignored_reason' => 'duplicate_event_payload_mismatch',
            'failure_message' => implode(' ', $messages),
            'receipt_id' => (int) $receipt->payment_provider_webhook_receipt_id,
        ]);

        throw ValidationException::withMessages([
            'provider_event_code' => $messages,
        ]);
    }

    /**
     * @param  array<string,mixed>  $event
     * @return array<string,mixed>
     */
    private function handleDepositWebhook(string $providerCode, array $event, PaymentProviderWebhookReceipt $receipt): array
    {
        // Deposit webhook tim session truoc; neu session mat thi fail receipt thay vi silently skip.
        $session = ReservationDepositPaymentSession::query()
            ->where('provider_code', $providerCode)
            ->where('provider_session_code', (string) $event['provider_session_code'])
            ->first();

        if (! $session instanceof ReservationDepositPaymentSession) {
            return $this->failReceipt($receipt, 'Deposit payment session not found for provider_session_code.');
        }

        $depositPaidRealtimeContext = null;

        // Pha 3: lock reservation scope de apply session/payment idempotent tren cung reservation.
        $result = $this->locks->withReservationLock((int) $session->reservation_id, function () use ($providerCode, $event, $receipt, $session, &$depositPaidRealtimeContext) {
            return DB::transaction(function () use ($providerCode, $event, $receipt, $session, &$depositPaidRealtimeContext) {
                $lockedSession = ReservationDepositPaymentSession::query()
                    ->whereKey((int) $session->deposit_payment_session_id)
                    ->lockForUpdate()
                    ->first();
                $reservation = Reservation::query()
                    ->whereKey((int) $session->reservation_id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedSession instanceof ReservationDepositPaymentSession || ! $reservation instanceof Reservation) {
                    return $this->decorateWebhookContext(
                        $this->failReceipt(
                            $receipt,
                            'Deposit payment session or reservation disappeared before webhook could be applied.',
                            $this->webhookContextFromSession($session, null),
                        ),
                        $session,
                        null,
                    );
                }

                $staleOutcome = $this->ignoreStaleEventIfNeeded(
                    receipt: $receipt,
                    session: $lockedSession,
                    reservation: $reservation,
                    event: $event,
                    providerCode: $providerCode,
                    scope: PaymentSessionScope::Deposit,
                );
                if ($staleOutcome !== null) {
                    return $this->decorateWebhookContext($staleOutcome, $lockedSession, $reservation);
                }

                $currentStatus = (string) ($lockedSession->session_status?->value ?? $lockedSession->session_status ?? '');
                // applyProviderResult cap nhat state machine cua session; applySucceeded... moi quyet dinh tao payment.
                $applied = $this->depositLifecycle->applyProviderResult($lockedSession, $event, null);
                $lockedPayments = Payment::query()
                    ->where('reservation_id', (int) $reservation->reservation_id)
                    ->lockForUpdate()
                    ->get();
                $appliedPayment = $this->depositLifecycle->applySucceededSessionIfNeeded($reservation, $lockedSession, $lockedPayments, null);
                if ($appliedPayment instanceof Payment) {
                    $depositPaidRealtimeContext ??= [
                        'reservation' => $reservation->fresh(),
                        'payment' => $appliedPayment->fresh(),
                        'payment_summary' => PaymentSummary::fromPayments($lockedPayments),
                    ];
                }

                return $this->decorateWebhookContext(
                    $this->completeReceipt(
                        receipt: $receipt,
                        providerCode: $providerCode,
                        scope: PaymentSessionScope::Deposit,
                        applied: $applied,
                        eventCode: (string) $event['provider_event_code'],
                        sessionCode: (string) $event['provider_session_code'],
                        ignoredOutcome: $this->ignoredStateRegressionOutcome($currentStatus, (string) ($event['session_status'] ?? $currentStatus)),
                        auditContext: $this->webhookContextFromSession($lockedSession, $reservation),
                    ),
                    $lockedSession,
                    $reservation,
                );
            });
        });

        $this->publishDepositPaidRealtimeEvent($depositPaidRealtimeContext);

        return $result;
    }

    /**
     * @param  array<string,mixed>  $event
     * @return array<string,mixed>
     */
    private function handleBillWebhook(string $providerCode, array $event, PaymentProviderWebhookReceipt $receipt): array
    {
        // Bill webhook follow cung mau nhu deposit nhung apply vao bill payment lifecycle.
        $session = ReservationBillPaymentSession::query()
            ->where('provider_code', $providerCode)
            ->where('provider_session_code', (string) $event['provider_session_code'])
            ->first();

        if (! $session instanceof ReservationBillPaymentSession) {
            return $this->failReceipt($receipt, 'Bill payment session not found for provider_session_code.');
        }

        return $this->locks->withReservationLock((int) $session->reservation_id, function () use ($providerCode, $event, $receipt, $session) {
            return DB::transaction(function () use ($providerCode, $event, $receipt, $session) {
                $lockedSession = ReservationBillPaymentSession::query()
                    ->whereKey((int) $session->bill_payment_session_id)
                    ->lockForUpdate()
                    ->first();
                $reservation = Reservation::query()
                    ->whereKey((int) $session->reservation_id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedSession instanceof ReservationBillPaymentSession || ! $reservation instanceof Reservation) {
                    return $this->decorateWebhookContext(
                        $this->failReceipt(
                            $receipt,
                            'Bill payment session or reservation disappeared before webhook could be applied.',
                            $this->webhookContextFromSession($session, null),
                        ),
                        $session,
                        null,
                    );
                }

                $staleOutcome = $this->ignoreStaleEventIfNeeded(
                    receipt: $receipt,
                    session: $lockedSession,
                    reservation: $reservation,
                    event: $event,
                    providerCode: $providerCode,
                    scope: PaymentSessionScope::Bill,
                );
                if ($staleOutcome !== null) {
                    return $this->decorateWebhookContext($staleOutcome, $lockedSession, $reservation);
                }

                $currentStatus = (string) ($lockedSession->session_status?->value ?? $lockedSession->session_status ?? '');
                $applied = $this->billLifecycle->applyProviderResult($lockedSession, $event, null);
                $lockedPayments = Payment::query()
                    ->where('reservation_id', (int) $reservation->reservation_id)
                    ->lockForUpdate()
                    ->get();
                $this->billLifecycle->applySucceededSessionIfNeeded($reservation, $lockedSession, $lockedPayments, null);

                return $this->decorateWebhookContext(
                    $this->completeReceipt(
                        receipt: $receipt,
                        providerCode: $providerCode,
                        scope: PaymentSessionScope::Bill,
                        applied: $applied,
                        eventCode: (string) $event['provider_event_code'],
                        sessionCode: (string) $event['provider_session_code'],
                        ignoredOutcome: $this->ignoredStateRegressionOutcome($currentStatus, (string) ($event['session_status'] ?? $currentStatus)),
                        auditContext: $this->webhookContextFromSession($lockedSession, $reservation),
                    ),
                    $lockedSession,
                    $reservation,
                );
            });
        });
    }

    /**
     * @return array<string,mixed>
     */
    private function completeReceipt(
        PaymentProviderWebhookReceipt $receipt,
        string $providerCode,
        PaymentSessionScope $scope,
        bool $applied,
        string $eventCode,
        string $sessionCode,
        ?array $ignoredOutcome = null,
        array $auditContext = [],
    ): array {
        $receipt->payment_scope = $scope->value;
        $receipt->delivery_status = $applied
            ? PaymentProviderWebhookReceiptStatus::Applied
            : PaymentProviderWebhookReceiptStatus::Ignored;
        $receipt->processed_at = Carbon::now('UTC');
        $receipt->failure_message = $applied
            ? null
            : Str::limit((string) ($ignoredOutcome['message'] ?? 'Webhook event was ignored because it would regress the stored payment session state.'), 255, '');
        $receipt->save();

        $response = [
            'duplicate' => false,
            'provider_code' => $providerCode,
            'provider_event_code' => $eventCode,
            'provider_session_code' => $sessionCode,
            'payment_scope' => $scope->value,
            'delivery_status' => $receipt->delivery_status->value,
            'receipt_id' => (int) $receipt->payment_provider_webhook_receipt_id,
        ];

        if (! $applied) {
            $response['ignored_reason'] = (string) ($ignoredOutcome['reason'] ?? 'terminal_state_regression_ignored');
            $response['message'] = (string) ($ignoredOutcome['message'] ?? 'Webhook event was ignored because it would regress a terminal payment session state.');
        }

        if ($auditContext !== []) {
            $response = array_merge($response, $auditContext);
        }

        $this->recordWebhookOutcome($applied ? 'applied' : 'ignored', $response);

        return $response;
    }

    /**
     * @return array<string,mixed>
     */
    private function failReceipt(PaymentProviderWebhookReceipt $receipt, string $message, array $auditContext = []): array
    {
        $receipt->delivery_status = PaymentProviderWebhookReceiptStatus::Failed;
        $receipt->processed_at = Carbon::now('UTC');
        $receipt->failure_message = Str::limit($message, 255, '');
        $receipt->save();

        $response = [
            'duplicate' => false,
            'provider_code' => (string) $receipt->provider_code,
            'provider_event_code' => (string) $receipt->provider_event_code,
            'provider_session_code' => (string) $receipt->provider_session_code,
            'payment_scope' => $receipt->payment_scope,
            'delivery_status' => $receipt->delivery_status->value,
            'receipt_id' => (int) $receipt->payment_provider_webhook_receipt_id,
            'failure_message' => (string) $receipt->failure_message,
        ];

        if ($auditContext !== []) {
            $response = array_merge($response, $auditContext);
        }

        $this->recordWebhookOutcome('failed', $response);

        return $response;
    }

    /**
     * @return array<string,mixed>
     */
    private function ignoreReceipt(
        PaymentProviderWebhookReceipt $receipt,
        string $providerCode,
        PaymentSessionScope $scope,
        string $eventCode,
        string $sessionCode,
        string $eventType,
        string $reason,
        string $message,
        array $auditContext = [],
    ): array {
        $receipt->payment_scope = $scope->value;
        $receipt->delivery_status = PaymentProviderWebhookReceiptStatus::Ignored;
        $receipt->processed_at = Carbon::now('UTC');
        $receipt->failure_message = Str::limit($message, 255, '');
        $receipt->save();

        $response = [
            'duplicate' => false,
            'provider_code' => $providerCode,
            'provider_event_code' => $eventCode,
            'provider_session_code' => $sessionCode,
            'payment_scope' => $scope->value,
            'delivery_status' => $receipt->delivery_status->value,
            'receipt_id' => (int) $receipt->payment_provider_webhook_receipt_id,
            'event_type' => $eventType,
            'ignored_reason' => $reason,
            'message' => $message,
        ];

        if ($auditContext !== []) {
            $response = array_merge($response, $auditContext);
        }

        $this->recordWebhookOutcome('ignored', $response);

        return $response;
    }

    private function resolveScope(string $providerCode, string $providerSessionCode, mixed $declaredScope): PaymentSessionScope
    {
        return $this->sessionScopeGuard->resolveVerifiedScope($providerCode, $providerSessionCode, $declaredScope);
    }

    private function paymentScopeForStorage(mixed $value): ?string
    {
        $normalized = $this->normalizeNullableString($value);
        if ($normalized === null) {
            return null;
        }

        return PaymentSessionScope::tryFrom($normalized)?->value;
    }

    /**
     * @param  array<string,mixed>  $event
     * @return array<string,mixed>
     */
    private function webhookFailureResponseContext(array $event): array
    {
        $declaredScope = $this->normalizeNullableString($event['payment_scope'] ?? null);

        return $declaredScope !== null ? ['payment_scope' => $declaredScope] : [];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildDuplicateResponse(string $providerCode, PaymentProviderWebhookReceipt $receipt): array
    {
        $response = [
            'duplicate' => true,
            'provider_code' => $providerCode,
            'provider_event_code' => (string) $receipt->provider_event_code,
            'provider_session_code' => (string) $receipt->provider_session_code,
            'payment_scope' => $receipt->payment_scope,
            'delivery_status' => (string) ($receipt->delivery_status?->value ?? $receipt->delivery_status),
            'receipt_id' => (int) $receipt->payment_provider_webhook_receipt_id,
        ];

        if (is_string($receipt->failure_message) && trim($receipt->failure_message) !== '') {
            $response['failure_message'] = (string) $receipt->failure_message;
        }

        return $response;
    }

    private function shouldResumeIncompleteDuplicateReceipt(PaymentProviderWebhookReceipt $receipt): bool
    {
        $status = (string) ($receipt->delivery_status?->value ?? $receipt->delivery_status);
        if ($status === PaymentProviderWebhookReceiptStatus::Received->value) {
            return true;
        }

        if ($status !== PaymentProviderWebhookReceiptStatus::Failed->value) {
            return false;
        }

        return $this->isRetryableFailedDuplicateReceipt($receipt);
    }

    private function isRetryableFailedDuplicateReceipt(PaymentProviderWebhookReceipt $receipt): bool
    {
        $message = trim((string) ($receipt->failure_message ?? ''));

        return in_array($message, [
            'Deposit payment session not found for provider_session_code.',
            'Bill payment session not found for provider_session_code.',
            'Deposit payment session or reservation disappeared before webhook could be applied.',
            'Bill payment session or reservation disappeared before webhook could be applied.',
            'Webhook payload payment_scope does not match the stored payment session scope.',
        ], true);
    }

    private function sameWebhookRequestBody(?string $storedRawBody, string $incomingRawBody): bool
    {
        return PaymentProviderPayloadSanitizer::webhookBodyFingerprint((string) ($storedRawBody ?? ''))
            === PaymentProviderPayloadSanitizer::webhookBodyFingerprint($incomingRawBody);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function isDuplicateWebhookReceiptException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'uq_payment_provider_webhook_receipts__provider_code__provider_event_code')
            || str_contains($message, 'payment_provider_webhook_receipts.provider_code')
            || str_contains($message, 'duplicate');
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        foreach ($exception->errors() as $messages) {
            if (is_array($messages) && isset($messages[0]) && is_string($messages[0]) && trim($messages[0]) !== '') {
                return $messages[0];
            }
        }

        return 'Webhook processing failed validation.';
    }

    /**
     * @param  array<string,mixed>  $event
     * @return array<string,mixed>|null
     */
    private function ignoreStaleEventIfNeeded(
        PaymentProviderWebhookReceipt $receipt,
        ReservationDepositPaymentSession|ReservationBillPaymentSession $session,
        ?Reservation $reservation,
        array $event,
        string $providerCode,
        PaymentSessionScope $scope
    ): ?array {
        $occurredAt = $event['occurred_at'] ?? null;
        $lastReconciledAt = $session->last_reconciled_at;

        if (! $occurredAt instanceof Carbon || ! $lastReconciledAt instanceof Carbon || ! $occurredAt->lt($lastReconciledAt)) {
            return null;
        }

        return $this->ignoreReceipt(
            receipt: $receipt,
            providerCode: $providerCode,
            scope: $scope,
            eventCode: (string) $event['provider_event_code'],
            sessionCode: (string) $event['provider_session_code'],
            eventType: trim((string) ($event['event_type'] ?? 'payment.session.updated')) ?: 'payment.session.updated',
            reason: 'stale_event_order_ignored',
            message: 'Webhook event was ignored because it is older than the last reconciled provider event for this payment session.',
            auditContext: $this->webhookContextFromSession($session, $reservation),
        );
    }

    /**
     * @return array{reason:string,message:string}|null
     */
    private function ignoredStateRegressionOutcome(string $currentStatus, string $incomingStatus): ?array
    {
        $reason = PaymentSessionStatusTransitionPolicy::ignoreReason($currentStatus, $incomingStatus);
        if ($reason === null) {
            return null;
        }

        return match ($reason) {
            'terminal_state_regression_ignored' => [
                'reason' => $reason,
                'message' => 'Webhook event was ignored because it would regress a terminal payment session state.',
            ],
            default => [
                'reason' => $reason,
                'message' => 'Webhook event was ignored because it would regress the stored payment session state.',
            ],
        };
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function recordWebhookOutcome(string $kind, array $context): void
    {
        if (! (bool) config('booking.payment_providers.observability.enabled', true)) {
            return;
        }

        $channel = trim((string) config('booking.payment_providers.observability.log_channel', 'audit'));
        $level = trim((string) config(sprintf('booking.payment_providers.observability.%s_level', $kind), 'info'));
        $payload = array_filter([
            'kind' => $kind,
            'provider_code' => $context['provider_code'] ?? null,
            'provider_event_code' => $context['provider_event_code'] ?? null,
            'provider_session_code' => $context['provider_session_code'] ?? null,
            'payment_scope' => $context['payment_scope'] ?? null,
            'delivery_status' => $context['delivery_status'] ?? null,
            'duplicate' => $context['duplicate'] ?? null,
            'ignored_reason' => $context['ignored_reason'] ?? null,
            'failure_message' => $context['failure_message'] ?? ($context['message'] ?? null),
            'receipt_id' => $context['receipt_id'] ?? null,
            'reservation_id' => $context['reservation_id'] ?? null,
            'payment_session_type' => $context['payment_session_type'] ?? null,
            'payment_session_id' => $context['payment_session_id'] ?? null,
            'linked_payment_id' => $context['linked_payment_id'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        try {
            AuditEvent::log($level !== '' ? $level : 'info', 'payment_provider_webhook', $payload);

            if ($channel !== '' && $channel !== 'audit') {
                Log::channel($channel)->log($level !== '' ? $level : 'info', 'payment_provider_webhook', $payload);
            }
        } catch (\Throwable) {
            Log::log($level !== '' ? $level : 'info', 'payment_provider_webhook', $payload);
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function decorateWebhookContext(
        array $payload,
        ReservationDepositPaymentSession|ReservationBillPaymentSession $session,
        ?Reservation $reservation,
    ): array {
        return array_merge($payload, $this->webhookContextFromSession($session, $reservation));
    }

    /**
     * @return array<string,int|string|null>
     */
    private function webhookContextFromSession(
        ReservationDepositPaymentSession|ReservationBillPaymentSession $session,
        ?Reservation $reservation,
    ): array {
        $sessionType = $session instanceof ReservationDepositPaymentSession
            ? 'deposit_payment_session'
            : 'bill_payment_session';
        $sessionId = $session instanceof ReservationDepositPaymentSession
            ? (int) $session->deposit_payment_session_id
            : (int) $session->bill_payment_session_id;

        return [
            'reservation_id' => $reservation?->reservation_id ?? $session->reservation_id,
            'payment_session_type' => $sessionType,
            'payment_session_id' => $sessionId,
            'linked_payment_id' => $session->linked_payment_id !== null ? (int) $session->linked_payment_id : null,
        ];
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
}
