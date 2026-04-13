<?php

declare(strict_types=1);

namespace App\Enums;

enum KitchenTicketStatus: string
{
    case Queued = 'Queued';
    case Fired = 'Fired';
    case Ready = 'Ready';
    case Completed = 'Completed';
    case Cancelled = 'Cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * @return list<string>
     */
    public function allowedActions(): array
    {
        return match ($this) {
            self::Queued => ['fire'],
            self::Fired => ['bump'],
            self::Ready => ['recall'],
            self::Completed, self::Cancelled => [],
        };
    }

    public function stateReason(): string
    {
        return match ($this) {
            self::Queued => 'awaiting_fire',
            self::Fired => 'in_preparation',
            self::Ready => 'awaiting_service_completion',
            self::Completed => 'order_item_served',
            self::Cancelled => 'order_item_cancelled',
        };
    }
}
