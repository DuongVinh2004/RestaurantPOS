<?php

declare(strict_types=1);

namespace App\Enums;

enum StaffConversationWorkflowState: string
{
    case Open = 'Open';
    case Triaged = 'Triaged';
    case Assigned = 'Assigned';
    case PendingCustomer = 'PendingCustomer';
    case Resolved = 'Resolved';
    case Closed = 'Closed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $state): string => $state->value,
            self::cases(),
        );
    }

    public static function tryFromInput(?string $value): ?self
    {
        $normalized = strtolower(str_replace(['-', '_', ' '], '', trim((string) $value)));

        return match ($normalized) {
            'open' => self::Open,
            'triaged' => self::Triaged,
            'assigned' => self::Assigned,
            'pendingcustomer', 'pending' => self::PendingCustomer,
            'resolved' => self::Resolved,
            'closed' => self::Closed,
            default => null,
        };
    }

    public function isQueueTerminal(): bool
    {
        return in_array($this, [self::Resolved, self::Closed], true);
    }

    public function isActiveQueueState(): bool
    {
        return ! $this->isQueueTerminal();
    }
}
