<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Staff;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Billing\Application\UseCases\Previews\OrderSettlementService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OrderSettlementServiceTest extends TestCase
{
    public function test_build_settlement_amounts_applies_deposit_before_final_payment(): void
    {
        $service = new OrderSettlementService();

        $deposit = new Payment();
        $deposit->payment_id = 1;
        $deposit->payment_type = 'Deposit';
        $deposit->status = 'Success';
        $deposit->amount = 30000.0;

        $final = new Payment();
        $final->payment_id = 2;
        $final->payment_type = 'Final';
        $final->status = 'Success';
        $final->amount = 20000.0;

        $settlement = $service->buildSettlementAmounts(new Collection([$deposit, $final]), 100000.0);

        $this->assertSame(30000.0, (float) $settlement['deposit_net_amount']);
        $this->assertSame(30000.0, (float) $settlement['deposit_applied_amount']);
        $this->assertSame(20000.0, (float) $settlement['final_paid_amount']);
        $this->assertSame(50000.0, (float) $settlement['settled_amount']);
        $this->assertSame(50000.0, (float) $settlement['remaining_due']);
    }
}
