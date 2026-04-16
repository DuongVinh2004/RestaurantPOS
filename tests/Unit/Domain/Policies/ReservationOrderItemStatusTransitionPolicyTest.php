<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Policies;

use App\Enums\ReservationOrderItemStatus;
use App\Modules\Ordering\Domain\Policies\ReservationOrderItemStatusTransitionPolicy;
use Tests\TestCase;

final class ReservationOrderItemStatusTransitionPolicyTest extends TestCase
{
    public function test_order_item_transition_matrix_rejects_backward_terminal_mutations(): void
    {
        $cases = [
            [ReservationOrderItemStatus::Ordered, ReservationOrderItemStatus::InProgress, true],
            [ReservationOrderItemStatus::Ordered, ReservationOrderItemStatus::Served, true],
            [ReservationOrderItemStatus::Ordered, ReservationOrderItemStatus::Cancelled, true],
            [ReservationOrderItemStatus::InProgress, ReservationOrderItemStatus::Served, true],
            [ReservationOrderItemStatus::InProgress, ReservationOrderItemStatus::Cancelled, true],
            [ReservationOrderItemStatus::Served, ReservationOrderItemStatus::Cancelled, false],
            [ReservationOrderItemStatus::Cancelled, ReservationOrderItemStatus::InProgress, false],
        ];

        foreach ($cases as [$from, $to, $expected]) {
            self::assertSame(
                $expected,
                ReservationOrderItemStatusTransitionPolicy::canTransition($from, $to),
                sprintf('Unexpected order item transition result for %s -> %s.', $from->value, $to->value),
            );
        }
    }
}
