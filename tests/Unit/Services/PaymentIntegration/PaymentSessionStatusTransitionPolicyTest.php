<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PaymentIntegration;

use App\Services\PaymentIntegration\PaymentSessionStatusTransitionPolicy;
use Tests\TestCase;

final class PaymentSessionStatusTransitionPolicyTest extends TestCase
{
    public function test_terminal_state_regression_is_rejected_with_terminal_reason(): void
    {
        self::assertFalse(PaymentSessionStatusTransitionPolicy::shouldApply('Succeeded', 'Pending'));
        self::assertSame(
            'terminal_state_regression_ignored',
            PaymentSessionStatusTransitionPolicy::ignoreReason('Succeeded', 'Pending')
        );
    }

    public function test_non_terminal_status_regression_is_rejected(): void
    {
        self::assertFalse(PaymentSessionStatusTransitionPolicy::shouldApply('Pending', 'Created'));
        self::assertSame(
            'state_regression_ignored',
            PaymentSessionStatusTransitionPolicy::ignoreReason('Pending', 'Created')
        );
    }
}
