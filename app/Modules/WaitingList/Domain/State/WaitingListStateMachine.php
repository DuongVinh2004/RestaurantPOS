<?php

declare(strict_types=1);

namespace App\Modules\WaitingList\Domain\State;

use App\Enums\WaitingListStatus;
use App\Modules\WaitingList\Domain\Models\WaitingList;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class WaitingListStateMachine
{
    private const ROW_VERSION_MESSAGE = 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.';

    public static function statusValue(WaitingList $entry): string
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

    public static function assertExpectedRowVersion(WaitingList $entry, ?int $expectedRowVersion): void
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

    public static function assertCanNotify(WaitingList $entry): void
    {
        $status = self::statusValue($entry);
        if (in_array($status, [WaitingListStatus::Cancelled->value, WaitingListStatus::Seated->value], true)) {
            throw ValidationException::withMessages([
                'status' => ['Waiting entry đã kết thúc, không thể notify.'],
            ]);
        }
    }

    public static function assertCanSeat(WaitingList $entry, ?Carbon $now = null): void
    {
        $status = self::statusValue($entry);
        if ($status !== WaitingListStatus::Notified->value) {
            throw ValidationException::withMessages([
                'status' => ['Chỉ có entry ở trạng thái Notified mới có thể seat.'],
            ]);
        }

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

    public static function assertCanCancel(WaitingList $entry): void
    {
        if (self::statusValue($entry) === WaitingListStatus::Seated->value) {
            throw ValidationException::withMessages([
                'status' => ['Entry đã seated, không thể cancel.'],
            ]);
        }
    }

    public static function applyNotified(WaitingList $entry, Carbon $now, Carbon $expireAt, ?int $staffUserId): void
    {
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

    public static function applyExpiredToWaiting(WaitingList $entry, ?int $staffUserId = null): void
    {
        $entry->status = WaitingListStatus::Waiting;
        $entry->notified_at = null;
        $entry->notify_expires_at = null;
        $entry->notified_by = null;
        $entry->customer_response_status = null;
        $entry->customer_responded_at = null;
        $entry->customer_confirmed_arrival_at = null;
        $entry->updated_by = $staffUserId;
    }

    public static function applyCancelled(WaitingList $entry, Carbon $now, string $cancelReason, ?int $staffUserId): void
    {
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

    public static function applySeated(WaitingList $entry, Carbon $checkedInAt, ?int $staffUserId, int $userId, string $notes = ''): void
    {
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
}
