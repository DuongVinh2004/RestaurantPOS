<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Promotions\Domain\Models\Voucher;
use App\Modules\Promotions\Domain\Policies\VoucherRedemptionSupport;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class VoucherRedemptionSupportTest extends TestCase
{
    public function test_it_ignores_cancelled_items_when_summarizing_orders(): void
    {
        $active = new ReservationOrderItem([
            'item_id' => 10,
            'quantity' => 2,
            'unit_price' => 50000,
            'line_total' => 100000,
            'currency' => 'VND',
            'status' => 'Ordered',
        ]);

        $cancelled = new ReservationOrderItem([
            'item_id' => 10,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
            'currency' => 'VND',
            'status' => 'Cancelled',
        ]);

        $order = new ReservationOrder;
        $order->setRelation('items', new Collection([$active, $cancelled]));

        $summary = VoucherRedemptionSupport::summarizeOrders([$order]);

        $this->assertSame(100000.0, $summary['subtotal']);
        $this->assertSame('VND', $summary['currency']);
        $this->assertSame(2, $summary['item_quantity_map'][10]);
    }

    public function test_it_calculates_free_item_discount_from_item_quantity_and_unit_price(): void
    {
        $item = new ReservationOrderItem([
            'item_id' => 99,
            'quantity' => 3,
            'unit_price' => 45000,
            'line_total' => 135000,
            'currency' => 'VND',
            'status' => 'Ordered',
        ]);

        $order = new ReservationOrder;
        $order->setRelation('items', new Collection([$item]));

        $voucher = new Voucher([
            'discount_type' => 'FreeItem',
            'free_item_id' => 99,
            'free_item_qty' => 2,
            'is_active' => true,
        ]);

        $result = VoucherRedemptionSupport::calculateDiscount($voucher, [$order]);

        $this->assertSame(90000.0, $result['discount_amount']);
        $this->assertSame(135000.0, $result['subtotal']);
        $this->assertSame('VND', $result['currency']);
    }

    public function test_it_calculates_percentage_discount_without_float_drift(): void
    {
        $item = new ReservationOrderItem([
            'item_id' => 7,
            'quantity' => 3,
            'unit_price' => 10000,
            'line_total' => 30000,
            'currency' => 'VND',
            'status' => 'Ordered',
        ]);

        $order = new ReservationOrder;
        $order->setRelation('items', new Collection([$item]));

        $voucher = new Voucher([
            'discount_type' => 'Percent',
            'discount_value' => '10',
            'is_active' => true,
        ]);

        $result = VoucherRedemptionSupport::calculateDiscount($voucher, [$order]);

        $this->assertSame(3000.0, $result['discount_amount']);
        $this->assertSame(30000.0, $result['subtotal']);
    }
}
