<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Modules\Payments\Domain\Policies\RefundAllocationPolicy;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class RefundAllocationPolicyTest extends TestCase
{
    public function test_it_allocates_across_sources_in_priority_order(): void
    {
        $allocations = RefundAllocationPolicy::allocate([
            [
                'source_key' => '11',
                'captured_amount' => 70.0,
                'already_refunded_amount' => 20.0,
            ],
            [
                'source_key' => '10',
                'captured_amount' => 60.0,
                'already_refunded_amount' => 0.0,
            ],
        ], 90.0);

        self::assertCount(2, $allocations);
        self::assertSame('11', $allocations[0]['source_key']);
        self::assertSame(50.0, $allocations[0]['allocation_amount']);
        self::assertSame('10', $allocations[1]['source_key']);
        self::assertSame(40.0, $allocations[1]['allocation_amount']);
    }

    public function test_it_rejects_requests_that_exceed_refundable_lineage(): void
    {
        $this->expectException(ValidationException::class);

        RefundAllocationPolicy::allocate([
            [
                'source_key' => '11',
                'captured_amount' => 70.0,
                'already_refunded_amount' => 70.0,
            ],
            [
                'source_key' => '10',
                'captured_amount' => 60.0,
                'already_refunded_amount' => 10.0,
            ],
        ], 55.0, 'Refund exceeds lineage.');
    }

    public function test_it_allocates_small_amounts_without_float_drift(): void
    {
        $allocations = RefundAllocationPolicy::allocate([
            [
                'source_key' => '1',
                'captured_amount' => '0.10',
                'already_refunded_amount' => '0.00',
            ],
            [
                'source_key' => '2',
                'captured_amount' => '0.30',
                'already_refunded_amount' => '0.10',
            ],
        ], '0.30');

        self::assertSame('1', $allocations[0]['source_key']);
        self::assertSame(0.10, $allocations[0]['allocation_amount']);
        self::assertSame(0.10, $allocations[0]['refundable_amount']);
        self::assertSame('2', $allocations[1]['source_key']);
        self::assertSame(0.20, $allocations[1]['allocation_amount']);
        self::assertSame(0.20, $allocations[1]['refundable_amount']);
    }
}
