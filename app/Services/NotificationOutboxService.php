<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\NotificationDeliveryAttempt;
use App\Models\NotificationOutbox;
use App\Models\Reservation;
use App\Services\Notifications\NotificationChannelManager;
use App\Services\Notifications\NotificationDeliveryException;
use App\Services\Notifications\NotificationPreferenceService;
use App\Support\AuditEvent;
use App\Support\PaymentSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class NotificationOutboxService
{
    private NotificationChannelManager $channelManager;

    private NotificationPreferenceService $preferenceService;

    public function __construct(
        ?NotificationChannelManager $channelManager = null,
        ?NotificationPreferenceService $preferenceService = null,
    ) {
        $this->channelManager = $channelManager ?? app(NotificationChannelManager::class);
        $this->preferenceService = $preferenceService ?? app(NotificationPreferenceService::class);
    }

    /**
     * @param  array<int,int|string>  $refundPaymentIds
     */
    private function buildRefundIdempotencyKey(
    int $reservationId,
    array $refundPaymentIds,
    float $refundAmount,
    string $refundScope
): string {
    $paymentIds = array_values(array_map('intval', $refundPaymentIds));
    sort($paymentIds);

    $payload = json_encode([
        'payment_ids' => $paymentIds,
        'refund_amount' => round($refundAmount, 2),
        'refund_scope' => $refundScope,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';

    // 40 ký tự hex là đủ ổn định và giữ tổng độ dài < 64
    $fingerprint = hash('sha1', $payload);

    return sprintf('reservation:%d:rf:%s', $reservationId, $fingerprint);
}

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function enqueueMessage(array $attributes): ?NotificationOutbox
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $channel = $this->normalizeChannel((string) ($attributes['channel'] ?? 'Email'));
        $recipient = trim((string) ($attributes['recipient'] ?? ''));
        $templateKey = $this->normalizeTemplateKey((string) ($attributes['template_key'] ?? ''));
        $payload = (array) ($attributes['payload'] ?? []);
        $relatedReservationId = isset($attributes['related_reservation_id']) && $attributes['related_reservation_id'] !== null
            ? (int) $attributes['related_reservation_id']
            : null;
        $recipientUserId = isset($attributes['recipient_user_id']) && $attributes['recipient_user_id'] !== null
            ? (int) $attributes['recipient_user_id']
            : null;
        $eventKey = $this->normalizeNullableString($attributes['event_key'] ?? $templateKey) ?? $templateKey;
        $idempotencyKey = $this->normalizeNullableString($attributes['idempotency_key'] ?? null)
            ?? $this->buildGeneratedIdempotencyKey($channel, $recipient, $templateKey, $payload);
        $dedupeKey = $this->normalizeNullableString($attributes['dedupe_key'] ?? $idempotencyKey);
        $cooldownSeconds = max(0, (int) ($attributes['cooldown_seconds'] ?? $this->cooldownSecondsForTemplate($templateKey)));
        $createdAt = ($attributes['created_at'] ?? null) instanceof Carbon
            ? $attributes['created_at']->copy()->utc()
            : Carbon::now('UTC');
        $missingRecipientAuditContext = (array) ($attributes['missing_recipient_audit_context'] ?? []);

        if ($recipient === '') {
            AuditEvent::warning('notification_outbox_skipped_missing_recipient', array_merge($missingRecipientAuditContext, [
                'channel' => $channel,
                'template_key' => $templateKey,
            ]));

            return null;
        }

        $existing = NotificationOutbox::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $preference = $this->preferenceService->evaluate($recipientUserId, $channel, $createdAt);
        if (! ($preference['enabled'] ?? true)) {
            AuditEvent::info('notification_outbox_suppressed_by_preference', [
                'channel' => $channel,
                'template_key' => $templateKey,
                'recipient_user_id' => $recipientUserId,
                'dedupe_key' => $dedupeKey,
                'reason' => $preference['reason'] ?? null,
            ]);

            return null;
        }

        if ($dedupeKey !== null && $cooldownSeconds > 0) {
            $suppressed = $this->findRecentMessageForDedupeKey(
                $dedupeKey,
                $createdAt->copy()->subSeconds($cooldownSeconds),
            );
            if ($suppressed !== null) {
                AuditEvent::info('notification_outbox_suppressed_by_cooldown', [
                    'outbox_id' => (int) $suppressed->outbox_id,
                    'channel' => $channel,
                    'template_key' => $templateKey,
                    'dedupe_key' => $dedupeKey,
                    'cooldown_seconds' => $cooldownSeconds,
                ]);

                return $suppressed;
            }
        }

        $message = new NotificationOutbox();
        $message->channel = $channel;
        $message->recipient = $recipient;
        $message->recipient_user_id = $recipientUserId;
        $message->template_key = $templateKey;
        $message->idempotency_key = $idempotencyKey;
        $message->dedupe_key = $dedupeKey;
        $message->payload_json = $this->enrichPayloadWithNotificationMeta(
            $payload,
            $channel,
            $templateKey,
            $eventKey,
            $recipientUserId,
            $dedupeKey,
            $cooldownSeconds,
        );
        $message->status = 'Pending';
        $message->processing_token = null;
        $message->locked_until = null;
        $message->locked_by = null;
        $message->attempt_count = 0;
        $message->last_attempted_at = null;
        $message->next_retry_at = $preference['quiet_until'] ?? null;
        $message->last_error = null;
        $message->related_reservation_id = $relatedReservationId;
        $message->created_at = $createdAt;
        $message->sent_at = null;
        $message->save();

        AuditEvent::info('notification_outbox_enqueued', [
            'outbox_id' => (int) $message->outbox_id,
            'reservation_id' => (int) ($message->related_reservation_id ?? 0),
            'channel' => (string) $message->channel,
            'template_key' => (string) $message->template_key,
            'recipient_masked' => $this->maskRecipientForAudit($recipient),
            'delayed_until_utc' => $message->next_retry_at?->toIso8601String(),
        ]);
        $this->recordMetric('notification_outbox_enqueued_total', [
            'channel' => (string) $message->channel,
            'template_key' => (string) $message->template_key,
        ]);

        return $message;
    }

    public function enqueueReservationCreated(Reservation $reservation): ?NotificationOutbox
    {
        return $this->enqueueReservationEmail(
            reservation: $reservation,
            templateKey: 'reservation.created',
            idempotencyKey: sprintf('reservation:%d:created:email', (int) $reservation->reservation_id),
            dedupeKey: sprintf('reservation:%d:%s:Email', (int) $reservation->reservation_id, 'reservation.created'),
        );
    }

    public function enqueueReservationCancelled(Reservation $reservation): ?NotificationOutbox
    {
        return $this->enqueueReservationEmail(
            reservation: $reservation,
            templateKey: 'reservation.cancelled',
            idempotencyKey: sprintf('reservation:%d:cancelled:email', (int) $reservation->reservation_id),
            dedupeKey: sprintf('reservation:%d:%s:Email', (int) $reservation->reservation_id, 'reservation.cancelled'),
        );
    }

    public function enqueueReservationUpdated(Reservation $reservation, array $changeSet = []): ?NotificationOutbox
    {
        return $this->enqueueReservationChangeEmail(
            reservation: $reservation,
            templateKey: 'reservation.updated',
            idempotencyKey: sprintf('reservation:%d:updated:v%d:email', (int) $reservation->reservation_id, (int) ($reservation->row_version ?? 1)),
            changeSet: $changeSet,
            dedupeKey: null,
        );
    }

    public function enqueueReservationRescheduled(Reservation $reservation, array $changeSet = []): ?NotificationOutbox
    {
        return $this->enqueueReservationChangeEmail(
            reservation: $reservation,
            templateKey: 'reservation.rescheduled',
            idempotencyKey: sprintf('reservation:%d:rescheduled:v%d:email', (int) $reservation->reservation_id, (int) ($reservation->row_version ?? 1)),
            changeSet: $changeSet,
            dedupeKey: null,
        );
    }

    public function enqueueReservationReminder(Reservation $reservation, ?int $leadMinutes = null): ?NotificationOutbox
    {
        $leadMinutes ??= (int) config('notifications.outbox.reminder_lead_minutes', 60);

        return $this->enqueueReservationEmail(
            reservation: $reservation,
            templateKey: 'reservation.reminder',
            idempotencyKey: sprintf('reservation:%d:reminder:%d:email', (int) $reservation->reservation_id, max(1, $leadMinutes)),
            dedupeKey: sprintf('reservation:%d:%s:Email', (int) $reservation->reservation_id, 'reservation.reminder'),
        );
    }

    public function enqueueReservationCheckedIn(Reservation $reservation): ?NotificationOutbox
    {
        return $this->enqueueReservationEmail(
            reservation: $reservation,
            templateKey: 'reservation.checked_in',
            idempotencyKey: sprintf('reservation:%d:checked-in:email', (int) $reservation->reservation_id),
            dedupeKey: sprintf('reservation:%d:%s:Email', (int) $reservation->reservation_id, 'reservation.checked_in'),
        );
    }

    public function enqueueReservationExpired(Reservation $reservation): ?NotificationOutbox
    {
        return $this->enqueueReservationEmail(
            reservation: $reservation,
            templateKey: 'reservation.expired',
            idempotencyKey: sprintf('reservation:%d:expired:email', (int) $reservation->reservation_id),
            dedupeKey: sprintf('reservation:%d:%s:Email', (int) $reservation->reservation_id, 'reservation.expired'),
        );
    }

    public function enqueueReservationNoShow(Reservation $reservation): ?NotificationOutbox
    {
        return $this->enqueueReservationEmail(
            reservation: $reservation,
            templateKey: 'reservation.no_show',
            idempotencyKey: sprintf('reservation:%d:no-show:email', (int) $reservation->reservation_id),
            dedupeKey: sprintf('reservation:%d:%s:Email', (int) $reservation->reservation_id, 'reservation.no_show'),
        );
    }

    public function enqueueCheckoutCompleted(Reservation $reservation): ?NotificationOutbox
    {
        return $this->enqueueReservationEmail(
            reservation: $reservation,
            templateKey: 'checkout.completed',
            idempotencyKey: sprintf('reservation:%d:checkout-completed:email', (int) $reservation->reservation_id),
            dedupeKey: sprintf('reservation:%d:%s:Email', (int) $reservation->reservation_id, 'checkout.completed'),
        );
    }

    public function enqueuePaymentRefunded(Reservation $reservation, array $refundMeta = []): ?NotificationOutbox
    {
        $reservation->loadMissing('user', 'tables', 'payments');

        $refundPaymentIds = array_values(array_map('intval', (array) ($refundMeta['refund_payment_ids'] ?? [])));
        sort($refundPaymentIds);

        $payload = array_merge($this->buildReservationPayload($reservation), [
            'refund_amount' => round((float) ($refundMeta['refund_amount'] ?? 0.0), 2),
            'refund_currency' => (string) ($refundMeta['currency'] ?? ($reservation->bill_currency ?: 'VND')),
            'refund_scope' => (string) ($refundMeta['refund_scope'] ?? 'all'),
        ]);

        $idempotencyKey = $this->buildRefundIdempotencyKey(
            (int) $reservation->reservation_id,
            $refundPaymentIds,
            (float) $payload['refund_amount'],
            (string) $payload['refund_scope'],
        );

        return $this->enqueueMessage([
            'channel' => 'Email',
            'recipient' => (string) ($reservation->user?->email ?? ''),
            'recipient_user_id' => $reservation->user_id !== null ? (int) $reservation->user_id : null,
            'template_key' => 'payment.refunded',
            'event_key' => 'payment.refunded',
            'idempotency_key' => $idempotencyKey,
            'dedupe_key' => $idempotencyKey,
            'payload' => $payload,
            'related_reservation_id' => (int) $reservation->reservation_id,
            'missing_recipient_audit_context' => [
                'reservation_id' => (int) $reservation->reservation_id,
                'user_id' => (int) ($reservation->user_id ?? 0),
            ],
        ]);
    }

    public function enqueueWaitingListNotified(\App\Models\WaitingList $entry, \App\Models\RestaurantTable $table, Carbon $expiresAt): ?NotificationOutbox
    {
        $entry->loadMissing('user');

        $payload = [
            'waiting_id' => (int) $entry->waiting_id,
            'customer_name' => (string) ($entry->user?->full_name ?: ($entry->guest_name ?: 'Quý khách')),
            'customer_email_masked' => $this->maskRecipientForAudit((string) ($entry->user?->email ?? '')),
            'guest_name' => (string) ($entry->guest_name ?? ''),
            'phone' => (string) ($entry->phone ?? ''),
            'guest_count' => (int) $entry->guest_count,
            'table_label' => (string) ($table->table_code ?? ('#' . $table->table_id)),
            'notify_expires_at_utc' => $this->formatUtcDateTime($expiresAt),
            'restaurant_name' => (string) config('app.name', 'RestaurantPOS'),
        ];

        $idempotencyKey = sprintf(
            'waiting-list:%d:notified:%s',
            (int) $entry->waiting_id,
            $expiresAt->copy()->utc()->format('YmdHis')
        );

        return $this->enqueueMessage([
            'channel' => 'Email',
            'recipient' => (string) ($entry->user?->email ?? ''),
            'recipient_user_id' => $entry->user_id !== null ? (int) $entry->user_id : null,
            'template_key' => 'waiting_list.notified',
            'event_key' => 'waiting_list.notified',
            'idempotency_key' => $idempotencyKey,
            'dedupe_key' => sprintf('waiting-list:%d:%s:Email', (int) $entry->waiting_id, 'waiting_list.notified'),
            'payload' => $payload,
            'related_reservation_id' => null,
            'missing_recipient_audit_context' => [
                'waiting_id' => (int) $entry->waiting_id,
                'user_id' => (int) ($entry->user_id ?? 0),
            ],
        ]);
    }

    public function enqueueDueReservationReminders(?Carbon $now = null): int
    {
        if (! $this->isEnabled() || ! (bool) config('notifications.outbox.reminder_enabled', true)) {
            return 0;
        }

        $now ??= Carbon::now('UTC');
        $leadMinutes = max(1, (int) config('notifications.outbox.reminder_lead_minutes', 60));
        $windowMinutes = max(1, (int) config('notifications.outbox.reminder_window_minutes', 10));

        $windowStart = $now->copy()->addMinutes($leadMinutes);
        $windowEnd = $windowStart->copy()->addMinutes($windowMinutes);

        $reservations = Reservation::query()
            ->with(['user', 'tables'])
            ->where('status', ReservationStatus::Confirmed->value)
            ->where('start_time', '>=', $windowStart)
            ->where('start_time', '<', $windowEnd)
            ->orderBy('start_time')
            ->orderBy('reservation_id')
            ->get();

        $count = 0;
        foreach ($reservations as $reservation) {
            $message = $this->enqueueReservationReminder($reservation, $leadMinutes);
            if ($message !== null && (bool) ($message->wasRecentlyCreated ?? false)) {
                $count++;
            }
        }

        if ($count > 0) {
            AuditEvent::info('notification_outbox_reminders_enqueued', [
                'count' => $count,
                'lead_minutes' => $leadMinutes,
                'window_minutes' => $windowMinutes,
                'window_start_utc' => $windowStart->toIso8601String(),
                'window_end_utc' => $windowEnd->toIso8601String(),
            ]);
        }

        return $count;
    }

    private function enqueueReservationChangeEmail(
        Reservation $reservation,
        string $templateKey,
        string $idempotencyKey,
        array $changeSet = [],
        ?string $dedupeKey = null,
    ): ?NotificationOutbox {
        $reservation->loadMissing('user', 'tables', 'payments');
        return $this->enqueueMessage([
            'channel' => 'Email',
            'recipient' => (string) ($reservation->user?->email ?? ''),
            'recipient_user_id' => $reservation->user_id !== null ? (int) $reservation->user_id : null,
            'template_key' => $templateKey,
            'event_key' => $templateKey,
            'idempotency_key' => $idempotencyKey,
            'dedupe_key' => $dedupeKey,
            'payload' => array_merge(
                $this->buildReservationPayload($reservation),
                [
                    'change_set' => $this->buildReservationChangePayload($changeSet),
                ],
            ),
            'related_reservation_id' => (int) $reservation->reservation_id,
            'missing_recipient_audit_context' => [
                'reservation_id' => (int) $reservation->reservation_id,
                'user_id' => (int) ($reservation->user_id ?? 0),
            ],
        ]);
    }

    public function processDueMessages(?int $limit = null, ?string $workerId = null): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $limit ??= (int) config('notifications.outbox.batch_size', 20);
        $workerId ??= sprintf('%s:%s', (string) config('app.name', 'app'), gethostname() ?: 'worker');

        $messages = $this->claimDueMessages($limit, $workerId);
        foreach ($messages as $message) {
            $this->deliverMessage($message, $workerId);
        }

        return $messages->count();
    }

    private function enqueueReservationEmail(
        Reservation $reservation,
        string $templateKey,
        string $idempotencyKey,
        ?string $dedupeKey = null,
    ): ?NotificationOutbox
    {
        $reservation->loadMissing('user', 'tables', 'payments');
        return $this->enqueueMessage([
            'channel' => 'Email',
            'recipient' => (string) ($reservation->user?->email ?? ''),
            'recipient_user_id' => $reservation->user_id !== null ? (int) $reservation->user_id : null,
            'template_key' => $templateKey,
            'event_key' => $templateKey,
            'idempotency_key' => $idempotencyKey,
            'dedupe_key' => $dedupeKey,
            'payload' => $this->buildReservationPayload($reservation),
            'related_reservation_id' => (int) $reservation->reservation_id,
            'missing_recipient_audit_context' => [
                'reservation_id' => (int) $reservation->reservation_id,
                'user_id' => (int) ($reservation->user_id ?? 0),
            ],
        ]);
    }

    /** @return Collection<int, NotificationOutbox> */
    private function claimDueMessages(int $limit, string $workerId): Collection
    {
        $now = Carbon::now('UTC');
        $lockSeconds = max(30, (int) config('notifications.outbox.lock_seconds', 90));
        $processingToken = (string) Str::uuid();

        return DB::transaction(function () use ($limit, $workerId, $now, $lockSeconds, $processingToken) {
            $messages = NotificationOutbox::query()
                ->whereIn('status', ['Pending', 'Failed', 'Processing'])
                ->where(function ($query) use ($now) {
                    $query->whereNull('next_retry_at')
                        ->orWhere('next_retry_at', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->where('status', '!=', 'Processing')
                        ->orWhereNull('locked_until')
                        ->orWhere('locked_until', '<=', $now);
                })
                ->orderBy('created_at')
                ->orderBy('outbox_id')
                ->lockForUpdate()
                ->limit($limit)
                ->get();

            foreach ($messages as $message) {
                $message->status = 'Processing';
                $message->processing_token = $processingToken;
                $message->locked_by = $workerId;
                $message->locked_until = $now->copy()->addSeconds($lockSeconds);
                $message->save();
            }

            if ($messages->isNotEmpty()) {
                $this->recordMetric('notification_outbox_claimed_total', ['status' => 'Processing'], $messages->count());
            }

            return $messages;
        });
    }

    private function deliverMessage(NotificationOutbox $message, string $workerId): void
    {
        $dispatchPayload = [];
        $providerKey = null;
        $attemptedAt = Carbon::now('UTC');

        try {
            $message->attempt_count = (int) $message->attempt_count + 1;
            $message->last_attempted_at = $attemptedAt;
            $message->save();

            $dispatchPayload = $this->buildDispatchPayload($message);
            $driver = $this->channelManager->resolve((string) $message->channel);
            $providerKey = $driver->providerKey();
            $result = $driver->send($message, $dispatchPayload);

            $this->recordDeliveryAttempt(
                message: $message,
                providerKey: $providerKey,
                status: 'Succeeded',
                dispatchPayload: $dispatchPayload,
                attemptedAt: $attemptedAt,
                providerStatus: $result->providerStatus,
                providerMessageId: $result->providerMessageId,
                responsePayload: $result->responsePayload,
            );

            $message->status = 'Sent';
            $message->processing_token = null;
            $message->locked_by = null;
            $message->locked_until = null;
            $message->next_retry_at = null;
            $message->last_error = null;
            $message->sent_at = Carbon::now('UTC');
            $message->save();

            AuditEvent::info('notification_outbox_sent', [
                'outbox_id' => (int) $message->outbox_id,
                'reservation_id' => (int) ($message->related_reservation_id ?? 0),
                'channel' => (string) $message->channel,
                'template_key' => (string) $message->template_key,
                'recipient_masked' => $this->maskRecipientForAudit((string) $message->recipient),
                'provider_key' => $providerKey,
                'worker_id' => $workerId,
            ]);
            $this->recordMetric('notification_outbox_sent_total', [
                'channel' => (string) $message->channel,
                'template_key' => (string) $message->template_key,
            ]);
        } catch (Throwable $e) {
            $maxAttempts = max(1, (int) config('notifications.outbox.max_attempts', 5));
            $exhausted = (int) $message->attempt_count >= $maxAttempts;
            $errorCode = $e instanceof NotificationDeliveryException ? $e->errorCode() : null;
            $responsePayload = $e instanceof NotificationDeliveryException ? $e->responsePayload() : [];

            $this->recordDeliveryAttempt(
                message: $message,
                providerKey: $providerKey,
                status: 'Failed',
                dispatchPayload: $dispatchPayload,
                attemptedAt: $attemptedAt,
                providerStatus: null,
                providerMessageId: null,
                responsePayload: $responsePayload,
                errorCode: $errorCode,
                errorMessage: $e->getMessage(),
            );

            $message->status = $exhausted ? 'Cancelled' : 'Failed';
            $message->processing_token = null;
            $message->locked_by = null;
            $message->locked_until = null;
            $message->next_retry_at = $exhausted ? null : $this->computeNextRetryAt((int) $message->attempt_count);
            $message->last_error = mb_substr($e->getMessage(), 0, 500);
            $message->save();

            AuditEvent::warning($exhausted ? 'notification_outbox_cancelled_after_max_attempts' : 'notification_outbox_failed', [
                'outbox_id' => (int) $message->outbox_id,
                'reservation_id' => (int) ($message->related_reservation_id ?? 0),
                'channel' => (string) $message->channel,
                'template_key' => (string) $message->template_key,
                'recipient_masked' => $this->maskRecipientForAudit((string) $message->recipient),
                'attempt_count' => (int) $message->attempt_count,
                'max_attempts' => $maxAttempts,
                'next_retry_at' => $message->next_retry_at?->toIso8601String(),
                'provider_key' => $providerKey,
                'worker_id' => $workerId,
                'error' => $e->getMessage(),
                'error_code' => $errorCode,
            ]);
            $this->recordMetric($exhausted ? 'notification_outbox_cancelled_total' : 'notification_outbox_failed_total', [
                'channel' => (string) $message->channel,
                'template_key' => (string) $message->template_key,
            ]);
        }
    }

    private function computeNextRetryAt(int $attemptCount): Carbon
    {
        $backoffMinutes = config('notifications.outbox.retry_backoff_minutes', [1, 5, 15, 60]);
        $index = max(0, min(count($backoffMinutes) - 1, $attemptCount - 1));
        $minutes = max(1, (int) ($backoffMinutes[$index] ?? 15));

        return Carbon::now('UTC')->addMinutes($minutes);
    }

    /**
     * @return array<string,mixed>
     */
    private function buildDispatchPayload(NotificationOutbox $message): array
    {
        $payload = (array) $message->payload_json;

        return [
            'channel' => (string) $message->channel,
            'recipient' => (string) $message->recipient,
            'template_key' => (string) $message->template_key,
            'payload' => $payload,
            'subject' => $this->resolveSubject((string) $message->template_key, $payload),
            'text_body' => $this->renderEmailBody((string) $message->template_key, $payload),
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function enrichPayloadWithNotificationMeta(
        array $payload,
        string $channel,
        string $templateKey,
        string $eventKey,
        ?int $recipientUserId,
        ?string $dedupeKey,
        int $cooldownSeconds,
    ): array {
        $meta = (array) ($payload['_notification'] ?? []);
        $meta['channel'] = $channel;
        $meta['template_key'] = $templateKey;
        $meta['event_key'] = $eventKey;
        $meta['recipient_user_id'] = $recipientUserId;
        $meta['dedupe_key'] = $dedupeKey;
        $meta['cooldown_seconds'] = $cooldownSeconds;
        $payload['_notification'] = $meta;

        return $payload;
    }

    private function findRecentMessageForDedupeKey(string $dedupeKey, Carbon $windowStart): ?NotificationOutbox
    {
        return NotificationOutbox::query()
            ->where('dedupe_key', $dedupeKey)
            ->where('status', '!=', 'Cancelled')
            ->where('created_at', '>=', $windowStart)
            ->orderByDesc('created_at')
            ->orderByDesc('outbox_id')
            ->first();
    }

    /**
     * @param  array<string,mixed>  $dispatchPayload
     * @param  array<string,mixed>  $responsePayload
     */
    private function recordDeliveryAttempt(
        NotificationOutbox $message,
        ?string $providerKey,
        string $status,
        array $dispatchPayload,
        Carbon $attemptedAt,
        ?string $providerStatus = null,
        ?string $providerMessageId = null,
        array $responsePayload = [],
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): void {
        try {
            NotificationDeliveryAttempt::query()->create([
                'outbox_id' => (int) $message->outbox_id,
                'channel' => (string) $message->channel,
                'provider_key' => $providerKey,
                'attempt_number' => (int) $message->attempt_count,
                'status' => $status,
                'recipient' => (string) $message->recipient,
                'provider_message_id' => $providerMessageId,
                'provider_status' => $providerStatus,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'request_payload_json' => $dispatchPayload === [] ? null : $dispatchPayload,
                'response_payload_json' => $responsePayload === [] ? null : $responsePayload,
                'attempted_at' => $attemptedAt,
                'completed_at' => Carbon::now('UTC'),
                'created_at' => $attemptedAt,
            ]);
        } catch (Throwable) {
            // delivery evidence is best-effort; outbox state must still be persisted.
        }
    }

    private function buildGeneratedIdempotencyKey(string $channel, string $recipient, string $templateKey, array $payload): string
    {
        $fingerprint = hash('sha1', json_encode([
            'channel' => $channel,
            'recipient' => $recipient,
            'template_key' => $templateKey,
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');

        return sprintf('auto:%s:%s', strtolower($channel), $fingerprint);
    }

    private function cooldownSecondsForTemplate(string $templateKey): int
    {
        return max(0, (int) data_get(config('notifications.outbox.cooldowns', []), $templateKey, 0));
    }

    private function normalizeChannel(string $channel): string
    {
        $trimmed = trim($channel);

        return match (strtolower($trimmed)) {
            'email' => 'Email',
            'sms' => 'SMS',
            'zalo' => 'Zalo',
            'push' => 'Push',
            'webhook' => 'Webhook',
            default => $trimmed,
        };
    }

    private function normalizeTemplateKey(string $templateKey): string
    {
        return strtolower(trim($templateKey));
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function buildReservationPayload(Reservation $reservation): array
    {
        $reservation->loadMissing('tables', 'user', 'payments');

        $summary = PaymentSummary::fromPayments($reservation->payments);
        $paidAmount = round((float) ($summary['net_paid_amount'] ?? 0.0), 2);
        $refundedAmount = round((float) ($summary['refunded_amount'] ?? 0.0), 2);

        return [
            'reservation_id' => (int) $reservation->reservation_id,
            'reservation_code' => (string) $reservation->reservation_code,
            'customer_name' => (string) ($reservation->user?->full_name ?? 'Quý khách'),
            'guest_count' => (int) $reservation->guest_count,
            'status' => (string) ($reservation->status?->value ?? $reservation->status),
            'start_time_utc' => $this->formatUtcDateTime($reservation->start_time),
            'end_time_utc' => $this->formatUtcDateTime($reservation->end_time),
            'checked_in_at_utc' => $this->formatUtcDateTime($reservation->checked_in_at),
            'checked_out_at_utc' => $this->formatUtcDateTime($reservation->checked_out_at),
            'table_labels' => $reservation->tables
                ->map(fn ($table) => (string) ($table->table_code ?? ('#' . $table->table_id)))
                ->values()
                ->all(),
            'notes' => $reservation->notes,
            'cancel_reason' => $reservation->cancel_reason,
            'paid_amount' => $paidAmount,
            'refunded_amount' => $refundedAmount,
            'bill_currency' => (string) ($reservation->bill_currency ?: 'VND'),
            'restaurant_name' => (string) config('app.name', 'RestaurantPOS'),
        ];
    }

    private function buildReservationChangePayload(array $changeSet): array
    {
        return [
            'previous_start_time_utc' => $this->normalizeMaybeUtcString($changeSet['previous_start_time_utc'] ?? null),
            'previous_end_time_utc' => $this->normalizeMaybeUtcString($changeSet['previous_end_time_utc'] ?? null),
            'previous_guest_count' => isset($changeSet['previous_guest_count']) ? (int) $changeSet['previous_guest_count'] : null,
            'previous_notes' => isset($changeSet['previous_notes']) ? (string) $changeSet['previous_notes'] : null,
            'previous_table_labels' => array_values(array_map('strval', (array) ($changeSet['previous_table_labels'] ?? []))),
            'new_start_time_utc' => $this->normalizeMaybeUtcString($changeSet['new_start_time_utc'] ?? null),
            'new_end_time_utc' => $this->normalizeMaybeUtcString($changeSet['new_end_time_utc'] ?? null),
            'new_guest_count' => isset($changeSet['new_guest_count']) ? (int) $changeSet['new_guest_count'] : null,
            'new_notes' => isset($changeSet['new_notes']) ? (string) $changeSet['new_notes'] : null,
            'new_table_labels' => array_values(array_map('strval', (array) ($changeSet['new_table_labels'] ?? []))),
            'change_reason' => isset($changeSet['reason']) ? (string) $changeSet['reason'] : null,
            'changed_fields' => array_values(array_map('strval', (array) ($changeSet['changed_fields'] ?? []))),
        ];
    }


    private function maskRecipientForAudit(?string $recipient): ?string
    {
        $recipient = trim((string) $recipient);
        if ($recipient === '') {
            return null;
        }

        $at = strpos($recipient, '@');
        if ($at === false) {
            if (mb_strlen($recipient) <= 4) {
                return str_repeat('*', mb_strlen($recipient));
            }

            return mb_substr($recipient, 0, 2) . str_repeat('*', max(1, mb_strlen($recipient) - 4)) . mb_substr($recipient, -2);
        }

        $local = mb_substr($recipient, 0, $at);
        $domain = mb_substr($recipient, $at + 1);
        $localMasked = match (mb_strlen($local)) {
            0 => '',
            1 => '*',
            2 => mb_substr($local, 0, 1) . '*',
            default => mb_substr($local, 0, 1) . str_repeat('*', max(1, mb_strlen($local) - 2)) . mb_substr($local, -1),
        };

        return $localMasked . '@' . $domain;
    }

    private function normalizeMaybeUtcString(mixed $dateTime): ?string
    {
        if ($dateTime === null || $dateTime === '') {
            return null;
        }

        return Carbon::parse((string) $dateTime)->utc()->format('Y-m-d H:i:s') . ' UTC';
    }

    private function formatUtcDateTime(mixed $dateTime): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        return Carbon::parse((string) $dateTime)->utc()->format('Y-m-d H:i:s') . ' UTC';
    }

    private function resolveSubject(string $templateKey, array $payload): string
    {
        return match ($templateKey) {
            'conversation.outbound_reply' => sprintf('[%s] Staff follow-up', (string) ($payload['restaurant_name'] ?? 'RestaurantPOS')),
            'reservation.created' => sprintf('[%s] Xác nhận đặt bàn %s', (string) ($payload['restaurant_name'] ?? 'RestaurantPOS'), (string) ($payload['reservation_code'] ?? '')),
            'reservation.cancelled' => sprintf('[%s] Đặt bàn %s đã được hủy', (string) ($payload['restaurant_name'] ?? 'RestaurantPOS'), (string) ($payload['reservation_code'] ?? '')),
            'reservation.updated' => sprintf('[%s] Thông tin đặt bàn %s đã được cập nhật', (string) ($payload['restaurant_name'] ?? 'RestaurantPOS'), (string) ($payload['reservation_code'] ?? '')),
            'reservation.rescheduled' => sprintf('[%s] Đặt bàn %s đã được dời lịch', (string) ($payload['restaurant_name'] ?? 'RestaurantPOS'), (string) ($payload['reservation_code'] ?? '')),
            'reservation.reminder' => sprintf('[%s] Nhắc lịch đặt bàn %s', (string) ($payload['restaurant_name'] ?? 'RestaurantPOS'), (string) ($payload['reservation_code'] ?? '')),
            'reservation.checked_in' => sprintf('[%s] Check-in thành công %s', (string) ($payload['restaurant_name'] ?? 'RestaurantPOS'), (string) ($payload['reservation_code'] ?? '')),
            'reservation.expired' => sprintf('[%s] Đặt bàn %s đã hết hiệu lực', (string) ($payload['restaurant_name'] ?? 'RestaurantPOS'), (string) ($payload['reservation_code'] ?? '')),
            'reservation.no_show' => sprintf('[%s] Đặt bàn %s đã được ghi nhận no-show', (string) ($payload['restaurant_name'] ?? 'RestaurantPOS'), (string) ($payload['reservation_code'] ?? '')),
            'checkout.completed' => sprintf('[%s] Thanh toán thành công %s', (string) ($payload['restaurant_name'] ?? 'RestaurantPOS'), (string) ($payload['reservation_code'] ?? '')),
            'payment.refunded' => sprintf('[%s] Hoàn tiền đã được ghi nhận %s', (string) ($payload['restaurant_name'] ?? 'RestaurantPOS'), (string) ($payload['reservation_code'] ?? '')),
            'waiting_list.notified' => sprintf('[%s] Đến lượt của bạn', (string) ($payload['restaurant_name'] ?? 'RestaurantPOS')),
            default => sprintf('[%s] Notification', (string) ($payload['restaurant_name'] ?? 'RestaurantPOS')),
        };
    }

    private function renderEmailBody(string $templateKey, array $payload): string
    {
        if ($templateKey === 'conversation.outbound_reply') {
            $customerName = (string) ($payload['customer_name'] ?? 'Customer');
            $reservationCode = (string) ($payload['reservation_code'] ?? '');

            return trim("Hello {$customerName},\n\n" .
                "Our staff sent a follow-up message regarding your recent conversation.\n" .
                ($reservationCode !== '' ? "Reservation: {$reservationCode}\n" : '') .
                "Message:\n" . (string) ($payload['message_text'] ?? '') . "\n\n" .
                "Conversation ID: " . (string) ($payload['conversation_id'] ?? '') . "\n" .
                "Branch: " . (string) ($payload['branch_name'] ?? ($payload['restaurant_name'] ?? 'RestaurantPOS')));
        }
        $customerName = (string) ($payload['customer_name'] ?? 'Quý khách');
        $reservationCode = (string) ($payload['reservation_code'] ?? '');
        $guestCount = (int) ($payload['guest_count'] ?? 0);
        $startTime = (string) ($payload['start_time_utc'] ?? '');
        $endTime = (string) ($payload['end_time_utc'] ?? '');
        $checkedInAt = (string) ($payload['checked_in_at_utc'] ?? '');
        $tables = implode(', ', array_filter(array_map('strval', (array) ($payload['table_labels'] ?? []))));
        $previousTables = implode(', ', array_filter(array_map('strval', (array) ($payload['previous_table_labels'] ?? []))));
        $newTables = implode(', ', array_filter(array_map('strval', (array) ($payload['new_table_labels'] ?? []))));
        $previousStart = (string) ($payload['previous_start_time_utc'] ?? '');
        $previousEnd = (string) ($payload['previous_end_time_utc'] ?? '');
        $newStart = (string) ($payload['new_start_time_utc'] ?? '');
        $newEnd = (string) ($payload['new_end_time_utc'] ?? '');
        $previousGuestCount = isset($payload['previous_guest_count']) ? (int) $payload['previous_guest_count'] : null;
        $newGuestCount = isset($payload['new_guest_count']) ? (int) $payload['new_guest_count'] : null;
        $changedFields = array_values(array_filter(array_map('strval', (array) ($payload['changed_fields'] ?? []))));
        $changeReason = trim((string) ($payload['change_reason'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        $cancelReason = trim((string) ($payload['cancel_reason'] ?? ''));
        $checkedOutAt = (string) ($payload['checked_out_at_utc'] ?? '');
        $paidAmount = number_format((float) ($payload['paid_amount'] ?? 0), 2, '.', ',');
        $refundedAmount = number_format((float) ($payload['refunded_amount'] ?? 0), 2, '.', ',');
        $refundAmount = number_format((float) ($payload['refund_amount'] ?? 0), 2, '.', ',');
        $currency = (string) ($payload['bill_currency'] ?? 'VND');
        $refundCurrency = (string) ($payload['refund_currency'] ?? $currency);
        $refundScope = (string) ($payload['refund_scope'] ?? 'all');
        $notifyExpiresAt = (string) ($payload['notify_expires_at_utc'] ?? '');
        $tableLabel = (string) ($payload['table_label'] ?? '');

        return match ($templateKey) {
            'reservation.created' => trim("Xin chào {$customerName},\n\n" .
                "Đặt bàn của bạn đã được xác nhận thành công.\n" .
                "Mã đặt bàn: {$reservationCode}\n" .
                "Thời gian: {$startTime} đến {$endTime}\n" .
                "Số khách: {$guestCount}\n" .
                ($tables !== '' ? "Bàn: {$tables}\n" : '') .
                ($notes !== '' ? "Ghi chú: {$notes}\n" : '') .
                "\nVui lòng đến đúng giờ hoặc liên hệ nhà hàng nếu cần thay đổi thông tin."),
            'reservation.cancelled' => trim("Xin chào {$customerName},\n\n" .
                "Đặt bàn {$reservationCode} của bạn đã được hủy.\n" .
                "Thời gian dự kiến: {$startTime} đến {$endTime}\n" .
                ($cancelReason !== '' ? "Lý do hủy: {$cancelReason}\n" : '') .
                ((float) ($payload['refunded_amount'] ?? 0) > 0.0001 ? "Tổng hoàn tiền đã ghi nhận: {$refundedAmount} {$currency}\n" : '') .
                "\nNếu đây là nhầm lẫn, vui lòng liên hệ nhà hàng để được hỗ trợ."),
            'reservation.updated' => trim("Xin chào {$customerName},\n\n" .
                "Thông tin đặt bàn {$reservationCode} của bạn đã được cập nhật.\n" .
                ($previousGuestCount !== null && $newGuestCount !== null && $previousGuestCount !== $newGuestCount ? "Số khách: {$previousGuestCount} -> {$newGuestCount}\n" : "Số khách hiện tại: {$guestCount}\n") .
                ($previousTables !== '' && $newTables !== '' && $previousTables !== $newTables ? "Bàn: {$previousTables} -> {$newTables}\n" : ($tables !== '' ? "Bàn hiện tại: {$tables}\n" : '')) .
                ($changeReason !== '' ? "Lý do cập nhật: {$changeReason}\n" : '') .
                (! empty($changedFields) ? "Các mục thay đổi: " . implode(', ', $changedFields) . "\n" : '') .
                "\nNếu bạn cần thay đổi thêm, vui lòng liên hệ nhà hàng để được hỗ trợ."),
            'reservation.rescheduled' => trim("Xin chào {$customerName},\n\n" .
                "Đặt bàn {$reservationCode} của bạn đã được dời lịch.\n" .
                (($previousStart !== '' || $previousEnd !== '') ? "Khung giờ cũ: {$previousStart} đến {$previousEnd}\n" : '') .
                (($newStart !== '' || $newEnd !== '') ? "Khung giờ mới: {$newStart} đến {$newEnd}\n" : "Khung giờ hiện tại: {$startTime} đến {$endTime}\n") .
                ($previousGuestCount !== null && $newGuestCount !== null && $previousGuestCount !== $newGuestCount ? "Số khách: {$previousGuestCount} -> {$newGuestCount}\n" : "Số khách: {$guestCount}\n") .
                ($previousTables !== '' && $newTables !== '' && $previousTables !== $newTables ? "Bàn: {$previousTables} -> {$newTables}\n" : ($tables !== '' ? "Bàn hiện tại: {$tables}\n" : '')) .
                ($changeReason !== '' ? "Lý do thay đổi: {$changeReason}\n" : '') .
                "\nVui lòng kiểm tra lại lịch và liên hệ nhà hàng nếu bạn cần hỗ trợ thêm."),
            'reservation.reminder' => trim("Xin chào {$customerName},\n\n" .
                "Đây là email nhắc lịch cho đặt bàn {$reservationCode}.\n" .
                "Thời gian: {$startTime} đến {$endTime}\n" .
                "Số khách: {$guestCount}\n" .
                ($tables !== '' ? "Bàn dự kiến: {$tables}\n" : '') .
                "\nNhà hàng đang chờ đón bạn. Vui lòng đến sớm vài phút để check-in thuận tiện."),
            'reservation.checked_in' => trim("Xin chào {$customerName},\n\n" .
                "Bạn đã check-in thành công cho đặt bàn {$reservationCode}.\n" .
                ($checkedInAt !== '' ? "Thời điểm check-in: {$checkedInAt}\n" : '') .
                ($tables !== '' ? "Bàn đang phục vụ: {$tables}\n" : '') .
                "\nChúc bạn có trải nghiệm tốt tại nhà hàng."),
            'reservation.expired' => trim("Xin chào {$customerName},\n\n" .
                "Đặt bàn {$reservationCode} đã hết hiệu lực vì đã quá thời gian phục vụ.\n" .
                "Khung giờ dự kiến: {$startTime} đến {$endTime}\n" .
                "\nNếu bạn vẫn có nhu cầu, vui lòng tạo đặt bàn mới hoặc liên hệ nhà hàng."),
            'reservation.no_show' => trim("Xin chào {$customerName},\n\n" .
                "Đặt bàn {$reservationCode} đã được ghi nhận là no-show.\n" .
                "Khung giờ dự kiến: {$startTime} đến {$endTime}\n" .
                "\nNếu đây là nhầm lẫn, vui lòng liên hệ nhà hàng để được hỗ trợ."),
            'checkout.completed' => trim("Xin chào {$customerName},\n\n" .
                "Thanh toán cho đặt bàn {$reservationCode} đã hoàn tất thành công.\n" .
                "Tổng đã ghi nhận: {$paidAmount} {$currency}\n" .
                ($checkedOutAt !== '' ? "Thời điểm checkout: {$checkedOutAt}\n" : '') .
                "\nCảm ơn bạn đã sử dụng dịch vụ của nhà hàng."),
            'payment.refunded' => trim("Xin chào {$customerName},\n\n" .
                "Nhà hàng đã ghi nhận hoàn tiền cho đặt bàn {$reservationCode}.\n" .
                "Số tiền hoàn: {$refundAmount} {$refundCurrency}\n" .
                ($refundScope !== '' ? "Phạm vi hoàn: {$refundScope}\n" : '') .
                "\nNếu bạn cần đối soát thêm, vui lòng liên hệ nhà hàng để được hỗ trợ."),
            'waiting_list.notified' => trim("Xin chào {$customerName},\n\n" .
                "Bàn của bạn đã sẵn sàng phục vụ.\n" .
                ($tableLabel !== '' ? "Bàn dự kiến: {$tableLabel}\n" : '') .
                ($notifyExpiresAt !== '' ? "Vui lòng có mặt trước: {$notifyExpiresAt}\n" : '') .
                "\nNếu bạn chưa thể đến ngay, vui lòng liên hệ nhà hàng để được hỗ trợ."),
            default => 'Thông báo từ hệ thống nhà hàng.',
        };
    }

    private function recordMetric(string $metric, array $labels = [], int $by = 1): void
    {
        try {
            if (! app()->bound(MetricsService::class)) {
                return;
            }

            app(MetricsService::class)->inc($metric, $labels, max(1, $by));
        } catch (Throwable) {
            // best-effort only
        }
    }

    private function isEnabled(): bool
    {
        return (bool) config('notifications.outbox.enabled', true);
    }
}
