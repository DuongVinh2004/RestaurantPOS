<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LoyaltyEarnReconciliation;
use PHPUnit\Framework\TestCase;

final class LoyaltyEarnReconciliationTest extends TestCase
{
    public function test_it_returns_positive_adjustment_when_more_points_should_be_earned(): void
    {
        $plan = LoyaltyEarnReconciliation::plan(12, 8, 100);

        self::assertSame(4, $plan['delta']);
        self::assertSame(4, $plan['adjustment_points']);
        self::assertSame(0, $plan['clawback_points']);
        self::assertSame(0, $plan['shortfall_points']);
    }

    public function test_it_claws_back_with_shortfall_when_user_balance_is_insufficient(): void
    {
        $plan = LoyaltyEarnReconciliation::plan(2, 10, 5);

        self::assertSame(-8, $plan['delta']);
        self::assertSame(-5, $plan['adjustment_points']);
        self::assertSame(5, $plan['clawback_points']);
        self::assertSame(3, $plan['shortfall_points']);
    }
}
