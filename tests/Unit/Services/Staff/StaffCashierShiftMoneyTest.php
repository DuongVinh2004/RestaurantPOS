<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Staff;

use App\Modules\Cashiering\Application\UseCases\Shifts\StaffCashierShiftService;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class StaffCashierShiftMoneyTest extends TestCase
{
    public function test_cash_summary_uses_exact_minor_unit_arithmetic(): void
    {
        $service = (new ReflectionClass(StaffCashierShiftService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(StaffCashierShiftService::class, 'cashSummary');
        $method->setAccessible(true);

        $cashCapture = new Payment();
        $cashCapture->payment_method = 'Cash';
        $cashCapture->payment_type = 'Final';
        $cashCapture->status = 'Success';
        $cashCapture->amount = '0.20';
        $cashCapture->currency = 'VND';

        $cashRefund = new Payment();
        $cashRefund->payment_method = 'Cash';
        $cashRefund->payment_type = 'Refund';
        $cashRefund->status = 'Refunded';
        $cashRefund->amount = '0.10';
        $cashRefund->currency = 'VND';

        $summary = $method->invoke($service, new Collection([$cashCapture, $cashRefund]), '0.10', 'VND');

        self::assertSame('0.10', $summary['summary']['opening_float_amount']);
        self::assertSame('0.20', $summary['summary']['captured_amount']);
        self::assertSame('0.10', $summary['summary']['refunded_amount']);
        self::assertSame('0.20', $summary['summary']['expected_cash_amount']);
    }
}
