<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Domain\Policies;

use App\Enums\KitchenTicketStatus;
use App\Enums\ReservationOrderItemStatus;
use Illuminate\Validation\ValidationException;

final class KitchenTicketTransitionPolicy
{
    public static function statusFromOrderItemStatus(ReservationOrderItemStatus|string $status): KitchenTicketStatus
    {
        $normalized = $status instanceof ReservationOrderItemStatus
            ? $status
            : ReservationOrderItemStatus::from((string) $status);

        return match ($normalized) {
            ReservationOrderItemStatus::InProgress => KitchenTicketStatus::Fired,
            ReservationOrderItemStatus::Served => KitchenTicketStatus::Completed,
            ReservationOrderItemStatus::Cancelled => KitchenTicketStatus::Cancelled,
            default => KitchenTicketStatus::Queued,
        };
    }

    public static function canApplyAction(KitchenTicketStatus|string $currentStatus, string $action): bool
    {
        $current = self::normalize($currentStatus);

        return in_array(trim($action), $current->allowedActions(), true);
    }

    public static function assertActionAllowed(
        KitchenTicketStatus|string $currentStatus,
        string $action,
        string $field = 'ticket_id',
    ): void {
        $current = self::normalize($currentStatus);
        $action = trim($action);

        if (self::canApplyAction($current, $action)) {
            return;
        }

        $message = match ($action) {
            'fire' => 'Only queued kitchen tickets can be fired.',
            'bump' => 'Only fired kitchen tickets can be bumped to ready.',
            'recall' => 'Only ready kitchen tickets can be recalled.',
            default => sprintf('Kitchen ticket action %s is not allowed from %s.', $action, $current->value),
        };

        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }

    public static function nextStatusForAction(KitchenTicketStatus|string $currentStatus, string $action): KitchenTicketStatus
    {
        self::assertActionAllowed($currentStatus, $action);

        return match (trim($action)) {
            'fire', 'recall' => KitchenTicketStatus::Fired,
            'bump' => KitchenTicketStatus::Ready,
            default => self::normalize($currentStatus),
        };
    }

    public static function resolveRedispatchStatus(
        KitchenTicketStatus|string $currentStatus,
        KitchenTicketStatus|string $derivedStatus,
    ): KitchenTicketStatus {
        return self::resolveProgressiveStatus($currentStatus, $derivedStatus);
    }

    public static function resolveSynchronizedStatus(
        KitchenTicketStatus|string $currentStatus,
        KitchenTicketStatus|string $derivedStatus,
    ): KitchenTicketStatus {
        return self::resolveProgressiveStatus($currentStatus, $derivedStatus);
    }

    private static function resolveProgressiveStatus(
        KitchenTicketStatus|string $currentStatus,
        KitchenTicketStatus|string $derivedStatus,
    ): KitchenTicketStatus {
        $current = self::normalize($currentStatus);
        $derived = self::normalize($derivedStatus);

        if ($current->isTerminal()) {
            return $current;
        }

        if ($derived->isTerminal()) {
            return $derived;
        }

        return self::precedence($current) >= self::precedence($derived)
            ? $current
            : $derived;
    }

    private static function precedence(KitchenTicketStatus $status): int
    {
        return match ($status) {
            KitchenTicketStatus::Queued => 0,
            KitchenTicketStatus::Fired => 1,
            KitchenTicketStatus::Ready => 2,
            KitchenTicketStatus::Completed,
            KitchenTicketStatus::Cancelled => 3,
        };
    }

    private static function normalize(KitchenTicketStatus|string $status): KitchenTicketStatus
    {
        return $status instanceof KitchenTicketStatus
            ? $status
            : KitchenTicketStatus::from((string) $status);
    }
}
