<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Modules\Payments\Domain\Policies\PaymentSessionStatusTransitionPolicy;
use PHPUnit\Framework\TestCase;

class PaymentSessionStatusTransitionPolicyTest extends TestCase
{
    public function test_non_terminal_status_can_progress_to_terminal_status(): void
    {
        self::assertTrue(PaymentSessionStatusTransitionPolicy::shouldApply('Pending', 'Succeeded'));
        self::assertTrue(PaymentSessionStatusTransitionPolicy::shouldApply('Created', 'Failed'));
    }

    public function test_terminal_status_rejects_regression_to_different_status(): void
    {
        self::assertFalse(PaymentSessionStatusTransitionPolicy::shouldApply('Succeeded', 'Pending'));
        self::assertFalse(PaymentSessionStatusTransitionPolicy::shouldApply('Cancelled', 'Succeeded'));
    }

    public function test_same_status_replay_is_treated_as_safe_and_idempotent(): void
    {
        self::assertTrue(PaymentSessionStatusTransitionPolicy::shouldApply('Succeeded', 'Succeeded'));
        self::assertTrue(PaymentSessionStatusTransitionPolicy::shouldApply('Pending', 'Pending'));
    }
}
