<?php

declare(strict_types=1);

namespace App\Modules\Waitlist\Domain\StateMachines;

use App\Enums\WaitingListCustomerResponseStatus;
use App\Enums\WaitingListStatus;
use App\Modules\Waitlist\Domain\Models\WaitlistEntry;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class WaitlistInvitationStateMachine
{
    private const ROW_VERSION_MESSAGE = 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.';

    public static function statusValue(WaitlistEntry $entry): string
    {
        $status = $entry->status;

        if ($status instanceof WaitingListStatus) {
            return $status->value;
        }

        $raw = trim((string) $entry->getRawOriginal('status'));
        if ($raw !== '') {
            return $raw;
        }

        return trim((string) $status);
    }

    public static function assertExpectedRowVersion(WaitlistEntry $entry, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($entry->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => [self::ROW_VERSION_MESSAGE],
            ]);
        }
    }

    public static function assertCanNotify(WaitlistEntry $entry): void
    {
        self::assertTransitionAllowed($entry, WaitingListStatus::Notified);
    }

    public static function assertCanSeat(WaitlistEntry $entry, ?Carbon $now = null): void
    {
        self::assertTransitionAllowed($entry, WaitingListStatus::Seated);

        $now ??= Carbon::now('UTC');
        $expiresAt = self::parseUtc($entry->notify_expires_at);

        if (! $expiresAt instanceof Carbon) {
            throw ValidationException::withMessages([
                'notify_window' => ['Notify window hiện không hợp lệ hoặc đã bị reset.'],
            ]);
        }

        if ($expiresAt->lessThanOrEqualTo($now)) {
            throw ValidationException::withMessages([
                'notify_window' => ['Notify window đã hết hạn. Hãy expire hoặc notify lại entry này trước khi seat.'],
            ]);
        }
    }

    public static function assertCanCancel(WaitlistEntry $entry): void
    {
        self::assertTransitionAllowed($entry, WaitingListStatus::Cancelled);
    }

    public static function applyNotified(WaitlistEntry $entry, Carbon $now, Carbon $expireAt, ?int $staffUserId): void
    {
        self::assertTransitionAllowed($entry, WaitingListStatus::Notified);

        $entry->status = WaitingListStatus::Notified;
        $entry->notified_at = $now;
        $entry->notify_expires_at = $expireAt;
        $entry->notified_by = $staffUserId;
        $entry->customer_response_status = null;
        $entry->customer_responded_at = null;
        $entry->customer_confirmed_arrival_at = null;
        $entry->cancelled_at = null;
        $entry->cancel_reason = null;
        $entry->updated_by = $staffUserId;
    }

    public static function applyExpiredToWaiting(WaitlistEntry $entry, ?int $staffUserId = null): void
    {
        self::assertTransitionAllowed($entry, WaitingListStatus::Waiting);

        $entry->status = WaitingListStatus::Waiting;
        $entry->notified_at = null;
        $entry->notify_expires_at = null;
        $entry->notified_by = null;
        $entry->customer_response_status = null;
        $entry->customer_responded_at = null;
        $entry->customer_confirmed_arrival_at = null;
        $entry->updated_by = $staffUserId;
    }

    public static function applyCancelled(WaitlistEntry $entry, Carbon $now, string $cancelReason, ?int $staffUserId): void
    {
        self::assertTransitionAllowed($entry, WaitingListStatus::Cancelled);

        $entry->status = WaitingListStatus::Cancelled;
        $entry->cancelled_at = $now;
        $entry->cancel_reason = trim($cancelReason) !== '' ? trim($cancelReason) : 'Cancelled by staff';
        $entry->notify_expires_at = null;
        $entry->notified_by = null;
        $entry->customer_response_status = null;
        $entry->customer_responded_at = null;
        $entry->customer_confirmed_arrival_at = null;
        $entry->updated_by = $staffUserId;
    }

    public static function applySeated(WaitlistEntry $entry, Carbon $checkedInAt, ?int $staffUserId, int $userId, string $notes = ''): void
    {
        self::assertTransitionAllowed($entry, WaitingListStatus::Seated);

        $entry->status = WaitingListStatus::Seated;
        $entry->seated_at = $checkedInAt;
        $entry->notify_expires_at = null;
        $entry->updated_by = $staffUserId;

        if ($entry->user_id === null) {
            $entry->user_id = $userId;
        }

        if (trim($notes) !== '') {
            $entry->notes = $notes;
        }
    }

    public static function applyCustomerDeclined(WaitlistEntry $entry, Carbon $now, ?int $actorUserId): void
    {
        self::assertTransitionAllowed($entry, WaitingListStatus::Cancelled);

        $entry->status = WaitingListStatus::Cancelled;
        $entry->cancelled_at = $now;
        $entry->cancel_reason = 'Declined by customer';
        $entry->notified_at = null;
        $entry->notify_expires_at = null;
        $entry->customer_response_status = WaitingListCustomerResponseStatus::Declined;
        $entry->customer_responded_at = $now;
        $entry->customer_confirmed_arrival_at = null;
        $entry->notified_by = null;
        $entry->updated_by = $actorUserId;
    }

    public static function applyCustomerCancelled(WaitlistEntry $entry, Carbon $now, string $cancelReason, ?int $actorUserId): void
    {
        self::assertTransitionAllowed($entry, WaitingListStatus::Cancelled);

        $entry->status = WaitingListStatus::Cancelled;
        $entry->cancelled_at = $now;
        $entry->cancel_reason = trim($cancelReason) !== '' ? trim($cancelReason) : 'Cancelled by customer';
        $entry->notified_at = null;
        $entry->notify_expires_at = null;
        $entry->customer_response_status = null;
        $entry->customer_responded_at = null;
        $entry->customer_confirmed_arrival_at = null;
        $entry->notified_by = null;
        $entry->updated_by = $actorUserId;
    }

    public static function canTransition(WaitlistEntry|WaitingListStatus|string $currentStatus, WaitingListStatus|string $targetStatus): bool
    {
        $current = self::normalizeStatus($currentStatus);
        $target = self::normalizeStatus($targetStatus);

        if ($current === $target) {
            return true;
        }

        return in_array($target, self::allowedTargets($current), true);
    }

    public static function assertTransitionAllowed(
        WaitlistEntry|WaitingListStatus|string $currentStatus,
        WaitingListStatus|string $targetStatus,
        string $field = 'status'
    ): void {
        $current = self::normalizeStatus($currentStatus);
        $target = self::normalizeStatus($targetStatus);

        if (self::canTransition($current, $target)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => [sprintf('Waiting list transition is not allowed: %s -> %s.', $current->value, $target->value)],
        ]);
    }

    /**
     * @return list<WaitingListStatus>
     */
    public static function allowedTargets(WaitlistEntry|WaitingListStatus|string $currentStatus): array
    {
        $current = self::normalizeStatus($currentStatus);

        return match ($current) {
            WaitingListStatus::Waiting => [
                WaitingListStatus::Notified,
                WaitingListStatus::Cancelled,
            ],
            WaitingListStatus::Notified => [
                WaitingListStatus::Waiting,
                WaitingListStatus::Seated,
                WaitingListStatus::Cancelled,
            ],
            WaitingListStatus::Seated,
            WaitingListStatus::Cancelled => [],
        };
    }

    private static function parseUtc(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))->utc();
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        return Carbon::parse($raw)->utc();
    }

    private static function normalizeStatus(WaitlistEntry|WaitingListStatus|string $status): WaitingListStatus
    {
        if ($status instanceof WaitlistEntry) {
            return WaitingListStatus::from(self::statusValue($status));
        }

        return $status instanceof WaitingListStatus
            ? $status
            : WaitingListStatus::from(trim((string) $status));
    }
}
