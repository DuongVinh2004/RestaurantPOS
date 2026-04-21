<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Policies;

use App\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Policies\PaymentStatusTransitionPolicy;
use Tests\TestCase;

final class PaymentStatusTransitionPolicyTest extends TestCase
{
    public function test_payment_transition_matrix_covers_refund_lineage_and_backward_rejections(): void
    {
        $cases = [
            [PaymentStatus::Pending, PaymentStatus::Partial, true],
            [PaymentStatus::Pending, PaymentStatus::Success, true],
            [PaymentStatus::Pending, PaymentStatus::Failed, true],
            [PaymentStatus::Partial, PaymentStatus::Success, true],
            [PaymentStatus::Partial, PaymentStatus::Refunded, true],
            [PaymentStatus::Success, PaymentStatus::Refunded, true],
            [PaymentStatus::Success, PaymentStatus::Partial, false],
            [PaymentStatus::Failed, PaymentStatus::Success, false],
            [PaymentStatus::Refunded, PaymentStatus::Success, false],
        ];

        foreach ($cases as [$from, $to, $expected]) {
            self::assertSame(
                $expected,
                PaymentStatusTransitionPolicy::canTransition($from, $to),
                sprintf('Unexpected payment transition result for %s -> %s.', $from->value, $to->value),
            );
        }
    }

    public function test_payment_policy_derives_capture_and_refund_semantics_consistently(): void
    {
        self::assertSame(
            PaymentStatus::Success,
            PaymentStatusTransitionPolicy::captureStatusForAppliedAmount(100_000, 100_000),
        );
        self::assertSame(
            PaymentStatus::Partial,
            PaymentStatusTransitionPolicy::captureStatusForAppliedAmount(50_000, 100_000),
        );
        self::assertSame(
            PaymentStatus::Success,
            PaymentStatusTransitionPolicy::derivedStatusForSettlementProgress(100_000, 100_000),
        );
        self::assertSame(
            PaymentStatus::Partial,
            PaymentStatusTransitionPolicy::derivedStatusForSettlementProgress(1, 100_000),
        );
        self::assertSame(
            PaymentStatus::Failed,
            PaymentStatusTransitionPolicy::derivedStatusForSettlementProgress(0, 100_000),
        );

        self::assertTrue(PaymentStatusTransitionPolicy::isRefundableSourceStatus(PaymentStatus::Success));
        self::assertTrue(PaymentStatusTransitionPolicy::isRefundableSourceStatus(PaymentStatus::Partial));
        self::assertFalse(PaymentStatusTransitionPolicy::isRefundableSourceStatus(PaymentStatus::Pending));
        self::assertTrue(PaymentStatusTransitionPolicy::isRefundedStatus(PaymentStatus::Refunded));
        self::assertFalse(PaymentStatusTransitionPolicy::isRefundedStatus(PaymentStatus::Failed));
    }
}
