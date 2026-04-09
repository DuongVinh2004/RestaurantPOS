<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Http\Requests\Staff\CheckoutOrderRequest;
use App\Http\Requests\Staff\FinalizeSettlementRequest;
use App\Support\StaffMutationRowVersionContract;
use PHPUnit\Framework\TestCase;

class StaffMutationRowVersionContractTest extends TestCase
{
    public function test_all_target_staff_mutation_requests_require_row_version(): void
    {
        $snapshot = StaffMutationRowVersionContract::snapshot();

        $this->assertSame('ok', $snapshot['status']);
        $this->assertSame(0, $snapshot['missing_required_count']);
        $this->assertSame($snapshot['required_request_count'], $snapshot['compliant_request_count']);
        $this->assertSame([], $snapshot['missing']);
    }

    public function test_runtime_contract_uses_checkout_request_and_drops_stale_finalize_request_entry(): void
    {
        $map = StaffMutationRowVersionContract::requestMap();

        $this->assertArrayHasKey(CheckoutOrderRequest::class, $map);
        $this->assertArrayNotHasKey(FinalizeSettlementRequest::class, $map);
        $this->assertSame('staff.checkout', $map[CheckoutOrderRequest::class]);
    }
}
