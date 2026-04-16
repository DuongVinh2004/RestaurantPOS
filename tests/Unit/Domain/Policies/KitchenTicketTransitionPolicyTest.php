<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Policies;

use App\Enums\KitchenTicketStatus;
use App\Enums\ReservationOrderItemStatus;
use App\Modules\KitchenDispatch\Domain\Policies\KitchenTicketTransitionPolicy;
use Tests\TestCase;

final class KitchenTicketTransitionPolicyTest extends TestCase
{
    public function test_ticket_action_matrix_only_allows_forward_operational_actions(): void
    {
        $cases = [
            [KitchenTicketStatus::Queued, 'fire', true, KitchenTicketStatus::Fired],
            [KitchenTicketStatus::Fired, 'bump', true, KitchenTicketStatus::Ready],
            [KitchenTicketStatus::Ready, 'recall', true, KitchenTicketStatus::Fired],
            [KitchenTicketStatus::Ready, 'fire', false, null],
            [KitchenTicketStatus::Queued, 'bump', false, null],
            [KitchenTicketStatus::Completed, 'recall', false, null],
        ];

        foreach ($cases as [$status, $action, $allowed, $nextStatus]) {
            self::assertSame(
                $allowed,
                KitchenTicketTransitionPolicy::canApplyAction($status, $action),
                sprintf('Unexpected kitchen action result for %s via %s.', $status->value, $action),
            );

            if ($allowed) {
                self::assertSame(
                    $nextStatus,
                    KitchenTicketTransitionPolicy::nextStatusForAction($status, $action),
                );
            }
        }
    }

    public function test_ticket_policy_preserves_terminal_and_forward_progression_during_sync(): void
    {
        self::assertSame(
            KitchenTicketStatus::Ready,
            KitchenTicketTransitionPolicy::resolveRedispatchStatus(KitchenTicketStatus::Ready, KitchenTicketStatus::Fired),
        );
        self::assertSame(
            KitchenTicketStatus::Completed,
            KitchenTicketTransitionPolicy::resolveSynchronizedStatus(KitchenTicketStatus::Fired, KitchenTicketStatus::Completed),
        );
        self::assertSame(
            KitchenTicketStatus::Completed,
            KitchenTicketTransitionPolicy::resolveSynchronizedStatus(KitchenTicketStatus::Completed, KitchenTicketStatus::Fired),
        );
        self::assertSame(
            KitchenTicketStatus::Cancelled,
            KitchenTicketTransitionPolicy::statusFromOrderItemStatus(ReservationOrderItemStatus::Cancelled),
        );
    }
}
